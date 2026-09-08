<?php
/**
 * Admin-Endpunkt: liefert ALLE Hundeboerse-Anzeigen (jeder Status), fuer die
 * Moderationsansicht im bestehenden Admin-Panel (admin/admin.js,
 * renderHundeboerse()).
 *
 * Antwortform bewusst identisch zum bisherigen content/hundeboerse.json
 * (siehe api/hundeboerse/anzeigen.php fuer die Begruendung), zusaetzlich
 * mit "version" fuer die optimistische Konflikterkennung beim Speichern
 * (ersetzt die bisherige Git-SHA-Pruefung aus admin.js doSave()).
 *
 * Auth: Authorization: Bearer <Netlify-Identity-JWT>, Berechtigung
 * "hundeboerse" (oder Rolle "admin") erforderlich - siehe
 * api/lib/identity_auth.php.
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

kjs_boerse_require_permission('hundeboerse');

$pdo = kjs_boerse_require_db();

$rows = $pdo->query(
    "SELECT id, status, type, title, breed, color, coat, price_type, price,
            postal_code, city, description, father, father_tests, mother,
            mother_tests, hunting_tests, training_level, provider_name,
            contact_person, email, phone, contact_notes, dog_name,
            birth_date, gender, litter_date, male_count, female_count,
            gallery_title, has_zuchtverband, zuchtverband, lat, lng,
            created_at, updated_at
     FROM hundeboerse_anzeigen
     ORDER BY created_at DESC"
)->fetchAll();

$imgStmt = $pdo->prepare('SELECT pfad, titel FROM hundeboerse_bilder WHERE anzeige_id = ? ORDER BY sortierung ASC, id ASC');

$anzeigen = [];
foreach ($rows as $row) {
    $imgStmt->execute([$row['id']]);
    $bilder = $imgStmt->fetchAll();
    $anzeigen[] = [
        'id' => $row['id'],
        'status' => $row['status'],
        'createdAt' => kjs_hb_admin_datetime_to_iso($row['created_at']),
        'updatedAt' => kjs_hb_admin_datetime_to_iso($row['updated_at']),
        'type' => $row['type'],
        'title' => $row['title'],
        'breed' => $row['breed'],
        'color' => $row['color'],
        'coat' => $row['coat'],
        'priceType' => $row['price_type'],
        'price' => $row['price'],
        'postalCode' => $row['postal_code'],
        'city' => $row['city'],
        'description' => $row['description'] ?? '',
        'father' => $row['father'],
        'fatherTests' => $row['father_tests'],
        'mother' => $row['mother'],
        'motherTests' => $row['mother_tests'],
        'huntingTests' => $row['hunting_tests'],
        'trainingLevel' => $row['training_level'] ?? '',
        'providerName' => $row['provider_name'],
        'contactPerson' => $row['contact_person'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'contactNotes' => $row['contact_notes'] ?? '',
        'dogName' => $row['dog_name'],
        'birthDate' => $row['birth_date'],
        'gender' => $row['gender'],
        'litterDate' => $row['litter_date'],
        'maleCount' => $row['male_count'],
        'femaleCount' => $row['female_count'],
        'galerie' => array_map(static function (array $b): array {
            return ['bild' => $b['pfad'], 'titel' => $b['titel']];
        }, $bilder),
        'galerie_titel' => $row['gallery_title'],
        'hasZuchtverband' => (bool) $row['has_zuchtverband'],
        'zuchtverband' => $row['zuchtverband'],
        'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
        'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
    ];
}

$zuchtverbaende = $pdo->query('SELECT name FROM hundeboerse_zuchtverbaende ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
$meta = $pdo->query('SELECT version, hero_bild FROM hundeboerse_meta WHERE id = 1')->fetch();

kjs_boerse_json_response(200, [
    'success' => true,
    'data' => [
        'hero_bild' => ($meta['hero_bild'] ?? '') ?: '',
        'anzeigen' => $anzeigen,
        'zuchtverbaende' => $zuchtverbaende,
    ],
    'version' => (int) ($meta['version'] ?? 0),
]);

function kjs_hb_admin_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}
