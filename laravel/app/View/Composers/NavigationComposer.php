<?php

namespace App\View\Composers;

use App\Support\Navigation;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
 * Laravel-Vollmigration).
 *
 * Ersetzt das bisherige "ZENTRALE NAVIGATION"-Modul in resources/js/app.js
 * (fetch aus navigation.json/navigation-extra.json + den drei Registry-
 * Dateien) durch eine serverseitige View-Composer-Struktur - siehe
 * App\Support\Navigation fuer die eigentliche Zusammenfuehrung. Nach
 * demselben Muster wie App\View\Composers\DesignComposer (Phase-3-
 * Nacharbeit "100% Laravel"): gebunden an components.site-header, wo
 * Desktop- UND Mobile-Navigation gemeinsam gerendert werden.
 */
class NavigationComposer
{
    public function compose(View $view): void
    {
        $view->with('kjsNav', Navigation::build());
    }
}
