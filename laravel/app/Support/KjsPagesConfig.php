<?php

namespace App\Support;

/**
 * KJS Bad Segeberg - Phase 3 (Read-API).
 *
 * Die "pages"-Tabelle (Phase 1) vereinheitlicht die bisherige "seiten-*"-
 * Registry-Familie (siehe database/migrations/2026_09_14_000080_create_
 * pages_table.php). Anders als frueher unterscheidet die DB selbst nicht
 * mehr zwischen "feste Vorlagen-Seite" (z.B. content/jaeger/hochwild.json)
 * und "per Registry hinzugefuegte Zusatzseite" (z.B. ein Eintrag in
 * content/seiten-aufgaben.json) - beide liegen als ganz normale Page-Zeile
 * mit section=X, parent_id=NULL nebeneinander.
 *
 * Fuer die Read-API (Phase 3 Punkt 6: kompatible Registry-Antworten) MUSS
 * diese Unterscheidung aber wieder rekonstruierbar sein, denn:
 * - content/seiten-kjs.json / seiten-aufgaben.json / seiten-verbraucher.json
 *   duerfen NUR die Zusatzseiten auflisten, nicht die festen Vorlagen-Seiten
 *   (die haben eigene, feste Navigationseintraege und werden nicht per
 *   Registry verlinkt).
 * - Die einzelnen Content-Endpunkte fuer feste Seiten liegen unter
 *   content/{section}/{slug}.json, die fuer Registry-Zusatzseiten dagegen
 *   unter content/seiten-{kurz}/{slug}.json (anderes Verzeichnis!).
 *
 * Diese Klasse haelt daher exakt dieselben Listen, die bereits
 * ImportContent::handle() beim Import verwendet (siehe dortige
 * importPagesFamily()-Aufrufe) - eine einzige Quelle der Wahrheit fuer
 * "welche Slugs sind in dieser Section fest", die von Importer UND
 * Read-API-Controllern gemeinsam genutzt werden koennte. Bewusst als
 * eigene, kleine Klasse statt Duplizierung in jedem Controller.
 */
class KjsPagesConfig
{
    /**
     * section => [fixedSlugs, contentDir, extraRegistryDatei, extraRegistryDir].
     *
     * extraRegistryDir ist NULL fuer "jaeger" und "verbraucher", weil der
     * Phase-2-Importer fuer diese beiden Sections defensiv KEINE
     * Registry-Zusatzseiten anlegt (siehe ImportContent::importPagesFamily(),
     * Aufrufe mit $extraDir = null) - in den echten Daten sind
     * seiten-kjs.json und seiten-verbraucher.json ohnehin durchgaengig leer.
     * Die Read-API baut die Endpunkte trotzdem generisch (liefert dann
     * einfach 0 Eintraege/404 - kein Unterschied zum heutigen leeren
     * Zustand), statt diese Faelle hart auszuschliessen.
     *
     * @return array<string, array{0: list<string>, 1: string, 2: string, 3: ?string}>
     */
    public static function fixedFamilies(): array
    {
        return [
            'jaeger' => [
                ['uebersicht', 'hochwild', 'infomobil', 'jaeger-werden',
                    'landesjagdverband', 'mitglied-werden', 'niederwild',
                    'satzung', 'schiessobleute', 'ueber-uns'],
                'jaeger',
                'seiten-kjs.json',
                'seiten-kjs',
            ],
            'aufgaben' => [
                ['jagdhorn', 'jugend', 'jungwildrettung', 'naturschutz',
                    'schiessen', 'schweisshunde'],
                'aufgaben',
                'seiten-aufgaben.json',
                'seiten-aufgaben',
            ],
            'verbraucher' => [
                ['gruenes-klassenzimmer', 'lernort-natur', 'waidmannssprache',
                    'wildfleisch'],
                'verbraucher',
                'seiten-verbraucher.json',
                'seiten-verbraucher',
            ],
        ];
    }

    /** @return list<string> */
    public static function fixedSlugs(string $section): array
    {
        return self::fixedFamilies()[$section][0] ?? [];
    }

    public static function contentDir(string $section): ?string
    {
        return self::fixedFamilies()[$section][1] ?? null;
    }

    public static function extraRegistryDir(string $section): ?string
    {
        return self::fixedFamilies()[$section][3] ?? null;
    }

    /**
     * Alle drei Sections, die dem "feste Seiten + optionale Unterseiten
     * (seiten-sub-<slug>.json) + optionale Registry-Zusatzseiten"-Muster
     * folgen (siehe ImportContent::importPagesFamily()).
     *
     * @return list<string>
     */
    public static function familySections(): array
    {
        return array_keys(self::fixedFamilies());
    }
}
