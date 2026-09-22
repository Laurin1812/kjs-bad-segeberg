<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 3 (Dynamische Seitenfamilien & Hundeausbildung,
 * Laravel-Vollmigration).
 *
 * Ersetzt aufgaben/hundeausbildung.html (Hub) + aufgaben/jagdhundeschule.html
 * (Kurs-Uebersicht + Kurs-Detail ueber "?s=<slug>"-Query-Parameter). Section
 * "hundeausbildung" ist ein Sonderfall der "pages"-Tabelle: EIN Hub
 * (parent_id=NULL) + bis zu 19 Kind-Seiten (parent_id=Hub-ID), siehe
 * ImportContent::importHundeausbildung() und Api\PageContentController::
 * hundeausbildungHub()/hundeausbildungKurs()/registryHundeausbildungSeiten().
 *
 * URL-Schema modernisiert (analog zu AktuellesController/PartnerController,
 * Abschlussbericht-Punkt-2-Muster): "jagdhundeschule.html?s=<slug>" wird zu
 * einer echten Route "/aufgaben/jagdhundeschule/{slug}" statt eines
 * Query-Parameters - ein unbekannter Slug fuehrt hier bereits ueber
 * firstOrFail() zu einer echten Laravel-404 statt der bisherigen
 * client-seitigen "Seite konnte nicht geladen werden"-Meldung.
 *
 * WICHTIG (routes/web.php): diese drei Routen sind LITERALE Routen
 * ("aufgaben/hundeausbildung", "aufgaben/jagdhundeschule[/…]") und MUESSEN
 * vor der generischen "{section}/{slug}"-Route (FesteSeiteController)
 * registriert werden - sonst wuerde "/aufgaben/hundeausbildung"
 * faelschlich als (nicht existierende) Registry-Seite der Section
 * "aufgaben" interpretiert (siehe identischer Kommentar in der
 * bisherigen JSON-Read-API, routes/api.php).
 */
class HundeausbildungController extends Controller
{
    private function findHub(): Page
    {
        return Page::where('section', 'hundeausbildung')->whereNull('parent_id')->firstOrFail();
    }

    /** GET /aufgaben/hundeausbildung - Hub-Seite (Kontakt/Intro/Inhalt, kein Downloads/Galerie-Block, 1:1 wie im Original). */
    public function hub(): View
    {
        $hub = $this->findHub();
        $anzahlKurse = Page::where('section', 'hundeausbildung')
            ->where('parent_id', $hub->id)
            ->where('veroeffentlicht', true)
            ->count();

        return view('hundeausbildung.hub', [
            'hub' => $hub,
            'anzahlKurse' => $anzahlKurse,
        ]);
    }

    /** GET /aufgaben/jagdhundeschule - Kurs-Uebersicht (Bildkacheln, nach "gruppe" sortiert/gruppiert). */
    public function index(): View
    {
        $hub = $this->findHub();

        $kurse = Page::where('section', 'hundeausbildung')
            ->where('parent_id', $hub->id)
            ->where('veroeffentlicht', true)
            ->orderBy('sortierung')
            ->get();

        return view('hundeausbildung.index', [
            'hub' => $hub,
            'kurse' => $kurse,
        ]);
    }

    /** GET /aufgaben/jagdhundeschule/{slug} - einzelner Kurs (mit Downloads/Galerie, 1:1 wie im Original). */
    public function show(string $slug): View
    {
        $hub = $this->findHub();

        $kurs = Page::where('section', 'hundeausbildung')
            ->where('parent_id', $hub->id)
            ->where('slug', $slug)
            ->where('veroeffentlicht', true)
            ->with(['downloads', 'galerieBilder'])
            ->firstOrFail();

        $seitenNav = Page::where('section', 'hundeausbildung')
            ->where('parent_id', $hub->id)
            ->where('veroeffentlicht', true)
            ->orderBy('sortierung')
            ->get();

        return view('hundeausbildung.show', [
            'hub' => $hub,
            'kurs' => $kurs,
            'seitenNav' => $seitenNav,
        ]);
    }
}
