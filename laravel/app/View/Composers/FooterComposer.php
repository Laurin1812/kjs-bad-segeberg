<?php

namespace App\View\Composers;

use App\Models\FooterLink;
use App\Models\Setting;
use App\Support\Navigation;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
 * Laravel-Vollmigration).
 *
 * Ersetzt das bisherige "ZENTRALER FOOTER"-Modul in resources/js/app.js
 * (fetch aus content/footer.json). Dieselbe Datenquelle wie
 * App\Http\Controllers\Api\SettingsContentController::footer() (settings-
 * Tabelle Gruppe "footer" + footer_links-Tabelle), hier direkt fuer die
 * Blade-Component components/site-footer.blade.php aufbereitet statt als
 * JSON-Read-API-Antwort. Alle Spalten-Links laufen durch
 * Navigation::prettyHref() (Auftrag Punkt 5: "Links müssen auf neue
 * Laravel-Routen zeigen", die footer_links-Tabelle enthaelt weiterhin die
 * alten ".html"-Pfade aus dem Import, siehe content/footer.json).
 */
class FooterComposer
{
    public function compose(View $view): void
    {
        $einstellungen = Setting::where('gruppe', 'footer')->pluck('value', 'key');

        $spalten = ['ueber_kjs', 'uebersicht', 'informationen'];
        $links = [];
        foreach ($spalten as $spalte) {
            $links[$spalte] = FooterLink::where('spalte', $spalte)
                ->orderBy('sortierung')
                ->get()
                ->map(fn (FooterLink $l) => ['label' => $l->label, 'href' => Navigation::prettyHref($l->href)])
                ->values();
        }

        $view->with('kjsFooter', [
            'ueberText' => trim((string) ($einstellungen['ueber_text'] ?? '')) ?: null,
            'facebookUrl' => trim((string) ($einstellungen['facebook_url'] ?? '')) ?: null,
            'instagramUrl' => trim((string) ($einstellungen['instagram_url'] ?? '')) ?: null,
            'copyright' => trim((string) ($einstellungen['copyright'] ?? '')) ?: 'Kreisjägerschaft Segeberg e.V.',
            'ueberKjs' => $links['ueber_kjs'],
            'uebersicht' => $links['uebersicht'],
            'informationen' => $links['informationen'],
        ]);
    }
}
