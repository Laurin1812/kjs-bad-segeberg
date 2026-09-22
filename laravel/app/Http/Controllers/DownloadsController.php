<?php

namespace App\Http\Controllers;

use App\Models\DownloadKategorie;
use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt downloads/index.html - NUR die zentrale Download-Bibliothek
 * (kategorie_id gesetzt, owner_type NULL), siehe Api\ContentController::
 * downloads(). Die im Original hart hinterlegten Platzhalter-/Dummy-
 * Downloads (Satzung/Beitragsordnung-Beispiele etc.) werden bewusst NICHT
 * uebernommen (siehe Abschlussbericht) - sie waren im Original ohnehin nur
 * ein reiner JS-Ladefallback, der real nie sichtbar war.
 */
class DownloadsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::where('gruppe', 'downloads')->pluck('value', 'key');

        $kategorien = DownloadKategorie::with(['downloads' => function ($q) {
            $q->whereNull('owner_type')->orderBy('sortierung');
        }])
            ->orderBy('sortierung')
            ->get()
            ->filter(fn (DownloadKategorie $k) => $k->downloads->isNotEmpty())
            ->values();

        return view('downloads', [
            'titel' => (string) ($settings['titel'] ?? 'Downloads'),
            'intro' => (string) ($settings['intro'] ?? ''),
            'kategorien' => $kategorien,
        ]);
    }
}
