<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageLink;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\KjsPagesConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL) - Schreib-Gegenstueck
 * zu PageContentController (Read-API, Phase 3), fuer diese Phase bewusst
 * NUR zum BEARBEITEN bestehender Seiten (kein Anlegen neuer Registry-Zusatz-
 * seiten/Unterseiten ueber die "+ Neue Unterseite/Seite"-Buttons - siehe
 * Abschlussbericht "offene Punkte"). Eine nicht gefundene Seite liefert
 * bewusst 404 statt sie stillschweigend anzulegen.
 *
 * RECHTE (Fortsetzung, vormaliger Blocker jetzt aufgeloest): anders als bei
 * den Settings-/Listen-Modulen oben (dort 1 Route <-> 1 fester
 * PERM_BY_KEY-Wert) haengt die Berechtigung fuer eine einzelne Seite in
 * admin.js von IHREM SLUG ab (PERM_BY_KEY/PERM_BY_DIR, z.B. 'niederwild' vs.
 * 'hochwild' vs. 'aufgaben_natur' - siehe dortige Tabelle), nicht vom
 * Endpunkt selbst. Diese ca. 30 Slug-zu-Recht-Zuordnungen sind jetzt 1:1
 * nach PHP portiert (siehe App\Support\PagePermissions) und werden ueber die
 * neue Middleware "identity.page_permission:<kind>" (routes/api.php,
 * App\Http\Middleware\EnsurePagePermission) VOR jeder dieser Methoden
 * geprueft - admin.js ist entsprechend angepasst (laravelModulFuerDatei()
 * inkl. neuer laravelPageModulFuerDatei()), Redakteure mit einzelnen
 * Seiten-Rechten speichern ihre Seiten dadurch jetzt ebenfalls ueber
 * Laravel/MySQL, nicht mehr ueber Git-Gateway.
 */
