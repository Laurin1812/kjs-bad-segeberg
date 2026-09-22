<?php

namespace App\Support;

use App\Models\Termin;
use Illuminate\Support\Collection;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Server-seitiger 1:1-Port der fachlichen Regeln aus js/content.js
 * (KJSContent.Termine). Regel (unveraendert, Frank-Wunsch 20.08.2026): ein
 * Termin verschwindet, sobald er im Admin archiviert wurde ODER sein Datum
 * mehr als 7 Tage zurueckliegt. Bei unlesbarem/fehlendem Datum sicherheits-
 * halber weiter anzeigen statt auszublenden.
 */
class TermineRules
{
    public static function istSichtbar(Termin $t): bool
    {
        if ($t->archiviert) {
            return false;
        }

        if (! $t->datum) {
            return true;
        }

        $grenze = $t->datum->copy()->addDays(7)->endOfDay();

        return $grenze->greaterThanOrEqualTo(now());
    }

    /**
     * Aufsteigend nach Datum (naechster Termin zuerst). Termine ohne Datum
     * landen ans Ende statt die Sortierung zu verfaelschen.
     *
     * @param  Collection<int, Termin>  $termine
     * @return Collection<int, Termin>
     */
    public static function sortiere(Collection $termine): Collection
    {
        return $termine->sort(function (Termin $a, Termin $b) {
            if (! $a->datum && ! $b->datum) {
                return 0;
            }
            if (! $a->datum) {
                return 1;
            }
            if (! $b->datum) {
                return -1;
            }

            return $a->datum <=> $b->datum;
        })->values();
    }

    /**
     * @param  Collection<int, Termin>  $alleTermine
     * @return Collection<int, Termin>
     */
    public static function sichtbareSortiert(Collection $alleTermine): Collection
    {
        return self::sortiere($alleTermine->filter(fn (Termin $t) => self::istSichtbar($t))->values());
    }
}
