<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\FaqFrage;
use App\Models\FaqKategorie;
use App\Models\GalerieBild;
use App\Models\Hegering;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PartnerVorteil;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Termin;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL) - Schreib-Gegenstueck
 * zu ContentController (Read-API, Phase 3) fuer die "flachen Listen"
 * (aktuelles, termine, vorstand, obleute, hegeringe, partner, faq,
 * downloads, kreisjaegermeister).
 *
 * WICHTIG (Auftrag Phase 4 Punkt 6 "differenziert updaten/upserten statt
 * blind alles loeschen"): admin.js schickt bei JEDER Aktion (Hinzufuegen/
 * Bearbeiten/Loeschen/Umsortieren) das VOLLSTAENDIGE Modul erneut (siehe
 * doSave(def.file, S.data, ...) - identisch zum bisherigen Git-Gateway-PUT,
 * das immer die ganze Datei ersetzte). Ein blindes "alles loeschen, aus dem
 * Payload neu anlegen" wuerde bei jedem Speichern NEUE Auto-Increment-IDs
 * fuer eigentlich unveraenderte Eintraege erzeugen - das bricht nichts
 * FUNKTIONAL (diese Module haben keine extern verlinkten IDs, anders als
 * z.B. Partner/Aktuelles), erzeugt aber unnoetiges DB-Rauschen (Timestamps,
 * IDs) und waere fuer eingebettete Bilder/Downloads riskant. Daher wird hier
 * durchgaengig per "_id" upgeserted: die Read-API (ContentController) gibt
 * dieses Feld inzwischen mit aus (harmlose Zusatz-Erweiterung, siehe
 * Kommentare dort) - admin.js reicht es unveraendert durch (siehe
 * personSave()/hegeringSave()/... - diese schreiben nur bekannte Felder auf
 * dasselbe Objekt, unbekannte Felder wie "_id" bleiben unangetastet).
 * Ein Item OHNE "_id" ist neu angelegt (siehe personAdd()/hegeringAdd()/...)
 * und wird als neue Zeile erstellt. Ein bestehendes Item, das im Payload
 * NICHT mehr vorkommt, wurde geloescht (siehe personDelete() etc.) und wird
 * entsprechend aus der DB entfernt.
 *
 * Aktuelles (Beitraege) und Partner haben stattdessen bereits eigene,
 * extern bedeutsame Schluessel (legacy_index bzw. external_id/"id") -
 * dieselbe Upsert-Logik nutzt dort konsequent DIESE Schluessel statt "_id",
 * damit z.B. aktuelles/beitrag.html?i=42 (Auftrag Punkt 8) oder
 * partner/detail.html?id=... (Auftrag Punkt 9) niemals bricht.
 */
class AdminListController extends Controller
{
    public const SECTION_AKTUELLES = 'aktuelles';

    public const SECTION_TERMINE = 'termine';

    public const SECTION_VORSTAND = 'vorstand';

    public const SECTION_OBLEUTE = 'obleute';

    public const SECTION_HEGERINGE = 'hegeringe';

    public const SECTION_PARTNER = 'partner';

    public const SECTION_FAQ = 'faq';

    public const SECTION_DOWNLOADS = 'downloads';

