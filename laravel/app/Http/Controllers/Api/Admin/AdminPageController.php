<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageLink;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\KjsPagesConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL) - Schreib-Gegenstueck
 * zu PageContentController (Read-API, Phase 3). Ursprünglich (Phase 4) NUR
 * zum BEARBEITEN bestehender Seiten gedacht - Phase 6 ("Neue Seiten und
 * Unterseiten ... über Laravel/MySQL anlegen") ergänzt hier die eigentlichen
 * store*()/destroy*()-Methoden für die "+ Neue Unterseite/Seite"-Buttons in
 * admin.js sowie deren "🗑️ Seite löschen", die zuvor (Phase 4/5) eine
 * bestehende Seite voraussetzten und bei einem noch nicht existierenden Slug
 * mit 404 abgelehnt hätten. Das BEARBEITEN bestehender Seiten (saveResolved()
 * unten) bleibt unverändert.
 *
 * RECHTE: anders als bei den Settings-/Listen-Modulen oben (dort 1 Route <->
 * 1 fester PERM_BY_KEY-Wert) haengt die Berechtigung fuer eine einzelne
 * Seite in admin.js von IHREM SLUG ab (PERM_BY_KEY/PERM_BY_DIR, z.B.
 * 'niederwild' vs. 'hochwild' vs. 'aufgaben_natur' - siehe dortige Tabelle),
 * nicht vom Endpunkt selbst. Diese ca. 30 Slug-zu-Recht-Zuordnungen sind 1:1
 * nach PHP portiert (siehe App\Support\PagePermissions) und werden ueber die
 * Middleware "identity.page_permission:<kind>" (routes/api.php,
 * App\Http\Middleware\EnsurePagePermission) VOR jeder dieser Methoden
 * geprueft - dieselben <kind>-Werte wie beim jeweiligen PUT-Pendant, weil die
 * Berechtigung fuer "Unterseite von X anlegen/löschen" dieselbe ist wie fuer
 * "X selbst bearbeiten" (Phase-6-Auftrag Punkt 6: bestehende Rechte-Logik
 * unveraendert wiederverwenden, keine neue Rechte-Ebene erfinden).
 *
 * Phase 6B ("Drag-&-Drop-Sortierung ... auf Laravel/MySQL umstellen")
 * ergänzt die reorder*()-Methoden (PATCH auf denselben URLs wie die
 * jeweilige store*()-Route) für admin.js' onSidebarReorder(), die bisher
 * (fuer die dynamischen Registry-/Weitere-/Unterseiten-/Hundeausbildung-
 * Listen) immer ueber ein separates Git-Gateway-Manifest lief, unabhaengig
 * von IS_PHP_HOST - siehe reordne() unten fuer die gemeinsame Logik.
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

    /**
     * Phase 6 Auftrag Punkt 5 ("Slug-Sicherheit"): serverseitige Prüfung,
     * unabhängig davon, was admin.js' makeSlug() clientseitig bereits erzeugt
     * hat (die dortige Erzeugung bleibt UI-Komfort, kein Vertrauensanker).
     * Nur Kleinbuchstaben/Ziffern/Bindestriche, kein "..", kein "/", nicht
     * leer, keine führenden/folgenden/doppelten Bindestriche - exakt die
     * Zeichenmenge, die makeSlug() selbst erzeugt, damit ein normal über den
     * Admin erzeugter Titel nie serverseitig abgelehnt wird.
     *
     * @return string|null Fehlermeldung, oder null wenn der Slug gültig ist.
     */
    private function slugFehler(string $slug): ?string
    {
        if ($slug === '') {
            return 'Bitte ein URL-Kürzel (Slug) angeben.';
        }
        if (mb_strlen($slug) > 100) {
            return 'URL-Kürzel ist zu lang (max. 100 Zeichen).';
        }
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            return 'URL-Kürzel darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten (keine Leerzeichen, Punkte, Schrägstriche oder Sonderzeichen).';
        }

        return null;
    }

    /**
     * Feldübernahme beim NEUANLEGEN (Punkt 3 "Datenmodell/Seitenregister") -
     * bewusst eine EIGENE, kleinere Methode statt applyFields() wiederzuver-
     * wenden: nimmt NUR die Felder entgegen, die admin.js' neueSeiteSpeedSave()
     * beim Anlegen tatsächlich mitschickt (siehe dortiges "newData"-Objekt),
     * und - anders als applyFields() - NIE "section"/"parent_id"/"slug"/
     * "sortierung"/"registry_veroeffentlicht"/"id" aus dem Client-Payload
     * (Punkt 3 "Keine IDs aus dem Browser übernehmen", Punkt 12 "keine
     * Mass-Assignment-Lücke") - diese fünf Felder setzt IMMER der jeweilige
     * store*()-Aufrufer selbst, serverseitig. Speichert bewusst NICHT selbst
     * (Aufrufer setzt zuerst section/parent_id/slug/sortierung, dann genau
     * ein $page->save()).
     */
    private function fillCreateFields(Page $page, array $data): void
    {
        $page->fill([
            'titel' => (string) ($data['titel'] ?? '') ?: null,
            'nav_label' => (string) ($data['nav_label'] ?? '') ?: null,
            'intro' => $data['intro'] ?? null,
            'inhalt' => $data['inhalt'] ?? null,
            'hero_bild' => (string) ($data['hero_bild'] ?? '') ?: null,
            'bild' => (string) ($data['bild'] ?? '') ?: null,
            'bild_alt' => (string) ($data['bild_alt'] ?? '') ?: null,
            // Nur bei Hundeausbildungs-Kursen von admin.js gesendet
            // (Kachel-Vorschau) - fuer alle anderen Faelle einfach leer.
            'vorschaubild' => (string) ($data['vorschaubild'] ?? '') ?: null,
            'kurzbeschreibung' => $data['kurzbeschreibung'] ?? null,
            'gruppe' => (string) ($data['gruppe'] ?? '') ?: null,
            'kontakt_name' => (string) ($data['kontakt_name'] ?? '') ?: null,
            'kontakt_email' => (string) ($data['kontakt_email'] ?? '') ?: null,
            'in_navigation' => $this->toBool($data['in_navigation'] ?? null, true),
            'veroeffentlicht' => $this->toBool($data['veroeffentlicht'] ?? null, true),
        ]);
    }

    /**
     * Punkt 7 ("Sortierung"): neue Seiten landen am Ende ihrer Geschwister
     * (gleiche section+parent_id, feste UND per Registry angelegte Seiten
     * zusammen) - "entweder am Ende" aus dem Auftrag. Bestehende
     * sortierung-Werte werden dabei nie verändert (nur gelesen), Lücken in
     * der Nummerierung sind fuer "ORDER BY sortierung" folgenlos.
     */
    private function naechsteSortierung(string $section, ?int $parentId): int
    {
        $max = Page::where('section', $section)->where('parent_id', $parentId)->max('sortierung');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /**
     * Gemeinsamer Speicher-Ablauf fuer alle store*()-Methoden unten: prüft
     * Titel/Slug, prüft Eindeutigkeit (Punkt 3 "keine doppelten Slugs
     * innerhalb derselben Seitenfamilie/Parent-Struktur" - EXPLIZIT per
     * Anwendungscode geprüft, nicht nur über den DB-Unique-Index verlassen,
     * weil MySQL mehrere NULLs in einer UNIQUE-Spalte nicht als Duplikate
     * behandelt und "parent_id" bei allen Top-Level-Seiten NULL ist - der
     * DB-Index allein würde zwei Top-Level-Seiten mit demselben Slug in
     * derselben Section NICHT verhindern), legt die Page an und vergibt die
     * erste Versionsnummer (ContentVersioning::bump()), damit ein
     * unmittelbar folgendes Bearbeiten/Speichern derselben Seite (Laden über
     * apiGetLaravel(), siehe admin.js) sofort eine gültige Versionsnummer
     * fuer die Konflikterkennung vorfindet.
     */
    private function erzeugeSeite(array $data, string $section, ?int $parentId, string $versionSection): JsonResponse
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($fehler = $this->slugFehler($slug)) {
            return $this->invalid($fehler);
        }
        $titel = trim((string) ($data['titel'] ?? ''));
        if ($titel === '') {
            return $this->invalid('Bitte einen Seitentitel angeben.');
        }
        if (Page::where('section', $section)->where('parent_id', $parentId)->where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'slug_taken',
                'message' => 'Dieses URL-Kürzel wird in diesem Bereich bereits verwendet. Bitte ein anderes wählen.',
            ], 409);
        }
        if ($parentId === null && in_array($section, KjsPagesConfig::familySections(), true)
            && in_array($slug, KjsPagesConfig::fixedSlugs($section), true)) {
            return response()->json([
                'success' => false,
                'error' => 'slug_reserved',
                'message' => 'Dieses URL-Kürzel ist für eine feste Seite dieses Bereichs reserviert.',
            ], 409);
        }

        try {
            return DB::transaction(function () use ($data, $section, $parentId, $slug, $versionSection) {
                $page = new Page(['section' => $section, 'parent_id' => $parentId, 'slug' => $slug]);
                $this->fillCreateFields($page, $data);
                $page->sortierung = $this->naechsteSortierung($section, $parentId);
                $page->save();
                $version = ContentVersioning::bump($versionSection);

                return response()->json([
                    'success' => true,
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'version' => $version,
                ], 201);
            });
        } catch (QueryException $e) {
            // Sicherheitsnetz gegen die oben beschriebene MySQL-NULL-
            // Unique-Lücke bei einem echten Gleichzeitigkeits-Wettlauf
            // (zwei Anfragen bestehen beide die exists()-Prüfung, bevor
            // die erste ihren Insert abschliesst) - kein Stacktrace/keine
            // rohe DB-Fehlermeldung in der Antwort (Punkt 12).
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'success' => false,
                    'error' => 'slug_taken',
                    'message' => 'Dieses URL-Kürzel wird in diesem Bereich bereits verwendet. Bitte ein anderes wählen.',
                ], 409);
            }
            throw $e;
        }
    }

    /** content/seiten-{kjs|aufgaben|verbraucher}.json - neue Registry-Zusatzseite (parent_id=NULL). */
    private function erstelleRegistrierteSeite(Request $request, string $section): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }
        $data = (array) $request->input('data');

        return $this->erzeugeSeite($data, $section, null, $this->versionSection($section, trim((string) ($data['slug'] ?? ''))));
    }

    public function storeRegistrierteSeiteKjs(Request $request): JsonResponse
    {
        return $this->erstelleRegistrierteSeite($request, 'jaeger');
    }

    public function storeRegistrierteSeiteAufgaben(Request $request): JsonResponse
    {
        return $this->erstelleRegistrierteSeite($request, 'aufgaben');
    }

    /**
     * Punkt 2 ("... Verbraucher, falls der bestehende Admin das wirklich
     * unterstützt"): siehe Analyse in Punkt 1 des Abschlussberichts - der
     * generische "➕ Neue Verbraucher-Seite"-Button wurde am 22.08.2026
     * bewusst entfernt (siehe admin.js-Kommentar bei "new-sub-wild"), admin.js
     * hat daher AKTUELL keinen UI-Button, der diesen Endpunkt aufruft. Der
     * Endpunkt existiert trotzdem (API-Vollständigkeit, exakt wie schon das
     * seit Phase 4 bestehende, ebenfalls ungenutzte PUT-Pendant
     * registrierteSeiteVerbraucher()), ist aber bewusst NICHT an admin.js
     * angebunden - "bestehende UI beibehalten" (Punkt 1) heisst hier: keinen
     * frueher entfernten Button wieder einführen.
     */
    public function storeRegistrierteSeiteVerbraucher(Request $request): JsonResponse
    {
        return $this->erstelleRegistrierteSeite($request, 'verbraucher');
    }

    /** content/seiten-weitere.json - neue "Weitere Themen"-Seite (section=weitere, immer parent_id=NULL). */
    public function storeWeitereSeite(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $data = (array) $request->input('data');

        return $this->erzeugeSeite($data, 'weitere', null, $this->versionSection('weitere', trim((string) ($data['slug'] ?? ''))));
    }

    /** content/seiten-sub-{parentSlug}.json - neue Unterseite unter einer bestehenden Hauptseite. */
    public function storeSubSeite(Request $request, string $parentSlug): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();
        if (! $parent) {
            // Ungueltiger Parent (Punkt 12 "ungueltiger Parent -> 404/422") -
            // z.B. Tippfehler in der URL oder eine zwischenzeitlich gelöschte
            // Hauptseite.
            return $this->notFound();
        }
        $data = (array) $request->input('data');

        return $this->erzeugeSeite(
            $data,
            $parent->section,
            $parent->id,
            $this->versionSection('sub', $parent->slug, trim((string) ($data['slug'] ?? '')))
        );
    }

    /** content/aufgaben/hundeausbildung-seiten.json - neuer Hundeausbildungs-Kurs unter dem festen Hub. */
    public function storeHundeausbildungKurs(Request $request): JsonResponse
    {
        if ($invalid = $this->requireDataArray($request)) {
            return $invalid;
        }
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        if (! $hub) {
            // Sollte im echten Datenbestand nie vorkommen (der Hub ist ein
            // fester Singleton) - fail-safe trotzdem als 404 statt eines
            // Fehlers mit unklarer Ursache.
            return $this->notFound();
        }
        $data = (array) $request->input('data');

        return $this->erzeugeSeite(
            $data,
            'hundeausbildung',
            $hub->id,
            $this->versionSection('hundeausbildung', trim((string) ($data['slug'] ?? '')))
        );
    }

    /**
     * Punkt 9 ("Löschen neuer Seiten"): admin.js' dynSeiteDelete() existiert
     * bereits (nur fuer isDynamic-Seiten, siehe renderStandard() dort) und
     * loeschte bisher IMMER per direktem Git-Gateway-DELETE, unabhaengig von
     * IS_PHP_HOST - das ist der Teil, der hier durch einen echten
     * Laravel/MySQL-Endpunkt ersetzt wird. "Niemals still Kinder mitlöschen"
     * (Auftrag): aktuell kann im Admin keine der hier loeschbaren Seiten
     * (Registry-Zusatzseite/Unterseite/Weitere-Themen-Seite/Hundeausbildungs-
     * Kurs) selbst wieder eigene Unterseiten haben (keine dieser vier hat in
     * admin.js ein "➕ Neue Unterseite"-Kind, siehe hatUnterseitenSystem()),
     * die Prüfung unten ist daher rein defensiv/zukunftssicher, nicht Teil
     * eines heute erreichbaren Falls.
     */
    private function loescheSeite(?Page $page): JsonResponse
    {
        if (! $page) {
            return $this->notFound();
        }
        $childCount = Page::where('parent_id', $page->id)->count();
        if ($childCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'has_children',
                'message' => 'Diese Seite hat noch '.$childCount.' Unterseite(n) und wurde nicht gelöscht. Bitte zuerst die Unterseiten entfernen oder verschieben.',
            ], 409);
        }

        return DB::transaction(function () use ($page) {
            // Eingebettete Downloads/Galerie/Linkliste der Seite mit aufräumen
            // (dieselben privaten Methoden wie applyFields() beim Speichern
            // verwendet, mit leerem Array = "alles entfernen") - sonst blieben
            // verwaiste Download-/Galerie-/Link-Zeilen mit einer owner_id
            // zurueck, die auf keine Page-Zeile mehr zeigt.
            $this->replaceEmbeddedDownloads($page, []);
            $this->replaceEmbeddedGalerie($page, []);
            $this->replacePageLinks($page, []);
            $page->delete();

            return response()->json(['success' => true]);
        });
    }

    private function destroyRegistrierteSeite(string $section, string $slug): JsonResponse
    {
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }
        $page = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section))
            ->where('slug', $slug)
            ->first();

        return $this->loescheSeite($page);
    }

    public function destroyRegistrierteSeiteKjs(string $slug): JsonResponse
    {
        return $this->destroyRegistrierteSeite('jaeger', $slug);
    }

    public function destroyRegistrierteSeiteAufgaben(string $slug): JsonResponse
    {
        return $this->destroyRegistrierteSeite('aufgaben', $slug);
    }

    public function destroyRegistrierteSeiteVerbraucher(string $slug): JsonResponse
    {
        return $this->destroyRegistrierteSeite('verbraucher', $slug);
    }

    public function destroyWeitereSeite(string $slug): JsonResponse
    {
        $page = Page::where('section', 'weitere')->where('slug', $slug)->first();

        return $this->loescheSeite($page);
    }

    public function destroySubSeite(string $parentSlug, string $childSlug): JsonResponse
    {
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();
        if (! $parent) {
            return $this->notFound();
        }
        $page = Page::where('section', $parent->section)->where('parent_id', $parent->id)->where('slug', $childSlug)->first();

        return $this->loescheSeite($page);
    }

    public function destroyHundeausbildungKurs(string $slug): JsonResponse
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        $page = $hub
            ? Page::where('section', 'hundeausbildung')->where('parent_id', $hub->id)->where('slug', $slug)->first()
            : null;

        return $this->loescheSeite($page);
    }

    /**
     * Phase 6B ("Drag-&-Drop-Sortierung ... auf Laravel/MySQL umstellen"):
     * gemeinsame Kernlogik fuer alle reorder*()-Endpunkte unten. $scope
     * grenzt die "Familie" ein, innerhalb derer sortiert werden darf (z.B.
     * "alle Registry-Zusatzseiten von section=jaeger" oder "alle Kinder
     * eines bestimmten Unterseiten-Parents") - dieselbe Eingrenzung, die
     * auch die jeweilige store*()/destroy*()-Methode oben schon verwendet,
     * damit keine fremde Section/kein fremder Parent versehentlich
     * mitsortiert werden kann (Auftrag Punkt 2/4: "keine fremden Sections
     * verschieben", "Parent/Child-Grenzen beachten"). $order ist die vom
     * Client gewuenschte neue Reihenfolge als Liste von Slugs.
     *
     * Verhalten bei einer nur TEILWEISEN Liste (nicht alle Seiten der
     * Familie genannt): identisch zum bisherigen Client-Verhalten in
     * admin.js' onSidebarReorder() ("Übrige Seiten (z.B. unveröffentlichte)
     * am Ende anhängen") - die genannten Seiten werden zuerst in der
     * gewuenschten Reihenfolge einsortiert, alle nicht genannten Seiten der
     * Familie werden unveraendert in ihrer bisherigen relativen Reihenfolge
     * dahinter angehaengt. So bleibt die Reihenfolge nach einem Reload
     * exakt stabil (Auftrag Punkt 4), auch wenn admin.js einmal nicht alle
     * Zeilen mitschickt.
     */
    private function reordne(Builder $scope, mixed $order): JsonResponse
    {
        if (! is_array($order) || $order === []) {
            return $this->invalid('Bitte eine Liste von Seiten in der gewünschten Reihenfolge angeben.');
        }
        $slugs = [];
        foreach ($order as $eintrag) {
            if (! is_string($eintrag) || trim($eintrag) === '') {
                return $this->invalid('Die Reihenfolge-Liste enthält einen ungültigen Eintrag.');
            }
            $slugs[] = $eintrag;
        }
        if (count($slugs) !== count(array_unique($slugs))) {
            // Punkt 4: "doppelte Slugs im Payload -> 422".
            return $this->invalid('Die Reihenfolge-Liste enthält doppelte Seiten.');
        }

        return DB::transaction(function () use ($scope, $slugs) {
            // lockForUpdate() serialisiert gleichzeitige Reorder-Anfragen
            // auf derselben Familie/demselben Parent, damit zwei parallele
            // Drag-Vorgaenge sich nicht gegenseitig die sortierung-Werte
            // kaputt schreiben.
            $seiten = (clone $scope)->orderBy('sortierung')->lockForUpdate()->get(['id', 'slug']);
            $bySlug = $seiten->keyBy('slug');

            foreach ($slugs as $slug) {
                if (! $bySlug->has($slug)) {
                    // Unbekannter Slug ODER eine Seite aus einer fremden
                    // Section/einem fremden Parent (beides würde hier
                    // fehlen, weil $scope schon korrekt eingegrenzt ist) -
                    // Punkt 4: "unbekannte Slugs -> 422", "Payload enthält
                    // Seite aus fremder Section/anderem Parent -> 422".
                    return $this->invalid('Unbekannte oder nicht zu diesem Bereich gehörende Seite: "'.$slug.'".');
                }
            }

            // Genannte Seiten zuerst in Wunsch-Reihenfolge, alle nicht
            // genannten Seiten der Familie danach in ihrer bisherigen
            // relativen Reihenfolge (siehe Klassenkommentar oben).
            $reihenfolge = [];
            foreach ($slugs as $slug) {
                $reihenfolge[] = $bySlug->get($slug);
            }
            $genannt = array_flip($slugs);
            foreach ($seiten as $seite) {
                if (! isset($genannt[$seite->slug])) {
                    $reihenfolge[] = $seite;
                }
            }

            foreach ($reihenfolge as $index => $seite) {
                Page::where('id', $seite->id)->update(['sortierung' => $index]);
            }

            return response()->json(['success' => true]);
        });
    }

    /** content/seiten-{kjs|aufgaben|verbraucher}.json (PATCH) - Reihenfolge der Registry-Zusatzseiten. */
    private function reordneRegistrierteSeite(Request $request, string $section): JsonResponse
    {
        if (! in_array($section, KjsPagesConfig::familySections(), true)) {
            return $this->notFound();
        }
        $scope = Page::where('section', $section)
            ->whereNull('parent_id')
            ->whereNotIn('slug', KjsPagesConfig::fixedSlugs($section));

        return $this->reordne($scope, $request->input('order'));
    }

    public function reorderRegistrierteSeiteKjs(Request $request): JsonResponse
    {
        return $this->reordneRegistrierteSeite($request, 'jaeger');
    }

    public function reorderRegistrierteSeiteAufgaben(Request $request): JsonResponse
    {
        return $this->reordneRegistrierteSeite($request, 'aufgaben');
    }

    /** Siehe Kommentar bei storeRegistrierteSeiteVerbraucher() - API-Vollständigkeit, aktuell kein UI-Button in admin.js. */
    public function reorderRegistrierteSeiteVerbraucher(Request $request): JsonResponse
    {
        return $this->reordneRegistrierteSeite($request, 'verbraucher');
    }

    /** content/seiten-weitere.json (PATCH) - Reihenfolge der "Weitere Themen"-Seiten (section=weitere, immer flach). */
    public function reorderWeitereSeiten(Request $request): JsonResponse
    {
        $scope = Page::where('section', 'weitere');

        return $this->reordne($scope, $request->input('order'));
    }

    /** content/seiten-sub-{parentSlug}.json (PATCH) - Reihenfolge der Unterseiten EINES Parents (Punkt 2: "Unterseiten nur innerhalb desselben Parents"). */
    public function reorderSubSeiten(Request $request, string $parentSlug): JsonResponse
    {
        $parent = Page::whereIn('section', KjsPagesConfig::familySections())
            ->whereNull('parent_id')
            ->where('slug', $parentSlug)
            ->first();
        if (! $parent) {
            return $this->notFound();
        }
        $scope = Page::where('section', $parent->section)->where('parent_id', $parent->id);

        return $this->reordne($scope, $request->input('order'));
    }

    /** content/aufgaben/hundeausbildung-seiten.json (PATCH) - Reihenfolge der Hundeausbildungs-Kurse UNTER DEM HUB (Punkt 2: "Hundeausbildung-Kurse nur innerhalb ihres Hub-Parents"). */
    public function reorderHundeausbildungKurse(Request $request): JsonResponse
    {
        $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
        if (! $hub) {
            return $this->notFound();
        }
        $scope = Page::where('section', 'hundeausbildung')->where('parent_id', $hub->id);

        return $this->reordne($scope, $request->input('order'));
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
