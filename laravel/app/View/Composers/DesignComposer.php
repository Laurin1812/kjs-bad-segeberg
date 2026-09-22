<?php

namespace App\View\Composers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 3 Nacharbeit (100%-Laravel-Architektur-Korrektur).
 *
 * Ersetzt den bisherigen clientseitigen "fetch('/api/content/design.json')"-
 * Block im Haupt-Layout (components/layouts/app.blade.php) - Design-/
 * Theme-Werte (Farben/Schriften/Schriftgroessen) kamen dort zur Laufzeit per
 * JS aus der JSON-Read-API (Api\SettingsContentController::design(), Gruppe
 * "design" der "settings"-Tabelle). Auftrag: "Laravel Route -> Controller/
 * View-Daten -> Eloquent/MySQL -> Blade" OHNE JSON-Zwischenschritt - dieser
 * View Composer laedt dieselben Werte stattdessen direkt serverseitig aus
 * Eloquent und stellt sie dem Layout als $kjsDesign zur Verfuegung. Das
 * Layout gibt daraus einen "<style>:root{--green-main:…}</style>"-Block
 * aus (siehe dortiger Kommentar) - exakt dieselbe CSS-Custom-Property-
 * Wirkung wie zuvor "document.documentElement.style.setProperty(...)",
 * nur serverseitig statt per Nachlade-Request.
 *
 * Registrierung siehe AppServiceProvider::boot().
 */
class DesignComposer
{
    public function compose(View $view): void
    {
        $view->with('kjsDesign', Setting::where('gruppe', 'design')->pluck('value', 'key'));
    }
}
