<?php
/**
 * Admin-Endpunkt: liefert ALLE Waffenboerse-Anzeigen (jeder Status).
 * Gegenstueck zu api/hundeboerse/admin/liste.php - siehe dort fuer die
 * ausfuehrliche Erklaerung.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/identity_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed']);
}

kjs_boerse_require_permission('waffenboerse');

$pdo = kjs_boerse_require_db();

$rows = $pdo->query(
    "SELECT id, status, titel, kategorie, hersteller, modell, zustand, preis,
            preis_typ, erwerbsberechtigung_erforderlich, beschreibung, plz, ort,
            versand_moeglich, versandkosten, anbieter_name, anbieter_email,
            anbieter_telefon, erstellt_am, aktualisiert_am
     FROM waffenboerse_anzeigen
     ORDER BY erstellt_am DESC"
)->fetchAll();

$imgStmt = $pdo->prepare('SELECT pfad, titel FROM waffenboerse_bilder WHERE anzeige_id = ? ORDER BY sortierung ASC, id ASC');
$kalStmt = $pdo->prepare('SELECT kaliber FROM waffenboerse_kaliber WHERE anzeige_id = ? ORDER BY sortierung ASC, id ASC');

$anzeigen = [];
foreach ($rows as $row) {
    $imgStmt->execute([$row['id']]);
    $bilder = $imgStmt->fetchAll();
    $kalStmt->execute([$row['id']]);
    $kaliber = $kalStmt->fetchAll(PDO::FETCH_COLUMN);

    $anzeigen[] = [
        'id' => $row['id'],
        'status' => $row['status'],
        'erstellt_am' => kjs_wb_admin_datetime_to_iso($row['erstellt_am']),
        'aktualisiert_am' => kjs_wb_admin_datetime_to_iso($row['aktualisiert_am']),
        'titel' => $row['titel'],
        'kategorie' => $row['kategorie'],
        'hersteller' => $row['hersteller'],
        'modell' => $row['modell'],
        'kaliber' => $kaliber,
        'zustand' => $row['zustand'],
        'preis' => $row['preis'],
        'preis_typ' => $row['preis_typ'],
        'erwerbsberechtigung_erforderlich' => (bool) $row['erwerbsberechtigung_erforderlich'],
        'beschreibung' => $row['beschreibung'] ?? '',
        'bilder' => array_map(static function (array $b): array {
            return ['bild' => $b['pfad'], 'titel' => $b['titel']];
        }, $bilder),
        'plz' => $row['plz'],
        'ort' => $row['ort'],
        'versand_moeglich' => (bool) $row['versand_moeglich'],
        'versandkosten' => $row['versandkosten'],
        'anbieter_name' => $row['anbieter_name'],
        'anbieter_email' => $row['anbieter_email'],
        'anbieter_telefon' => $row['anbieter_telefon'],
    ];
}

$kategorien = $pdo->query('SELECT name FROM waffenboerse_kategorien ORDER BY sortierung ASC, name ASC')->fetchAll(PDO::FETCH_COLUMN);
$meta = $pdo->query('SELECT version FROM waffenboerse_meta WHERE id = 1')->fetch();

kjs_boerse_json_response(200, [
    'success' => true,
    'data' => [
        'anzeigen' => $anzeigen,
        'kategorien' => $kategorien,
    ],
    'version' => (int) ($meta['version'] ?? 0),
]);

function kjs_wb_admin_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}
