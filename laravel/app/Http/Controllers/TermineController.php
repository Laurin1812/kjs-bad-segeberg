<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Termin;
use App\Support\TermineRules;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt termine/index.html. Sichtbarkeits-/Sortierregeln 1:1 aus
 * js/content.js (KJSContent.Termine) nach App\Support\TermineRules
 * portiert. Der Kategorie-Filter (Buttons "Alle"/<Kategorie>) bleibt
 * bewusst clientseitiges Zeigen/Verstecken der bereits server-gerenderten
 * Tabellenzeilen (siehe resources/js/app.js) - kein fetch() mehr noetig.
 * Der optionale Google-Kalender-Embed nutzt weiterhin die datenschutz-
 * freundliche Zwei-Klick-Einbindung (kjsEmbedPlaceholder-Muster), jetzt
 * direkt serverseitig in der Blade-View gebaut statt per JS-Helper-Funktion.
 */
class TermineController extends Controller
{
    private const GOLD_KATEGORIEN = ['Jagdhornblasen', 'Kreisveranstaltung', 'Hauptversammlung', 'Tradition'];

    public function index(): View
    {
        $einstellungen = Setting::where('gruppe', 'termine')->pluck('value', 'key');

        $alleTermine = Termin::orderBy('id')->get();
        $sichtbar = TermineRules::sichtbareSortiert($alleTermine);

        $kategorien = $sichtbar->map(fn (Termin $t) => $t->kategorie)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $googleKalenderUrl = (string) ($einstellungen['google_kalender_url'] ?? '');

        return view('termine', [
            'ueberschrift' => (string) ($einstellungen['ueberschrift'] ?? 'Veranstaltungskalender'),
            'einleitung' => (string) ($einstellungen['einleitung'] ?? ''),
            'termine' => $sichtbar,
            'kategorien' => $kategorien,
            'goldKategorien' => self::GOLD_KATEGORIEN,
            'googleKalenderUrl' => $googleKalenderUrl,
            'googleKalenderTitel' => (string) ($einstellungen['google_kalender_titel'] ?? 'Terminbuchung'),
        ]);
    }
}
