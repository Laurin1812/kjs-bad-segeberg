<?php

namespace App\Support;

use App\Models\Beitrag;
use Illuminate\Support\Collection;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Server-seitiger 1:1-Port der fachlichen Regeln aus js/content.js
 * (KJSContent.Aktuelles) - bewusst NICHT als neue/eigene Logik geschrieben,
 * sondern Zeile fuer Zeile aus der bisherigen, zentralen JS-Datenschicht
 * uebernommen, damit sich fuer Besucher am sichtbaren Verhalten (welche
 * Beitraege wann sichtbar sind, in welcher Reihenfolge) nichts aendert.
 *
 * "archiviert" bedeutet ueberall dasselbe: erscheint nicht mehr in den
 * oeffentlichen Standardansichten, bleibt aber als Datensatz erhalten.
 * postYear()/sortKey() unveraendert aus aktuelles/index.html uebernommen
 * (b.jahr hat Vorrang vor dem Jahr aus b.datum, fuer per Admin nachtraeglich
 * einsortierte Beitraege).
 */
class AktuellesRules
{
    /**
     * Erscheinungsjahr eines Beitrags: das Admin-Feld "Erscheinungsjahr"
     * (jahr) hat Vorrang und bestimmt, in welchem Archiv-Jahr der Beitrag
     * einsortiert wird - unabhaengig vom eigentlichen Datum. Ist es leer,
     * faellt es automatisch auf das Jahr aus dem Datum zurueck.
     */
    public static function postYear(Beitrag $b): int
    {
        if ($b->jahr) {
            return (int) $b->jahr;
        }

        return $b->datum?->year ?? 0;
    }

    /**
     * Sortier-Schluessel: Jahr (mit Erscheinungsjahr-Override ueber
     * postYear()) kombiniert mit Monat/Tag aus datum. Neuere Beitraege
     * landen zuverlaessig oben.
     */
    public static function sortKey(Beitrag $b): int
    {
        $year = self::postYear($b);
        $monat = $b->datum?->month ?? 0;
        $tag = $b->datum?->day ?? 0;

        return $year * 10000 + $monat * 100 + $tag;
    }

    /**
     * Neueste zuerst. Sortiert immer eine Kopie der uebergebenen Collection.
     *
     * @param  Collection<int, Beitrag>  $beitraege
     * @return Collection<int, Beitrag>
     */
    public static function sortiertNeuesteZuerst(Collection $beitraege): Collection
    {
        return $beitraege->sortByDesc(fn (Beitrag $b) => self::sortKey($b))->values();
    }

    /**
     * "Standardauswahl" der Aktuelles-Uebersichtsseite ohne gewaehlten
     * Jahres-Filter: aktuelles Jahr, mit Fallback auf alle Beitraege, falls
     * das aktuelle Jahr leer ist. einstellungen.hauptseite_anzahl wirkt
     * weiterhin als "letzte N"-Override.
     *
     * @param  Collection<int, Beitrag>  $nichtArchiviert  bereits nicht-archivierte Beitraege
     * @return Collection<int, Beitrag>
     */
    public static function standardAuswahl(Collection $nichtArchiviert, int $hauptseiteAnzahl): Collection
    {
        $sortiert = self::sortiertNeuesteZuerst($nichtArchiviert);

        if ($hauptseiteAnzahl > 0) {
            return $sortiert->take($hauptseiteAnzahl)->values();
        }

        $currentYear = (int) now()->format('Y');
        $thisYear = $sortiert->filter(fn (Beitrag $b) => self::postYear($b) === $currentYear)->values();

        return $thisYear->isNotEmpty() ? $thisYear : $sortiert;
    }

    /**
     * Jahre fuer die Archiv-Sidebar: nur Jahre, in denen mindestens ein
     * nicht archivierter Beitrag existiert. Neueste zuerst.
     *
     * @param  Collection<int, Beitrag>  $nichtArchiviert
     * @return list<int>
     */
    public static function sichtbareJahre(Collection $nichtArchiviert): array
    {
        $jahre = [];
        foreach ($nichtArchiviert as $b) {
            $y = self::postYear($b);
            if ($y && ! in_array($y, $jahre, true)) {
                $jahre[] = $y;
            }
        }
        rsort($jahre);

        return $jahre;
    }

    /**
     * "Weitere Beitraege" auf der Detailseite: sichtbare (nicht archivierte)
     * Beitraege desselben Jahres, ohne den aktuell angezeigten Beitrag
     * selbst.
     *
     * @param  Collection<int, Beitrag>  $nichtArchiviert
     * @return Collection<int, Beitrag>
     */
    public static function weitereDesJahres(Collection $nichtArchiviert, Beitrag $aktuell, int $limit = 4): Collection
    {
        $jahr = self::postYear($aktuell);

        $weitere = $nichtArchiviert
            ->filter(fn (Beitrag $b) => $b->id !== $aktuell->id && self::postYear($b) === $jahr)
            ->values();

        return self::sortiertNeuesteZuerst($weitere)->take($limit)->values();
    }
}
