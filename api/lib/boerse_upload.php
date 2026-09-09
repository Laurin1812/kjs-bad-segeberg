<?php
/**
 * Serverseitige Bild-Upload-Verarbeitung fuer oeffentliche Einreichungen
 * (Hundeboerse/Waffenboerse). NUR fuer diesen Weg gedacht - Admin-seitig
 * hochgeladene Bilder laufen weiterhin unveraendert ueber die bestehende
 * Medienverwaltung (admin/admin.js, git-gateway nach images/), da Admins
 * dafuer bereits eine funktionierende, authentifizierte Upload-Route haben.
 * Oeffentliche Website-Besucher haben dagegen KEINEN git-gateway-Zugriff
 * (aus gutem Grund) und brauchen deshalb diesen eigenen, unauthentifizierten
 * aber serverseitig streng geprueften Upload-Pfad.
 *
 * Bilder werden als echte Dateien unterhalb von uploads/boersen/<modul>/
 * gespeichert (NICHT als Base64 in MySQL, siehe Punkt 4 des Auftrags) -
 * die Datenbank speichert ausschliesslich den resultierenden Pfad.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

if (!function_exists('kjs_boerse_upload_dir')) {
    function kjs_boerse_upload_dir(string $modul): string
    {
        return dirname(__DIR__, 2) . '/uploads/boersen/' . $modul;
    }
}

if (!function_exists('kjs_boerse_mime_to_ext')) {
    function kjs_boerse_mime_to_ext(string $mime): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? null;
    }
}

if (!function_exists('kjs_boerse_generate_image_variants')) {
    /**
     * Echte kleinere Vorschau-Dateien neben dem Original (09.09.2026, "Echte
     * Thumbnail-/Vorschaubilder wie Concrete5") - dieselben zwei Stufen wie
     * bei admin-seitig hochgeladenen Bildern (siehe admin/admin.js:
     * THUMB_MAX_DIMENSION=480 / CARD_MAX_DIMENSION=800), hier aber
     * SERVERSEITIG per GD erzeugt, weil oeffentliche Einreichungen nicht
     * durch den authentifizierten Admin-Upload laufen (siehe Kommentar oben
     * im Datei-Header). Landen unter uploads/boersen/<modul>/thumb/ bzw.
     * .../card/, IMMER unter demselben Dateinamen wie das Original - dadurch
     * bleibt die Datenbank unveraendert (Spalte "pfad" speichert weiterhin
     * nur den Original-Pfad, keine Schema-Migration noetig), Thumb/Card
     * werden im Frontend rein aus dem Dateinamen abgeleitet (siehe
     * hundeboerse/waffenboerse index.html + detail.html:
     * boerseThumbUrl()/boerseCardUrl()).
     *
     * Rein additiv und NIEMALS blockierend fuer den eigentlichen Upload:
     * fehlt die GD-Extension auf dem Server (siehe Abschlussbericht - auf
     * Netlify gibt es das serverseitige Aequivalent ueberhaupt nicht, hier
     * beim PHP-Server aber schon, sofern GD installiert ist), laesst sich
     * die Datei nicht dekodieren, oder reicht der Speicher nicht, wird das
     * stillschweigend uebersprungen - das Original ist zu diesem Zeitpunkt
     * schon gespeichert und bleibt so oder so nutzbar; das Frontend faellt
     * dann per onerror automatisch auf das Original zurueck.
     */
    function kjs_boerse_generate_image_variants(string $srcPath, string $dir, string $randomName, string $mime): void
    {
        if (!function_exists('imagecreatetruecolor')) return; // GD-Extension fehlt auf diesem Server

        $variants = ['thumb' => 480, 'card' => 800];

        try {
            switch ($mime) {
                case 'image/jpeg':
                    $src = @imagecreatefromjpeg($srcPath);
                    break;
                case 'image/png':
                    $src = @imagecreatefrompng($srcPath);
                    break;
                case 'image/webp':
                    $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false;
                    break;
                default:
                    $src = false;
            }
            if (!$src) return;

            $srcW = imagesx($src);
            $srcH = imagesy($src);

            foreach ($variants as $folder => $maxDim) {
                $scale = min(1, $maxDim / max($srcW, $srcH));
                // Kleiner als das Original macht bei einer bereits kleinen
                // Datei keinen Sinn - dann lieber gar keine separate Variante
                // (spart eine praktisch identische Zweitdatei, Frontend faellt
                // in dem Fall automatisch per onerror aufs Original zurueck).
                if ($scale >= 1) continue;

                $tw = max(1, (int) round($srcW * $scale));
                $th = max(1, (int) round($srcH * $scale));

                $dst = imagecreatetruecolor($tw, $th);
                if ($mime === 'image/png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $srcW, $srcH);

                $variantDir = $dir . '/' . $folder;
                if (!is_dir($variantDir) && !@mkdir($variantDir, 0755, true) && !is_dir($variantDir)) {
                    imagedestroy($dst);
                    continue;
                }
                $variantPath = $variantDir . '/' . $randomName;

                switch ($mime) {
                    case 'image/jpeg':
                        @imagejpeg($dst, $variantPath, $folder === 'thumb' ? 72 : 78);
                        break;
                    case 'image/png':
                        @imagepng($dst, $variantPath);
                        break;
                    case 'image/webp':
                        if (function_exists('imagewebp')) @imagewebp($dst, $variantPath, $folder === 'thumb' ? 72 : 78);
                        break;
                }
                @chmod($variantPath, 0644);
                imagedestroy($dst);
            }
            imagedestroy($src);
        } catch (\Throwable $e) {
            // Variante ist ein "nice to have" - niemals den eigentlichen
            // Upload (der schon erfolgreich war) daran scheitern lassen.
            error_log('KJS Boerse: Thumbnail-Erzeugung fehlgeschlagen - ' . $e->getMessage());
        }
    }
}

