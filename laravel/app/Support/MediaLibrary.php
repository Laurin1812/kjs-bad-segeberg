<?php

namespace App\Support;

use App\Models\MedienArchivEintrag;
use App\Models\MedienEintrag;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Phase 7K (Admin-Modul "Medien"): reines Lese-Modell fuer die neue Blade-
 * Medienbibliothek. Es gibt bereits eine echte, generische zentrale
 * Medienverwaltung (Phase 5B.1/5B.2) - App\Support\MediaStorage fuer Pfade/
 * URLs, App\Support\MediaUploadService fuer Upload/Loeschen (inkl.
 * Referenzpruefung), App\Models\MedienEintrag als DB-Tabelle fuer alles ab
 * da neu Hochgeladene, und ein Dateisystem-Scan fuer die ca. 250 historischen
 * Bilder/PDFs ohne DB-Zeile - siehe Api\Admin\AdminMediaController, das genau
 * das seit Phase 5B.2 bereits als JSON-API fuer das ALTE admin.js bereitstellt.
 * Diese Klasse erfindet dafuer bewusst KEINE neue Architektur, sondern baut
 * auf denselben Bausteinen auf (Auftrag Phase 7K: "bestehende Datenquelle
 * verwenden, keine neue zweite Medienhaltung").
 *
 * Bewusst EIGENSTAENDIG statt AdminMediaController::index() wiederzuverwenden:
 * dessen Aufbereitungsmethoden (serialize()/serializeLegacy()/scanLegacyFiles())
 * sind private, und diese JSON-API ist bereits produktiv vom alten admin.js im
 * Einsatz, bislang OHNE jede Testabdeckung - ein Umbau dort haette in dieser
 * Phase ein unnoetiges Regressionsrisiko fuer ein bereits funktionierendes
 * Modul bedeutet (Auftrag: "bestehende Upload-Flows nicht umbauen", dieselbe
 * Vorsicht gilt sinngemaess auch fuer die bestehende Medien-JSON-API selbst).
 * scanLegacyFiles() ist deshalb unten bewusst 1:1 erneut geschrieben (nicht
 * kopiert-eingefuegt) - eine kleine, stabile, seit Phase 5B.1 unveraenderte
 * Methode, deren doppelte Pflege ein vertretbar kleines Risiko ist gegenueber
 * dem Risiko, die bestehende API anzufassen.
 *
 * NEU in Phase 7K: erste tatsaechliche Verwendung von App\Models\
 * MedienArchivEintrag ("medien_archiv"-Tabelle) - die Tabelle existiert
 * bereits seit dem Content-Import (Phase 2, ImportContent::importMedienArchiv())
 * mit den historischen archivierten Dateinamen, hatte aber bislang KEINEN
 * Konsumenten. Das alte admin.js versucht auf einem PHP-/Laravel-Host zwar
 * ebenfalls zu archivieren (medienArchivToggle() -> doSave('content/
 * medien-archiv.json', ...)), dafuer existiert aber KEINE Laravel-Bruecken-
 * route (siehe routes/api.php - "content/medien-archiv.json" kommt dort
 * nirgends vor) - das Archivieren ist auf einem bereits umgestellten PHP-/
 * Laravel-Host also schon vor dieser Phase praktisch wirkungslos (der
 * Schreibversuch schlaegt fehl, admin.js faengt das nur mit einer Fehler-
 * Toast ab). Diese Phase ist damit der ERSTE tatsaechlich funktionierende
 * Schreibweg dafuer - kein Konflikt mit einem zweiten, parallel schreibenden
 * Altsystem moeglich, deshalb auch keine ContentVersioning-Absicherung noetig
 * (anders als z.B. bei Partner/Termine, wo admin.js und Blade-Admin auf dem
 * PHP-Host tatsaechlich BEIDE erfolgreich in dieselbe Tabelle schreiben
 * koennen).
 *
 * Archivieren bleibt bewusst NUR fuer Bilder ein Konzept (1:1 wie im alten
 * System - "Medien & Bilder" kannte nie eine PDF-Archivierung) - siehe
 * Admin\MedienController, der die Archivieren-Aktion nur fuer media_type
 * "image" anbietet.
 */
class MediaLibrary
{
    /**
     * Liefert ALLE Eintraege (aktive + archivierte, DB-Zeilen + historische
     * Dateisystem-Funde), neueste zuerst - der Aufrufer filtert nach Bedarf
     * nach Typ/Archiv-Status. $mediaType schraenkt optional auf "image"
     * oder "pdf" ein (analog AdminMediaController::index()' "type"-Parameter).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function liste(?string $mediaType = null): array
    {
        $types = in_array($mediaType, ['image', 'pdf'], true) ? [$mediaType] : ['image', 'pdf'];
        $archivierteDateinamen = MedienArchivEintrag::pluck('dateiname')->all();

        $items = [];
        foreach ($types as $type) {
            $dbByFilename = [];
            foreach (MedienEintrag::where('media_type', $type)->get() as $medium) {
                $dbByFilename[$medium->path] = true;
                // Nachtrag (Sicherheitsfix): auch fuer DB-Zeilen pruefen, dass
                // "path" nicht auf einen Symlink zeigt, der aus dem erlaubten
                // Medienverzeichnis herausfuehrt - siehe istSicherAufDerPlatte()-
                // Kommentar. Eine regulaere, ueber MediaUploadService erzeugte
                // Zeile kann das nie sein (Dateien werden per random_bytes()-
                // Namen frisch geschrieben, nie verlinkt), das deckt nur eine
                // nachtraegliche, ausserhalb der App vorgenommene Manipulation
                // der Platte ab.
                if (! self::istSicherAufDerPlatte($type, $medium->path)) {
                    continue;
                }
                $items[] = self::ausDbZeile($medium, $archivierteDateinamen);
            }
            foreach (self::scanLegacyFiles($type) as $filename => $meta) {
                if (isset($dbByFilename[$filename])) {
                    continue; // bereits per DB-Zeile erfasst - keine Duplikate (1:1 wie AdminMediaController::index())
                }
                $items[] = self::ausDateisystem($type, $filename, $meta['size'], $meta['mtime'], $archivierteDateinamen);
            }
        }

        usort($items, fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return $items;
    }

    /** @param string[] $archiviert */
    private static function ausDbZeile(MedienEintrag $medium, array $archiviert): array
    {
        return [
            'sort' => $medium->created_at?->timestamp ?? 0,
            'media_type' => $medium->media_type,
            'dateiname' => $medium->path,
            'original_name' => $medium->original_name,
            'size_bytes' => $medium->size_bytes,
            'size_human' => self::formatBytes($medium->size_bytes),
            'width' => $medium->width,
            'height' => $medium->height,
            'created_at' => $medium->created_at,
            'url' => MediaStorage::publicUrl($medium),
            'thumb_url' => MediaStorage::thumbUrl($medium),
            'ist_archiviert' => in_array($medium->path, $archiviert, true),
            'ist_historisch' => false,
        ];
    }

    /** @param string[] $archiviert */
    private static function ausDateisystem(string $type, string $filename, int $size, int $mtime, array $archiviert): array
    {
        // Nur fuer die Pfad-/URL-Ableitung ueber MediaStorage genutzt (die
        // Methoden dort erwarten ein MedienEintrag-Objekt) - wird NIE
        // gespeichert, siehe MediaUploadService::deleteByFilename() fuer
        // dasselbe, bereits etablierte Muster.
        $temp = new MedienEintrag(['media_type' => $type, 'path' => $filename]);

        return [
            'sort' => $mtime,
            'media_type' => $type,
            'dateiname' => $filename,
            'original_name' => $filename,
            'size_bytes' => $size,
            'size_human' => self::formatBytes($size),
            'width' => null,
            'height' => null,
            'created_at' => $mtime > 0 ? Carbon::createFromTimestamp($mtime) : null,
            'url' => MediaStorage::publicUrl($temp),
            'thumb_url' => MediaStorage::thumbUrl($temp),
            'ist_archiviert' => in_array($filename, $archiviert, true),
            'ist_historisch' => true,
        ];
    }

    /**
     * Fachlich wie AdminMediaController::scanLegacyFiles() (siehe dortiger
     * Kommentar) - bewusst eigenstaendig gehalten statt jene private
     * Methode von aussen zugaenglich zu machen, siehe Klassenkommentar
     * oben. ZWEI bewusste Verbesserungen gegenueber dem Original (betreffen
     * nur dieses neue Blade-Modul, die bestehende JSON-API bleibt
     * unveraendert):
     * - Auftrag Punkt 3 "keine versteckten Systemdateien anzeigen":
     *   Eintraege, deren Name mit "." beginnt, werden uebersprungen.
     * - Sicherheitsfix (Nachtrag): Symlinks werden im Legacy-Scan generell
     *   uebersprungen, statt is_file() zu vertrauen - das folgt Symlinks
     *   und wuerde sonst eine Datei ausserhalb von images/downloads ueber
     *   die Medienbibliothek exponieren, wenn irgendwo ein entsprechender
     *   Symlink im Medienverzeichnis liegt. Es gibt keinen fachlichen Grund
     *   fuer Symlinks innerhalb von images/downloads (echte Uploads landen
     *   dort immer als regulaere Datei, siehe MediaUploadService), deshalb
     *   grundsaetzliches Ueberspringen statt einer aufwendigeren
     *   Realpath-Sonderbehandlung nur fuer diesen einen Fall.
     *
     * @return array<string, array{size:int, mtime:int}>
     */
    private static function scanLegacyFiles(string $mediaType): array
    {
        $dir = MediaStorage::rootPathFor($mediaType);
        if (! is_dir($dir)) {
            return [];
        }
        $pattern = $mediaType === 'image' ? '/\.(jpg|jpeg|png|gif|webp|svg)$/i' : '/\.pdf$/i';

        $out = [];
        foreach (scandir($dir) ?: [] as $entry) {
            $full = $dir.'/'.$entry;
            if (str_starts_with($entry, '.') || is_link($full) || ! is_file($full) || ! preg_match($pattern, $entry)) {
                continue;
            }
            $out[$entry] = ['size' => (int) (@filesize($full) ?: 0), 'mtime' => (int) (@filemtime($full) ?: 0)];
        }

        return $out;
    }

    /**
     * Sicherheitsfix (Nachtrag): defense-in-depth zusaetzlich zum
     * is_link()-Ausschluss in scanLegacyFiles() oben - deckt den (in der
     * Praxis nur durch eine Manipulation ausserhalb der App moegliche) Fall
     * ab, dass eine "medien"-DB-Zeile auf einen Dateinamen zeigt, der auf
     * der Platte (mittlerweile) ein Symlink aus dem Medienverzeichnis
     * heraus ist. Baut bewusst KEINE neue Pfad-Pruefung: nutzt dieselbe
     * App\Support\MediaStorage::assertWithinRoot(), die bereits vor jedem
     * Loeschen greift (siehe MediaUploadService::delete()) - hier nur
     * lesend, vor der Aufnahme ins Listing statt vor einer Schreiboperation.
     * Existiert die Datei gar nicht (haeufigster Fall bei einer verwaisten
     * DB-Zeile), wirft assertWithinRoot() bewusst NICHTS (siehe dortiger
     * Kommentar) - das bleibt unveraendert wie zuvor sichtbar.
     */
    private static function istSicherAufDerPlatte(string $mediaType, string $filename): bool
    {
        $root = MediaStorage::rootPathFor($mediaType);
        $full = $root.'/'.$filename;
        if (is_link($full)) {
            return false;
        }

        try {
            MediaStorage::assertWithinRoot($full, $root);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
