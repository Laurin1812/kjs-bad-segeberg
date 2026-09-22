<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\KjsPagesConfig;
use App\Support\Navigation;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 3 (Dynamische Seitenfamilien & Hundeausbildung,
 * Laravel-Vollmigration).
 *
 * Generischer Controller fuer die "festen Vorlagen-Seiten" der drei
 * Seitenfamilien jaeger/aufgaben/verbraucher (z.B. jaeger/hochwild.html,
 * aufgaben/jagdhorn.html, verbraucher/wildfleisch.html) - siehe
 * App\Support\KjsPagesConfig::fixedSlugs() fuer die feste Slug-Liste je
 * Section, identisch zur bisherigen JSON-Read-API
 * (Api\PageContentController::festeSeite()). BEWUSST ein einziger Controller
 * fuer alle ~24 festen Seiten dieser drei Familien statt eines eigenen
 * Controllers/View pro Seite, da die Datenstruktur (Page-Zeile mit
 * section+parent_id=NULL+slug) und das Template (siehe resources/views/
 * pages/show.blade.php) fuer alle identisch sind (Auftrag Phase 3, Punkt
 * "generische Architektur").
 *
 * SONDERFALL "uebersicht" (Section jaeger): im Original (jaeger/index.html)
 * hat diese eine Seite zusaetzlich zum normalen Seiteninhalt ein Kachel-
 * Raster zu den Geschwisterseiten (Vorstand/Hegeringe/Obleute/…). In Phase 3
 * war "volle Navigation aus MySQL" noch explizit nicht beauftragt, daher
 * wurde das Raster damals bewusst nicht nachgebaut (siehe Abschlussbericht
 * Punkt 1/6) - Phase 4 (Auftrag Punkt 7, "Jäger-Übersicht") beauftragt genau
 * das jetzt nach: App\Support\Navigation::jaegerUebersichtKacheln() liefert
 * dieselbe Geschwisterseiten-Liste wie das Hauptmenue (Settings-Gruppe
 * "navigation" + dynamische Registry-Seiten), keine hart codierte Liste
 * mehr im Code dieses Controllers.
 *
 * ROUTING: "{section}/{slug}" (routes/web.php) zeigt bewusst EINHEITLICH
 * hierher (nicht auf zwei getrennte Routen mit unterschiedlichem Muster,
 * da "welcher Slug ist fest" pro Section eine variable Liste ist, kein
 * Regex-Pattern). Ist der Slug keine feste Vorlagen-Seite, wird transparent
 * an RegistrySeiteController::show() weitergereicht - fuer Besucher ist die
 * Unterscheidung "fest" vs. "per Registry hinzugefuegt" ohnehin nur ein
 * CMS-internes Detail, keine sichtbare URL-Struktur.
 */
class FesteSeiteController extends Controller
{
    public function show(string $section, string $slug): View
    {
        abort_unless(in_array($section, KjsPagesConfig::familySections(), true), 404);

        if (! in_array($slug, KjsPagesConfig::fixedSlugs($section), true)) {
            return app(RegistrySeiteController::class)->show($section, $slug);
        }

        $page = Page::where('section', $section)
            ->whereNull('parent_id')
            ->where('slug', $slug)
            ->where('veroeffentlicht', true)
            ->with(['links', 'children' => function ($q) {
                $q->where('veroeffentlicht', true)->where('in_navigation', true)->orderBy('sortierung');
            }])
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'section' => $section,
            'mode' => 'feste',
            'geschwister' => $section === 'jaeger' && $slug === 'uebersicht'
                ? Navigation::jaegerUebersichtKacheln($slug)
                : null,
        ]);
    }
}
