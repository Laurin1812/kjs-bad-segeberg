<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\Setting;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\DownloadKategorieUpdater;
use App\Support\DownloadUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7E (Admin-Modul "Downloads", zentrale
 * Download-Bibliothek).
 *
 * Viertes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (siehe
 * Phase 7A: Auth/Zugriffsschutz/Admin-Shell; Phase 7B: "Inhalte/Seiten";
 * Phase 7C: "Aktuelles"; Phase 7D: "Termine" als naechstes Vorbild) -
 * bildet ausschliesslich die ZENTRALE Download-Bibliothek ab (App\Models\
 * DownloadKategorie + App\Models\Download mit gesetzter "kategorie_id" UND
 * owner_type/owner_id = NULL).
 *
 * ABGRENZUNG (Analyse-Auftrag Phase 7E, Punkt D "Scope-Schutz wegen
 * geteilter downloads-Tabelle"): die Tabelle "downloads" wird AUSSERDEM von
 * den seiteneigenen "Dokumente & Downloads"-Bloecken benutzt (owner_type/
 * owner_id gesetzt, kategorie_id NULL - siehe App\Support\PageUpdater /
 * App\Support\BeitragUpdater::replaceEmbeddedDownloads()). Dieses Modul
 * fasst diese Zeilen NIRGENDS an: index() laedt Downloads ausschliesslich
 * ueber DownloadKategorie::downloads() (die Relation filtert bereits
 * implizit auf "kategorie_id = diese Kategorie", was fuer eingebettete
 * Downloads nie zutrifft, da deren kategorie_id immer NULL ist), und jede
 * Einzelaktion auf einem Download prueft zusaetzlich explizit
 * ensureBibliothekseintrag() (Verteidigung gegen einen manipulierten
 * Request, der versucht, ueber diese Routen einen embedded Download zu
 * bearbeiten/loeschen - siehe dortiger Methodenkommentar). PageUpdater,
 * BeitragUpdater, AdminMediaController und MediaUploadService werden von
 * Phase 7E nicht veraendert.
 *
 * SCHREIBLOGIK (Analyse-Auftrag Punkt A "keine doppelte Fachlogik"): nutzt
 * fuer die eigentliche Feldzuweisung ausschliesslich App\Support\
 * DownloadKategorieUpdater / App\Support\DownloadUpdater - dieselben
 * Klassen, die (seit Phase 7E) auch Api\Admin\AdminListController::
 * downloads() fuer denselben fachlichen Teil nutzen (siehe dortiger
 * Kommentar).
 *
 * VERSIONIERUNG: admin.js' Downloads-Schreibweg versioniert NICHT pro
 * Kategorie/Download, sondern fuer die GESAMTE Bibliothek + die Titel/
 * Einleitungstext-Einstellungen der oeffentlichen Seite ("downloads" ist
 * ein einziger ContentVersioning-Schluessel, siehe AdminListController::
 * SECTION_DOWNLOADS). Dieses Modul nutzt bewusst DENSELBEN Schluessel: ein
 * Konflikt hier bedeutet "die Downloads-Daten wurden zwischenzeitlich
 * irgendwo anders (JSON-API ODER dieser Blade-Admin) veraendert".
 *
 * KATEGORIE LOESCHEN (Nutzer-Entscheidung Phase-7E-Analyse, bewusst ANDERS
 * als Admin\AktuellesController::kategorieLoeschen()): eine Download-
 * Kategorie ist ein echter Container, kein geteiltes Tag - Loeschen ist
 * IMMER erlaubt und nimmt alle enthaltenen Bibliotheks-Downloads per
 * DB-cascadeOnDelete() mit (nur clientseitiger confirm()-Dialog, keine
 * serverseitige "in Verwendung"-Sperre wie bei Aktuelles-Kategorien) - 1:1
 * admin.js' dlKatDelete().
 *
 * SORTIERUNG: keine neue Sortier-UI (kein Drag&Drop, keine Auf/Ab-Buttons -
 * admin.js hat dafuer ebenfalls keine UI). Neue Kategorien/Downloads werden
 * ans Ende ihrer jeweiligen Liste angehaengt, bestehende "sortierung"-Werte
 * bleiben beim Bearbeiten unveraendert.
 *
 * TYP-DROPDOWN: siehe typOptionen() - Standardwerte + tatsaechlich in der
 * Bibliothek verwendete DB-Werte + aktueller Wert, keine eigene
 * Typ-Verwaltung (analog zu TermineController::kategorieOptionen()).
 *
 * "vorschau": wird von diesem Modul bewusst nirgends gelesen, angezeigt
 * oder geschrieben - siehe DownloadUpdater-Klassenkommentar.
 */
class DownloadsController extends Controller
{
    private const SECTION = 'downloads';

    /**
     * 1:1 aus admin.js' renderDownloads()-Typ-<select> uebernommen (siehe
     * Klassenkommentar "Typ-Dropdown").
     *
     * @var list<string>
     */
    private const STANDARD_TYPEN = ['PDF', 'Word', 'Excel', 'ZIP', 'Sonstiges'];

    public function index(): View
    {
        $einstellungen = Setting::where('gruppe', self::SECTION)->pluck('value', 'key');

        $kategorien = DownloadKategorie::with(['downloads' => function ($q) {
            // Bewusst OHNE Sichtbarkeits-/Nicht-Leer-Filter (anders als die
            // oeffentliche Seite) - der Admin muss auch leere Kategorien
            // sehen und befuellen koennen (siehe Klassenkommentar
            // "Abgrenzung"). "owner_type IS NULL" ist hier eigentlich
            // redundant (kategorie_id ist bei eingebetteten Downloads immer
            // NULL, die Relation traegt also ohnehin nie welche), wird aber
            // als Defense-in-depth trotzdem explizit mitgefuehrt.
            $q->whereNull('owner_type')->orderBy('sortierung');
        }])
            ->orderBy('sortierung')
            ->get();

        return view('admin.downloads.index', [
            'kategorien' => $kategorien,
            'titel' => (string) ($einstellungen['titel'] ?? ''),
            'intro' => (string) ($einstellungen['intro'] ?? ''),
            // Ein einziges gemeinsames Typ-Optionen-Set fuer ALLE Downloads
            // dieser Seite (anders als TermineController::kategorieOptionen(),
            // das pro Formular fuer genau einen Termin aufgerufen wird - hier
            // zeigt eine einzige Liste alle Downloads gleichzeitig). Das ist
            // unproblematisch: jeder hier angezeigte Download ist bereits
            // gespeichert, sein "typ" steckt also zwangslaeufig schon in
            // "verwendet" (siehe typOptionen()-Query) - kein Wert kann aus
            // dem Dropdown fallen.
            'typOptionen' => $this->typOptionen(null),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    /**
     * "➕ Kategorie hinzufügen" - 1:1 aus admin.js' window.dlKatAdd()
     * uebernommen (dort: sofort eine neue, leere Kategorie "Neue Kategorie"
     * anlegen, die dann inline umbenannt wird - kein Namens-Eingabefeld
     * vorab wie bei Aktuelles' "+ Neu").
     */
    public function kategorieAnlegen(Request $request): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                $kategorie = new DownloadKategorie;
                DownloadKategorieUpdater::applyFields($kategorie, [
                    'titel' => 'Neue Kategorie',
                    'sortierung' => DownloadKategorie::count(),
                ]);

                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Kategorie angelegt.');
    }

    /**
     * Kategorie-Titel umbenennen (im Alt-Admin Teil desselben
     * Gesamt-Speicherns, hier eine eigene kleine Aktion - siehe
     * Klassenkommentar "Schreiblogik").
     */
    public function kategorieUmbenennen(Request $request, DownloadKategorie $kategorie): RedirectResponse
    {
        $request->validate([
            'titel' => ['required', 'string', 'max:190'],
        ], [
            'titel.required' => 'Bitte einen Kategorie-Titel angeben.',
        ]);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($request, $kategorie, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                DownloadKategorieUpdater::applyFields($kategorie, [
                    'titel' => $request->input('titel'),
                    'sortierung' => $kategorie->sortierung,
                ]);
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Kategorie gespeichert.');
    }

    /**
     * "Kategorie löschen" - 1:1 aus admin.js' window.dlKatDelete()
     * uebernommen: loescht IMMER, auch mit enthaltenen Downloads, ohne
     * serverseitige Verwendungspruefung (siehe Klassenkommentar "Kategorie
     * löschen"). DB-cascadeOnDelete() auf downloads.kategorie_id raeumt die
     * enthaltenen Bibliotheks-Downloads automatisch mit auf - seiteneigene
     * Downloads (owner_type gesetzt, kategorie_id NULL) sind von dieser
     * Cascade grundsaetzlich nie betroffen.
     */
    public function kategorieLoeschen(Request $request, DownloadKategorie $kategorie): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($kategorie, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                $kategorie->delete();
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Kategorie und enthaltene Downloads gelöscht.');
    }

    /**
     * "+ Download hinzufügen" innerhalb einer Kategorie - 1:1 aus admin.js'
     * window.dlAdd() uebernommen (dort: sofort eine neue, leere Download-
     * Zeile anlegen). "pfad" ist im neuen Blade-Admin ein Pflichtfeld (siehe
     * Klassenkommentar Phase-7E-Analyse Punkt "Zusätzliche Festlegung") -
     * admin.js selbst erzwingt dort nichts, blendet aber ohnehin sofort ein
     * Formularfeld dafuer ein, das der Redakteur ausfuellen soll.
     */
    public function downloadAnlegen(Request $request, DownloadKategorie $kategorie): RedirectResponse
    {
        // Beim Neuanlegen gibt es noch keinen bestehenden Datensatz, auf den
        // "pfad" zurueckfallen koennte - hier bleibt das Feld deshalb
        // unbedingt erforderlich (siehe validateData()-Kommentar).
        $validated = $this->validateData($request, pfadErforderlich: true);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($validated, $kategorie, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                $download = new Download(['kategorie_id' => $kategorie->id, 'owner_type' => null, 'owner_id' => null]);
                DownloadUpdater::applyFields($download, array_merge($validated, [
                    'sortierung' => Download::where('kategorie_id', $kategorie->id)->count(),
                ]));

                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Download angelegt.');
    }

    /**
     * Preservation (siehe Klassenkommentar Phase-7E-Analyse Punkt C): erst
     * die aktuellen Werte laden, dann nur die tatsaechlich gesendeten Felder
     * ueberschreiben - identisch zu TermineController::update().
     */
    public function downloadAktualisieren(Request $request, Download $download): RedirectResponse
    {
        $this->ensureBibliothekseintrag($download);

        // Beim Bearbeiten DARF "pfad" fehlen (Preservation - siehe
        // validateData()-Kommentar): das echte Formular sendet es zwar immer
        // vorbelegt mit, ein Teil-Request, der es trotzdem weglaesst, darf
        // den bestehenden Pfad aber nicht verlieren. Wird "pfad" gesendet,
        // muss es weiterhin nicht-leer sein - ein Download darf nie aktiv
        // auf einen leeren Pfad gesetzt werden.
        $validated = $this->validateData($request, pfadErforderlich: false);
        $data = array_merge($this->currentFieldValues($download), $validated);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($download, $data, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                DownloadUpdater::applyFields($download, $data);
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Download gespeichert.');
    }

    public function downloadLoeschen(Request $request, Download $download): RedirectResponse
    {
        $this->ensureBibliothekseintrag($download);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($download, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                $download->delete();
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Download gelöscht.');
    }

    /**
     * Titel/Einleitungstext der öffentlichen Downloads-Seite - 1:1 aus
     * admin.js' Downloads-Formularkopf uebernommen, hier als eigene Route
     * (kein einzelner Kategorie-/Download-Datensatz betroffen). Nutzt
     * denselben ContentVersioning-Schluessel wie die Bibliothek selbst
     * (siehe Klassenkommentar "Versionierung").
     */
    public function einstellungenSpeichern(Request $request): RedirectResponse
    {
        $request->validate([
            'titel' => ['nullable', 'string', 'max:190'],
            'intro' => ['nullable', 'string'],
        ]);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($request, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                Setting::updateOrCreate(
                    ['gruppe' => self::SECTION, 'key' => 'titel'],
                    ['value' => (string) $request->input('titel', '')]
                );
                Setting::updateOrCreate(
                    ['gruppe' => self::SECTION, 'key' => 'intro'],
                    ['value' => (string) $request->input('intro', '')]
                );

                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.downloads.index')->with('downloads_fehler', 'Die Downloads-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.downloads.index')->with('downloads_status', 'Titel & Einleitungstext gespeichert.');
    }

    /**
     * "pfad" (Nutzer-Entscheidung Phase-7E-Analyse): admin.js erzwingt hier
     * zwar nichts, ein Download ohne jeden Pfad/URL waere aber auf der
     * oeffentlichen Seite ein toter Download-Button. Beim Neuanlegen
     * ($pfadErforderlich=true) MUSS es deshalb mitgeschickt werden - es gibt
     * noch keinen bestehenden Wert, auf den zurueckgefallen werden koennte.
     * Beim Bearbeiten ($pfadErforderlich=false, "sometimes") darf es
     * dagegen fehlen (Preservation - siehe Analysebericht Punkt C: "wenn
     * nur beschreibung geändert wird, muss pfad unverändert bleiben") - wird
     * es trotzdem gesendet, darf es weiterhin nicht leer sein ("required"
     * greift dann zusaetzlich), ein Download darf nie aktiv auf einen
     * leeren Pfad gesetzt werden. "titel" bleibt bewusst NICHT required
     * (faellt sonst auf "pfad" zurueck, siehe DownloadUpdater-Kommentar) -
     * "beschreibung"/"typ" bleiben optional, exakt wie im bestehenden
     * JSON-Weg.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request, bool $pfadErforderlich): array
    {
        $request->validate([
            'pfad' => [$pfadErforderlich ? 'required' : 'sometimes', 'required', 'string', 'max:255'],
        ], [
            'pfad.required' => 'Bitte eine URL oder einen Dateipfad angeben.',
        ]);

        return $request->only(['titel', 'beschreibung', 'typ', 'pfad']);
    }

    /**
     * Aktuelle Werte aller von DownloadUpdater::applyFields() verwalteten
     * Felder. Siehe Klassenkommentar Phase-7E-Analyse Punkt C
     * "Preservation".
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(Download $download): array
    {
        return [
            'titel' => $download->titel,
            'beschreibung' => $download->beschreibung,
            'typ' => $download->typ,
            'pfad' => $download->pfad,
        ];
    }

    /**
     * Verteidigung gegen einen manipulierten Request, der versucht, ueber
     * diese (Bibliotheks-)Routen einen seiteneigenen, eingebetteten
     * Download zu bearbeiten oder zu loeschen (siehe Klassenkommentar
     * "Abgrenzung" und Analysebericht Punkt D) - ein "Download"
     * Route-Model-Binding findet JEDE Zeile der geteilten "downloads"-
     * Tabelle anhand ihrer ID, unabhaengig von owner_type/kategorie_id.
     */
    private function ensureBibliothekseintrag(Download $download): void
    {
        abort_unless($download->owner_type === null, 404);
    }

    /**
     * Standardwerte + tatsaechlich in der Bibliothek verwendete Werte (siehe
     * Klassenkommentar "Typ-Dropdown") - dieselbe Dedup-Reihenfolge wie
     * TermineController::kategorieOptionen(). $aktuellerTyp wird zusaetzlich
     * angehaengt, falls er weder Standard- noch (mehr) ein verwendeter Wert
     * waere - ein bestehender Download darf seinen Typ-Wert nie durch ein
     * Dropdown verlieren, das ihn nicht zeigt.
     *
     * @return list<string>
     */
    private function typOptionen(?string $aktuellerTyp): array
    {
        $verwendet = Download::whereNotNull('kategorie_id')
            ->whereNull('owner_type')
            ->whereNotNull('typ')
            ->where('typ', '!=', '')
            ->distinct()
            ->orderBy('typ')
            ->pluck('typ')
            ->all();

        $kandidaten = self::STANDARD_TYPEN;
        foreach ($verwendet as $t) {
            $kandidaten[] = $t;
        }
        if ($aktuellerTyp !== null && $aktuellerTyp !== '') {
            $kandidaten[] = $aktuellerTyp;
        }

        $out = [];
        foreach ($kandidaten as $t) {
            if (! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }
}
