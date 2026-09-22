<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
 * Laravel-Vollmigration).
 *
 * Server-seitiger Nachbau des bisherigen "ZENTRALE NAVIGATION"-Moduls aus
 * resources/js/app.js (1:1 aus js/main.js uebernommen, siehe dortiger
 * Kommentar "ZENTRALE NAVIGATION" vor dieser Korrektur). Dieselbe
 * Datenquelle wie zuvor per fetch() gelesen (settings-Tabelle, Gruppen
 * "navigation"/"navigation_extra" - ehemals navigation.json/
 * navigation-extra.json, siehe App\View\Composers\DesignComposer fuer das
 * Vorbild dieses "aus MySQL statt aus JSON-Datei"-Musters), ergaenzt um
 * genau dieselben dynamischen Registry-Seiten (Page-Zeilen mit
 * section=jaeger|aufgaben|verbraucher, parent_id=NULL, Slug NICHT in
 * KjsPagesConfig::fixedSlugs()) wie zuvor "seiten-kjs.json"/
 * "seiten-aufgaben.json"/"seiten-verbraucher.json" (PageContentController::
 * registrySeiten()) - jetzt direkt per Eloquent statt per fetch()
 * zusammengefuehrt, keine Laufzeit-Abhaengigkeit von /api/content/*.json
 * mehr.
 *
 * Bewusst AUSSERHALB der Hauptnavigation bleiben "weitere"-Seiten (siehe
 * ImportContent::importWeitere()-Klassenkommentar: liefen schon im
 * Original ueber einen abweichenden Rendering-/Navigationspfad, waren nie
 * Teil des alten __navReady-Merges) - keine Verhaltensaenderung gegenueber
 * der produktiven Seite.
 */
class Navigation
{
    /**
     * Liefert die fertig aufbereitete Hauptmenue-Struktur fuer
     * components/site-header.blade.php (Desktop UND Mobile teilen sich
     * dieselben Daten, genau wie zuvor renderDesktopNav()/
     * renderMobileNav() dieselbe zusammengefuehrte $nav-Struktur nutzten).
     *
     * Jeder Eintrag: ['key','label','href' (null bei Dropdown),'children'
     * (null bei einfachem Link, sonst Liste aus
     * ['type'=>'link','label','href'] bzw.
     * ['type'=>'flyout','label','children'=>[['label','href'],...]])].
     *
     * @return list<array<string, mixed>>
     */
    public static function build(): array
    {
        $blob = self::blob('navigation');
        $extraBlob = self::blob('navigation_extra');

        $sektionsnamen = is_array($blob['sektionsnamen'] ?? null) ? $blob['sektionsnamen'] : [];
        $hauptmenu = is_array($blob['hauptmenu'] ?? null) ? $blob['hauptmenu'] : [];
        $hauptmenuMeta = is_array($blob['hauptmenu_meta'] ?? null) ? $blob['hauptmenu_meta'] : [];
        $jaegerDropdown = is_array($blob['jaeger_dropdown'] ?? null) ? $blob['jaeger_dropdown'] : [];
        $jaegerDropdownMeta = is_array($blob['jaeger_dropdown_meta'] ?? null) ? $blob['jaeger_dropdown_meta'] : [];

        $kjs = self::mergeDynamic('jaeger', is_array($blob['kjs'] ?? null) ? $blob['kjs'] : []);
        $aufgaben = self::mergeDynamic('aufgaben', is_array($blob['aufgaben'] ?? null) ? $blob['aufgaben'] : []);
        $verbraucher = self::mergeDynamic('verbraucher', is_array($blob['verbraucher'] ?? null) ? $blob['verbraucher'] : []);

        $items = [];
        foreach ($hauptmenu as $key) {
            if ($key === 'jaeger') {
                $items[] = [
                    'key' => 'jaeger',
                    'label' => $sektionsnamen['jaeger'] ?? 'Jäger',
                    'href' => null,
                    'children' => self::jaegerChildren($jaegerDropdown, $jaegerDropdownMeta, $sektionsnamen, $kjs, $aufgaben),
                ];

                continue;
            }
            if ($key === 'verbraucher') {
                $items[] = [
                    'key' => 'verbraucher',
                    'label' => $sektionsnamen['verbraucher'] ?? 'Verbraucher',
                    'href' => null,
                    'children' => array_map(
                        fn (array $it) => ['type' => 'link', 'label' => $it['label'] ?? '', 'href' => self::prettyHref($it['href'] ?? null)],
                        $verbraucher
                    ),
                ];

                continue;
            }

            $meta = $hauptmenuMeta[$key] ?? null;
            if (! is_array($meta)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'href' => self::prettyHref($meta['href'] ?? null),
                'children' => null,
            ];
        }

        self::insertExtra($items, $extraBlob);

        return $items;
    }