class AdminPageController extends Controller
{
    private function conflictResponse(ContentVersionConflictException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'version_conflict',
            'message' => 'Diese Inhalte wurden zwischenzeitlich an anderer Stelle geändert. Nicht gespeichert.',
            'current_version' => $e->currentVersion,
        ], 409);
    }

    private function ok(int $version): JsonResponse
    {
        return response()->json(['success' => true, 'version' => $version]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'not_found', 'message' => 'Seite nicht gefunden.'], 404);
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'invalid_payload', 'message' => $message], 422);
    }

    /**
     * Schritt 2/9 (Sicherheits-Mindesttest "ungueltiger Payload -> 422"):
     * ohne diese Pruefung faellt bodyAndVersion() unten bei fehlendem/falsch
     * typisiertem "data" auf $request->except('expected_version') zurueck -
     * bei einem leeren PUT-Body waere das ein leeres Array, mit dem
     * applyFields() die bestehende Seite anschliessend auf lauter
     * Leer-/Standardwerte zurueckgesetzt haette, statt den Request
     * abzulehnen. Siehe identisches Gegenstueck in AdminSettingsController/
     * AdminListController.
     */
    private function requireDataArray(Request $request): ?JsonResponse
    {
        if (! is_array($request->input('data'))) {
            return $this->invalid('Feld "data" fehlt oder ist kein gültiges Objekt.');
        }

        return null;
    }

    /** @return array{data: array<string, mixed>, expected_version: int|null} */
    private function bodyAndVersion(Request $request): array
    {
        $expected = $request->input('expected_version');

        return [
            'data' => is_array($request->input('data')) ? $request->input('data') : $request->except('expected_version'),
            'expected_version' => is_numeric($expected) ? (int) $expected : null,
        ];
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', '1'], true)) {
                return true;
            }

            return false;
        }

        return (bool) $value;
    }

    /**
     * Uebertraegt alle Felder aus dem Payload auf eine BESTEHENDE Page -
     * Spiegelbild von ImportContent::createPageFromFields(), aber als
     * update() statt create() und bewusst OHNE "registry_veroeffentlicht"
     * anzufassen (siehe Klassenkommentar bei importWeitere()/Migration
     * 2026_09_16_000004: dieses Feld gehoert der REGISTRY-Ansicht, nicht der
     * Einzelseite - ein Speichern der Einzelseite darf den dort separat
     * hinterlegten Registry-Wert nicht ueberschreiben, sonst waere genau der
     * in Phase 3 Runde 3 behobene Fehler wieder da).
     */
    private function applyFields(Page $page, array $data): void
    {
        $page->fill([
            'titel' => (string) ($data['titel'] ?? '') ?: null,
            'untertitel' => is_string($data['untertitel'] ?? null) ? $data['untertitel'] : null,
            'nav_label' => (string) ($data['nav_label'] ?? '') ?: null,
            'intro' => $data['intro'] ?? null,
            'inhalt' => $data['inhalt'] ?? null,
            'hero_bild' => (string) ($data['hero_bild'] ?? '') ?: null,
            'bild' => (string) ($data['bild'] ?? '') ?: null,
            'bild_alt' => (string) ($data['bild_alt'] ?? '') ?: null,
            'vorschaubild' => (string) ($data['vorschaubild'] ?? '') ?: null,
            'kurzbeschreibung' => $data['kurzbeschreibung'] ?? null,
            'bild_groesse' => (string) ($data['bild_groesse'] ?? '') ?: null,
            'bild_flat' => $this->toBool($data['bild_flat'] ?? null, false),
            'kontakt_name' => (string) ($data['kontakt_name'] ?? '') ?: null,
            'kontakt_email' => (string) ($data['kontakt_email'] ?? '') ?: null,
            'kontakt_telefon' => (string) ($data['kontakt_telefon'] ?? '') ?: null,
            'antrag_url' => (string) ($data['antrag_url'] ?? '') ?: null,
            'unterseiten_titel' => (string) ($data['unterseiten_titel'] ?? '') ?: null,
            'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
            'gruppe' => (string) ($data['gruppe'] ?? '') ?: null,
            'linkliste_titel' => (string) ($data['linkliste_titel'] ?? '') ?: null,
            'hundeboerse_cta_titel' => (string) ($data['hundeboerse_cta_titel'] ?? '') ?: null,
            'hundeboerse_cta_text' => (string) ($data['hundeboerse_cta_text'] ?? '') ?: null,
            'hundeboerse_cta_button' => (string) ($data['hundeboerse_cta_button'] ?? '') ?: null,
            'in_navigation' => $this->toBool($data['in_navigation'] ?? null, true),
            'veroeffentlicht' => $this->toBool($data['veroeffentlicht'] ?? null, true),
        ]);
        $page->save();

        $this->replaceEmbeddedDownloads($page, $data['downloads'] ?? null);
        $this->replaceEmbeddedGalerie($page, $data['galerie'] ?? null);
        $this->replacePageLinks($page, $data['linkliste'] ?? null);
    }

    private function replaceEmbeddedDownloads(Page $page, mixed $items): void
    {
        \App\Models\Download::where('owner_type', $page->getMorphClass())->where('owner_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $pfad = trim((string) ($item['datei'] ?? ''));
            $titel = trim((string) ($item['titel'] ?? ''));
            if ($pfad === '' && $titel === '') {
                continue;
            }
            \App\Models\Download::create([
                'owner_type' => $page->getMorphClass(),
                'owner_id' => $page->id,
                'titel' => $titel !== '' ? $titel : $pfad,
                'pfad' => $pfad,
                'vorschau' => (string) ($item['vorschau'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    private function replaceEmbeddedGalerie(Page $page, mixed $items): void
    {
        \App\Models\GalerieBild::where('owner_type', $page->getMorphClass())->where('owner_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $pfad = trim((string) ($item['bild'] ?? ''));
            if ($pfad === '') {
                continue;
            }
            \App\Models\GalerieBild::create([
                'owner_type' => $page->getMorphClass(),
                'owner_id' => $page->id,
                'pfad' => $pfad,
                'titel' => (string) ($item['titel'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    private function replacePageLinks(Page $page, mixed $items): void
    {
        PageLink::where('page_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['url'] ?? ''));
            $label = trim((string) ($item['titel'] ?? ''));
            if ($href === '' && $label === '') {
                continue;
            }
            PageLink::create(['page_id' => $page->id, 'label' => $label, 'href' => $href, 'sortierung' => $i]);
        }
    }

    /** Gemeinsamer Speicher-Ablauf: Version pruefen, Felder anwenden, Version erhoehen. */
    private function saveResolved(?Page $page, string $versionSection, array $data, ?int $expected): JsonResponse
    {
        if (! $page) {
            return $this->notFound();
        }
        try {
            return DB::transaction(function () use ($page, $versionSection, $data, $expected) {
                ContentVersioning::assertNotStale($versionSection, $expected);
                $this->applyFields($page, $data);

                return $this->ok(ContentVersioning::bump($versionSection));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    /** "section:slug" - eindeutiger, stabiler Bezeichner je Seite fuer ContentVersioning (siehe AdminVersionController). */
    private function versionSection(string ...$parts): string
    {
        return 'page:'.implode(':', $parts);
    }

    public function festeSeite(Request $request, string $section, string $slug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        if (! in_array($section, KjsPagesConfig::familySections(), true) || ! in_array($slug, KjsPagesConfig::fixedSlugs($section), true)) {
            return $this->notFound();
        }
        $page = Page::where('section', $section)->whereNull('parent_id')->where('slug', $slug)->first();
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection($section, $slug), $data, $expected);
    }

    private function registrierteSeite(Request $request, string $section, string $slug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }
        $page = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->where('slug', $slug)
            ->first();
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection($section, $slug), $data, $expected);
    }

    public function registrierteSeiteKjs(Request $request, string $slug): JsonResponse
    {
        return $this->registrierteSeite($request, 'jaeger', $slug);
    }

    public function registrierteSeiteAufgaben(Request $request, string $slug): JsonResponse
    {
        return $this->registrierteSeite($request, 'aufgaben', $slug);
    }

    public function registrierteSeiteVerbraucher(Request $request, string $slug): JsonResponse
    {
        return $this->registrierteSeite($request, 'verbraucher', $slug);
    }

    /**
     * content/seiten-weitere/{slug}.json - siehe Klassenkommentar bei
     * applyFields(): "registry_veroeffentlicht" bleibt bewusst unangetastet.
     */
    public function weitereSeite(Request $request, string $slug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $page = Page::where('section', 'weitere')->where('slug', $slug)->first();
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection('weitere', $slug), $data, $expected);
    }

    public function subSeite(Request $request, string $parentSlug, string $childSlug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();
        if (! $parent) {
            return $this->notFound();
        }
        $page = Page::where('section', $parent->section)->where('parent_id', $parent->id)->where('slug', $childSlug)->first();
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection('sub', $parentSlug, $childSlug), $data, $expected);
    }

    public function hundeausbildungHub(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $page = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection('hundeausbildung', 'hub'), $data, $expected);
    }

    public function hundeausbildungKurs(Request $request, string $slug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        $page = $hub
            ? Page::where('section', 'hundeausbildung')->where('parent_id', $hub->id)->where('slug', $slug)->first()
            : null;
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        return $this->saveResolved($page, $this->versionSection('hundeausbildung', $slug), $data, $expected);
    }
}