if (!function_exists('kjs_boerse_handle_image_uploads')) {
    /**
     * Verarbeitet ein multipart-Feld mit mehreren Dateien (PHP-Array-Notation,
     * z.B. $_FILES['bilder']) fuer ein bestimmtes Modul ("hundeboerse" oder
     * "waffenboerse"). Gibt eine Liste von ['pfad' => '/uploads/...', 'titel' => '']
     * in Einreichungsreihenfolge zurueck.
     *
     * Sicherheitsmassnahmen (Punkt 4 + 14 des Auftrags):
     *  - maximal $maxFiles Dateien, jede maximal $maxBytes gross
     *  - MIME-Typ wird serverseitig per fileinfo aus dem tatsaechlichen
     *    Dateiinhalt erkannt (NICHT dem vom Browser gesendeten Content-Type
     *    oder der Dateiendung vertraut) - nur jpeg/png/webp erlaubt
     *  - Dateiname wird IMMER serverseitig zufaellig neu vergeben
     *    (random_bytes), der urspruengliche Dateiname wird nirgends
     *    uebernommen oder auch nur gespeichert
     *  - Zielverzeichnis liegt ausserhalb jeder PHP-Ausfuehrung (siehe
     *    uploads/.htaccess) - auch ein durchgerutschtes, als Bild
     *    getarntes Skript koennte dort nicht ausgefuehrt werden
     *
     * Wirft eine RuntimeException mit einer fuer Endbenutzer verstaendlichen
     * deutschen Meldung, wenn eine Datei die Pruefung nicht besteht - der
     * Aufrufer faengt das ab und meldet es als Validierungsfehler.
     */
    function kjs_boerse_handle_image_uploads(array $filesField, string $modul, int $maxFiles, int $maxBytes): array
    {
        if (empty($filesField['name']) || !is_array($filesField['name'])) {
            return [];
        }

        $count = count($filesField['name']);
        if ($count > $maxFiles) {
            throw new RuntimeException('Es koennen maximal ' . $maxFiles . ' Bilder hochgeladen werden.');
        }

        $dir = kjs_boerse_upload_dir($modul);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Bild-Upload derzeit nicht moeglich (Speicherverzeichnis fehlt).');
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $errorCode = $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Ein Bild konnte nicht hochgeladen werden (Fehlercode ' . $errorCode . ').');
            }

            $tmpPath = $filesField['tmp_name'][$i] ?? '';
            $size = (int) ($filesField['size'][$i] ?? 0);

            if (!is_uploaded_file($tmpPath)) {
                throw new RuntimeException('Ungueltiger Datei-Upload.');
            }
            if ($size <= 0 || $size > $maxBytes) {
                throw new RuntimeException('Ein Bild ist zu gross (maximal ' . (int) round($maxBytes / 1024 / 1024) . ' MB pro Bild).');
            }

            $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : (string) mime_content_type($tmpPath);
            $ext = kjs_boerse_mime_to_ext($mime);
            if ($ext === null) {
                throw new RuntimeException('Nicht unterstuetztes Bildformat - erlaubt sind JPG, PNG und WebP.');
            }

            // Zusaetzliche Plausibilitaetspruefung: die Datei muss sich
            // tatsaechlich als Bild dekodieren lassen (getimagesize prueft
            // Header-Strukturen) - fischt offensichtlich manipulierte
            // Dateien heraus, die nur zufaellig valide MIME-Magic-Bytes
            // haben.
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) {
                throw new RuntimeException('Datei konnte nicht als Bild gelesen werden.');
            }

            $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
            $destPath = $dir . '/' . $randomName;
            if (!move_uploaded_file($tmpPath, $destPath)) {
                throw new RuntimeException('Bild konnte nicht gespeichert werden.');
            }
            @chmod($destPath, 0644);

            kjs_boerse_generate_image_variants($destPath, $dir, $randomName, $mime);

            $results[] = [
                'pfad' => '/uploads/boersen/' . $modul . '/' . $randomName,
                'titel' => '',
            ];
        }

        if ($finfo) finfo_close($finfo);

        return $results;
    }
}
