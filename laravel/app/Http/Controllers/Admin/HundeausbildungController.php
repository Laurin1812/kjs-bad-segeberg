<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7H (Admin-Modul "Hundeausbildung / Jagdhundeschule").
 *
 * KURZANALYSE-ERGEBNIS (vor der Umsetzung, wie im Auftrag verlangt):
 * "Hundeausbildung" ist KEIN eigenstaendiges Fachmodul mit eigenem
 * Datenmodell - es ist ausschliesslich eine weitere Page-Familie
 * (section = 'hundeausbildung') innerhalb derselben "pages"-Tabelle, die
 * bereits Phase 7B fuer jaeger/aufgaben/verbraucher/weitere erschlossen hat:
 *   - Hub-Seite (parent_id = null, slug='hundeausbildung', titel=
 *     'Jagdhundeschule') = die Uebersichtsseite.
 *   - 19 Kurs-Unterseiten (parent_id = Hub-ID) = die einzelnen Kurse/
 *     Themen der Jagdhundeschule (siehe ImportContent::
 *     importHundeausbildung()).
 * Kein Trainer-/Kurstermin-/Buchungsmodell, keine eigenen Bilder-/Downloads-
 * Tabellen (die Kurs-Seiten nutzen wie jede andere Page auch die
 * bestehenden downloads/galerieBilder/links-Relationen). Bestaetigt durch
 * Api\Admin\AdminPageController::hundeausbildungHub()/hundeausbildungKurs()
 * (bestehender JSON-Schreibweg), die beide bereits App\Support\PageUpdater
 * fuer die eigentliche Feldzuweisung nutzen - dieselbe Klasse, die auch
 * Http\Controllers\Admin\InhalteController verwendet.
 *
 * FOLGE (Auftrag "Fall A"): KEIN neuer Updater, KEIN neues Datenmodell,
 * KEINE doppelte CRUD-Logik. Phase 7H erweitert lediglich InhalteController::
 * IN_SCOPE_SECTIONS um 'hundeausbildung' (siehe dortiger Klassenkommentar)
 * - Bearbeiten/Speichern laeuft ab sofort 1:1 ueber die bereits bestehenden
 * Routen admin.inhalte.bearbeiten/admin.inhalte.update, keine zweite
 * Speichern-Logik hier. Dieser Controller bietet AUSSCHLIESSLICH eine
 * gefilterte Einstiegsseite (index()) - keine eigene store()/update()/
 * destroy()-Methode.
 *
 * ABGRENZUNG (bewusst NICHT Teil dieser Phase, siehe Auftrag "keine neue
 * Architektur"): der bestehende JSON-Weg bietet fuer Hundeausbildungs-Kurse
 * zusaetzlich Neu-Anlegen/Loeschen/Sortieren an (AdminPageController::
 * storeHundeausbildungKurs()/destroyHundeausbildungKurs()/
 * reorderHundeausbildungKurse()) - das bietet der neue Blade-Admin fuer
 * KEINE Page-Section an (auch nicht fuer jaeger/aufgaben/verbraucher/
 * weitere in InhalteController), Hundeausbildung wuerde sonst gegenueber
 * allen anderen Sections bevorzugt behandelt. Bleibt bis zu einer
 * moeglichen spaeteren, sectionsuebergreifenden Phase weiterhin nur ueber
 * admin.js/die JSON-API moeglich.
 */
class HundeausbildungController extends Controller
{
    public function index(): View
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();

        $kurse = $hub
            ? Page::where('section', 'hundeausbildung')->where('parent_id', $hub->id)->orderBy('sortierung')->orderBy('titel')->get()
            : collect();

        return view('admin.hundeausbildung.index', [
            'hub' => $hub,
            'kurse' => $kurse,
        ]);
    }
}
