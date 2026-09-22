<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * KJS Bad Segeberg - Phase 6A (Sondermodule inventarisieren + Hundeboerse
 * auf Laravel/MySQL).
 *
 * Server-seitiger Laravel-Port von api/lib/boerse_upload.php
 * (kjs_boerse_handle_image_uploads/kjs_boerse_generate_image_variants) fuer
 * oeffentliche Boersen-Einreichungen ("Anbieten"). Bewusst als eigene,
 * modulunabhaengige Klasse (Parameter $modul wie im PHP-Original) statt nur
 * hart in HundeboerseController - im PHP-Original ist boerse_upload.php
 * ebenfalls bereits fuer Hundeboerse UND Waffenboerse gemeinsam gebaut, eine
 * spaetere Waffenboerse-Migration (naechste Phase, siehe Abschlussbericht)
 * kann diese Klasse dann unveraendert mitverwenden - keine neue
 * Generalisierung, nur derselbe bereits im PHP-Original etablierte
 * Zuschnitt.
 *
 * Sicherheitsmassnahmen (1:1 aus dem PHP-Original uebernommen, siehe dort
 * fuer die ausfuehrliche Begruendung):
 *  - serverseitige MIME-Erkennung aus dem tatsaechlichen Dateiinhalt
 *    (Laravel-Validierungsregel "mimes:" nutzt bereits finfo/Magic-Bytes,
 *    NICHT den vom Browser gesendeten Content-Type oder die Dateiendung -
 *    siehe HundeboerseAnbietenRequest::rules())
 *  - zusaetzliche Plausibilitaetspruefung per getimagesize() (fischt
 *    offensichtlich manipulierte Dateien heraus, die nur zufaellig valide
 *    MIME-Magic-Bytes haben)
 *  - Dateiname wird IMMER serverseitig zufaellig neu vergeben
 *    (random_bytes) - der urspruengliche Dateiname wird nirgends
 *    uebernommen oder gespeichert
 *  - Zielverzeichnis (public/uploads/boersen/<modul>/) liegt ausserhalb
 *    jeder PHP-Ausfuehrung (siehe uploads/.htaccess im alten Webroot als
 *    Vorbild) - auch eine als Bild getarnte Datei koennte dort nicht
 *    ausgefuehrt werden
 */
class BoerseUploads
{
    private const VARIANTS = ['thumb' => 480, 'card' => 800];

    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Verarbeitet eine Liste hochgeladener Bilddateien fuer ein Modul
     * ("hundeboerse"). Gibt eine Liste von ['pfad' => '/uploads/...',
     * 'titel' => ''] in Einreichungsreihenfolge zurueck - "pfad" ist eine
     * public-relative URL, NIE Bilddaten selbst (siehe HundeboerseBild-
     * Model/Migration).
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{pfad: string, titel: string}>
     */
    public static function store(array $files, string $modul): array
    {
        $dir = public_path('uploads/boersen/'.$modul);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $results = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            // Zusaetzliche Plausibilitaetspruefung ueber die Laravel-
            // Validierung hinaus (siehe Klassenkommentar): die Datei muss
            // sich tatsaechlich als Bild dekodieren lassen.
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false) {
                continue;
            }

            $mime = $file->getMimeType() ?? '';
            $ext = self::MIME_TO_EXT[$mime] ?? null;
            if ($ext === null) {
                continue;
            }

            $randomName = bin2hex(random_bytes(16)).'.'.$ext;
            $file->move($dir, $randomName);
            $destPath = $dir.'/'.$randomName;
            @chmod($destPath, 0644);

            self::generateVariants($destPath, $dir, $randomName, $mime);

            $results[] = [
                'pfad' => '/uploads/boersen/'.$modul.'/'.$randomName,
                'titel' => '',
            ];
        }

        return $results;
    }

    /**
     * Echte kleinere Vorschau-Dateien neben dem Original (siehe
     * api/lib/boerse_upload.php::kjs_boerse_generate_image_variants() fuer
     * die vollstaendige Begruendung) - landen unter
     * uploads/boersen/<modul>/thumb/ bzw. .../card/, IMMER unter demselben
     * Dateinamen wie das Original, damit die Datenbank unveraendert bleibt
     * (Spalte "pfad" speichert nur den Original-Pfad) und Thumb/Card rein
     * aus dem Dateinamen ableitbar sind (siehe Images::boerseThumbUrl()/
     * boerseCardUrl()).
     *
     * Rein additiv und NIEMALS blockierend: fehlt die GD-Extension, laesst
     * sich die Datei nicht dekodieren, oder reicht der Speicher nicht, wird
     * das stillschweigend uebersprungen - das Original ist zu diesem
     * Zeitpunkt schon gespeichert und bleibt so oder so nutzbar; das
     * Frontend faellt per onerror automatisch auf das Original zurueck.
     */
    private static function generateVariants(string $srcPath, string $dir, string $randomName, string $mime): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        try {
            $src = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($srcPath),
                'image/png' => @imagecreatefrompng($srcPath),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
                default => false,
            };
            if (! $src) {
                return;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);

            foreach (self::VARIANTS as $folder => $maxDim) {
                $scale = min(1, $maxDim / max($srcW, $srcH));
                if ($scale >= 1) {
                    continue;
                }

                $tw = max(1, (int) round($srcW * $scale));
                $th = max(1, (int) round($srcH * $scale));

                $dst = imagecreatetruecolor($tw, $th);
                if ($mime === 'image/png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $srcW, $srcH);

                $variantDir = $dir.'/'.$folder;
                if (! is_dir($variantDir) && ! @mkdir($variantDir, 0755, true) && ! is_dir($variantDir)) {
                    imagedestroy($dst);

                    continue;
                }
                $variantPath = $variantDir.'/'.$randomName;

                match ($mime) {
                    'image/jpeg' => @imagejpeg($dst, $variantPath, $folder === 'thumb' ? 72 : 78),
                    'image/png' => @imagepng($dst, $variantPath),
                    'image/webp' => function_exists('imagewebp') ? @imagewebp($dst, $variantPath, $folder === 'thumb' ? 72 : 78) : null,
                    default => null,
                };
                @chmod($variantPath, 0644);
                imagedestroy($dst);
            }
            imagedestroy($src);
        } catch (\Throwable) {
            // Variante ist ein "nice to have" - niemals den eigentlichen
            // Upload (der schon erfolgreich war) daran scheitern lassen.
        }
    }
}
