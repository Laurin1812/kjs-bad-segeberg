<?php

namespace App\Http\Controllers;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt service.html (Phase 8C - letzter migrierter CMS-Rest, siehe
 * Api\ContentController::service() fuer das identische Datenmodell). Diese
 * Seite ist ein Hybrid aus Settings-Modul (Titel/Hero-Bild/Kontakt) und
 * Beitragsliste (Kategorie-Akkordeon). Jahr-/Kategorie-Filter bleiben
 * bewusst client-seitiges Zeigen/Verstecken auf dem BEREITS server-
 * gerenderten DOM (siehe resources/js/app.js) - das ist keine Verletzung
 * der "kein JSON zur Laufzeit"-Vorgabe, da dabei kein fetch() mehr passiert.
 * Markdown-Text wird jetzt serverseitig ueber Illuminate\Support\Str::
 * markdown() gerendert (marked.js-Ersatz, siehe Abschlussbericht Punkt 6).
 */
class ServiceController extends Controller
{
    public function show(): View
    {
        $settings = Setting::where('gruppe', 'service')->pluck('value', 'key');

        $kategorienReihenfolge = BeitragKategorie::where('typ', 'service')
            ->orderBy('sortierung')
            ->pluck('name')
            ->all();

        $beitraege = Beitrag::where('typ', 'service')
            ->where('archiviert', false)
            ->with('downloads')
            ->get();

        // Kategorie-Reihenfolge: erst die im Admin verwaltete Liste, danach
        // alles, was nur an Beitraegen haengt, aber (noch) nicht in der
        // Liste steht (identische Fallback-Logik wie im alten service.html).
        $katOrder = [];
        foreach (array_merge($kategorienReihenfolge, $beitraege->pluck('kategorie.name')->filter()->all()) as $k) {
            $k = trim((string) $k);
            if ($k !== '' && ! in_array($k, $katOrder, true)) {
                $katOrder[] = $k;
            }
        }

        $jahre = $beitraege
            ->map(fn (Beitrag $b) => $b->datum?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $kategorien = collect($katOrder)->map(function (string $kat) use ($beitraege) {
            $inKat = $beitraege
                ->filter(fn (Beitrag $b) => trim((string) $b->kategorie?->name) === $kat)
                ->sortByDesc(fn (Beitrag $b) => $b->datum?->timestamp ?? 0)
                ->values()
                ->map(fn (Beitrag $b) => [
                    'beitrag' => $b,
                    'jahr' => $b->datum?->year,
                    'youtube_id' => $this->youtubeId($b->link),
                    'text_html' => $b->text ? Str::markdown($b->text) : '',
                ]);

            return ['titel' => $kat, 'beitraege' => $inKat];
        })->filter(fn (array $g) => $g['beitraege']->isNotEmpty())->values();

        return view('service', [
            'titel' => (string) ($settings['titel'] ?? 'Service'),
            'heroBild' => (string) ($settings['hero_bild'] ?? ''),
            'kontaktName' => (string) ($settings['kontakt_name'] ?? ''),
            'kontaktEmail' => (string) ($settings['kontakt_email'] ?? ''),
            'jahre' => $jahre,
            'kategorien' => $kategorien,
        ]);
    }

    /**
     * YouTube-URL (watch?v=, youtu.be/, shorts/, embed/) -> Video-ID,
     * identisch zur bisherigen clientseitigen youtubeId()-Funktion.
     */
    private function youtubeId(?string $url): string
    {
        if (! $url) {
            return '';
        }

        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return $m[1];
        }

        return '';
    }
}
