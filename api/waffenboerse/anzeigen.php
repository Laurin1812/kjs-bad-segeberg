<?php
/**
 * Oeffentlicher Endpunkt der Waffenboerse - Gegenstueck zu
 * api/hundeboerse/anzeigen.php, siehe dort fuer die ausfuehrliche
 * Erklaerung von GET/POST-Vertrag und Sicherheitsmassnahmen.
 *
 * POST-Antwortform ({ok,id}/{ok,error}) entspricht exakt dem bereits in
 * waffenboerse/anbieten.html (Funktion submitWaffenboerseAnzeige(),
 * Kommentar "ERWARTETE SPAETERE BACKEND-SCHNITTSTELLE") dokumentierten
 * Vertrag - dieser Endpunkt loest genau das vorbereitete Formular ein.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/boerse_upload.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    kjs_wb_handle_list();
} elseif ($method === 'POST') {
    kjs_wb_handle_submit();
} else {
    header('Allow: GET, POST');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed', 'message' => 'Nur GET/POST erlaubt.']);
}

function kjs_wb_handle_list(): void
{
    $pdo = kjs_boerse_require_db();

    $rows = $pdo->query(
        "SELECT id, status, titel, kategorie, hersteller, modell, zustand, preis,
                preis_typ, erwerbsberechtigung_erforderlich, beschreibung, plz, ort,
                versand_moeglich, versandkosten, anbieter_name, anbieter_email,
                anbieter_telefon, erstellt_am, aktualisiert_am
         FROM waffenboerse_anzeigen
         WHERE status = 'published'
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
        $anzeigen[] = kjs_wb_row_to_public($row, $bilder, $kaliber);
    }

    $kategorien = $pdo->query('SELECT name FROM waffenboerse_kategorien ORDER BY sortierung ASC, name ASC')->fetchAll(PDO::FETCH_COLUMN);

    kjs_boerse_json_response(200, [
        'anzeigen' => $anzeigen,
        'kategorien' => $kategorien,
    ]);
}

function kjs_wb_row_to_public(array $row, array $bilder, array $kaliber): array
{
    return [
        'id' => $row['id'],
        'status' => $row['status'],
        'erstellt_am' => kjs_wb_datetime_to_iso($row['erstellt_am']),
        'aktualisiert_am' => kjs_wb_datetime_to_iso($row['aktualisiert_am']),
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

function kjs_wb_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}

/**
 * Baut aus reinem Freitext sicheres, minimales Absatz-HTML (Absaetze durch
 * Leerzeilen getrennt, Zeilenumbrueche als <br>) - der komplette Text wird
 * dabei escaped, es koennen also NIE HTML-/Script-Tags aus der Eingabe
 * durchschlagen. Ersetzt serverseitig das clientseitige "alsSicheresHtml()"
 * aus waffenboerse/anbieten.html (Grundsatz: dem Client wird bei
 * sicherheitsrelevanter Verarbeitung nie vertraut, auch wenn er es "schon
 * richtig macht").
 */
function kjs_wb_text_to_safe_html(string $text): string
{
    $bloecke = preg_split('/\n{2,}/', trim($text)) ?: [];
    $html = '';
    foreach ($bloecke as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $escaped = htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '<p>' . str_replace("\n", '<br>', $escaped) . '</p>';
    }
    return $html;
}

