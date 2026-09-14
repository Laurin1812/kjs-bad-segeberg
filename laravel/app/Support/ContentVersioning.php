<?php

namespace App\Support;

use App\Models\ContentVersion;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL).
 *
 * Generischer Ersatz fuer die Git-SHA-basierte Konflikterkennung aus
 * admin.js/doSave() (siehe dortiger Kommentar bei trackSha()/
 * saveConflictError()): pro admin-editierbarem Modul ("section") eine
 * fortlaufende Versionsnummer in content_versions, exakt nach demselben
 * Muster wie das bereits bestehende hundeboerse_meta/waffenboerse_meta
 * ("version"-Spalte, siehe admin.js apiGetBoerse()/apiPutBoerse()).
 *
 * Ablauf beim Speichern (siehe AdminSettingsController/AdminListController/
 * AdminPageController): der Client schickt die zuletzt beim Laden gesehene
 * Versionsnummer als "expected_version" mit. Stimmt sie nicht mit der
 * aktuellen ueberein, hat zwischenzeitlich jemand anders gespeichert -
 * Abbruch mit HTTP 409, OHNE zu schreiben (identisches Verhalten zur
 * bisherigen 409-Behandlung bei einer veralteten Git-SHA, siehe doSave()).
 * Ist expected_version NULL (z.B. beim allerersten Speichern eines vorher
 * nie ueber Laravel geladenen Moduls), wird - wie im Git-SHA-Fall bisher
 * auch - ohne Konfliktpruefung gespeichert.
 */
class ContentVersioning
{
    public static function current(string $section): int
    {
        return (int) (ContentVersion::query()->where('section', $section)->value('version') ?? 0);
    }

    /**
     * Wirft eine ConflictException, wenn $expectedVersion bekannt ist (nicht
     * null) und nicht mehr der aktuellen Version entspricht. Aufrufer fuehrt
     * dies VOR dem eigentlichen Schreiben aus, innerhalb derselben
     * Transaktion wie das Speichern selbst (siehe Aufrufer), damit zwischen
     * Pruefung und Schreiben keine fremde Aenderung mehr dazwischenkommen
     * kann.
     */
    public static function assertNotStale(string $section, ?int $expectedVersion): void
    {
        if ($expectedVersion === null) {
            return;
        }
        $current = self::current($section);
        if ($current !== $expectedVersion) {
            throw new ContentVersionConflictException($section, $current);
        }
    }

    /** Erhoeht die Version um 1 (legt die Zeile bei Bedarf an) und gibt die neue Version zurueck. */
    public static function bump(string $section): int
    {
        return DB::transaction(function () use ($section) {
            $row = ContentVersion::query()->lockForUpdate()->find($section);
            if (! $row) {
                $row = new ContentVersion(['section' => $section, 'version' => 0]);
            }
            $row->version = ((int) $row->version) + 1;
            $row->save();

            return $row->version;
        });
    }
}
