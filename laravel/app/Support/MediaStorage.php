<?php

namespace App\Support;

use App\Models\MedienEintrag;
use RuntimeException;

/**
 * Phase 5B.1 (sichere Laravel-Medienarchitektur): EINZIGER Ort, der weiss,
 * wie sich aus einem MedienEintrag ein Dateisystempfad oder eine
 * oeffentliche URL ergibt - liest dafuer ausschliesslich config/
 * kjs_media.php (siehe dortiger Kommentar zu "path speichert nur den
 * Dateinamen"). App\Support\MediaUploadService und AdminMediaController
 * rufen NIE direkt config('kjs_media...') fuer Pfad-Zusammensetzung auf,
 * sondern immer diese Klasse - so bleibt "wo liegt was" an einer Stelle
 * pflegbar (Auftrag Punkt 2).
 */
class MediaStorage
{
    public static function imagesPath(): string
    {
        return rtrim((string) config('kjs_media.images.path'), '/');
    }

    public static function downloadsPath(): string
    {
        return rtrim((string) config('kjs_media.downloads.path'), '/');
    }

    public static function imagesUrlPrefix(): string
    {
        return rtrim((string) config('kjs_media.images.url_prefix'), '/');
    }

    public static function downloadsUrlPrefix(): string
    {
        return rtrim((string) config('kjs_media.downloads.url_prefix'), '/');
    }

    public static function thumbDirName(): string
    {
        return (string) config('kjs_media.images.thumb_dir');
    }

    public static function cardDirName(): string
    {
        return (string) config('kjs_media.images.card_dir');
    }

    /** Wurzelverzeichnis fuer einen media_type ("image"/"pdf") - fuer die Path-Traversal-Pruefung beim Loeschen. */
    public static function rootPathFor(string $mediaType): string
    {
        return $mediaType === 'image' ? self::imagesPath() : self::downloadsPath();
    }

    public static function ensureDirectoryExists(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Zielverzeichnis konnte nicht angelegt werden: '.$dir);
        }
    }

    public static function originalDiskPath(MedienEintrag $medium): string
    {
        $root = self::rootPathFor($medium->media_type);

        return $root.'/'.$medium->path;
    }

    public static function thumbDiskPath(MedienEintrag $medium): ?string
    {
        if (! $medium->isImage()) {
            return null;
        }

        return self::imagesPath().'/'.self::thumbDirName().'/'.$medium->path;
    }

    public static function cardDiskPath(MedienEintrag $medium): ?string
    {
        if (! $medium->isImage()) {
            return null;
        }

        return self::imagesPath().'/'.self::cardDirName().'/'.$medium->path;
    }

    public static function publicUrl(MedienEintrag $medium): string
    {
        $prefix = $medium->isImage() ? self::imagesUrlPrefix() : self::downloadsUrlPrefix();

        return $prefix.'/'.$medium->path;
    }

    /** Oeffentliche URL der Thumb-Variante - null bei PDFs (siehe thumbDiskPath()). */
    public static function thumbUrl(MedienEintrag $medium): ?string
    {
        if (! $medium->isImage()) {
            return null;
        }

        return self::imagesUrlPrefix().'/'.self::thumbDirName().'/'.$medium->path;
    }

    /** Oeffentliche URL der Card-Variante - null bei PDFs (siehe cardDiskPath()). */
    public static function cardUrl(MedienEintrag $medium): ?string
    {
        if (! $medium->isImage()) {
            return null;
        }

        return self::imagesUrlPrefix().'/'.self::cardDirName().'/'.$medium->path;
    }

    /**
     * Path-Traversal-Schutz (Auftrag Punkt 7): stellt sicher, dass ein
     * gegebener Dateisystempfad nach Aufloesung von "..", Symlinks etc.
     * TATSAECHLICH innerhalb von $allowedRoot liegt. Wird sowohl vor dem
     * Schreiben (Zieldateiname ist zwar immer serverseitig zufaellig
     * erzeugt, siehe MediaUploadService - aber die Pruefung schuetzt
     * zusaetzlich vor einer fehlerhaften/manipulierten Konfiguration) als
     * auch vor jedem Loeschen aufgerufen. realpath() liefert false, wenn
     * der Pfad nicht existiert - das ist beim Loeschen einer bereits
     * fehlenden Datei kein Sicherheitsproblem, wird vom Aufrufer separat
     * behandelt (siehe MediaUploadService::delete()).
     */
    public static function assertWithinRoot(string $path, string $allowedRoot): void
    {
        $resolvedRoot = realpath($allowedRoot);
        if ($resolvedRoot === false) {
            throw new RuntimeException('Erlaubtes Wurzelverzeichnis existiert nicht: '.$allowedRoot);
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            // Datei existiert nicht - kein Traversal-Fund, aber auch nichts
            // zu loeschen/schreiben. Aufrufer entscheidet, wie er damit
            // umgeht (z.B. "Datei existierte ohnehin nicht" statt Fehler).
            return;
        }

        if ($resolvedPath !== $resolvedRoot && ! str_starts_with($resolvedPath, $resolvedRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Pfad liegt ausserhalb des erlaubten Medienverzeichnisses.');
        }
    }
}
