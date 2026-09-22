<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt impressum.html: Route -> Controller -> Eloquent -> Blade, ohne
 * jede clientseitige JSON-Nachladung. Liest dieselben zwei Settings-Gruppen,
 * die bereits die Api\SettingsContentController::impressum()/einstellungen()
 * Read-API bedienen ("impressum" fuer Vereinsdaten, "einstellungen" fuer
 * Adresse/Telefon/E-Mail - Frank-Entscheidung 22.08.2026: Kontaktdaten
 * zentral in einstellungen.json statt dupliziert in impressum.json).
 */
class ImpressumController extends Controller
{
    public function show(): View
    {
        $impressum = Setting::where('gruppe', 'impressum')->pluck('value', 'key');
        $einstellungen = Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');

        return view('impressum', [
            'verein' => (string) ($impressum['verein'] ?? ''),
            'vertretenDurch' => (string) ($impressum['vertreten_durch'] ?? ''),
            'registergericht' => (string) ($impressum['registergericht'] ?? ''),
            'registernummer' => (string) ($impressum['registernummer'] ?? ''),
            'verantwortlich' => (string) ($impressum['verantwortlich'] ?? ''),
            'adresse' => (string) ($einstellungen['adresse'] ?? ''),
            'postadresse' => (string) ($einstellungen['postadresse'] ?? ''),
            'telefon' => (string) ($einstellungen['telefon'] ?? ''),
            'email' => (string) ($einstellungen['email'] ?? ''),
        ]);
    }
}
