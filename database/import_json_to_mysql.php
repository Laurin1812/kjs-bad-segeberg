<?php
/**
 * EINMALIGES, MANUELL AUSZUFUEHRENDES Import-Skript:
 * content/hundeboerse.json + content/waffenboerse.json -> MySQL.
 *
 * WICHTIG: Dieses Skript wird NIE automatisch ausgefuehrt (kein Aufruf aus
 * irgendeinem der oeffentlichen/Admin-Endpunkte) - es ist bewusst nur ein
 * Kommandozeilen-Werkzeug fuer den einmaligen Umstieg von den bisherigen
 * JSON-Dateien auf die neue Datenbank, siehe Auftragspunkt 11 ("bestehende
 * JSON-Daten nicht sofort loeschen ... Uebergang sicher gestalten").
 *
 * Voraussetzungen vor der Ausfuehrung:
 *   1. database/schema.sql wurde bereits auf der Ziel-Datenbank angewendet.
 *   2. Echte DB-Zugangsdaten sind konfiguriert (Environment-Variablen oder
 *      config/db.local.php, siehe config/db.example.php).
 *   3. Die Ziel-Tabellen sind leer (das Skript bricht sonst ab, um keine
 *      Duplikate oder Vermischung mit bereits ueber die neue API
 *      angelegten Anzeigen zu erzeugen - siehe kjs_import_guard_empty()).
 *
 * Aufruf (auf dem Server mit PHP-CLI-Zugriff, z.B. per SSH):
 *   php database/import_json_to_mysql.php
 *
 * Das Skript ist bewusst NICHT ueber HTTP aufrufbar (siehe _redirects:
 * /database/* ist auf der Netlify-Staging-Umgebung geblockt; auf dem echten
 * PHP-Host sollte der Ordner database/ zusaetzlich per Server-Konfiguration
 * oder durch Verschieben ausserhalb des Webroots vor direktem HTTP-Zugriff
 * geschuetzt werden, sonst koennte ein Aufruf ueber die URL versehentlich
 * einen Import ausloesen).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Dieses Skript darf nur ueber die Kommandozeile (PHP-CLI) ausgefuehrt werden.\n";
    exit(1);
}

require_once __DIR__ . '/../api/lib/response.php';
require_once __DIR__ . '/../api/lib/db.php';

function kjs_import_fail(string $message): void
{
    fwrite(STDERR, "FEHLER: $message\n");
    exit(1);
}

function kjs_import_guard_empty(PDO $pdo, string $table): void
{
    $count = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    if ($count > 0) {
        kjs_import_fail("Tabelle '$table' enthaelt bereits $count Zeile(n) - Import abgebrochen, um Duplikate zu vermeiden. " .
            "Bitte vor einem erneuten Import bewusst leeren oder dieses Skript anpassen.");
    }
}

$pdo = kjs_boerse_db();
if ($pdo === null) {
    kjs_import_fail('Keine Datenbankverbindung konfiguriert (siehe config/db.example.php). Import abgebrochen.');
}

$projectRoot = dirname(__DIR__);

// ---------------------------------------------------------------------------
// HUNDEBOERSE
// ---------------------------------------------------------------------------
$hbPath = $projectRoot . '/content/hundeboerse.json';
if (is_file($hbPath)) {
    kjs_import_guard_empty($pdo, 'hundeboerse_anzeigen');

    $hbData = json_decode((string) file_get_contents($hbPath), true);
    if (!is_array($hbData)) {
        kjs_import_fail('content/hundeboerse.json konnte nicht gelesen/geparst werden.');
    }

    $anzeigen = is_array($hbData['anzeigen'] ?? null) ? $hbData['anzeigen'] : [];
    $insertStmt = $pdo->prepare(
        'INSERT INTO hundeboerse_anzeigen
            (id, status, type, title, breed, color, coat, price_type, price,
             postal_code, city, description, father, father_tests, mother,
             mother_tests, hunting_tests, training_level, provider_name,
             contact_person, email, phone, contact_notes, dog_name,
             birth_date, gender, litter_date, male_count, female_count,
             gallery_title, has_zuchtverband, zuchtverband, lat, lng,
             created_at, updated_at)
         VALUES
            (:id, :status, :type, :title, :breed, :color, :coat, :price_type, :price,
             :postal_code, :city, :description, :father, :father_tests, :mother,
             :mother_tests, :hunting_tests, :training_level, :provider_name,
             :contact_person, :email, :phone, :contact_notes, :dog_name,
             :birth_date, :gender, :litter_date, :male_count, :female_count,
             :gallery_title, :has_zuchtverband, :zuchtverband, :lat, :lng,
             :created_at, :updated_at)'
    );
    $imgStmt = $pdo->prepare('INSERT INTO hundeboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');

    $pdo->beginTransaction();
    $imported = 0;
    foreach ($anzeigen as $a) {
        if (!is_array($a) || empty($a['id'])) continue;
        $insertStmt->execute([
            'id' => (string) $a['id'],
            'status' => $a['status'] ?? 'pending',
            'type' => $a['type'] ?? 'single',
            'title' => $a['title'] ?? '',
            'breed' => $a['breed'] ?? '',
            'color' => $a['color'] ?? '',
            'coat' => $a['coat'] ?? '',
            'price_type' => $a['priceType'] ?? 'on_request',
            'price' => $a['price'] ?? '',
            'postal_code' => $a['postalCode'] ?? '',
            'city' => $a['city'] ?? '',
            'description' => $a['description'] ?? '',
            'father' => $a['father'] ?? '',
            'father_tests' => $a['fatherTests'] ?? '',
            'mother' => $a['mother'] ?? '',
            'mother_tests' => $a['motherTests'] ?? '',
            'hunting_tests' => $a['huntingTests'] ?? '',
            'training_level' => $a['trainingLevel'] ?? '',
            'provider_name' => $a['providerName'] ?? '',
            'contact_person' => $a['contactPerson'] ?? '',
            'email' => $a['email'] ?? '',
            'phone' => $a['phone'] ?? '',
            'contact_notes' => $a['contactNotes'] ?? '',
            'dog_name' => $a['dogName'] ?? '',
            'birth_date' => $a['birthDate'] ?? '',
            'gender' => $a['gender'] ?? '',
            'litter_date' => $a['litterDate'] ?? '',
            'male_count' => $a['maleCount'] ?? '',
            'female_count' => $a['femaleCount'] ?? '',
            'gallery_title' => $a['galerie_titel'] ?? 'Bilder',
            'has_zuchtverband' => !empty($a['hasZuchtverband']) ? 1 : 0,
            'zuchtverband' => $a['zuchtverband'] ?? '',
            'lat' => is_numeric($a['lat'] ?? null) ? (float) $a['lat'] : null,
            'lng' => is_numeric($a['lng'] ?? null) ? (float) $a['lng'] : null,
            'created_at' => kjs_import_iso_to_mysql($a['createdAt'] ?? null),
            'updated_at' => kjs_import_iso_to_mysql($a['updatedAt'] ?? null),
        ]);
        $galerie = is_array($a['galerie'] ?? null) ? $a['galerie'] : [];
        foreach ($galerie as $sort => $g) {
            if (!is_array($g) || empty($g['bild'])) continue;
            $imgStmt->execute([(string) $a['id'], $g['bild'], $g['titel'] ?? '', $sort]);
        }
        $imported++;
    }

    $zuchtverbaende = is_array($hbData['zuchtverbaende'] ?? null) ? $hbData['zuchtverbaende'] : [];
    $zvStmt = $pdo->prepare('INSERT IGNORE INTO hundeboerse_zuchtverbaende (name) VALUES (?)');
    foreach ($zuchtverbaende as $z) {
        if (is_string($z) && trim($z) !== '') $zvStmt->execute([trim($z)]);
    }

    $heroBild = is_string($hbData['hero_bild'] ?? null) ? $hbData['hero_bild'] : null;
    $pdo->prepare('UPDATE hundeboerse_meta SET hero_bild = ?, version = version + 1 WHERE id = 1')->execute([$heroBild]);

    $pdo->commit();
    echo "Hundeboerse: $imported Anzeige(n) importiert.\n";
} else {
    echo "Hundeboerse: content/hundeboerse.json nicht gefunden - uebersprungen.\n";
}

// ---------------------------------------------------------------------------
// WAFFENBOERSE
// ---------------------------------------------------------------------------
$wbPath = $projectRoot . '/content/waffenboerse.json';
if (is_file($wbPath)) {
    kjs_import_guard_empty($pdo, 'waffenboerse_anzeigen');

    $wbData = json_decode((string) file_get_contents($wbPath), true);
    if (!is_array($wbData)) {
        kjs_import_fail('content/waffenboerse.json konnte nicht gelesen/geparst werden.');
    }

    $anzeigen = is_array($wbData['anzeigen'] ?? null) ? $wbData['anzeigen'] : [];
    $insertStmt = $pdo->prepare(
        'INSERT INTO waffenboerse_anzeigen
            (id, status, titel, kategorie, hersteller, modell, zustand, preis,
             preis_typ, erwerbsberechtigung_erforderlich, beschreibung, plz, ort,
             versand_moeglich, versandkosten, anbieter_name, anbieter_email,
             anbieter_telefon, erstellt_am, aktualisiert_am)
         VALUES
            (:id, :status, :titel, :kategorie, :hersteller, :modell, :zustand, :preis,
             :preis_typ, :erwerb, :beschreibung, :plz, :ort,
             :versand, :versandkosten, :anbieter_name, :anbieter_email,
             :anbieter_telefon, :erstellt_am, :aktualisiert_am)'
    );
    $imgStmt = $pdo->prepare('INSERT INTO waffenboerse_bilder (anzeige_id, pfad, titel, sortierung) VALUES (?, ?, ?, ?)');
    $kalStmt = $pdo->prepare('INSERT INTO waffenboerse_kaliber (anzeige_id, kaliber, sortierung) VALUES (?, ?, ?)');

    $pdo->beginTransaction();
    $imported = 0;
    foreach ($anzeigen as $a) {
        if (!is_array($a) || empty($a['id'])) continue;
        $insertStmt->execute([
            'id' => (string) $a['id'],
            'status' => $a['status'] ?? 'pending',
            'titel' => $a['titel'] ?? '',
            'kategorie' => $a['kategorie'] ?? '',
            'hersteller' => $a['hersteller'] ?? '',
            'modell' => $a['modell'] ?? '',
            'zustand' => $a['zustand'] ?? '',
            'preis' => $a['preis'] ?? '',
            'preis_typ' => $a['preis_typ'] ?? '',
            'erwerb' => !empty($a['erwerbsberechtigung_erforderlich']) ? 1 : 0,
            'beschreibung' => $a['beschreibung'] ?? '',
            'plz' => $a['plz'] ?? '',
            'ort' => $a['ort'] ?? '',
            'versand' => !empty($a['versand_moeglich']) ? 1 : 0,
            'versandkosten' => $a['versandkosten'] ?? '',
            'anbieter_name' => $a['anbieter_name'] ?? '',
            'anbieter_email' => $a['anbieter_email'] ?? '',
            'anbieter_telefon' => $a['anbieter_telefon'] ?? '',
            'erstellt_am' => kjs_import_iso_to_mysql($a['erstellt_am'] ?? null),
            'aktualisiert_am' => kjs_import_iso_to_mysql($a['aktualisiert_am'] ?? null),
        ]);
        $bilder = is_array($a['bilder'] ?? null) ? $a['bilder'] : [];
        foreach ($bilder as $sort => $g) {
            if (!is_array($g) || empty($g['bild'])) continue;
            $imgStmt->execute([(string) $a['id'], $g['bild'], $g['titel'] ?? '', $sort]);
        }
        $kaliber = is_array($a['kaliber'] ?? null) ? $a['kaliber'] : [];
        foreach ($kaliber as $sort => $k) {
            if (!is_string($k) || trim($k) === '') continue;
            $kalStmt->execute([(string) $a['id'], $k, $sort]);
        }
        $imported++;
    }

    $kategorien = is_array($wbData['kategorien'] ?? null) ? $wbData['kategorien'] : [];
    if (!empty($kategorien)) {
        $pdo->exec('DELETE FROM waffenboerse_kategorien');
        $katStmt = $pdo->prepare('INSERT INTO waffenboerse_kategorien (name, sortierung) VALUES (?, ?)');
        foreach ($kategorien as $sort => $k) {
            if (is_string($k) && trim($k) !== '') $katStmt->execute([trim($k), $sort]);
        }
    }

    $pdo->prepare('UPDATE waffenboerse_meta SET version = version + 1 WHERE id = 1')->execute();

    $pdo->commit();
    echo "Waffenboerse: $imported Anzeige(n) importiert.\n";
} else {
    echo "Waffenboerse: content/waffenboerse.json nicht gefunden - uebersprungen.\n";
}

echo "Import abgeschlossen.\n";

function kjs_import_iso_to_mysql(?string $iso): ?string
{
    if (!$iso) return null;
    $ts = strtotime($iso);
    if ($ts === false) return null;
    return gmdate('Y-m-d H:i:s', $ts);
}
