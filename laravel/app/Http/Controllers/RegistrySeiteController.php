<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\KjsPagesConfig;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 3 (Dynamische Seitenfamilien & Hundeausbildung,
 * Laravel-Vollmigration).
 *
 * Generischer Controller fuer alle per Admin-Registry HINZUGEFUEGTEN
 * (nicht in KjsPagesConfig::fixedSlugs() gelisteten) Seiten - ersetzt
 * gemeinsam mit resources/views/pages/show.blade.php das bisherige
 * seiten/index.html?s=<slug> (siehe dortiger Kommentar "versucheOrdner()"):
 * - Zusatzseiten der drei Familien jaeger/aufgaben/verbraucher
 *   (frueher content/seiten-{kjs|aufgaben|verbraucher}/{slug}.json),
 * - Seiten der Section "weitere" (kein "fixed_dir", ALLE Seiten dieser
 *   Section sind Registry-Zusatzseiten, siehe Api\PageContentController::
 *   weitereSeite()),
 * - dynamisch angelegte UNTERSEITEN (Kind-Seiten via parent_id) einer
 *   beliebigen festen ODER Registry-Seite (frueher content/
 *   seiten-sub-{parent}/{child}.json).
 *
 * Nutzt exakt dieselbe Blade-View (resources/views/pages/show.blade.php,
 * mode="registry") wie FesteSeiteController (mode="feste") - die
 * Page-Datenstruktur ist fuer feste und per Registry hinzugefuegte Seiten
 * identisch (siehe KjsPagesConfig-Klassenkommentar: die DB selbst
 * unterscheidet beide Faelle nicht mehr). show()/weitere()/sub() bilden
 * hier nur die drei unterschiedlichen Lookup-Wege ab, kein eigenes
 * Controller/View pro Seite.
 */
class RegistrySeiteController extends Controller
{
    /** @return array<int, string> */
    private function relations(): array
    {
        return ['downloads', 'galerieBilder', 'children' => function ($q) {
            $q->where('veroeffentlicht', true)->where('in_navigation', true)->orderBy('sortierung');
        }];
    }

    /** {section}/{slug} - Registry-Zusatzseite von jaeger/aufgaben/verbraucher. */
    public function show(string $section, string $slug): View
    {
        abort_unless(in_array($section, KjsPagesConfig::familySections(), true), 404);

        $page = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->where('slug', $slug)
            ->where('veroeffentlicht', true)
            ->with($this->relations())
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'section' => $section,
            'mode' => 'registry',
        ]);
    }

    /** weitere/{slug} - Seite der Section "weitere" (immer Registry-Art, kein fixedSlugs-Konzept). */
    public function weitere(string $slug): View
    {
        $page = Page::where('section', 'weitere')
            ->where('slug', $slug)
            ->where('veroeffentlicht', true)
            ->with($this->relations())
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'section' => 'weitere',
            'mode' => 'registry',
        ]);
    }

    /** {section}/{parentSlug}/{childSlug} - dynamisch angelegte Unterseite einer Eltern-Seite. */
    public function sub(string $section, string $parentSlug, string $childSlug): View
    {
        abort_unless(in_array($section, KjsPagesConfig::familySections(), true), 404);

        $parent = Page::where('section', $section)
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->firstOrFail();

        $page = Page::where('section', $section)
            ->where('parent_id', $parent->id)
            ->where('slug', $childSlug)
            ->where('veroeffentlicht', true)
            ->with($this->relations())
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'section' => $section,
            'mode' => 'registry',
            // Phase 4 (Auftrag Punkt 3, "Bei Unterseiten Elternseite
            // einbeziehen"): die Breadcrumb-Anzeige in pages/show.blade.php
            // baut daraus eine vierte Ebene (Startseite -> Section ->
            // Elternseite -> diese Unterseite), z.B. "Startseite -> Jäger ->
            // Hochwild -> <Unterseite>".
            'parent' => $parent,
        ]);
    }
}
