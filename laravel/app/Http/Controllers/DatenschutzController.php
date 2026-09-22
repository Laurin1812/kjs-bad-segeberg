<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt datenschutz.html. Der Fliesstext selbst ist im Original fest im
 * HTML verdrahtet (keine Admin-Verwaltung) - nur der "Verantwortlicher"-
 * Absatz laedt Adresse/Postadresse/E-Mail dynamisch aus
 * content/einstellungen.json. Beides bleibt hier erhalten: der Text wandert
 * unveraendert in die Blade-View, die Adressdaten kommen weiterhin aus der
 * "einstellungen"-Settings-Gruppe - nur eben serverseitig statt per fetch().
 */
class DatenschutzController extends Controller
{
    public function show(): View
    {
        $einstellungen = Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');

        return view('datenschutz', [
            'adresse' => (string) ($einstellungen['adresse'] ?? ''),
            'postadresse' => (string) ($einstellungen['postadresse'] ?? ''),
            'email' => (string) ($einstellungen['email'] ?? ''),
        ]);
    }
}
