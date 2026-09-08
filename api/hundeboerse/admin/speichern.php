<?php
/**
 * Admin-Endpunkt: speichert den GESAMTEN Hundeboerse-Datenbestand
 * (alle Anzeigen + Zuchtverband-Liste + Hero-Bild) in einem Zug.
 *
 * WARUM "ALLES AUF EINMAL" STATT EINZELNER CRUD-ENDPUNKTE:
 * Das bestehende Admin-Panel (admin/admin.js) ist komplett darauf gebaut,
 * bei JEDER Aktion (Speichern, Freigeben, Ablehnen, Archivieren, Loeschen,
 * Zuchtverband hinzufuegen, Hero-Bild aendern) den kompletten Datensatz per
 * doSave(S.section.file, S.data, message) als eine JSON-Datei zu schreiben -
 * das war exakt das bisherige Verhalten mit content/hundeboerse.json ueber
 * Git. Dieser Endpunkt bildet dasselbe Verhalten 1:1 auf MySQL ab (Loeschen
 * + Neuanlegen aller Zeilen in einer Transaktion), damit admin.js in seiner
 * gesamten Render-/Sammel-Logik (renderHundeboerse, hundeboerseCollect,
 * hundeboerseSave/-Freigeben/-Ablehnen/-Archivieren/-Delete usw.)
 * UNVERAENDERT weiterlaufen kann - nur die Transportfunktionen doSave()/
 * apiGet() in admin.js wurden angepasst, um bei diesen beiden Dateipfaden
 * hierher statt zu git-gateway zu gehen (siehe dortiger Kommentar). Das ist
 * bei der ueberschaubaren Groessenordnung dieser Module (typischerweise
 * niedrige zweistellige Anzahl an Anzeigen) unproblematisch und deutlich
 * risikoaermer als admin.js' gesamte Speicherlogik pro Modul neu zu
 * entwerfen.
 *
 * Konflikterkennung: "expected_version" muss die zuletzt von liste.php
 * gelieferte Versionsnummer sein. Weicht sie von der aktuellen
 * Datenbank-Version ab (= jemand anderes hat zwischenzeitlich gespeichert),
 * wird NICHTS geschrieben und 409 mit error:"conflict" zurueckgegeben -
 * admin.js' bestehende doSave()-Logik interpretiert ein HTTP 409 bereits
 * als Speicherkonflikt (siehe dortiger Code) und zeigt die schon vorhandene
 * Konfliktmeldung.
 *
 * Auth: wie liste.php, Berechtigung "hundeboerse" erforderlich.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/identity_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed']);
}

kjs_boerse_require_permission('hundeboerse');

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
$allowedType = ['single', 'litter'];
$allowedGender = ['male', 'female', ''];
$allowedPriceType = ['fixed', 'negotiable', 'on_request', 'none'];
$maxShort = 190;
$maxLong = 20000;

function kjs_hb_admin_str($value, int $max): string
{
    if (!is_string($value)) {
        if (is_scalar($value)) $value = (string) $value; else return '';
    }
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$anzeigenIn = is_array($data['anzeigen'] ?? null) ? $data['anzeigen'] : [];
$zuchtverbaendeIn = is_array($data['zuchtverbaende'] ?? null) ? $data['zuchtverbaende'] : [];
$heroBild = kjs_hb_admin_str($data['hero_bild'] ?? '', 255);

$rowsToInsert = [];
$imagesToInsert = []; // [ [anzeige_id, pfad, titel, sort], ... ]
$errors = [];

foreach ($anzeigenIn as $i => $a) {
    if (!is_array($a)) continue;
    $id = kjs_hb_admin_str($a['id'] ?? '', 40);
    if ($id === '') { $errors[] = "Anzeige #$i: fehlende id"; continue; }

    $status = in_array($a['status'] ?? '', $allowedStatus, true) ? $a['status'] : 'pending';
    $type = in_array($a['type'] ?? '', $allowedType, true) ? $a['type'] : 'single';
    $gender = in_array($a['gender'] ?? '', $allowedGender, true) ? $a['gender'] : '';
    $priceType = in_array($a['priceType'] ?? '', $allowedPriceType, true) ? $a['priceType'] : 'on_request';

    $rowsToInsert[] = [
        'id' => $id,
        'status' => $status,
        'type' => $type,
        'title' => kjs_hb_admin_str($a['title'] ?? '', $maxShort),
        'breed' => kjs_hb_admin_str($a['breed'] ?? '', $maxShort),
        'color' => kjs_hb_admin_str($a['color'] ?? '', $maxShort),
        'coat' => kjs_hb_admin_str($a['coat'] ?? '', $maxShort),
        'price_type' => $priceType,
        'price' => kjs_hb_admin_str($a['price'] ?? '', 40),
        'postal_code' => kjs_hb_admin_str($a['postalCode'] ?? '', 10),
        'city' => kjs_hb_admin_str($a['city'] ?? '', $maxShort),
        'description' => kjs_hb_admin_str($a['description'] ?? '', $maxLong),
        'father' => kjs_hb_admin_str($a['father'] ?? '', $maxShort),
        'father_tests' => kjs_hb_admin_str($a['fatherTests'] ?? '', $maxShort),
        'mother' => kjs_hb_admin_str($a['mother'] ?? '', $maxShort),
        'mother_tests' => kjs_hb_admin_str($a['motherTests'] ?? '', $maxShort),
        'hunting_tests' => kjs_hb_admin_str($a['huntingTests'] ?? '', $maxShort),
        'training_level' => kjs_hb_admin_str($a['trainingLevel'] ?? '', $maxLong),
        'provider_name' => kjs_hb_admin_str($a['providerName'] ?? '', $maxShort),
        'contact_person' => kjs_hb_admin_str($a['contactPerson'] ?? '', $maxShort),
        'email' => kjs_hb_admin_str($a['email'] ?? '', $maxShort),
        'phone' => kjs_hb_admin_str($a['phone'] ?? '', 60),
        'contact_notes' => kjs_hb_admin_str($a['contactNotes'] ?? '', $maxLong),
        'dog_name' => kjs_hb_admin_str($a['dogName'] ?? '', $maxShort),
        'birth_date' => kjs_hb_admin_str($a['birthDate'] ?? '', 20),
        'gender' => $gender,
        'litter_date' => kjs_hb_admin_str($a['litterDate'] ?? '', 20),
        'male_count' => kjs_hb_admin_str($a['maleCount'] ?? '', 10),
        'female_count' => kjs_hb_admin_str($a['femaleCount'] ?? '', 10),
        'gallery_title' => kjs_hb_admin_str($a['galerie_titel'] ?? 'Bilder', $maxShort) ?: 'Bilder',
        'has_zuchtverband' => !empty($a['hasZuchtverband']) ? 1 : 0,
        'zuchtverband' => kjs_hb_admin_str($a['zuchtverband'] ?? '', $maxShort),
        'lat' => is_numeric($a['lat'] ?? null) ? (float) $a['lat'] : null,
        'lng' => is_numeric($a['lng'] ?? null) ? (float) $a['lng'] : null,
    ];

    $galerie = is_array($a['galerie'] ?? null) ? $a['galerie'] : [];
    foreach (array_slice($galerie, 0, 30) as $sort => $g) {
        if (!is_array($g)) continue;
        $pfad = kjs_hb_admin_str($g['bild'] ?? '', 255);
        if ($pfad === '') continue;
        $imagesToInsert[] = [$id, $pfad, kjs_hb_admin_str($g['titel'] ?? '', $maxShort), $sort];
    }
}

if (!empty($errors)) {
    kjs_boerse_json_response(422, ['success' => false, 'error' => 'validation_failed', 'details' => $errors]);
}

$zuchtverbaende = [];
foreach ($zuchtverbaendeIn as $z) {
    if (!is_string($z)) continue;
    $z = trim($z);
    if ($z === '') continue;
    if (!in_array($z, $zuchtverbaende, true)) $zuchtverbaende[] = kjs_hb_admin_str($z, $maxShort);
}

try {
    $pdo->beginTransaction();

    $metaStmt = $pdo->prepare('SELECT version FROM hundeboerse_meta WHERE id = 1 FOR UPDATE');
    $metaStmt->execute();
    $currentVersion = (int) $metaStmt->fetchColumn();

    if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
        $pdo->rollBack();
        kjs_boerse_json_response(409, ['success' => false, 'error' => 'conflict', 'message' => 'Diese Daten wurden zwischenzeitlich anderswo geaendert.']);
    }

    $pdo->exec('DELETE FROM hundeboerse_anzeigen');
    $pdo->exec('DELETE FROM hundeboerse_zuchtverbaende');

    $insertStmt = $pdo->prepare(
        'INSERT INTO hundeboerse_anzeigen
            (id, status, type, title, breed, color, coat, price_type, price,
             postal_code, city, description, father, father_tests, mother,
             mother_tests, hunting_tests, training_level, provider_name,
             contact_person, email, phone, contact_notes, dog_name,
             birth_date, gender, litter_date, male_count, female_count,
             gallery_title, has_zuchtverband, zuchtverband, lat, lng)
         VALUES
            (:id, :status, :type, :title, :breed, :color, :coat, :price_type, :price,
             :postal_code, :city, :description, :father, :father_tests, :mother,
             :mother_tests, :hunting_tests, :training_level, :provider_name,
             :contact_person, :email, :phone, :contact_notes, :dog_name,
             :birth_date, :gender, :litter_date, :male_count, :female_count,
             :gallery_title, :has_zuchtverband, :zuchtverband, :lat, :lng)'
    );
    foreach ($rowsToInsert as $row) {
        $insertStmt->execute($row);
    }

    $imgStmt = $pdo->prepare('INSERT INTO hundeboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');
    foreach ($imagesToInsert as $img) {
        $imgStmt->execute($img);
    }

    foreach ($zuchtverbaende as $z) {
        $pdo->prepare('INSERT IGNORE INTO hundeboerse_zuchtverbaende (name) VALUES (?)')->execute([$z]);
    }

    $newVersion = $currentVersion + 1;
    $pdo->prepare('UPDATE hundeboerse_meta SET version = ?, hero_bild = ? WHERE id = 1')
        ->execute([$newVersion, $heroBild !== '' ? $heroBild : null]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('KJS Hundeboerse Admin-Speichern fehlgeschlagen: ' . $e->getMessage());
    kjs_boerse_json_response(500, ['success' => false, 'error' => 'internal_error', 'message' => 'Speichern fehlgeschlagen.']);
}

kjs_boerse_json_response(200, ['success' => true, 'version' => $newVersion]);
