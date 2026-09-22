<?php

namespace App\View\Composers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
 * Laravel-Vollmigration).
 *
 * Ersetzt das bisherige "Topbar & Geschäftsstelle dynamisch laden"-Modul in
 * resources/js/app.js (fetch aus content/einstellungen.json) fuer die
 * Topbar-Kontaktdaten - dasselbe Muster wie components/kontaktbox.blade.php
 * (Phase-3-Nacharbeit) fuer die Geschaeftsstellen-Box im Seiteninhalt.
 * Anders als dort (die Box blendet bei fehlenden Werten komplett aus)
 * bleibt die Topbar selbst immer sichtbar - fehlt ein Wert, wird nur der
 * jeweilige Link nicht gerendert statt eines erfundenen Platzhalters.
 */
class TopbarComposer
{
    public function compose(View $view): void
    {
        $einstellungen = Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');
        $footer = Setting::where('gruppe', 'footer')->pluck('value', 'key');

        $telefon = trim((string) ($einstellungen['telefon_header'] ?? $einstellungen['telefon'] ?? ''));

        $view->with('kjsTopbar', [
            'email' => trim((string) ($einstellungen['email'] ?? '')) ?: null,
            'telefon' => $telefon !== '' ? $telefon : null,
            'facebookUrl' => trim((string) ($footer['facebook_url'] ?? '')) ?: null,
            'instagramUrl' => trim((string) ($footer['instagram_url'] ?? '')) ?: null,
        ]);
    }
}