    public const SECTION_KJM = 'kreisjaegermeister';

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
     * Robuste Boolean-Normalisierung - 1:1 identisch zu
     * ImportContent::toBool(), damit Import und Admin-Speicherung dieselbe
     * Interpretation eines Werts liefern.
     */
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
     * Deutsches Datumsformat "TT.MM.JJJJ" -> ISO, 1:1 identisch zu
     * ImportContent::parseDatum() - admin.js' isoToDatum() liefert genau
     * dieses Format (siehe fDate()/t.datum=isoToDatum(...) in admin.js), da
     * die zugrundeliegende JSON-Struktur unveraendert dieses Format nutzt.
     */
    private function parseDatum(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d.m.Y', trim($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function embeddedId(mixed $item): ?int
    {
        return is_array($item) && isset($item['_id']) && is_numeric($item['_id']) ? (int) $item['_id'] : null;
    }

    /**
     * Ersetzt die eingebetteten downloads[]/galerie[] eines einzelnen
     * Owners (Beitrag/Page) komplett - anders als bei den Top-Level-Listen
     * oben ist hier "alles loeschen, neu anlegen" unbedenklich (Auftrag
     * Punkt 6: "sofern dadurch keine IDs/Beziehungen kaputtgehen koennten"):
     * diese Zeilen haben keine eigene, dem Admin bekannte oder extern
     * verlinkte ID, und nichts in der DB referenziert sie ihrerseits (reine
     * Blattdaten) - identisch zum bisherigen Verhalten, bei dem die ganze
     * Datei inkl. dieser Arrays ersetzt wurde.
     */
    private function replaceEmbeddedDownloads(Model $owner, mixed $items): void
    {
        Download::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->delete();
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
            Download::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'titel' => $titel !== '' ? $titel : $pfad,
                'pfad' => $pfad,
                'vorschau' => (string) ($item['vorschau'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    private function replaceEmbeddedGalerie(Model $owner, mixed $items): void
    {
        GalerieBild::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->delete();
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
            GalerieBild::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'pfad' => $pfad,
                'titel' => (string) ($item['titel'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Aktuelles
    // ------------------------------------------------------------------

    /**
     * Spiegelbild von ImportContent::importBeitraege() - siehe Auftrag Punkt
     * 8 ("legacy_index unbedingt erhalten, Edit darf beitrag.html?i=42 nicht
     * kaputt machen"). Zuordnung bestehender Zeilen NICHT ueber die
     * Array-Position (die sich durch Hinzufuegen/Loeschen jederzeit
     * verschiebt), sondern ueber das von der Read-API mitgelieferte
     * "legacy_index"-Feld (siehe ContentController::aktuelles()) - ein Item
     * OHNE dieses Feld ist ein neuer, im Admin gerade erst angelegter
     * Beitrag und bekommt den naechsten freien legacy_index (fortlaufend
     * ans Ende, NIE eine Luecke fuellen oder einen bestehenden Wert
     * neu vergeben).
     */
    public function aktuelles(Request $request): JsonResponse
    {
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        $items = is_array($data['beitraege'] ?? null) ? $data['beitraege'] : [];
        $einstellungen = is_array($data['einstellungen'] ?? null) ? $data['einstellungen'] : [];

        try {
            return DB::transaction(function () use ($items, $einstellungen, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_AKTUELLES, $expected);

                foreach (['hauptseite_anzahl', 'hauptseite_modus'] as $key) {
                    if (array_key_exists($key, $einstellungen) && ! is_array($einstellungen[$key])) {
                        Setting::updateOrCreate(
                            ['gruppe' => 'aktuelles', 'key' => $key],
                            ['value' => (string) $einstellungen[$key]]
                        );
                    }
                }

                // Kategorien (Auftrag: window.aktuellesKategorieAdd() haengt
                // dauerhaft neue Namen an diese Liste an) - Zuordnung ueber
                // den NAMEN (kategorie_id ist reine interne Verknuepfung,
                // admin.js kennt/sendet nur den Namen je Beitrag).
                $kategorieNamen = is_array($einstellungen['kategorien'] ?? null) ? $einstellungen['kategorien'] : [];
                BeitragKategorie::where('typ', 'aktuelles')->delete();
                $kategorieMap = [];
                foreach (array_values($kategorieNamen) as $i => $name) {
                    if (! is_string($name) || trim($name) === '') {
                        continue;
                    }
                    $kat = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => $name, 'sortierung' => $i]);
                    $kategorieMap[$name] = $kat->id;
                }
                $ensureKategorie = function (string $name) use (&$kategorieMap) {
                    if ($name === '') {
                        return null;
                    }
                    if (! isset($kategorieMap[$name])) {
                        $kat = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => $name, 'sortierung' => count($kategorieMap)]);
                        $kategorieMap[$name] = $kat->id;
                    }

                    return $kategorieMap[$name];
                };

                $existingByLegacyIndex = Beitrag::where('typ', 'aktuelles')->get()->keyBy('legacy_index');
                $nextLegacyIndex = ((int) Beitrag::where('typ', 'aktuelles')->max('legacy_index')) + 1;
                $keepIds = [];

                foreach (array_values($items) as $position => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $legacyIndex = isset($item['legacy_index']) && is_numeric($item['legacy_index']) ? (int) $item['legacy_index'] : null;
                    $existing = $legacyIndex !== null ? $existingByLegacyIndex->get($legacyIndex) : null;

                    $kategorieName = trim((string) ($item['kategorie'] ?? ''));
                    $fields = [
                        'typ' => 'aktuelles',
                        'titel' => (string) ($item['titel'] ?? ''),
                        'datum' => $this->parseDatum($item['datum'] ?? null),
                        'jahr' => isset($item['jahr']) && $item['jahr'] !== '' ? (int) $item['jahr'] : null,
                        'kategorie_id' => $kategorieName !== '' ? $ensureKategorie($kategorieName) : null,
                        'bild' => (string) ($item['bild'] ?? '') ?: null,
                        'text' => $item['text'] ?? null,
                        'link' => (string) ($item['link'] ?? '') ?: null,
                        'galerie_titel' => (string) ($item['galerie_titel'] ?? '') ?: null,
                        'archiviert' => $this->toBool($item['archiviert'] ?? null, false),
                        'sortierung' => $position,
                    ];

                    if ($existing) {
                        $existing->update($fields);
                        $beitrag = $existing;
                    } else {
                        $titel = $fields['titel'] !== '' ? $fields['titel'] : ('beitrag-'.$nextLegacyIndex);
                        $slug = $titel;
                        $baseSlug = \Illuminate\Support\Str::slug($slug) ?: 'beitrag';
                        $candidate = $baseSlug;
                        $n = 2;
                        while (Beitrag::where('typ', 'aktuelles')->where('slug', $candidate)->exists()) {
                            $candidate = $baseSlug.'-'.$n;
                            $n++;
                        }
                        $beitrag = Beitrag::create($fields + ['slug' => $candidate, 'legacy_index' => $nextLegacyIndex]);
                        $nextLegacyIndex++;
                    }

                    $this->replaceEmbeddedDownloads($beitrag, $item['downloads'] ?? null);
                    $this->replaceEmbeddedGalerie($beitrag, $item['galerie'] ?? null);
                    $keepIds[] = $beitrag->id;
                }

                Beitrag::where('typ', 'aktuelles')->whereNotIn('id', $keepIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_AKTUELLES));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Termine
    // ------------------------------------------------------------------

    /** Spiegelbild von ImportContent::importTermine(). Zuordnung ueber "_id" (siehe Klassenkommentar). */
    public function termine(Request $request): JsonResponse
    {
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        $items = is_array($data['termine'] ?? null) ? $data['termine'] : [];
        $einstellungen = is_array($data['einstellungen'] ?? null) ? $data['einstellungen'] : [];

        try {
            return DB::transaction(function () use ($items, $einstellungen, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_TERMINE, $expected);

                foreach (['ueberschrift', 'einleitung'] as $key) {
                    if (array_key_exists($key, $einstellungen) && ! is_array($einstellungen[$key])) {
                        Setting::updateOrCreate(
                            ['gruppe' => 'termine', 'key' => $key],
                            ['value' => (string) $einstellungen[$key]]
                        );
                    }
                }

                $existingIds = Termin::pluck('id')->all();
                $keepIds = [];
                foreach (array_values($items) as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $fields = [
                        // parseDatum() liefert null statt eines geratenen
                        // Datums, wenn der Wert leer/unparsbar ist - anders
                        // als beim Erstimport (dort now()-Fallback fuer eine
                        // Pflichtspalte) darf ein Admin-Speichern ein
                        // absichtlich leeres Datumsfeld nicht stillschweigend
                        // auf "heute" setzen; ein wirklich leeres Datum ist
                        // im echten Datenbestand ohnehin nicht vorgesehen
                        // (Formularfeld <input type="date">).
                        'datum' => $this->parseDatum($item['datum'] ?? null),
                        'uhrzeit' => (string) ($item['uhrzeit'] ?? '') ?: null,
                        'veranstaltung' => (string) ($item['veranstaltung'] ?? ''),
                        'strasse' => (string) ($item['strasse'] ?? '') ?: null,
                        'plz' => (string) ($item['plz'] ?? '') ?: null,
                        'ort' => (string) ($item['ort'] ?? '') ?: null,
                        'revier' => (string) ($item['revier'] ?? '') ?: null,
                        'kategorie' => (string) ($item['kategorie'] ?? '') ?: null,
                        'archiviert' => $this->toBool($item['archiviert'] ?? null, false),
                    ];
                    $id = $this->embeddedId($item);
                    if ($id !== null && in_array($id, $existingIds, true)) {
                        Termin::where('id', $id)->update($fields);
                        $keepIds[] = $id;
                    } else {
                        $keepIds[] = Termin::create($fields)->id;
                    }
                }
                Termin::whereNotIn('id', $keepIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_TERMINE));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Vorstand / Obleute (Person, "gremium")
    // ------------------------------------------------------------------

    private function savePersonenGremium(string $gremium, array $items): void
    {
        $existingIds = Person::where('gremium', $gremium)->pluck('id')->all();
        $keepIds = [];
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $fields = [
                'gremium' => $gremium,
                'rolle' => (string) ($item['rolle'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'email' => (string) ($item['email'] ?? '') ?: null,
                'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                'bild' => (string) ($item['bild'] ?? '') ?: null,
                'sortierung' => $i,
            ];
            $id = $this->embeddedId($item);
            if ($id !== null && in_array($id, $existingIds, true)) {
                Person::where('id', $id)->update($fields);
                $keepIds[] = $id;
            } else {
                $keepIds[] = Person::create($fields)->id;
            }
        }
        Person::where('gremium', $gremium)->whereNotIn('id', $keepIds)->delete();
    }

    public function vorstand(Request $request): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        $items = is_array($request->input('data.mitglieder')) ? $request->input('data.mitglieder') : [];

        try {
            return DB::transaction(function () use ($items, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_VORSTAND, $expected);
                $this->savePersonenGremium('vorstand', $items);

                return $this->ok(ContentVersioning::bump(self::SECTION_VORSTAND));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    public function obleute(Request $request): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        $items = is_array($request->input('data.obleute')) ? $request->input('data.obleute') : [];

        try {
            return DB::transaction(function () use ($items, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_OBLEUTE, $expected);
                $this->savePersonenGremium('obmann', $items);

                return $this->ok(ContentVersioning::bump(self::SECTION_OBLEUTE));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Hegeringe
    // ------------------------------------------------------------------

    public function hegeringe(Request $request): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        $items = is_array($request->input('data.hegeringe')) ? $request->input('data.hegeringe') : [];

        try {
            return DB::transaction(function () use ($items, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_HEGERINGE, $expected);

                $existingIds = Hegering::pluck('id')->all();
                $keepIds = [];
                foreach (array_values($items) as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $fields = [
                        'nummer' => (string) ($item['nummer'] ?? ''),
                        'name' => (string) ($item['name'] ?? ''),
                        'obmann' => (string) ($item['obmann'] ?? '') ?: null,
                        'gemeinden' => (string) ($item['gemeinden'] ?? '') ?: null,
                        'email' => (string) ($item['email'] ?? '') ?: null,
                        'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                        'geschlecht' => (string) ($item['geschlecht'] ?? '') ?: null,
                        'sortierung' => $i,
                    ];
                    $id = $this->embeddedId($item);
                    if ($id !== null && in_array($id, $existingIds, true)) {
                        Hegering::where('id', $id)->update($fields);
                        $keepIds[] = $id;
                    } else {
                        $keepIds[] = Hegering::create($fields)->id;
                    }
                }
                Hegering::whereNotIn('id', $keepIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_HEGERINGE));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Partner
    // ------------------------------------------------------------------

    /**
     * Spiegelbild von ImportContent::importPartner(). Zuordnung ueber "_id"
     * (die Read-API liefert diese seit Phase 4 zusaetzlich zum externen
     * "id"/external_id, siehe ContentController::partner()) - ein neu im
     * Admin angelegter Partner (window.partnerAdd(), admin.js) traegt
     * bereits ein clientseitig generiertes "id":"pn-"+Date.now(), aber noch
     * kein "_id" und wird daher hier als neue Zeile angelegt, deren
     * external_id auf genau diesen Wert gesetzt wird (Auftrag Punkt 9:
     * "external_id muss erhalten bleiben" - gilt hier von der ersten
     * Speicherung an).
     *
     * "rahmenvertrag"/"vorteile" werden im bestehenden Admin-Formular als
     * FREIER TEXT bearbeitet (fTextarea, siehe renderPartner()/personEdit-
     * Aequivalent in admin.js) statt als echtes Boolean bzw. eine
     * strukturierte Liste - das spiegelt exakt den heutigen, bereits vor
     * Phase 4 bestehenden Realzustand wider (ImportContent::importPartner()-
     * Klassenkommentar: "im REALEN Datenbestand bei allen 14 Partnern
     * durchgaengig '' statt Boolean/Array"). Diese Diskrepanz zwischen
     * Admin-UI (Freitext) und DB-Schema (boolean/eigene Tabelle) wird hier
     * NICHT angetastet (keine ungefragte UI-Aenderung) - siehe
     * Abschlussbericht "offene Punkte" fuer eine dokumentierte, bewusst
     * unveraenderte Uebernahme: "rahmenvertrag" per toBool() (leer -> false,
     * wie bisher), "vorteile" zeilenweise (eine Zeile Freitext = ein
     * Vorteil) in partner_vorteile gespeichert, damit ein tatsaechlich
     * eingegebener Text nicht kommentarlos verworfen wird.
     */
    public function partner(Request $request): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        $items = is_array($request->input('data.partner')) ? $request->input('data.partner') : [];

        try {
            return DB::transaction(function () use ($items, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_PARTNER, $expected);

                $existingIds = Partner::pluck('id')->all();
                $keepIds = [];
                foreach (array_values($items) as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $rahmenvertragRoh = $item['rahmenvertrag'] ?? null;
                    $fields = [
                        'name' => (string) ($item['name'] ?? ''),
                        'logo' => (string) ($item['logo'] ?? '') ?: null,
                        'kurzbeschreibung' => (string) ($item['kurzbeschreibung'] ?? '') ?: null,
                        'beschreibung' => $item['beschreibung'] ?? null,
                        'ansprechpartner' => (string) ($item['ansprechpartner'] ?? '') ?: null,
                        'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                        'email' => (string) ($item['email'] ?? '') ?: null,
                        'website' => (string) ($item['website'] ?? '') ?: null,
                        'rahmenvertrag' => is_string($rahmenvertragRoh) ? $this->toBool($rahmenvertragRoh, false) : $this->toBool($rahmenvertragRoh, false),
                        'weitere_infos' => (string) ($item['weitere_infos'] ?? '') ?: null,
                        'aktiv' => $this->toBool($item['aktiv'] ?? null, true),
                        'sortierung' => $i,
                    ];

                    $id = $this->embeddedId($item);
                    if ($id !== null && in_array($id, $existingIds, true)) {
                        Partner::where('id', $id)->update($fields);
                        $partner = Partner::find($id);
                    } else {
                        $externalId = isset($item['id']) && is_string($item['id']) && trim($item['id']) !== ''
                            ? trim($item['id'])
                            : ('pn-'.now()->valueOf());
                        $partner = Partner::create($fields + ['external_id' => $externalId]);
                    }

                    // "vorteile": siehe Methodenkommentar - Freitext, eine
                    // Zeile je Vorteil.
                    PartnerVorteil::where('partner_id', $partner->id)->delete();
                    $vorteileRoh = $item['vorteile'] ?? null;
                    if (is_string($vorteileRoh) && trim($vorteileRoh) !== '') {
                        $zeilen = preg_split('/\r\n|\r|\n/', $vorteileRoh) ?: [];
                        $j = 0;
                        foreach ($zeilen as $zeile) {
                            $zeile = trim($zeile);
                            if ($zeile === '') {
                                continue;
                            }
                            PartnerVorteil::create(['partner_id' => $partner->id, 'text' => $zeile, 'sortierung' => $j]);
                            $j++;
                        }
                    } elseif (is_array($vorteileRoh)) {
                        foreach (array_values($vorteileRoh) as $j => $text) {
                            if (! is_string($text) || trim($text) === '') {
                                continue;
                            }
                            PartnerVorteil::create(['partner_id' => $partner->id, 'text' => $text, 'sortierung' => $j]);
                        }
                    }

                    $keepIds[] = $partner->id;
                }
                Partner::whereNotIn('id', $keepIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_PARTNER));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // FAQ
    // ------------------------------------------------------------------

    public function faq(Request $request): JsonResponse
    {
        $expectedRaw = $request->input('expected_version');
        $expected = is_numeric($expectedRaw) ? (int) $expectedRaw : null;
        $kategorien = is_array($request->input('data.kategorien')) ? $request->input('data.kategorien') : [];

        try {
            return DB::transaction(function () use ($kategorien, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_FAQ, $expected);

                $existingKatIds = FaqKategorie::pluck('id')->all();
                $keepKatIds = [];
                foreach (array_values($kategorien) as $i => $kat) {
                    if (! is_array($kat)) {
                        continue;
                    }
                    $katFields = ['titel' => (string) ($kat['titel'] ?? ''), 'sortierung' => $i];
                    $katId = $this->embeddedId($kat);
                    if ($katId !== null && in_array($katId, $existingKatIds, true)) {
                        FaqKategorie::where('id', $katId)->update($katFields);
                    } else {
                        $katId = FaqKategorie::create($katFields)->id;
                    }
                    $keepKatIds[] = $katId;

                    $fragen = is_array($kat['fragen'] ?? null) ? $kat['fragen'] : [];
                    $existingFrageIds = FaqFrage::where('faq_kategorie_id', $katId)->pluck('id')->all();
                    $keepFrageIds = [];
                    foreach (array_values($fragen) as $j => $f) {
                        if (! is_array($f)) {
                            continue;
                        }
                        $frage = trim((string) ($f['frage'] ?? ''));
                        if ($frage === '') {
                            continue;
                        }
                        $fFields = ['faq_kategorie_id' => $katId, 'frage' => $frage, 'antwort' => $f['antwort'] ?? null, 'sortierung' => $j];
                        $fId = $this->embeddedId($f);
                        if ($fId !== null && in_array($fId, $existingFrageIds, true)) {
                            FaqFrage::where('id', $fId)->update($fFields);
                            $keepFrageIds[] = $fId;
                        } else {
                            $keepFrageIds[] = FaqFrage::create($fFields)->id;
                        }
                    }
                    FaqFrage::where('faq_kategorie_id', $katId)->whereNotIn('id', $keepFrageIds)->delete();
                }
                // cascadeOnDelete() auf faq_fragen.faq_kategorie_id raeumt
                // die Fragen einer geloeschten Kategorie automatisch mit auf.
                FaqKategorie::whereNotIn('id', $keepKatIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_FAQ));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Downloads (zentrale Bibliothek)
    // ------------------------------------------------------------------

    public function downloads(Request $request): JsonResponse
    {
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);
        $kategorien = is_array($data['kategorien'] ?? null) ? $data['kategorien'] : [];

        try {
            return DB::transaction(function () use ($data, $kategorien, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_DOWNLOADS, $expected);

                foreach (['titel', 'intro'] as $key) {
                    if (array_key_exists($key, $data) && ! is_array($data[$key])) {
                        Setting::updateOrCreate(
                            ['gruppe' => 'downloads', 'key' => $key],
                            ['value' => (string) $data[$key]]
                        );
                    }
                }

                $existingKatIds = DownloadKategorie::pluck('id')->all();
                $keepKatIds = [];
                foreach (array_values($kategorien) as $i => $kat) {
                    if (! is_array($kat)) {
                        continue;
                    }
                    $katFields = ['titel' => (string) ($kat['titel'] ?? ''), 'sortierung' => $i];
                    $katId = $this->embeddedId($kat);
                    if ($katId !== null && in_array($katId, $existingKatIds, true)) {
                        DownloadKategorie::where('id', $katId)->update($katFields);
                    } else {
                        $katId = DownloadKategorie::create($katFields)->id;
                    }
                    $keepKatIds[] = $katId;

                    $downloads = is_array($kat['downloads'] ?? null) ? $kat['downloads'] : [];
                    $existingDlIds = Download::where('kategorie_id', $katId)->pluck('id')->all();
                    $keepDlIds = [];
                    foreach (array_values($downloads) as $j => $d) {
                        if (! is_array($d)) {
                            continue;
                        }
                        $url = trim((string) ($d['url'] ?? ''));
                        $name = trim((string) ($d['name'] ?? ''));
                        if ($url === '' && $name === '') {
                            continue;
                        }
                        $dFields = [
                            'kategorie_id' => $katId,
                            'owner_type' => null,
                            'owner_id' => null,
                            'titel' => $name !== '' ? $name : $url,
                            'beschreibung' => (string) ($d['beschreibung'] ?? '') ?: null,
                            'typ' => (string) ($d['typ'] ?? '') ?: null,
                            'pfad' => $url,
                            'sortierung' => $j,
                        ];
                        $dId = $this->embeddedId($d);
                        if ($dId !== null && in_array($dId, $existingDlIds, true)) {
                            Download::where('id', $dId)->update($dFields);
                            $keepDlIds[] = $dId;
                        } else {
                            $keepDlIds[] = Download::create($dFields)->id;
                        }
                    }
                    Download::where('kategorie_id', $katId)->whereNotIn('id', $keepDlIds)->delete();
                }
                // cascadeOnDelete() auf downloads.kategorie_id raeumt die
                // Downloads einer geloeschten Kategorie automatisch mit auf.
                DownloadKategorie::whereNotIn('id', $keepKatIds)->delete();

                return $this->ok(ContentVersioning::bump(self::SECTION_DOWNLOADS));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }

    // ------------------------------------------------------------------
    // Kreisjaegermeister (Singleton-Page, section=kreisjaegermeister)
    // ------------------------------------------------------------------

    public function kreisjaegermeister(Request $request): JsonResponse
    {
        ['data' => $data, 'expected_version' => $expected] = $this->bodyAndVersion($request);

        try {
            return DB::transaction(function () use ($data, $expected) {
                ContentVersioning::assertNotStale(self::SECTION_KJM, $expected);

                $page = Page::firstOrNew(['section' => 'kreisjaegermeister', 'slug' => 'kreisjaegermeister']);
                $isNew = ! $page->exists;
                $page->fill([
                    'section' => 'kreisjaegermeister',
                    'parent_id' => null,
                    'slug' => 'kreisjaegermeister',
                    'titel' => $page->titel ?? 'Kreisjägermeister',
                    'kontakt_name' => (string) ($data['name'] ?? '') ?: null,
                    'kontakt_email' => (string) ($data['email'] ?? '') ?: null,
                    'kontakt_telefon' => (string) ($data['telefon'] ?? '') ?: null,
                    'bild' => (string) ($data['bild'] ?? '') ?: null,
                    'inhalt' => (string) ($data['aufgaben'] ?? '') ?: null,
                    'grusswort' => (string) ($data['grußwort'] ?? $data['gruszwort'] ?? '') ?: null,
                    'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
                    'in_navigation' => $isNew ? true : $page->in_navigation,
                    'veroeffentlicht' => $isNew ? true : $page->veroeffentlicht,
                    'sortierung' => $isNew ? 0 : $page->sortierung,
                ]);
                $page->save();

                $this->replaceEmbeddedDownloads($page, $data['downloads'] ?? null);
                $this->replaceEmbeddedGalerie($page, $data['galerie'] ?? null);

                return $this->ok(ContentVersioning::bump(self::SECTION_KJM));
            });
        } catch (ContentVersionConflictException $e) {
            return $this->conflictResponse($e);
        }
    }
}
