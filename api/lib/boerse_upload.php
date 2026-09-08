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

            $results[] = [
                'pfad' => '/uploads/boersen/' . $modul . '/' . $randomName,
                'titel' => '',
            ];
        }

        if ($finfo) finfo_close($finfo);

        return $results;
    }
}
