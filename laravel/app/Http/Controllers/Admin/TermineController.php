<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hegering;
use App\Models\Setting;
use App\Models\Termin;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\TermineRules;
use App\Support\TerminUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7D (Admin-Modul "Termine").
 *
 * Drittes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (siehe
 * Phase 7A: Auth/Zugriffsschutz/Admin-Shell; Phase 7B: "Inhalte/Seiten";
 * Phase 7C: "Aktuelles" als naechstes Vorbild fuer volles CRUD) - bildet
 * die Datensaetze aus App\Models\Termin ab (Tabelle "termine", bereits seit
 * Phase 2 vollstaendig auf MySQL migriert, siehe TermineController
 * (oeffentlich) fuer den Leseweg).
 *
 * SCHREIBLOGIK (Analyse-Auftrag Punkt 2 "keine doppelte Fachlogik"): nutzt
 * fuer die eigentliche Feld-Zuweisung ausschliesslich App\Support\
 * TerminUpdater - dieselbe Klasse, die (seit Phase 7D) auch Api\Admin\
 * AdminListController::termine() fuer denselben fachlichen Teil nutzt
 * (siehe dortiger Kommentar). Anders als "Aktuelles" gibt es hier keine
 * eingebetteten Relationen (downloads/galerie) - die Preservation-Merge-
 * Vorbelegung (siehe currentFieldValues()) ist trotzdem defensiv
 * uebernommen, exakt aus demselben Grund wie dort: das aktuelle Formular
 * zeigt zwar bereits ALLE neun fachlichen Felder (kein Preservation-
 * Unterschied heute), ein spaeter hinzukommendes Feld soll aber nicht
 * denselben Datenverlust-Bug wiederholen, der in Phase 7B gefunden wurde.
 *
 * VERSIONIERUNG (Analyse-Auftrag Punkt 6): admin.js' Termine-Schreibweg
 * versioniert NICHT pro Termin, sondern fuer die GESAMTE Liste + die
 * Ueberschrift/Einleitungstext-Einstellungen der oeffentlichen Seite
 * ("termine" ist ein einziger ContentVersioning-Schluessel, siehe
 * Api\Admin\AdminListController::SECTION_TERMINE). Dieses Modul nutzt
 * bewusst DENSELBEN Schluessel (keinen neuen, feineren erfunden): ein
 * Konflikt hier bedeutet "die Termine-Daten wurden zwischenzeitlich
 * irgendwo anders (JSON-API ODER dieser Blade-Admin) veraendert", exakt
 * wie zuvor.
 *
 * KATEGORIEN (Nutzer-Entscheidung Phase 7D, bewusst ANDERS als bei
 * Aktuelles): "kategorie" ist bei Termin ein reines String-Feld ohne
 * eigene Tabelle/FK (anders als Beitrag::kategorie_id -> BeitragKategorie).
 * admin.js bietet zwar "+ Neu"/Loeschen fuer Termine-Kategorien an
 * (window.termineKategorieAdd/-Delete), deren Ziel-Schluessel
 * ("einstellungen.kategorien") wird von Api\Admin\AdminListController::
 * termine() aber nachweislich GAR NICHT persistiert (dort werden nur
 * "ueberschrift"/"einleitung" aus $einstellungen uebernommen) - eine
 * bereits VOR Phase 7D bestehende, separate Luecke. Auftrag: diese Luecke
 * jetzt NICHT schliessen und KEINE neue Kategorie-Verwaltung bauen -
 * kategorieOptionen() unten zeigt deshalb nur eine Auswahl aus den
 * bisherigen Standardwerten (KAT_TERMINE aus admin.js) plus tatsaechlich
 * in der DB verwendeten Werten (damit ein Termin mit einer "exotischen"
 * Kategorie weiterhin editierbar bleibt, ohne dass sein Wert aus dem
 * Dropdown faellt) - kein Anlegen/Loeschen einer Kategorie ueber dieses
 * Modul.
 */
class TermineController extends Controller
{
    private const SECTION = 'termine';

    /**
     * 1:1 aus admin.js' KAT_TERMINE-Konstante uebernommen (siehe
     * Klassenkommentar "Kategorien").
     *
     * @var list<string>
     */
    private const STANDARD_KATEGORIEN = [
        'Vorstand', 'Schießwesen', 'Hundeausbildung', 'Jagdhornblasen', 'Jugend',
        'Hegering', 'Naturschutz', 'Ausbildung', 'Kreisveranstaltung', 'Hauptversammlung', 'Tradition',
    ];

