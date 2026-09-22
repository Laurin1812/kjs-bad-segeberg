<?php

namespace App\Http\Controllers;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Setting;
use App\Support\AktuellesRules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt aktuelles/index.html + aktuelles/beitrag.html. Sichtbarkeits-/
 * Sortier-/Archiv-Regeln sind 1:1 aus js/content.js (KJSContent.Aktuelles)
 * nach App\Support\AktuellesRules portiert (siehe dortiger Klassenkommentar).
 *
 * URL-Schema bewusst geaendert (siehe Abschlussbericht Punkt 2): die alte
 * Detail-Adresse "beitrag.html?i=<Array-Index>" wird durch den echten,
 * bereits vorhandenen "slug"-Spaltenwert ersetzt (/aktuelles/beitrag/{slug})
 * - idiomatische Laravel-Route statt fragilem Array-Index-Query-Parameter.
 * Jahres-/Kategorie-Filter der Uebersicht laufen jetzt ueber echte
 * Query-Parameter (?jahr=&kategorie[]=), serverseitig ausgewertet - kein
 * clientseitiges Filtern von JSON mehr noetig.
 */
class AktuellesController extends Controller
{
    public function index(Request $request): View
    {
        $einstellungen = Setting::where('gruppe', 'aktuelles')->pluck('value', 'key');
        $hauptseiteAnzahl = (int) ($einstellungen['hauptseite_anzahl'] ?? 0);

        $alle = Beitrag::where('typ', 'aktuelles')->with('kategorie')->get();
        $nichtArchiviert = $alle->where('archiviert', false)->values();

        $jahrParam = $request->query('jahr');
        $jahr = null;
        if ($jahrParam === 'alle') {
            $jahr = 'alle';
        } elseif (is_numeric($jahrParam)) {
            $jahr = (int) $jahrParam;
        }

        if ($jahr === 'alle') {
            $basis = $nichtArchiviert;
            $headline = 'Alle Beiträge';
        } elseif (is_int($jahr)) {
            $basis = $nichtArchiviert->filter(fn (Beitrag $b) => AktuellesRules::postYear($b) === $jahr)->values();
            $headline = 'Beiträge '.$jahr;
        } else {
            $basis = AktuellesRules::standardAuswahl($nichtArchiviert, $hauptseiteAnzahl);
            $headline = $hauptseiteAnzahl > 0
                ? 'Aktuelles (letzte '.$hauptseiteAnzahl.')'
                : 'Aktuelles aus unserer Kreisjägerschaft';
        }

        // Kategorien, die im gerade aktiven Jahres-Filter tatsaechlich
        // vorkommen (identisch zur alten buildKategorieInline()-Logik).
        $verfuegbareKategorien = $basis->map(fn (Beitrag $b) => trim((string) $b->kategorie?->name))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $ausgewaehlteKategorien = collect((array) $request->query('kategorie', []))
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->values();

        $gefiltert = $ausgewaehlteKategorien->isNotEmpty()
            ? $basis->filter(fn (Beitrag $b) => $ausgewaehlteKategorien->contains(trim((string) $b->kategorie?->name)))->values()
            : $basis;

        $beitraege = AktuellesRules::sortiertNeuesteZuerst($gefiltert);

        return view('aktuelles.index', [
            'headline' => $headline,
            'beitraege' => $beitraege,
            'jahre' => AktuellesRules::sichtbareJahre($nichtArchiviert),
            'aktivesJahr' => $jahr,
            'verfuegbareKategorien' => $verfuegbareKategorien,
            'ausgewaehlteKategorien' => $ausgewaehlteKategorien,
        ]);
    }

    public function show(string $slug): View
    {
        $beitrag = Beitrag::where('typ', 'aktuelles')
            ->where('slug', $slug)
            ->with(['kategorie', 'downloads', 'galerieBilder'])
            ->firstOrFail();

        $alleAktuelles = Beitrag::where('typ', 'aktuelles')->get();
        $nichtArchiviert = $alleAktuelles->where('archiviert', false)->values();

        return view('aktuelles.show', [
            'beitrag' => $beitrag,
            'textHtml' => $beitrag->text ? Str::markdown($beitrag->text) : '',
            'weitereBeitraege' => AktuellesRules::weitereDesJahres($nichtArchiviert, $beitrag),
        ]);
    }
}
