<?php

namespace App\Http\Controllers;

use App\Models\Beitrag;
use App\Models\Setting;
use App\Models\StartseiteHeroSlide;
use App\Models\Termin;
use App\Models\Testimonial;
use App\Support\AktuellesRules;
use App\Support\Navigation;
use App\Support\TermineRules;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
 * Laravel-Vollmigration).
 *
 * Ersetzt die bisherige Laravel-Welcome-Seite UND das produktive index.html
 * (statische Datei mit mehreren Inline-<script>-Bloecken, die per fetch()
 * aus /api/content/startseite.json, /api/content/aktuelles.json,
 * /api/content/termine.json nachluden) durch eine echte "Route ->
 * Controller -> Eloquent/MySQL -> Blade"-Seite (Auftrag Phase 4 Punkt 1).
 * Dieselbe Datenquelle wie zuvor die JSON-Read-API (siehe
 * Api\SettingsContentController::startseite()): settings-Tabelle Gruppe
 * "startseite" + startseite_hero_slides + testimonials. Die Vorschauen
 * "Was passiert in der KJS"/"Nächste Termine" nutzen dieselben, bereits in
 * Phase 2 gebauten Sichtbarkeits-/Sortierregeln wie /aktuelles bzw. /termine
 * (AktuellesRules/TermineRules) - keine zweite, abweichende Logik.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        $startseite = Setting::where('gruppe', 'startseite')->pluck('value', 'key');

        $heroSlides = StartseiteHeroSlide::orderBy('sortierung')->get()->filter(fn ($s) => (bool) $s->bild)->values();

        $testimonials = Testimonial::orderBy('sortierung')->get();
        $testimonialsSichtbar = $testimonials->isEmpty() ? true : (bool) $testimonials->first()->sichtbar;

        $nichtArchivierteBeitraege = Beitrag::where('typ', 'aktuelles')->where('archiviert', false)->get();
        $neuesteBeitraege = AktuellesRules::sortiertNeuesteZuerst($nichtArchivierteBeitraege)->take(3);

        $alleTermine = Termin::orderBy('id')->get();
        $naechsteTermine = TermineRules::sichtbareSortiert($alleTermine)->take(4);

        // "jaeger/index.html" ist im Original die Jäger-Übersichtsseite
        // (heute /jaeger/uebersicht, siehe FesteSeiteController) - ein
        // Sonderfall, da "index.html" fuer diese eine Section KEIN
        // bereinigtes "/jaeger" ergibt (es gibt keine Route ohne Slug), im
        // Gegensatz zu allen anderen ".../index.html"-Verweisen der
        // settings-Tabelle (siehe Navigation::prettyHref()-Klassenkommentar).
        $quicklinkHref = function (?string $raw): ?string {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return null;
            }
            if (ltrim($raw, '/') === 'jaeger/index.html') {
                return '/jaeger/uebersicht';
            }

            return Navigation::prettyHref('/'.ltrim($raw, '/'));
        };

        return view('home', [
            's' => $startseite,
            'heroSlides' => $heroSlides,
            'testimonials' => $testimonialsSichtbar ? $testimonials : collect(),
            'testimonialsSichtbar' => $testimonialsSichtbar,
            'beitraege' => $neuesteBeitraege,
            'termine' => $naechsteTermine,
            'quicklinkHref' => $quicklinkHref,
        ]);
    }
}
