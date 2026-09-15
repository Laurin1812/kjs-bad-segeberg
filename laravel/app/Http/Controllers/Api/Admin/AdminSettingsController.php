<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterLink;
use App\Models\Setting;
use App\Models\StartseiteHeroSlide;
use App\Models\Testimonial;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL) - Schreib-Gegenstueck
 * zu SettingsContentController (Read-API, Phase 3). Jede Methode hier
 * spiegelt GENAU die Rueckrichtung der jeweiligen ImportContent::import*()-
 * Methode (siehe dortige Kommentare fuer die Feld-fuer-Feld-Begruendung, hier
 * nicht wiederholt) - dieselbe Normalisierung (Bool/leer->null/Trim), damit
 * ein Speichern -> Laden -> Speichern-Zyklus stabil bleibt und die
 * Read-API (Phase 3, 0 echte Abweichungen) unveraendert korrekt bedient wird.
 *
 * Konflikterkennung: jede Methode erwartet im Request-Body optional
 * "expected_version" (siehe ContentVersioning/AdminVersionController) -
 * stimmt sie nicht mit der aktuellen Version ueberein, wird MIT HTTP 409
 * abgebrochen, OHNE zu schreiben (Bestandsschutz-Aequivalent zur bisherigen
 * Git-SHA-Konflikterkennung in admin.js/doSave()).
 */
class AdminSettingsController extends Controller
{
    /** Konstanten statt Strings verstreut - siehe AdminVersionController. */
    public const SECTION_DESIGN = 'design';

    public const SECTION_EINSTELLUNGEN = 'einstellungen';

    public const SECTION_FOOTER = 'footer';

    public const SECTION_IMPRESSUM = 'impressum';

    public const SECTION_NAVIGATION = 'navigation';

    public const SECTION_NAVIGATION_EXTRA = 'navigation-extra';

