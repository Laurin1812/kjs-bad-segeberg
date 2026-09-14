<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageLink;
use App\Support\KjsPagesConfig;
use Illuminate\Http\JsonResponse;

/**
 * KJS Bad Segeberg - Phase 3 Read-API.
 *
 * Rekonstruiert die gesamte bisherige "seiten-*"-Registry-/Einzelseiten-
 * Familie (siehe database/migrations/2026_09_14_000080_create_pages_table.php
 * und App\Support\KjsPagesConfig) aus der vereinheitlichten "pages"-Tabelle.
 *
 * Zwei grundsaetzlich verschiedene Antwort-Formen:
 * - Registry-Antworten ({"seiten": [...]}) - schlanke Liste fuer Navigation/
 *   Uebersichtskacheln (nur Metadaten, KEIN Volltext-Inhalt), siehe
 *   registrySeiten()/registryHundeausbildungSeiten()/registrySub().
 * - Einzelseiten-Antworten (voller Seiteninhalt) - siehe pageToJson().
 */
class PageContentController extends Controller
{
    /**
     * Rekonstruiert eine einzelne Seite in voller Breite. Bewusst wird HIER
     * IMMER der volle, gemeinsame Feldsatz ausgegeben (auch Felder, die im
     * jeweiligen Original nur bei bestimmten Seitenfamilien vorkamen, z.B.
     * "vorschaubild"/"gruppe" nur bei Hundeausbildungs-Kursen,
     * "hundeboerse_cta_*" nur bei hundevermittlung.json) - siehe
     * Abschlussbericht Punkt 5: zusaetzliche, im Original nicht vorhandene
     * Felder sind fuer das Frontend folgenlos (unbekannte Objekt-Schluessel
     * werden schlicht ignoriert), wohingegen ein FEHLENDES, tatsaechlich
     * gebrauchtes Feld eine echte Regression waere. Diese "Superset"-
     * Strategie ist die sicherere Richtung.
     *
     * @return array<string, mixed>
     */
    public static function pageToJson(Page $page): array
    {
        return [
            'titel' => $page->titel ?? '',
            'untertitel' => $page->untertitel ?? '',
            'nav_label' => $page->nav_label ?? '',
            'slug' => $page->slug,
            'intro' => $page->intro ?? '',
            'inhalt' => $page->inhalt ?? '',
            'hero_bild' => $page->hero_bild ?? '',
            'bild' => $page->bild ?? '',
            'bild_alt' => $page->bild_alt ?? '',
            'bild_groesse' => $page->bild_groesse ?? '',
            'bild_flat' => (bool) $page->bild_flat,
            'vorschaubild' => $page->vorschaubild ?? '',
            'kurzbeschreibung' => $page->kurzbeschreibung ?? '',
            'kontakt_name' => $page->kontakt_name ?? '',
            'kontakt_email' => $page->kontakt_email ?? '',
            'kontakt_telefon' => $page->kontakt_telefon ?? '',
            // Phase-3-Fix: siehe Migration 2026_09_16_000003 - bislang nur
            // bei jaeger/mitglied-werden real vorhanden, fuer alle anderen
            // Seiten bleibt es "" (Superset-Strategie, no-op).
            'antrag_url' => $page->antrag_url ?? '',
            'unterseiten_titel' => $page->unterseiten_titel ?? '',
            'gruppe' => $page->gruppe ?? '',
            'linkliste_titel' => $page->linkliste_titel ?? '',
            'hundeboerse_cta_titel' => $page->hundeboerse_cta_titel ?? '',
            'hundeboerse_cta_text' => $page->hundeboerse_cta_text ?? '',
            'hundeboerse_cta_button' => $page->hundeboerse_cta_button ?? '',
            'in_navigation' => (bool) $page->in_navigation,
            'veroeffentlicht' => (bool) $page->veroeffentlicht,
            'downloads' => ContentController::embeddedDownloads($page),
            'galerie' => ContentController::embeddedGalerie($page),
            'galerie_titel' => $page->galerie_titel ?? '',
            'linkliste' => PageLink::where('page_id', $page->id)
                ->orderBy('sortierung')
                ->get()
                ->map(fn (PageLink $l) => ['titel' => $l->label, 'url' => $l->href])
                ->values()
                ->all(),
        ];
    }