    /**
     * @param  list<string>  $dropdown
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $sektionsnamen
     * @param  list<array{label:string,href:string}>  $kjs
     * @param  list<array{label:string,href:string}>  $aufgaben
     * @return list<array<string, mixed>>
     */
    private static function jaegerChildren(array $dropdown, array $meta, array $sektionsnamen, array $kjs, array $aufgaben): array
    {
        $out = [];
        foreach ($dropdown as $jkey) {
            $jmeta = $meta[$jkey] ?? null;
            if (! is_array($jmeta) || ! empty($jmeta['hidden'])) {
                continue;
            }

            if ($jkey === 'kjs-segeberg') {
                $out[] = [
                    'type' => 'flyout',
                    'label' => $sektionsnamen['kjs'] ?? 'KJS Segeberg',
                    'children' => array_map(fn (array $it) => ['label' => $it['label'] ?? '', 'href' => self::prettyHref($it['href'] ?? null)], $kjs),
                ];

                continue;
            }
            if ($jkey === 'aufgaben') {
                $out[] = [
                    'type' => 'flyout',
                    'label' => $sektionsnamen['aufgaben'] ?? 'Aufgaben der Kreisjägerschaft',
                    'children' => array_map(fn (array $it) => ['label' => $it['label'] ?? '', 'href' => self::prettyHref($it['href'] ?? null)], $aufgaben),
                ];

                continue;
            }

            $out[] = ['type' => 'link', 'label' => $jmeta['label'] ?? $jkey, 'href' => self::prettyHref($jmeta['href'] ?? null)];
        }

        return $out;
    }

    /**
     * Haengt die per Registry dynamisch angelegten Zusatzseiten einer
     * Familie (section=jaeger|aufgaben|verbraucher) VOR die fest in den
     * Settings hinterlegte Liste (identische Reihenfolge wie zuvor
     * mergeDynamicSeiten() in resources/js/app.js: "dynKjs.concat(nav.kjs
     * || [])"). "veroeffentlicht" nutzt bewusst denselben Vorrang wie
     * PageContentController::registryEntry() (registry_veroeffentlicht vor
     * veroeffentlicht), damit sich am sichtbaren Verhalten nichts aendert.
     *
     * @param  list<array{label?:string,href?:string}>  $static
     * @return list<array{label:string,href:string}>
     */
    private static function mergeDynamic(string $section, array $static): array
    {
        $dynamisch = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->orderBy('sortierung')
            ->get()
            ->filter(function (Page $p) {
                $veroeffentlicht = $p->registry_veroeffentlicht ?? $p->veroeffentlicht;

                return $veroeffentlicht && $p->in_navigation;
            })
            ->map(fn (Page $p) => [
                'label' => $p->nav_label ?: $p->titel,
                'href' => '/'.$section.'/'.$p->slug,
            ])
            ->values()
            ->all();

        $statisch = array_map(fn (array $it) => ['label' => $it['label'] ?? '', 'href' => $it['href'] ?? null], $static);

        return array_merge($dynamisch, $statisch);
    }

    /**
     * Server-seitiger Nachbau von insertNavigationExtra() (resources/js/
     * app.js): fuegt die in "navigation_extra" (Gruppe "navigation_extra",
     * ehemals navigation-extra.json) hinterlegten Zusatzpunkte VOR "FAQ"
     * ein (bzw. ans Ende, falls kein FAQ-Eintrag existiert). Im echten
     * Datenbestand aktuell leer (siehe content/navigation-extra.json) -
     * dennoch vollstaendig implementiert, damit ein spaeter im Admin
     * angelegter Hauptpunkt automatisch erscheint, ohne Code-Aenderung.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $extraBlob
     */
    private static function insertExtra(array &$items, array $extraBlob): void
    {
        $hauptpunkte = is_array($extraBlob['hauptpunkte'] ?? null) ? $extraBlob['hauptpunkte'] : [];
        if (! $hauptpunkte) {
            return;
        }

        $neueEintraege = [];
        foreach ($hauptpunkte as $hp) {
            if (! is_array($hp)) {
                continue;
            }
            $seiten = collect(is_array($hp['seiten'] ?? null) ? $hp['seiten'] : [])
                ->filter(fn ($s) => is_array($s) && ($s['veroeffentlicht'] ?? false) === true && ($s['in_navigation'] ?? false) === true)
                ->values();
            if ($seiten->isEmpty() || empty($hp['label'])) {
                continue;
            }

            if ($seiten->count() === 1) {
                $neueEintraege[] = [
                    'key' => 'extra-'.Str::slug((string) $hp['label']),
                    'label' => $hp['label'],
                    'href' => self::resolvePageHref($seiten[0]['slug'] ?? null),
                    'children' => null,
                ];

                continue;
            }

            $neueEintraege[] = [
                'key' => 'extra-'.Str::slug((string) $hp['label']),
                'label' => $hp['label'],
                'href' => null,
                'children' => $seiten->map(fn (array $s) => [
                    'type' => 'link',
                    'label' => $s['nav_label'] ?? $s['titel'] ?? '',
                    'href' => self::resolvePageHref($s['slug'] ?? null),
                ])->all(),
            ];
        }

        if (! $neueEintraege) {
            return;
        }

        $faqIndex = null;
        foreach ($items as $i => $item) {
            if (($item['key'] ?? null) === 'faq') {
                $faqIndex = $i;
                break;
            }
        }

        if ($faqIndex === null) {
            array_push($items, ...$neueEintraege);
        } else {
            array_splice($items, $faqIndex, 0, $neueEintraege);
        }
    }