    public const SECTION_STARTSEITE = 'startseite';

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

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'invalid_payload', 'message' => $message], 422);
    }

    /**
     * Schritt 2/9 (Sicherheits-Mindesttest "ungueltiger Payload -> 422"):
     * bislang wurde ein fehlendes/falsch typisiertes "data"-Feld von
     * bodyAndVersion() stillschweigend zu [] normalisiert, wodurch z.B. ein
     * PUT ohne "data" den kompletten Datensatz (getestet: footer.json) auf
     * leer zurueckgesetzt statt abgelehnt hat. Diese Methode schliesst die
     * Luecke zentral - siehe identisches Gegenstueck in AdminListController.
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
            'data' => is_array($request->input('data')) ? $request->input('data') : [],
            'expected_version' => is_numeric($expected) ? (int) $expected : null,
        ];
    }

    /**
     * Spiegelbild von ImportContent::importScalarSettings(): ersetzt ALLE
     * settings-Zeilen einer Gruppe (ausser $excludeKeys, die anderswo
     * behandelt werden) durch genau die im Payload gelieferten Schluessel.
     * Ein im Payload fehlender Schluessel (z.B. weil ein Formularfeld
     * entfernt wurde) loescht die zugehoerige Zeile - das entspricht dem
     * bisherigen Git-Verhalten, bei dem die komplette Datei ersetzt wurde.
     */
    private function replaceScalarSettings(string $gruppe, array $data, array $excludeKeys): void
    {
        Setting::where('gruppe', $gruppe)->whereNotIn('key', $excludeKeys)->delete();
        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif ($value === null) {
                $value = null;
            } else {
                $value = (string) $value;
            }
            Setting::create(['gruppe' => $gruppe, 'key' => (string) $key, 'value' => $value]);
        }
    }

    public function design(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        try {
            return DB::transaction(function () use ($data, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_DESIGN, $expected);
                $this->replaceScalarSettings('design', $data, []);

                return $this->ok(ContentVersioning::bump(self::SECTION_DESIGN));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    public function impressum(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        try {
            return DB::transaction(function () use ($data, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_IMPRESSUM, $expected);
                $this->replaceScalarSettings('impressum', $data, []);

                return $this->ok(ContentVersioning::bump(self::SECTION_IMPRESSUM));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    /** Spiegelbild von ImportContent::importEinstellungen(). */
    public function einstellungen(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        try {
            return DB::transaction(function () use ($data, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_EINSTELLUNGEN, $expected);
                $this->replaceScalarSettings('einstellungen', $data, ['oeffnungszeiten']);

                if (isset($data['oeffnungszeiten']) && is_array($data['oeffnungszeiten'])) {
                    // Bugfix (im lokalen Sicherheits-/E2E-Test entdeckt):
                    // "oeffnungszeiten" wird oben bewusst von
                    // replaceScalarSettings() ausgenommen, die Zeile bleibt
                    // also bei jedem Speichern bestehen - ein Setting::create()
                    // hier hat deshalb ab dem ZWEITEN Speichern (die Zeile
                    // existiert ja schon seit dem Import) immer einen
                    // Duplicate-Key-Fehler auf settings_gruppe_key_unique
                    // ausgeloest und jeden Einstellungen-Save mit HTTP 500
                    // abgebrochen. updateOrCreate() ist hier korrekt, exakt
                    // wie bereits in saveNavigationBlob() oben verwendet.
                    Setting::updateOrCreate(
                        ['gruppe' => 'einstellungen', 'key' => 'oeffnungszeiten'],
                        ['value' => json_encode($data['oeffnungszeiten'], JSON_UNESCAPED_UNICODE)]
                    );
                }

                return $this->ok(ContentVersioning::bump(self::SECTION_EINSTELLUNGEN));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    /** Spiegelbild von ImportContent::importFooter(). */
    public function footer(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        $listKeys = ['spalte_ueber_kjs' => 'ueber_kjs', 'spalte_uebersicht' => 'uebersicht', 'spalte_informationen' => 'informationen'];
        try {
            return DB::transaction(function () use ($data, $expected, $listKeys) {
                ContentVersioning::assertNotStale(self::SECTION_FOOTER, $expected);
                $this->replaceScalarSettings('footer', $data, array_keys($listKeys));

                foreach ($listKeys as $jsonKey => $spalte) {
                    FooterLink::where('spalte', $spalte)->delete();
                    $items = is_array($data[$jsonKey] ?? null) ? $data[$jsonKey] : [];
                    foreach (array_values($items) as $i => $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $href = trim((string) ($item['href'] ?? ''));
                        if ($href === '') {
                            continue;
                        }
                        FooterLink::create([
                            'spalte' => $spalte,
                            'label' => (string) ($item['label'] ?? $href),
                            'href' => $href,
                            'sortierung' => $i,
                        ]);
                    }
                }

                return $this->ok(ContentVersioning::bump(self::SECTION_FOOTER));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    /** Spiegelbild von ImportContent::importNavigationBlob(). */
    private function saveNavigationBlob(Request $request, string $gruppe, string $section): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        // navigation.json/navigation-extra.json sind selbst schon das
        // vollstaendige JSON-Objekt (kein {"data": {...}}-Wrapper wie bei den
        // anderen Settings-Modulen) - admin.js' doSave() schickt fuer diese
        // beiden Module das komplette S.data direkt als apiPut()-Payload,
        // siehe Verwendung in NAV (form:'navReihenfolge'/'navExtra').
        $data = $request->input('data');
        $blob = is_array($data) ? $data : $request->all();
        unset($blob['expected_version']);

        // Kein "data"-Wrapper bei diesen beiden Modulen (siehe Methoden-
        // kommentar oben) - ein voellig leeres/kein Objekt enthaltendes
        // Payload ist trotzdem kein gueltiger Speicherwunsch, sondern der
        // Sicherheits-Mindesttest "ungueltiger Payload -> 422" (Auftrag
        // Schritt 9) bzw. ein defekter Client-Request.
        if (! is_array($blob) || count($blob) === 0) {
            return $this->invalid('Leeres oder ungültiges Payload.');
        }

        try {
            return DB::transaction(function () use ($gruppe, $section, $blob, $expected) {
                ContentVersioning::assertNotStale($section, $expected);
                Setting::updateOrCreate(
                    ['gruppe' => $gruppe, 'key' => 'data'],
                    ['value' => json_encode($blob, JSON_UNESCAPED_UNICODE)]
                );

                return $this->ok(ContentVersioning::bump($section));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    public function navigation(Request $request): JsonResponse
    {
        return $this->saveNavigationBlob($request, 'navigation', self::SECTION_NAVIGATION);
    }

    public function navigationExtra(Request $request): JsonResponse
    {
        return $this->saveNavigationBlob($request, 'navigation_extra', self::SECTION_NAVIGATION_EXTRA);
    }

    /** Spiegelbild von ImportContent::importStartseite(). */
    public function startseite(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        $exclude = ['hero_slides', 'testimonials', 'testimonials_sichtbar', 'downloads', 'galerie'];

        try {
            return DB::transaction(function () use ($data, $expected, $exclude) {
                ContentVersioning::assertNotStale(self::SECTION_STARTSEITE, $expected);
                $this->replaceScalarSettings('startseite', $data, $exclude);

                StartseiteHeroSlide::query()->delete();
                $slides = is_array($data['hero_slides'] ?? null) ? $data['hero_slides'] : [];
                foreach (array_values($slides) as $i => $slide) {
                    if (! is_array($slide)) {
                        continue;
                    }
                    $bild = trim((string) ($slide['bild'] ?? ''));
                    if ($bild === '') {
                        continue;
                    }
                    StartseiteHeroSlide::create([
                        'bild' => $bild,
                        'dauer' => isset($slide['dauer']) ? (string) $slide['dauer'] : null,
                        'sortierung' => $i,
                    ]);
                }

                Testimonial::query()->delete();
                // Siehe SettingsContentController::startseite(): dieselbe
                // Flag gilt beim Lesen fuer ALLE Testimonials gemeinsam -
                // beim Schreiben daher konsequent auf jede neue Zeile
                // uebertragen (kein Datenverlust ggue. dem bisherigen
                // gemeinsamen Feld).
                $sichtbar = $data['testimonials_sichtbar'] ?? true;
                $sichtbar = is_bool($sichtbar) ? $sichtbar : (is_numeric($sichtbar) ? ((int) $sichtbar === 1) : (bool) $sichtbar);
                $testimonials = is_array($data['testimonials'] ?? null) ? $data['testimonials'] : [];
                foreach (array_values($testimonials) as $i => $t) {
                    if (! is_array($t)) {
                        continue;
                    }
                    $text = trim((string) ($t['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    Testimonial::create([
                        'text' => $text,
                        'name' => (string) ($t['name'] ?? ''),
                        'rolle' => (string) ($t['rolle'] ?? '') ?: null,
                        'icon' => (string) ($t['icon'] ?? '') ?: null,
                        'sichtbar' => $sichtbar,
                        'sortierung' => $i,
                    ]);
                }

                // downloads/galerie: im echten Datenbestand immer leer (siehe
                // ImportContent::importStartseite()) - werden bewusst nicht
                // gespeichert (kein Zieltabellenmodell in Phase 1/4 dafuer
                // vorgesehen), identisch zum Import-Verhalten.

                return $this->ok(ContentVersioning::bump(self::SECTION_STARTSEITE));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }
}
