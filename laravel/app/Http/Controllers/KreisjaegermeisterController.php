<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt kreisjjaegermeister/index.html - Singleton-Seite, section=
 * kreisjaegermeister in "pages" (siehe Api\ContentController::
 * kreisjaegermeister()). "aufgaben"/"grusswort" koennen historisch
 * entweder rohes HTML (TipTap-Editor-Ausgabe, ab Phase 5B.5) oder reinen
 * Markdown-/Fliesstext (aeltere Datensaetze) enthalten - dieselbe
 * Erkennung wie im alten Client-Code (/<[a-z][\s\S]*>/i) entscheidet,
 * ob unveraendert durchgereicht oder ueber Str::markdown() gerendert wird.
 */
class KreisjaegermeisterController extends Controller
{
    public function show(): View
    {
        $page = Page::where('section', 'kreisjaegermeister')->with(['downloads', 'galerieBilder'])->first();

        return view('kreisjaegermeister', [
            'page' => $page,
            'aufgabenHtml' => $this->renderRichText($page?->inhalt),
            'grusswortHtml' => $this->renderRichText($page?->grusswort),
        ]);
    }

    private function renderRichText(?string $raw): string
    {
        if (! $raw) {
            return '';
        }

        if (preg_match('/<[a-z][\s\S]*>/i', $raw)) {
            return $raw;
        }

        return Str::markdown($raw);
    }
}
