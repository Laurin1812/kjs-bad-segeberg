<?php
/**
 * Einmaliges Migrationsskript (09.09.2026, "Echte Thumbnail-/Vorschaubilder
 * wie Concrete5"): erzeugt für BESTEHENDE, bereits hochgeladene Bilder die
 * fehlenden Thumbnail-/Card-Vorschauvarianten nach - für neue Uploads
 * passiert das automatisch (admin.js beim Bild-Upload / api/lib/
 * boerse_upload.php bei öffentlichen Hundebörse-/Waffenbörse-Einreichungen),
 * für alle VOR dieser Umstellung hochgeladenen Bilder fehlen die Varianten
 * noch - genau die füllt dieses Skript auf.
 *
 * Nutzt bewusst dieselbe Erzeugungs-Funktion wie api/lib/boerse_upload.php
 * (kjs_boerse_generate_image_variants) - keine zweite, separat gepflegte
 * Implementierung der Skalier-/Format-Logik.
 *
 * WICHTIG - zwei getrennte Einsatzorte, siehe Abschlussbericht:
 *  1. images/ (Netlify-gehostete, über den Admin/git-gateway hochgeladene
 *     Bilder) - dieses Verzeichnis liegt IM GIT-REPOSITORY. Wurde im Rahmen
 *     dieser Umstellung bereits einmalig hier im Repository ausgeführt (die
 *     erzeugten Dateien sind Teil des Commits) - ein erneuter Lauf ist nur
 *     nötig, falls jemals wieder Bilder außerhalb des normalen Admin-Uploads
 *     ins images/-Verzeichnis gelangen.
 *  2. uploads/boersen/hundeboerse/ und uploads/boersen/waffenboerse/ (echte,
 *     serverseitig gespeicherte Dateien der öffentlichen Hundebörse-/
 *     Waffenbörse-Einreichungen) - dieses Verzeichnis liegt NICHT im
 *     Repository, sondern ausschließlich auf dem echten PHP-Server. Für
 *     bereits vor dieser Umstellung eingereichte Anzeigen müssen die
 *     Vorschauvarianten dort einmalig direkt auf dem Server nachgezogen
 *     werden - siehe "Verwendung" unten.
 *
 * Mehrfach ausführbar, ohne etwas kaputt zu machen: verarbeitet jede Datei
 * nur, wenn mindestens eine der beiden Varianten noch fehlt; Originale
 * werden nie angefasst oder überschrieben.
 *
 * Verwendung (auf dem jeweiligen Server bzw. hier im Repo-Checkout):
 *   php scripts/generate-thumbnails.php images
 *   php scripts/generate-thumbnails.php uploads/boersen/hundeboerse
 *   php scripts/generate-thumbnails.php uploads/boersen/waffenboerse
 *
 * Ohne Argument wird "images" (relativ zum Projekt-Wurzelverzeichnis)
 * verwendet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/lib/boerse_upload.php';

$targetArg = $argv[1] ?? 'images';
$dir = rtrim($targetArg, '/');
if ($dir === '' || $dir[0] !== '/') {
    $dir = dirname(__DIR__) . '/' . $dir;
}

if (!is_dir($dir)) {
    fwrite(STDERR, "Verzeichnis nicht gefunden: $dir\n");
    exit(1);
}

if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD-Extension ist auf diesem PHP nicht verfügbar - Migration kann hier nicht ausgeführt werden.\n");
    fwrite(STDERR, "Bitte auf einem Server mit aktivierter GD-Extension ausführen (php -m | grep gd zum Prüfen).\n");
    exit(1);
}

$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$allowedExt = ['jpg', 'jpeg', 'png', 'webp']; // gif/svg werden bewusst nie rasterisiert/verarbeitet (siehe admin.js IMG_SKIP_TYPES)

$total = 0; $processed = 0; $skippedExisting = 0; $skippedType = 0; $errors = 0;

$entries = scandir($dir);
foreach ($entries as $name) {
    if ($name === '.' || $name === '..') continue;
    $path = $dir . '/' . $name;
    if (!is_file($path)) continue; // thumb/ und card/ Unterordner selbst überspringen

    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $skippedType++;
        continue;
    }

    $total++;

    $thumbPath = $dir . '/thumb/' . $name;
    $cardPath = $dir . '/card/' . $name;
    if (is_file($thumbPath) && is_file($cardPath)) {
        $skippedExisting++;
        continue; // beide Varianten existieren bereits - nichts zu tun
    }

    $mime = $finfo ? (string) finfo_file($finfo, $path) : (string) mime_content_type($path);
    $mimeToExt = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true];
    if (!isset($mimeToExt[$mime])) {
        fwrite(STDERR, "Übersprungen (kein unterstütztes Bildformat laut Dateiinhalt): $name ($mime)\n");
        $skippedType++;
        continue;
    }

    try {
        kjs_boerse_generate_image_variants($path, $dir, $name, $mime);
        $ok = is_file($thumbPath) || is_file($cardPath) ||
            // Sehr kleine Bilder (kleiner als die Thumb-Zielgröße) erzeugen
            // absichtlich gar keine Variante (siehe Kommentar in
            // kjs_boerse_generate_image_variants) - das ist kein Fehler.
            true;
        $processed++;
        echo "✓ $name\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "✗ Fehler bei $name: " . $e->getMessage() . "\n");
        $errors++;
    }
}

if ($finfo) finfo_close($finfo);

echo "\n";
echo "Verzeichnis: $dir\n";
echo "Bilddateien gefunden: $total\n";
echo "Verarbeitet (mind. eine Variante fehlte): $processed\n";
echo "Bereits vollständig (übersprungen): $skippedExisting\n";
echo "Übersprungen (kein unterstütztes Format, z.B. gif/svg): $skippedType\n";
echo "Fehler: $errors\n";
