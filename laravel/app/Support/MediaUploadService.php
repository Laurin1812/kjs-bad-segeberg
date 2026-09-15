<?php

namespace App\Support;

use App\Models\MedienEintrag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase 5B.1 (sichere Laravel-Medienarchitektur): zentrale Upload-/
 * Loesch-Logik fuer die neue authentifizierte Admin-Medien-API. Die
 * Sicherheitspruefungen sind bewusst 1:1 an api/lib/boerse_upload.php
 * angelehnt (siehe Analysebericht Phase 5A Punkt 6 - dort bereits
 * produktiv fuer oeffentliche Hundeboerse-/Waffenboerse-Einreichungen
 * im Einsatz): echte MIME-Erkennung per finfo aus dem Dateiinhalt (nie
 * Content-Type-Header oder Dateiendung vertrauen), getimagesize() als
 * zusaetzliche Bild-Plausibilitaetspruefung, serverseitig per random_bytes
 * zufaellig vergebene Dateinamen (Originalname wird NIE als physischer
 * Dateiname uebernommen, nur als Metadatum in der DB gespeichert).
 *
 * NEU gegenueber dem Vorbild (Auftrag Punkt 4, "Upload + Varianten
 * moeglichst atomar behandeln"): waehrend api/lib/boerse_upload.php eine
 * fehlgeschlagene Thumb/Card-Erzeugung bewusst stillschweigend ueberspringt
 * (das Original bleibt dort so oder so nutzbar, das Frontend faellt per
 * onerror darauf zurueck), verlangt die Admin-Medienbibliothek hier
 * garantiert vorhandene Varianten - schlaegt die Varianten-Erzeugung fehl
 * (inkl. komplett fehlender GD-Erweiterung), wird der gesamte Upload
 * (inkl. bereits geschriebenem Original) rueckgaengig gemacht statt einen
 * Datensatz ohne Thumb/Card zurueckzulassen.
 */
class MediaUploadService
{
    /** @return array{mime: string, ext: string} */
    private function detectAndValidateImage(UploadedFile $file): array
    {
        $maxBytes = (int) config('kjs_media.images.max_bytes');
        if ($file->getSize() === false || $file->getSize() > $maxBytes) {
            throw new MediaValidationException('Bild ist zu groß (maximal '.(int) round($maxBytes / 1024 / 1024).' MB erlaubt).');
        }

        $mime = $this->realMimeType($file->getRealPath());
        $allowed = config('kjs_media.images.allowed_mimes');
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! in_array($mime, $allowed, true) || ! isset($extMap[$mime])) {
            throw new MediaValidationException('Nicht unterstütztes Bildformat – erlaubt sind ausschließlich JPEG, PNG und WebP.');
        }

        // Zusaetzliche Plausibilitaetspruefung (identisch zu
        // api/lib/boerse_upload.php): die Datei muss sich tatsaechlich als
        // Bild dekodieren lassen - fischt Dateien heraus, die nur zufaellig
        // valide MIME-Magic-Bytes tragen (z.B. ein als .jpg umbenanntes
        // PHP-Skript mit angehaengten Bild-Bytes).
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new MediaValidationException('Datei konnte nicht als gültiges Bild gelesen werden.');
        }

        return ['mime' => $mime, 'ext' => $extMap[$mime]];
    }

    private function realMimeType(string $path): string
    {
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        if ($finfo === false) {
            // Kein Fallback auf $file->getClientMimeType() (das ist nur der
            // vom Browser gesendete, ungeprüfte Header) - ohne echte
            // Inhaltspruefung lieber ablehnen als eine Blackbox durchlassen.
            throw new MediaServiceUnavailableException('Datei-Typ-Erkennung (fileinfo-Erweiterung) auf diesem Server nicht verfügbar – Upload derzeit nicht möglich.');
        }
        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime;
    }

    public function uploadImage(UploadedFile $file, ?string $uploadedBy): MedienEintrag
    {
        ['mime' => $mime, 'ext' => $ext] = $this->detectAndValidateImage($file);

        // GD-Verfuegbarkeit VOR jedem Schreibvorgang pruefen (Auftrag Punkt
        // 4: "Upload darf nicht still kaputtgehen ... keinen halben Upload
        // hinterlassen") - so wird bei fehlender Erweiterung ueberhaupt
        // keine Datei angelegt, statt hinterher wieder aufraeumen zu muessen.
        if (! extension_loaded('gd')) {
            throw new MediaServiceUnavailableException('Bildverarbeitung (GD-Erweiterung) auf diesem Server nicht verfügbar – Upload nicht möglich.');
        }

        $imagesRoot = MediaStorage::imagesPath();
        MediaStorage::ensureDirectoryExists($imagesRoot);

        $filename = bin2hex(random_bytes(16)).'.'.$ext;
        $originalPath = $imagesRoot.'/'.$filename;

        // Nachverfolgung, was tatsaechlich schon auf die Platte geschrieben
        // wurde - im Fehlerfall wird GENAU das wieder entfernt (Auftrag
        // Punkt 4 "atomar"), nie mehr und nie weniger.
        $written = [];

        try {
            $file->move($imagesRoot, $filename);
            $written[] = $originalPath;

            $thumbDir = $imagesRoot.'/'.MediaStorage::thumbDirName();
            $cardDir = $imagesRoot.'/'.MediaStorage::cardDirName();
            MediaStorage::ensureDirectoryExists($thumbDir);
            MediaStorage::ensureDirectoryExists($cardDir);

            $thumbPath = $thumbDir.'/'.$filename;
            $this->generateVariant($originalPath, $thumbPath, $mime, (int) config('kjs_media.images.thumb_max_dimension'), (int) config('kjs_media.images.thumb_quality'));
            $written[] = $thumbPath;

            $cardPath = $cardDir.'/'.$filename;
            $this->generateVariant($originalPath, $cardPath, $mime, (int) config('kjs_media.images.card_max_dimension'), (int) config('kjs_media.images.card_quality'));
            $written[] = $cardPath;

            $imageInfo = @getimagesize($originalPath);
            $checksum = @hash_file('sha256', $originalPath) ?: null;

            return DB::transaction(function () use ($filename, $file, $mime, $imageInfo, $checksum, $uploadedBy) {
                return MedienEintrag::create([
                    'path' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => filesize(MediaStorage::imagesPath().'/'.$filename) ?: 0,
                    'media_type' => 'image',
                    'checksum' => $checksum,
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'uploaded_by' => $uploadedBy,
                ]);
            });
        } catch (Throwable $e) {
            $this->cleanupFiles($written);
            if ($e instanceof MediaValidationException || $e instanceof MediaServiceUnavailableException) {
                throw $e;
            }
            throw new MediaValidationException('Bild-Upload fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Erzeugt EINE Vorschau-Variante per GD - wirft bei jedem Fehlschlag
     * (anders als das Vorbild api/lib/boerse_upload.php, siehe Klassen-
     * kommentar), damit der Aufrufer den gesamten Upload zurueckrollen kann.
     */
    private function generateVariant(string $srcPath, string $destPath, string $mime, int $maxDimension, int $quality): void
    {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png' => @imagecreatefrompng($srcPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
            default => false,
        };
        if ($src === false) {
            throw new MediaValidationException('Bild konnte für die Vorschau-Erzeugung nicht dekodiert werden.');
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $scale = min(1, $maxDimension / max($srcW, $srcH));
            $tw = max(1, (int) round($srcW * $scale));
            $th = max(1, (int) round($srcH * $scale));

            $dst = imagecreatetruecolor($tw, $th);
            if ($dst === false) {
                throw new MediaValidationException('Vorschau-Bild konnte nicht erzeugt werden.');
            }
            if ($mime === 'image/png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $srcW, $srcH);

            $ok = match ($mime) {
                'image/jpeg' => @imagejpeg($dst, $destPath, $quality),
                'image/png' => @imagepng($dst, $destPath),
                'image/webp' => function_exists('imagewebp') ? @imagewebp($dst, $destPath, $quality) : false,
                default => false,
            };
            imagedestroy($dst);

            if (! $ok || ! is_file($destPath)) {
                throw new MediaValidationException('Vorschau-Bild konnte nicht gespeichert werden.');
            }
            @chmod($destPath, 0644);
        } finally {
            imagedestroy($src);
        }
    }

    public function uploadPdf(UploadedFile $file, ?string $uploadedBy): MedienEintrag
    {
        $maxBytes = (int) config('kjs_media.downloads.max_bytes');
        if ($file->getSize() === false || $file->getSize() > $maxBytes) {
            throw new MediaValidationException('Datei ist zu groß (maximal '.(int) round($maxBytes / 1024 / 1024).' MB erlaubt).');
        }

        $mime = $this->realMimeType($file->getRealPath());
        if (! in_array($mime, config('kjs_media.downloads.allowed_mimes'), true)) {
            throw new MediaValidationException('Nicht unterstütztes Dateiformat – nur PDF ist erlaubt.');
        }

        // Zusaetzliche Plausibilitaetspruefung analog zu getimagesize() bei
        // Bildern (es gibt keine eingebaute PHP-Funktion, die ein PDF
        // "dekodiert" - die Magic-Bytes-Pruefung ist das praktische
        // Aequivalent): eine echte PDF-Datei beginnt immer mit "%PDF-".
        $handle = @fopen($file->getRealPath(), 'rb');
        $header = $handle ? fread($handle, 5) : false;
        if ($handle) {
            fclose($handle);
        }
        if ($header !== '%PDF-') {
            throw new MediaValidationException('Datei ist kein gültiges PDF.');
        }

        $downloadsRoot = MediaStorage::downloadsPath();
        MediaStorage::ensureDirectoryExists($downloadsRoot);

        $filename = bin2hex(random_bytes(16)).'.pdf';
        $destPath = $downloadsRoot.'/'.$filename;

        try {
            $file->move($downloadsRoot, $filename);
            $checksum = @hash_file('sha256', $destPath) ?: null;

            return DB::transaction(function () use ($filename, $file, $mime, $checksum, $uploadedBy) {
                return MedienEintrag::create([
                    'path' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => filesize(MediaStorage::downloadsPath().'/'.$filename) ?: 0,
                    'media_type' => 'pdf',
                    'checksum' => $checksum,
                    'width' => null,
                    'height' => null,
                    'uploaded_by' => $uploadedBy,
                ]);
            });
        } catch (Throwable $e) {
            $this->cleanupFiles([$destPath]);
            if ($e instanceof MediaValidationException || $e instanceof MediaServiceUnavailableException) {
                throw $e;
            }
            throw new MediaValidationException('PDF-Upload fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Loescht Original + (bei Bildern) Thumb/Card gemeinsam, MIT
     * Path-Traversal-Pruefung je Datei (Auftrag Punkt 7 aus Phase 5B.1) -
     * erst danach den DB-Datensatz (falls vorhanden, siehe deleteByFilename()
     * fuer historische Dateien ohne DB-Zeile).
     *
     * Phase 5B.2 (Auftrag Punkt 5 "Löschen – Sicherheitsverbesserung"):
     * VOR jedem Loeschvorgang wird jetzt zusaetzlich MediaReferenceScanner
     * befragt - wird die Datei noch irgendwo referenziert, wird
     * MediaReferencedException geworfen und NICHTS geloescht (weder Dateien
     * noch DB-Zeile). Bewusst weiterhin KEINE automatische Entfernung der
     * gefundenen Referenzen und kein Override-Parameter (siehe dortiger
     * Klassenkommentar) - das war in Phase 5B.1 noch nicht gebaut ("erstmal
     * klar dokumentieren"), ist jetzt die zentrale Sicherheitsverbesserung
     * dieser Phase.
     */
    public function delete(MedienEintrag $medium): void
    {
        $publicPath = MediaStorage::publicUrl($medium);
        $references = MediaReferenceScanner::referencingLocations($publicPath);
        if (! empty($references)) {
            throw new MediaReferencedException($references);
        }

        $root = MediaStorage::rootPathFor($medium->media_type);
        $paths = array_filter([
            MediaStorage::originalDiskPath($medium),
            MediaStorage::thumbDiskPath($medium),
            MediaStorage::cardDiskPath($medium),
        ]);

        foreach ($paths as $path) {
            MediaStorage::assertWithinRoot($path, $root);
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $medium->delete();
    }

    /**
     * Phase 5B.2: einheitlicher Loesch-Einstieg fuer die Medienbibliothek,
     * die jetzt sowohl echte "medien"-Datensaetze (neu ueber Laravel
     * hochgeladen) als auch historische, nur auf der Platte liegende
     * Dateien OHNE DB-Zeile anzeigt (siehe AdminMediaController::index()).
     * admin.js kennt fuer beide Faelle nur Dateiname + Typ, nie eine
     * numerische ID - deshalb wird hier IMMER per (media_type, path)
     * nachgeschaut: existiert eine DB-Zeile, wird sie inkl. Metadaten
     * geloescht; existiert keine (historische Datei), wird ein
     * NICHT gespeichertes MedienEintrag-Objekt nur fuer die Pfad-/
     * Varianten-Ableitung verwendet - delete() unten faellt dann bei
     * $medium->delete() auf ein no-op zurueck (Eloquent macht bei einem
     * Model ohne exists=true keinen Query), es werden aber trotzdem
     * Original+Thumb+Card auf der Platte entfernt.
     */
    public function deleteByFilename(string $mediaType, string $filename): void
    {
        $medium = MedienEintrag::where('media_type', $mediaType)->where('path', $filename)->first();
        if ($medium === null) {
            $medium = new MedienEintrag(['media_type' => $mediaType, 'path' => $filename]);
        }

        $this->delete($medium);
    }

    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