function kjs_wb_handle_submit(): void
{
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
    if (!kjs_rate_limit_check(kjs_client_ip(), 'wb_submit')) {
        kjs_boerse_json_response(429, ['ok' => false, 'error' => 'Zu viele Einreichungen. Bitte versuchen Sie es in ein paar Minuten erneut.']);
    }

    $pdo = kjs_boerse_require_db();

    $maxShort = 190;
    $maxLong = 20000;

    $titel = kjs_boerse_field($input, 'titel', $maxShort);
    $kategorie = kjs_boerse_field($input, 'kategorie', $maxShort);
    $hersteller = kjs_boerse_field($input, 'hersteller', $maxShort);
    $modell = kjs_boerse_field($input, 'modell', $maxShort);
    $zustandRaw = kjs_boerse_field($input, 'zustand', 20);
    $zustand = in_array($zustandRaw, ['neu', 'gebraucht'], true) ? $zustandRaw : '';
    $preisTypRaw = kjs_boerse_field($input, 'preis_typ', 20);
    $preisTyp = in_array($preisTypRaw, ['festpreis', 'vb'], true) ? $preisTypRaw : '';
    $preis = kjs_boerse_field($input, 'preis', 40);
    $versandMoeglich = kjs_boerse_bool($input['versand_moeglich'] ?? false);
    $versandkosten = $versandMoeglich ? kjs_boerse_field($input, 'versandkosten', 40) : '';
    $plz = kjs_boerse_field($input, 'plz', 10);
    $ort = kjs_boerse_field($input, 'ort', $maxShort);
    $erwerbErforderlich = kjs_boerse_bool($input['erwerbsberechtigung_erforderlich'] ?? false);
    // "beschreibung" kommt vom Client als reiner Freitext (siehe Kommentar
    // bei kjs_wb_text_to_safe_html oben) - die HTML-Umwandlung passiert
    // ausschliesslich hier serverseitig.
    $beschreibungText = kjs_boerse_field($input, 'beschreibung', $maxLong);
    $beschreibung = kjs_wb_text_to_safe_html($beschreibungText);
    $anbieterName = kjs_boerse_field($input, 'anbieter_name', $maxShort);
    $anbieterEmail = kjs_boerse_field($input, 'anbieter_email', $maxShort);
    $anbieterTelefon = kjs_boerse_field($input, 'anbieter_telefon', 60);

    $kaliberRaw = is_array($input['kaliber'] ?? null) ? $input['kaliber'] : [];
    $kaliber = [];
    foreach (array_slice($kaliberRaw, 0, 20) as $k) {
        if (!is_string($k)) continue;
        $k = trim($k);
        if (function_exists('mb_substr')) $k = mb_substr($k, 0, 100); else $k = substr($k, 0, 100);
        if ($k !== '' && !kjs_boerse_has_line_breaks($k)) $kaliber[] = $k;
    }

    $errors = [];
    if ($titel === '') $errors[] = 'titel';

    $bekannteKategorien = $pdo->query('SELECT name FROM waffenboerse_kategorien')->fetchAll(PDO::FETCH_COLUMN);
    if ($kategorie === '' || !in_array($kategorie, $bekannteKategorien, true)) $errors[] = 'kategorie';

    if ($hersteller === '') $errors[] = 'hersteller';
    if ($zustand === '') $errors[] = 'zustand';
    if ($preisTyp === '') $errors[] = 'preis_typ';
    if ($preis === '' || !preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+([.,]\d{1,2})?$/', $preis)) $errors[] = 'preis';
    if ($plz === '' || !preg_match('/^\d{5}$/', $plz)) $errors[] = 'plz';
    if ($ort === '') $errors[] = 'ort';
    if ($beschreibungText === '') $errors[] = 'beschreibung';
    if ($anbieterName === '') $errors[] = 'anbieter_name';
    if ($anbieterEmail === '' || !filter_var($anbieterEmail, FILTER_VALIDATE_EMAIL) || kjs_boerse_has_line_breaks($anbieterEmail)) $errors[] = 'anbieter_email';

    foreach (['titel' => $titel, 'anbieter_name' => $anbieterName, 'hersteller' => $hersteller, 'modell' => $modell] as $key => $value) {
        if (kjs_boerse_has_line_breaks($value) && !in_array($key, $errors, true)) $errors[] = $key;
    }

    $uploadedImages = [];
    try {
        $uploadedImages = kjs_boerse_handle_image_uploads($_FILES['bilder'] ?? [], 'waffenboerse', 10, 8 * 1024 * 1024);
    } catch (RuntimeException $e) {
        kjs_boerse_json_response(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
    if (count($uploadedImages) < 1) {
        $errors[] = 'bilder';
    }

    if (!empty($errors)) {
        foreach ($uploadedImages as $img) {
            @unlink(dirname(__DIR__, 2) . $img['pfad']);
        }
        kjs_boerse_json_response(422, [
            'ok' => false,
            'error' => 'Bitte pruefen Sie Ihre Angaben: ' . implode(', ', $errors),
            'fields' => $errors,
        ]);
    }

    $id = 'wb-' . (string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(3));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO waffenboerse_anzeigen
                (id, status, titel, kategorie, hersteller, modell, zustand, preis,
                 preis_typ, erwerbsberechtigung_erforderlich, beschreibung, plz, ort,
                 versand_moeglich, versandkosten, anbieter_name, anbieter_email, anbieter_telefon)
             VALUES
                (:id, \'pending\', :titel, :kategorie, :hersteller, :modell, :zustand, :preis,
                 :preis_typ, :erwerb, :beschreibung, :plz, :ort,
                 :versand, :versandkosten, :anbieter_name, :anbieter_email, :anbieter_telefon)'
        );
        $stmt->execute([
            'id' => $id, 'titel' => $titel, 'kategorie' => $kategorie, 'hersteller' => $hersteller,
            'modell' => $modell, 'zustand' => $zustand, 'preis' => $preis, 'preis_typ' => $preisTyp,
            'erwerb' => $erwerbErforderlich ? 1 : 0, 'beschreibung' => $beschreibung, 'plz' => $plz, 'ort' => $ort,
            'versand' => $versandMoeglich ? 1 : 0, 'versandkosten' => $versandkosten,
            'anbieter_name' => $anbieterName, 'anbieter_email' => $anbieterEmail, 'anbieter_telefon' => $anbieterTelefon,
        ]);

        $imgStmt = $pdo->prepare('INSERT INTO waffenboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');
        foreach ($uploadedImages as $i => $img) {
            $imgStmt->execute([$id, $img['pfad'], $img['titel'], $i]);
        }

        $kalStmt = $pdo->prepare('INSERT INTO waffenboerse_kaliber (anzeige_id, kaliber, sortierung) VALUES (?, ?, ?)');
        foreach ($kaliber as $i => $k) {
            $kalStmt->execute([$id, $k, $i]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($uploadedImages as $img) {
            @unlink(dirname(__DIR__, 2) . $img['pfad']);
        }
        error_log('KJS Waffenboerse: Einreichung fehlgeschlagen - ' . $e->getMessage());
        kjs_boerse_json_response(500, ['ok' => false, 'error' => 'Die Einreichung ist technisch fehlgeschlagen. Bitte versuchen Sie es spaeter erneut.']);
    }

    kjs_boerse_json_response(200, ['ok' => true, 'id' => $id]);
}
