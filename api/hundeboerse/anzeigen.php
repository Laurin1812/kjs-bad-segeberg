<?php
/**
 * Oeffentlicher Endpunkt der Hundeboerse.
 *
 *   GET  /api/hundeboerse/anzeigen.php
 *     -> alle veroeffentlichten Anzeigen + Zuchtverband-Vorschlagsliste +
 *        Hero-Bild, in genau der Form, die hundeboerse/index.html und
 *        hundeboerse/anbieten.html bisher direkt aus
 *        content/hundeboerse.json gelesen haben (siehe js unten in den
 *        beiden Dateien) - absichtlich so, damit das Frontend bei
 *        Nichtverfuegbarkeit dieses Endpunkts (z.B. MySQL noch nicht
 *        konfiguriert) 1:1 auf die bestehende JSON-Datei zurueckfallen
 *        kann, ohne eigene Umformungslogik doppelt pflegen zu muessen.
 *
 *   POST /api/hundeboerse/anzeigen.php  (multipart/form-data)
 *     Felder:
 *       "daten"  - JSON-String mit den Formularfeldern (siehe
 *                  hundeboerse/anbieten.html), OHNE id/status/Zeitstempel -
 *                  die setzt ausschliesslich der Server.
 *       "bilder" - bis zu 10 Bilddateien (jpg/png/webp, siehe
 *                  api/lib/boerse_upload.php)
 *     Antwort: { ok: true, id: "hb-..." } bzw. { ok: false, error: "..." }
 *     (gleicher Rueckgabe-Vertrag wie bereits fuer die Waffenboerse in
 *     waffenboerse/anbieten.html dokumentiert - hier fuer Konsistenz
 *     identisch uebernommen).
 *
 * Sicherheit: Honeypot ("_honey"), IP-Rate-Limit, serverseitige
 * Nachvalidierung aller Felder (niemals nur der Client-Validierung
 * vertrauen), Laengenbegrenzung, Zeilenumbruch-/Header-Injection-Schutz,
 * feste Whitelist erlaubter Werte (Anliegen/Typ/Geschlecht/Preisart).
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/boerse_upload.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    kjs_hb_handle_list();
} elseif ($method === 'POST') {
    kjs_hb_handle_submit();
} else {
    header('Allow: GET, POST');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed', 'message' => 'Nur GET/POST erlaubt.']);
}

function kjs_hb_handle_list(): void
{
    $pdo = kjs_boerse_require_db();

    $stmt = $pdo->query(
        "SELECT id, status, type, title, breed, color, coat, price_type, price,
                postal_code, city, description, father, father_tests, mother,
                mother_tests, hunting_tests, training_level, provider_name,
                contact_person, email, phone, contact_notes, dog_name,
                birth_date, gender, litter_date, male_count, female_count,
                gallery_title, has_zuchtverband, zuchtverband, lat, lng,
                created_at, updated_at
         FROM hundeboerse_anzeigen
         WHERE status = 'published'
         ORDER BY created_at DESC"
    );
    $rows = $stmt->fetchAll();

    $imgStmt = $pdo->prepare('SELECT anzeige_id, pfad, titel FROM hundeboerse_bilder WHERE anzeige_id = ? ORDER BY sortierung ASC, id ASC');

    $anzeigen = [];
    foreach ($rows as $row) {
        $imgStmt->execute([$row['id']]);
        $bilder = $imgStmt->fetchAll();
        $anzeigen[] = kjs_hb_row_to_public($row, $bilder);
    }

    $zuchtverbaende = $pdo->query('SELECT name FROM hundeboerse_zuchtverbaende ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
    $heroBild = $pdo->query('SELECT hero_bild FROM hundeboerse_meta WHERE id = 1')->fetchColumn();

    kjs_boerse_json_response(200, [
        'hero_bild' => $heroBild ?: null,
        'anzeigen' => $anzeigen,
        'zuchtverbaende' => $zuchtverbaende,
    ]);
}

function kjs_hb_row_to_public(array $row, array $bilder): array
{
    return [
        'id' => $row['id'],
        'status' => $row['status'],
        'createdAt' => kjs_hb_datetime_to_iso($row['created_at']),
        'updatedAt' => kjs_hb_datetime_to_iso($row['updated_at']),
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

function kjs_hb_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}

function kjs_hb_handle_submit(): void
{
    // Honeypot zuerst pruefen (vor Rate-Limit/DB) - Bots bekommen einen
    // stillen "Erfolg", ohne dass irgendetwas gespeichert wird.
    $rawDaten = $_POST['daten'] ?? '';
    $input = [];
    if (is_string($rawDaten) && $rawDaten !== '') {
        $decoded = json_decode($rawDaten, true);
        if (is_array($decoded)) $input = $decoded;
    }

    $honey = kjs_boerse_field($input, '_honey', 200);
    if ($honey !== '') {
        kjs_boerse_json_response(200, ['ok' => true, 'id' => null]);
    }

    require_once __DIR__ . '/../lib/rate_limit.php';
    if (!kjs_rate_limit_check(kjs_client_ip(), 'hb_submit')) {
        kjs_boerse_json_response(429, ['ok' => false, 'error' => 'Zu viele Einreichungen. Bitte versuchen Sie es in ein paar Minuten erneut.']);
    }

    $pdo = kjs_boerse_require_db();

    $maxShort = 190;
    $maxLong = 5000;

    $type = kjs_boerse_field($input, 'type', 20) === 'litter' ? 'litter' : 'single';
    $title = kjs_boerse_field($input, 'title', $maxShort);
    $breed = kjs_boerse_field($input, 'breed', $maxShort);
    $color = kjs_boerse_field($input, 'color', $maxShort);
    $coat = kjs_boerse_field($input, 'coat', $maxShort);
    $dogName = $type === 'single' ? kjs_boerse_field($input, 'dogName', $maxShort) : '';
    $birthDate = $type === 'single' ? kjs_boerse_iso_date_to_de(kjs_boerse_field($input, 'birthDate', 20)) : '';
    $genderRaw = $type === 'single' ? kjs_boerse_field($input, 'gender', 10) : '';
    $gender = in_array($genderRaw, ['male', 'female'], true) ? $genderRaw : '';
    $litterDate = $type === 'litter' ? kjs_boerse_iso_date_to_de(kjs_boerse_field($input, 'litterDate', 20)) : '';
    $maleCount = $type === 'litter' ? preg_replace('/\D/', '', kjs_boerse_field($input, 'maleCount', 10)) : '';
    $femaleCount = $type === 'litter' ? preg_replace('/\D/', '', kjs_boerse_field($input, 'femaleCount', 10)) : '';

    $priceTypeRaw = kjs_boerse_field($input, 'priceType', 20, 'on_request');
    $allowedPriceTypes = ['fixed', 'negotiable', 'on_request', 'none'];
    $priceType = in_array($priceTypeRaw, $allowedPriceTypes, true) ? $priceTypeRaw : 'on_request';
    $price = kjs_boerse_field($input, 'price', 40);

    $postalCode = kjs_boerse_field($input, 'postalCode', 10);
    $city = kjs_boerse_field($input, 'city', $maxShort);
    $huntingTests = kjs_boerse_field($input, 'huntingTests', $maxShort);
    $trainingLevel = kjs_boerse_field($input, 'trainingLevel', $maxLong);
    $father = kjs_boerse_field($input, 'father', $maxShort);
    $fatherTests = kjs_boerse_field($input, 'fatherTests', $maxShort);
    $mother = kjs_boerse_field($input, 'mother', $maxShort);
    $motherTests = kjs_boerse_field($input, 'motherTests', $maxShort);
    $hasZuchtverband = kjs_boerse_bool($input['hasZuchtverband'] ?? false);
    $zuchtverband = $hasZuchtverband ? kjs_boerse_field($input, 'zuchtverband', $maxShort) : '';
    $description = kjs_boerse_field($input, 'description', $maxLong);
    $providerName = kjs_boerse_field($input, 'providerName', $maxShort);
    $contactPerson = kjs_boerse_field($input, 'contactPerson', $maxShort);
    $email = kjs_boerse_field($input, 'email', $maxShort);
    $phone = kjs_boerse_field($input, 'phone', 60);
    $contactNotes = kjs_boerse_field($input, 'contactNotes', $maxLong);

    $errors = [];
    if ($title === '') $errors[] = 'title';
    if ($breed === '') $errors[] = 'breed';
    if ($type === 'single') {
        if ($birthDate === '') $errors[] = 'birthDate';
        if ($gender === '') $errors[] = 'gender';
    } else {
        if ($litterDate === '') $errors[] = 'litterDate';
    }
    if ($postalCode === '' || !preg_match('/^\d{5}$/', $postalCode)) $errors[] = 'postalCode';
    if ($city === '') $errors[] = 'city';
    if ($description === '') $errors[] = 'description';
    if ($providerName === '') $errors[] = 'providerName';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || kjs_boerse_has_line_breaks($email)) $errors[] = 'email';

    foreach (['title' => $title, 'providerName' => $providerName, 'contactPerson' => $contactPerson, 'zuchtverband' => $zuchtverband] as $key => $value) {
        if (kjs_boerse_has_line_breaks($value) && !in_array($key, $errors, true)) $errors[] = $key;
    }

    $uploadedImages = [];
    try {
        $uploadedImages = kjs_boerse_handle_image_uploads($_FILES['bilder'] ?? [], 'hundeboerse', 10, 8 * 1024 * 1024);
    } catch (RuntimeException $e) {
        kjs_boerse_json_response(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
    if (count($uploadedImages) < 1) {
        $errors[] = 'bilder';
    }

    if (!empty($errors)) {
        // Bereits hochgeladene Bilder wieder entfernen, wenn der Rest der
        // Einreichung ungueltig ist - es sollen keine verwaisten Dateien
        // liegen bleiben.
        foreach ($uploadedImages as $img) {
            @unlink(dirname(__DIR__, 2) . $img['pfad']);
        }
        kjs_boerse_json_response(422, [
            'ok' => false,
            'error' => 'Bitte pruefen Sie Ihre Angaben: ' . implode(', ', $errors),
            'fields' => $errors,
        ]);
    }

    $id = 'hb-' . (string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(3));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO hundeboerse_anzeigen
                (id, status, type, title, breed, color, coat, price_type, price,
                 postal_code, city, description, father, father_tests, mother,
                 mother_tests, hunting_tests, training_level, provider_name,
                 contact_person, email, phone, contact_notes, dog_name,
                 birth_date, gender, litter_date, male_count, female_count,
                 gallery_title, has_zuchtverband, zuchtverband)
             VALUES
                (:id, \'pending\', :type, :title, :breed, :color, :coat, :price_type, :price,
                 :postal_code, :city, :description, :father, :father_tests, :mother,
                 :mother_tests, :hunting_tests, :training_level, :provider_name,
                 :contact_person, :email, :phone, :contact_notes, :dog_name,
                 :birth_date, :gender, :litter_date, :male_count, :female_count,
                 \'Bilder\', :has_zuchtverband, :zuchtverband)'
        );
        $stmt->execute([
            'id' => $id, 'type' => $type, 'title' => $title, 'breed' => $breed, 'color' => $color, 'coat' => $coat,
            'price_type' => $priceType, 'price' => $price, 'postal_code' => $postalCode, 'city' => $city,
            'description' => $description, 'father' => $father, 'father_tests' => $fatherTests, 'mother' => $mother,
            'mother_tests' => $motherTests, 'hunting_tests' => $huntingTests, 'training_level' => $trainingLevel,
            'provider_name' => $providerName, 'contact_person' => $contactPerson, 'email' => $email, 'phone' => $phone,
            'contact_notes' => $contactNotes, 'dog_name' => $dogName, 'birth_date' => $birthDate, 'gender' => $gender,
            'litter_date' => $litterDate, 'male_count' => $maleCount, 'female_count' => $femaleCount,
            'has_zuchtverband' => $hasZuchtverband ? 1 : 0, 'zuchtverband' => $zuchtverband,
        ]);

        $imgStmt = $pdo->prepare('INSERT INTO hundeboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');
        foreach ($uploadedImages as $i => $img) {
            $imgStmt->execute([$id, $img['pfad'], $img['titel'], $i]);
        }

        if ($zuchtverband !== '') {
            $pdo->prepare('INSERT IGNORE INTO hundeboerse_zuchtverbaende (name) VALUES (?)')->execute([$zuchtverband]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($uploadedImages as $img) {
            @unlink(dirname(__DIR__, 2) . $img['pfad']);
        }
        error_log('KJS Hundeboerse: Einreichung fehlgeschlagen - ' . $e->getMessage());
        kjs_boerse_json_response(500, ['ok' => false, 'error' => 'Die Einreichung ist technisch fehlgeschlagen. Bitte versuchen Sie es spaeter erneut.']);
    }

    kjs_boerse_json_response(200, ['ok' => true, 'id' => $id]);
}