    /**
     * Ein Registry-Eintrag ({"seiten": [...]}) - bewusst schlanker als eine
     * volle Einzelseite (kein "inhalt"/"downloads"/"galerie" - genau wie im
     * Original, das fuer Registries nur Navigations-Metadaten fuehrt).
     */
    private static function registryEntry(Page $page): array
    {
        return [
            'slug' => $page->slug,
            'nav_label' => $page->nav_label ?: ($page->titel ?? ''),
            'titel' => $page->titel ?? '',
            'in_navigation' => (bool) $page->in_navigation,
            'veroeffentlicht' => (bool) $page->veroeffentlicht,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Seite nicht gefunden.'], 404);
    }

    // ------------------------------------------------------------------
    // Registries: seiten-kjs.json / seiten-aufgaben.json /
    // seiten-verbraucher.json - nur die per Registry hinzugefuegten
    // ZUSATZSeiten (section=X, parent_id=NULL, slug NICHT in den festen
    // Vorlagen-Slugs), siehe KjsPagesConfig.
    // ------------------------------------------------------------------

    public function registrySeiten(string $section): JsonResponse
    {
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }

        $seiten = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Page $p) => self::registryEntry($p))
            ->values()
            ->all();

        return response()->json(['seiten' => $seiten]);
    }

    /** content/seiten-kjs.json */
    public function registrySeitenKjs(): JsonResponse
    {
        return $this->registrySeiten('jaeger');
    }

    /** content/seiten-aufgaben.json */
    public function registrySeitenAufgaben(): JsonResponse
    {
        return $this->registrySeiten('aufgaben');
    }

    /** content/seiten-verbraucher.json */
    public function registrySeitenVerbraucher(): JsonResponse
    {
        return $this->registrySeiten('verbraucher');
    }

    /**
     * content/seiten-weitere.json - hier sind ALLE Seiten der Section
     * "weitere" Registry-Eintraege (kein "fixed_dir", siehe
     * ImportContent::importWeitere()).
     */
    public function registryWeitere(): JsonResponse
    {
        $seiten = Page::where('section', 'weitere')
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Page $p) => self::registryEntry($p))
            ->values()
            ->all();

        return response()->json(['seiten' => $seiten]);
    }

    /**
     * content/aufgaben/hundeausbildung-seiten.json - abweichende Form
     * gegenueber den anderen Registries: kein "in_navigation" im Original,
     * dafuer "vorschaubild"/"kurzbeschreibung"/"gruppe" (siehe
     * ImportContent::importHundeausbildung()).
     */
    public function registryHundeausbildungSeiten(): JsonResponse
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        if (! $hub) {
            return response()->json(['seiten' => []]);
        }

        $seiten = Page::where('section', 'hundeausbildung')
            ->where('parent_id', $hub->id)
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Page $p) => [
                'slug' => $p->slug,
                'nav_label' => $p->nav_label ?: ($p->titel ?? ''),
                'veroeffentlicht' => (bool) $p->veroeffentlicht,
                'vorschaubild' => $p->vorschaubild ?? '',
                'kurzbeschreibung' => $p->kurzbeschreibung ?? '',
                'gruppe' => $p->gruppe ?? '',
            ])
            ->values()
            ->all();

        return response()->json(['seiten' => $seiten]);
    }

    /**
     * content/seiten-sub-{parentSlug}.json - Unterseiten-Registry einer
     * festen Familien-Seite (jaeger/aufgaben/verbraucher). Fuer Eltern-
     * Seiten ohne jegliche Unterseiten (der Normalfall) identisch zum
     * heutigen leeren {"seiten": []} - auch wenn die Elternseite selbst
     * nicht existiert (z.B. die 2 jaeger-Seiten ohne jede seiten-sub-*.json-
     * Datei, siehe Abschlussbericht Phase 2), da eine fehlende Datei und
     * eine leere Registry frontend-seitig ohnehin gleich behandelt werden
     * (versucheOrdner() faengt beide Faelle ab).
     */
    public function registrySub(string $parentSlug): JsonResponse
    {
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();

        if (! $parent) {
            return response()->json(['seiten' => []]);
        }

        $seiten = Page::where('section', $parent->section)
            ->where('parent_id', $parent->id)
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Page $p) => self::registryEntry($p))
            ->values()
            ->all();

        return response()->json(['seiten' => $seiten]);
    }

    /** content/seiten.json - im echten Datenbestand bewusst immer leer, siehe ImportContent::importSeitenLeeresRegistry(). */
    public function registryLeer(): JsonResponse
    {
        return response()->json(['seiten' => []]);
    }

    // ------------------------------------------------------------------
    // Einzelseiten
    // ------------------------------------------------------------------

    /** content/{section}/{slug}.json - feste Vorlagen-Seite. */
    public function festeSeite(string $section, string $slug): JsonResponse
    {
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }
        if (! in_array($slug, KjsPagesConfig::fixedSlugs($section), true)) {
            return $this->notFound();
        }

        $page = Page::where('section', $section)->whereNull('parent_id')->where('slug', $slug)->first();

        return $page ? response()->json(self::pageToJson($page)) : $this->notFound();
    }

    /** content/seiten-{kjs|aufgaben|verbraucher}/{slug}.json - Registry-Zusatzseite. */
    public function registrierteSeite(string $section, string $slug): JsonResponse
    {
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }

        $page = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->where('slug', $slug)
            ->first();

        return $page ? response()->json(self::pageToJson($page)) : $this->notFound();
    }

    /** content/seiten-kjs/{slug}.json */
    public function registrierteSeiteKjs(string $slug): JsonResponse
    {
        return $this->registrierteSeite('jaeger', $slug);
    }

    /** content/seiten-aufgaben/{slug}.json */
    public function registrierteSeiteAufgaben(string $slug): JsonResponse
    {
        return $this->registrierteSeite('aufgaben', $slug);
    }

    /** content/seiten-verbraucher/{slug}.json */
    public function registrierteSeiteVerbraucher(string $slug): JsonResponse
    {
        return $this->registrierteSeite('verbraucher', $slug);
    }

    /** content/seiten-weitere/{slug}.json. */
    public function weitereSeite(string $slug): JsonResponse
    {
        $page = Page::where('section', 'weitere')->where('slug', $slug)->first();

        return $page ? response()->json(self::pageToJson($page)) : $this->notFound();
    }

    /** content/seiten-sub-{parentSlug}/{childSlug}.json. */
    public function subSeite(string $parentSlug, string $childSlug): JsonResponse
    {
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();
        if (! $parent) {
            return $this->notFound();
        }

        $page = Page::where('section', $parent->section)
            ->where('parent_id', $parent->id)
            ->where('slug', $childSlug)
            ->first();

        return $page ? response()->json(self::pageToJson($page)) : $this->notFound();
    }

    /** content/aufgaben/hundeausbildung.json - Hub (Singleton, section=hundeausbildung, parent_id=NULL). */
    public function hundeausbildungHub(): JsonResponse
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();

        return $hub ? response()->json(self::pageToJson($hub)) : $this->notFound();
    }

    /** content/aufgaben/hundeausbildung/{slug}.json - ein einzelner Kurs. */
    public function hundeausbildungKurs(string $slug): JsonResponse
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        if (! $hub) {
            return $this->notFound();
        }

        $kurs = Page::where('section', 'hundeausbildung')->where('parent_id', $hub->id)->where('slug', $slug)->first();

        return $kurs ? response()->json(self::pageToJson($kurs)) : $this->notFound();
    }
}
