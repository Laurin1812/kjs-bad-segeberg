<?php
/**
 * Admin-Endpunkt: speichert den GESAMTEN Waffenboerse-Datenbestand (alle
 * Anzeigen + Kategorienliste). Gegenstueck zu
 * api/hundeboerse/admin/speichern.php - siehe dort fuer die ausfuehrliche
 * Begruendung des "alles auf einmal"-Ansatzes und der Konflikterkennung
 * ueber "expected_version".
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/identity_auth.php';
require_once __DIR__ . '/../../lib/html_sanitize.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed']);
}

kjs_boerse_require_permission('waffenboerse');

$body = kjs_boerse_read_json_body();
$data = is_array($body['data'] ?? null) ? $body['data'] : null;
if ($data === null) {
    kjs_boerse_json_response(400, ['success' => false, 'error' => 'missing_data', 'message' => 'Kein Datenobjekt uebergeben.']);
}
$expectedVersion = array_key_exists('expected_version', $body) && $body['expected_version'] !== null
    ? (int) $body['expected_version']
    : null;

$pdo = kjs_boerse_require_db();

$allowedStatus = ['pending', 'published', 'rejected', 'archived'];
$allowedZustand = ['neu', 'gebraucht', 'vorfuehrwaffe'];
$allowedPreisTyp = ['', 'festpreis', 'vb'];
$maxShort = 190;
$maxLong = 20000;

function kjs_wb_admin_str($value, int $max): string
{
    if (!is_string($value)) {
        if (is_scalar($value)) $value = (string) $value; else return '';
    }
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$anzeigenIn = is_array($data['anzeigen'] ?? null) ? $data['anzeigen'] : [];
$kategorienIn = is_array($data['kategorien'] ?? null) ? $data['kategorien'] : [];

$rowsToInsert = [];
$imagesToInsert = [];
$kaliberToInsert = [];
$errors = [];

foreach ($anzeigenIn as $i => $a) {
    if (!is_array($a)) continue;
    $id = kjs_wb_admin_str($a['id'] ?? '', 40);
    if ($id === '') { $errors[] = "Anzeige #$i: fehlende id"; continue; }

    $status = in_array($a['status'] ?? '', $allowedStatus, true) ? $a['status'] : 'pending';
    $zustand = in_array($a['zustand'] ?? '', $allowedZustand, true) ? $a['zustand'] : '';
    $preisTyp = in_array($a['preis_typ'] ?? '', $allowedPreisTyp, true) ? $a['preis_typ'] : '';

    $rowsToInsert[] = [
        'id' => $id,
        'status' => $status,
        'titel' => kjs_wb_admin_str($a['titel'] ?? '', $maxShort),
        'kategorie' => kjs_wb_admin_str($a['kategorie'] ?? '', $maxShort),
        'hersteller' => kjs_wb_admin_str($a['hersteller'] ?? '', $maxShort),
        'modell' => kjs_wb_admin_str($a['modell'] ?? '', $maxShort),
        'zustand' => $zustand,
        'preis' => kjs_wb_admin_str($a['preis'] ?? '', 40),
        'preis_typ' => $preisTyp,
        'erwerb' => !empty($a['erwerbsberechtigung_erforderlich']) ? 1 : 0,
        // Beschreibung kommt aus dem admin-eigenen TipTap-Editor. WICHTIG
        // (Security-Finalisierung): dieser Endpunkt ist ein normaler JSON-
        // POST, den jeder Client mit gueltigem Bearer-Token und
        // "waffenboerse"/"admin"-Berechtigung aufrufen kann - auch ohne die
        // TipTap-Oberflaeche zu benutzen. "Kommt aus dem eigenen Editor"
        // ist deshalb KEINE Sicherheitsgrenze; ein Redakteur-Account koennte
        // sonst per direktem API-Aufruf rohes <script>/HTML einschleusen und
        // im selben Request "status":"published" setzen. Daher wird die
        // Beschreibung serverseitig durch eine DOM-basierte Allowlist
        // (api/lib/html_sanitize.php) bereinigt - legitime Formatierung aus
        // dem Editor bleibt erhalten, gefaehrliches HTML wird entfernt.
        'beschreibung' => kjs_boerse_sanitize_rich_html(kjs_wb_admin_str($a['beschreibung'] ?? '', $maxLong)),
        'plz' => kjs_wb_admin_str($a['plz'] ?? '', 10),
        'ort' => kjs_wb_admin_str($a['ort'] ?? '', $maxShort),
        'versand' => !empty($a['versand_moeglich']) ? 1 : 0,
        'versandkosten' => kjs_wb_admin_str($a['versandkosten'] ?? '', 40),
        'anbieter_name' => kjs_wb_admin_str($a['anbieter_name'] ?? '', $maxShort),
        'anbieter_email' => kjs_wb_admin_str($a['anbieter_email'] ?? '', $maxShort),
        'anbieter_telefon' => kjs_wb_admin_str($a['anbieter_telefon'] ?? '', 60),
    ];

    $bilder = is_array($a['bilder'] ?? null) ? $a['bilder'] : [];
    foreach (array_slice($bilder, 0, 30) as $sort => $g) {
        if (!is_array($g)) continue;
        $pfad = kjs_wb_admin_str($g['bild'] ?? '', 255);
        if ($pfad === '') continue;
        $imagesToInsert[] = [$id, $pfad, kjs_wb_admin_str($g['titel'] ?? '', $maxShort), $sort];
    }

    $kaliberArr = is_array($a['kaliber'] ?? null) ? $a['kaliber'] : [];
    foreach (array_slice($kaliberArr, 0, 20) as $sort => $k) {
        if (!is_string($k)) continue;
        $k = kjs_wb_admin_str($k, 100);
        if ($k === '') continue;
        $kaliberToInsert[] = [$id, $k, $sort];
    }
}

if (!empty($errors)) {
    kjs_boerse_json_response(422, ['success' => false, 'error' => 'validation_failed', 'details' => $errors]);
}

$kategorien = [];
foreach ($kategorienIn as $idx => $k) {
    if (!is_string($k)) continue;
    $k = trim($k);
    if ($k === '') continue;
    if (!in_array($k, $kategorien, true)) $kategorien[] = kjs_wb_admin_str($k, $maxShort);
}

try {
    $pdo->beginTransaction();

    $metaStmt = $pdo->prepare('SELECT version FROM waffenboerse_meta WHERE id = 1 FOR UPDATE');
    $metaStmt->execute();
    $currentVersion = (int) $metaStmt->fetchColumn();

    if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
        $pdo->rollBack();
        kjs_boerse_json_response(409, ['success' => false, 'error' => 'conflict', 'message' => 'Diese Daten wurden zwischenzeitlich anderswo geaendert.']);
    }

    $pdo->exec('DELETE FROM waffenboerse_anzeigen');
    $pdo->exec('DELETE FROM waffenboerse_kategorien');

    $insertStmt = $pdo->prepare(
        'INSERT INTO waffenboerse_anzeigen
            (id, status, titel, kategorie, hersteller, modell, zustand, preis,
             preis_typ, erwerbsberechtigung_erforderlich, beschreibung, plz, ort,
             versand_moeglich, versandkosten, anbieter_name, anbieter_email, anbieter_telefon)
         VALUES
            (:id, :status, :titel, :kategorie, :hersteller, :modell, :zustand, :preis,
             :preis_typ, :erwerb, :beschreibung, :plz, :ort,
             :versand, :versandkosten, :anbieter_name, :anbieter_email, :anbieter_telefon)'
    );
    foreach ($rowsToInsert as $row) {
        $insertStmt->execute($row);
    }

    $imgStmt = $pdo->prepare('INSERT INTO waffenboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');
    foreach ($imagesToInsert as $img) {
        $imgStmt->execute($img);
    }

    $kalStmt = $pdo->prepare('INSERT INTO waffenboerse_kaliber (anzeige_id, kaliber, sortierung) VALUES (?, ?, ?)');
    foreach ($kaliberToInsert as $k) {
        $kalStmt->execute($k);
    }

    // Kategorien wieder mit fortlaufender Sortierung anlegen (Reihenfolge
    // aus dem Client uebernommen, wie bisher in content/waffenboerse.json).
    if (empty($kategorien)) {
        $kategorien = ['Büchsen', 'Flinten', 'Kombinierte Waffen', 'Kurzwaffen', 'Optik', 'Zubehör', 'Sonstiges'];
    }
    $katStmt = $pdo->prepare('INSERT INTO waffenboerse_kategorien (name, sortierung) VALUES (?, ?)');
    foreach ($kategorien as $sort => $k) {
        $katStmt->execute([$k, $sort]);
    }

    $newVersion = $currentVersion + 1;
    $pdo->prepare('UPDATE waffenboerse_meta SET version = ? WHERE id = 1')->execute([$newVersion]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('KJS Waffenboerse Admin-Speichern fehlgeschlagen: ' . $e->getMessage());
    kjs_boerse_json_response(500, ['success' => false, 'error' => 'internal_error', 'message' => 'Speichern fehlgeschlagen.']);
}

kjs_boerse_json_response(200, ['success' => true, 'version' => $newVersion]);