    public function index(): View
    {
        $einstellungen = Setting::where('gruppe', self::SECTION)->pluck('value', 'key');

        return view('admin.termine.index', [
            // Bewusst OHNE TermineRules::istSichtbar()-Filter (anders als
            // die oeffentliche Seite): der Admin muss auch archivierte und
            // laengst vergangene Termine sehen und bearbeiten koennen.
            // Sortierung (naechster Termin zuerst, Termine ohne Datum ans
            // Ende) ist dieselbe bestehende Regel wie auf der oeffentlichen
            // Seite - keine neue Sortier-Logik.
            'termine' => TermineRules::sortiere(Termin::all()),
            'ueberschrift' => (string) ($einstellungen['ueberschrift'] ?? ''),
            'einleitung' => (string) ($einstellungen['einleitung'] ?? ''),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function neu(): View
    {
        // Standardkategorie "Kreisveranstaltung" - 1:1 aus admin.js'
        // window.termineNeu() uebernommen.
        $termin = new Termin(['kategorie' => 'Kreisveranstaltung', 'archiviert' => false]);

        return view('admin.termine.bearbeiten', [
            'termin' => $termin,
            'istNeu' => true,
            'kategorien' => $this->kategorieOptionen(null),
            'hegeringOptionen' => $this->hegeringOptionen(),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            $termin = DB::transaction(function () use ($validated, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                $termin = new Termin;
                TerminUpdater::applyFields($termin, $validated);

                ContentVersioning::bump(self::SECTION);

                return $termin;
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'veranstaltung' => 'Die Termine-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.termine.bearbeiten', $termin)->with('status', 'Termin angelegt.');
    }

    public function edit(Termin $termin): View
    {
        return view('admin.termine.bearbeiten', [
            'termin' => $termin,
            'istNeu' => false,
            'kategorien' => $this->kategorieOptionen($termin->kategorie),
            'hegeringOptionen' => $this->hegeringOptionen(),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function update(Request $request, Termin $termin): RedirectResponse
    {
        $validated = $this->validateData($request);

        // Preservation (siehe Klassenkommentar): aktuelle Werte vorbelegen,
        // das Formular ueberschreibt nur, was es tatsaechlich sendet.
        $data = array_merge($this->currentFieldValues($termin), $validated);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($termin, $data, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                TerminUpdater::applyFields($termin, $data);
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'veranstaltung' => 'Die Termine-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.termine.bearbeiten', $termin)->with('status', 'Termin gespeichert.');
    }

    /**
     * Ein-Klick-Archivieren/Wiederherstellen direkt aus der Liste - 1:1 aus
     * admin.js' window.termineArchivToggle() uebernommen (dort: einfaches
     * Umschalten des Flags, sofortiges Speichern). Die "archiviert"-
     * Checkbox im normalen Bearbeiten-Formular bleibt zusaetzlich bestehen
     * (beide Wege fuehren zum selben Feld, kein Widerspruch).
     */
    public function archivToggle(Request $request, Termin $termin): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($termin, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                $termin->archiviert = ! $termin->archiviert;
                $termin->save();
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'veranstaltung' => 'Die Termine-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.termine.index')->with('status', $termin->archiviert ? 'Termin ins Archiv verschoben.' : 'Termin aus dem Archiv zurückgeholt.');
    }

    public function destroy(Request $request, Termin $termin): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($termin, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                $termin->delete();
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'veranstaltung' => 'Die Termine-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.termine.index')->with('status', 'Termin gelöscht.');
    }

    /**
     * Ueberschrift/Einleitungstext der oeffentlichen Termine-Seite - 1:1
     * aus admin.js' window.termineEinstSave() uebernommen, jetzt als eigene
     * Route/Aktion (kein einzelner Termin-Datensatz betroffen). Nutzt
     * denselben ContentVersioning-Schluessel wie die Termine-Liste selbst
     * (siehe Klassenkommentar "Versionierung").
     */
    public function einstellungenSpeichern(Request $request): RedirectResponse
    {
        $request->validate([
            'ueberschrift' => ['nullable', 'string', 'max:190'],
            'einleitung' => ['nullable', 'string'],
        ]);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($request, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                Setting::updateOrCreate(
                    ['gruppe' => self::SECTION, 'key' => 'ueberschrift'],
                    ['value' => (string) $request->input('ueberschrift', '')]
                );
                Setting::updateOrCreate(
                    ['gruppe' => self::SECTION, 'key' => 'einleitung'],
                    ['value' => (string) $request->input('einleitung', '')]
                );

                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'veranstaltung' => 'Die Termine-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.termine.index')->with('status', 'Überschrift & Einleitungstext gespeichert.');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array
    {
        // "datum": required (Nutzer-Entscheidung Phase 7D Punkt 3 - die
        // DB-Spalte ist NOT NULL, admin.js zeigte dafuer zwar keine
        // Pflichtfeld-Markierung, ein leeres Datum ist im echten
        // Datenbestand aber ohnehin nicht vorgesehen). "veranstaltung"
        // bleibt bewusst NICHT required (siehe TerminUpdater-Kommentar) -
        // die Spalte ist zwar ebenfalls NOT NULL, erlaubt aber einen leeren
        // String, und admin.js erzwingt hier nichts.
        $request->validate([
            'datum' => ['required', 'date'],
        ], [
            'datum.required' => 'Bitte ein Datum angeben.',
            'datum.date' => 'Bitte ein gültiges Datum angeben.',
        ]);

        $data = $request->only(['datum', 'uhrzeit', 'veranstaltung', 'strasse', 'plz', 'ort', 'revier', 'kategorie']);
        $data['archiviert'] = $request->boolean('archiviert');

        return $data;
    }

    /**
     * Aktuelle Werte aller von TerminUpdater::applyFields() verwalteten
     * Felder. Siehe Klassenkommentar "Preservation".
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(Termin $termin): array
    {
        return [
            'datum' => $termin->datum?->toDateString(),
            'uhrzeit' => $termin->uhrzeit,
            'veranstaltung' => $termin->veranstaltung,
            'strasse' => $termin->strasse,
            'plz' => $termin->plz,
            'ort' => $termin->ort,
            'revier' => $termin->revier,
            'kategorie' => $termin->kategorie,
            'archiviert' => $termin->archiviert,
        ];
    }

    /**
     * Standardwerte + tatsaechlich in der DB verwendete Werte (siehe
     * Klassenkommentar "Kategorien") - 1:1 dieselbe Dedup-Reihenfolge wie
     * admin.js' alleTermineKategorien() (kats.concat(used), erstes
     * Vorkommen gewinnt). $aktuelleKategorie wird zusaetzlich angehaengt,
     * falls sie aus irgendeinem Grund weder Standard- noch (mehr) ein in
     * der DB verwendeter Wert waere - ein bestehender Termin darf seinen
     * Kategorie-Wert nie durch ein Dropdown verlieren, das ihn nicht zeigt.
     *
     * @return list<string>
     */
    private function kategorieOptionen(?string $aktuelleKategorie): array
    {
        $verwendet = Termin::whereNotNull('kategorie')
            ->where('kategorie', '!=', '')
            ->distinct()
            ->orderBy('kategorie')
            ->pluck('kategorie')
            ->all();

        $kandidaten = self::STANDARD_KATEGORIEN;
        foreach ($verwendet as $k) {
            $kandidaten[] = $k;
        }
        if ($aktuelleKategorie !== null && $aktuelleKategorie !== '') {
            $kandidaten[] = $aktuelleKategorie;
        }

        $out = [];
        foreach ($kandidaten as $k) {
            if (! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /**
     * "nummer – name" je Hegering, als Vorschlagsliste fuer das freie
     * Revier-Textfeld - 1:1 aus admin.js' ladeHegeringOptionen()
     * uebernommen (dort per fetch('/content/hegeringe.json'), hier direkt
     * ueber das bereits migrierte Hegering-Model).
     *
     * @return list<string>
     */
    private function hegeringOptionen(): array
    {
        return Hegering::orderBy('sortierung')->get()
            ->map(fn (Hegering $h) => trim(($h->nummer ?? '').($h->name ? ' – '.$h->name : '')))
            ->filter()
            ->values()
            ->all();
    }
}