    /**
     * Loest den Slug eines "navigation_extra"-Eintrags auf die tatsaechliche
     * Laravel-Route der betroffenen Seite auf (sektionsuebergreifend, da
     * das Original per generischem "/seiten/?s=<slug>" auf JEDE Section
     * verweisen konnte) - keine erfundene URL, "#" nur als letzter Ausweg
     * bei fehlender/geloeschter Seite.
     */
    private static function resolvePageHref(?string $slug): string
    {
        if (! $slug) {
            return '#';
        }

        $page = Page::whereNull('parent_id')->where('slug', $slug)->first();
        if (! $page) {
            return '#';
        }

        return '/'.$page->section.'/'.$page->slug;
    }

    /**
     * Server-seitiger Port von prettyHref() (resources/js/app.js):
     * ".../index.html" -> ".../", "*.html" -> ohne Endung. Anders als im
     * JS-Original (dort genuegte das fuer ein statisches Hosting mit
     * Verzeichnis-Index) wird der verbleibende Trailing-Slash zusaetzlich
     * entfernt - Laravel-Routen wie "aktuelles" sind ohne Slash registriert
     * (routes/web.php), "/aktuelles/" wuerde sonst ins Leere laufen.
     */
    public static function prettyHref(?string $href): ?string
    {
        if (! $href || $href === '#' || preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $h = (string) preg_replace('#(^|/)index\.html?$#i', '$1', $href);
        $h = (string) preg_replace('#\.html?$#i', '', $h);
        if ($h !== '/') {
            $h = rtrim($h, '/');
        }

        return $h === '' ? '/' : $h;
    }

    /**
     * KJS Bad Segeberg - Phase 4, Auftrag Punkt 7 ("Jäger-Übersicht"): das
     * in Phase 3 bewusst nicht nachgebaute Kachel-Raster von jaeger/
     * index.html (siehe FesteSeiteController-Klassenkommentar) - jetzt
     * "sauber aus MySQL/Nav-Daten erzeugt, keine hart codierte Liste der
     * Geschwisterseiten". Nutzt dieselbe Datenquelle wie das Hauptmenue
     * (Settings-Gruppe "navigation", Schluessel "kjs", ergaenzt um
     * dynamische Registry-Zusatzseiten) statt einer im Code hinterlegten
     * Liste. Fuer Eintraege, die einer echten "pages"-Zeile entsprechen
     * (z.B. Hochwild/Niederwild), werden Kurzbeschreibung/Bild aus der DB
     * ergaenzt - fuer die uebrigen (Vorstand/Obleute/Hegeringe/… haben
     * eigene, von "pages" unabhaengige Controller, siehe
     * PersonenGremiumController/HegeringeController) bleibt es bei
     * Label+Link ohne erfundene Zusatztexte/Bilder.
     *
     * @return list<array{label:string,href:string,beschreibung:?string,bild:?string}>
     */
    public static function jaegerUebersichtKacheln(string $excludeSlug): array
    {
        $blob = self::blob('navigation');
        $kjs = self::mergeDynamic('jaeger', is_array($blob['kjs'] ?? null) ? $blob['kjs'] : []);

        $pagesBySlug = Page::where('section', 'jaeger')
            ->whereNull('parent_id')
            ->get()
            ->keyBy('slug');

        $kacheln = [];
        foreach ($kjs as $it) {
            $href = self::prettyHref($it['href'] ?? null);
            if (! $href) {
                continue;
            }
            $slug = trim((string) preg_replace('#^/jaeger/#', '', $href), '/');
            if ($slug === $excludeSlug) {
                continue;
            }

            $page = $slug !== '' ? $pagesBySlug->get($slug) : null;

            $kacheln[] = [
                'label' => $it['label'] ?? '',
                'href' => $href,
                'beschreibung' => $page?->kurzbeschreibung ?: null,
                'bild' => $page?->vorschaubild ?: ($page?->bild ?: null),
            ];
        }

        return $kacheln;
    }

    /**
     * @return array<string, mixed>
     */
    private static function blob(string $gruppe): array
    {
        $roh = Setting::where('gruppe', $gruppe)->where('key', 'data')->value('value');
        $data = is_string($roh) && $roh !== '' ? json_decode($roh, true) : null;

        return is_array($data) ? $data : [];
    }
}
