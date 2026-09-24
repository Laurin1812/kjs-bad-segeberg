<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\PartnerUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7F (Admin-Modul "Partner").
 *
 * Fuenftes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (siehe
 * Phase 7A: Auth/Zugriffsschutz/Admin-Shell; Phase 7B: "Inhalte/Seiten";
 * Phase 7C: "Aktuelles"; Phase 7D: "Termine" als naechstes Vorbild fuer
 * volles CRUD ohne eingebettete Relationen ausser einer einfachen
 * Freitext-Liste) - bildet die Datensaetze aus App\Models\Partner (Tabelle
 * "partner", bereits seit Phase 2 vollstaendig auf MySQL migriert, siehe
 * PartnerController (oeffentlich) fuer den Leseweg) inkl. ihrer
 * App\Models\PartnerVorteil-Zeilen ab.
 *
 * SCHREIBLOGIK (Analyse-Auftrag "keine doppelte Fachlogik"): nutzt fuer die
 * eigentliche Feld-Zuweisung/-Normalisierung sowie das Ersetzen der
 * partner_vorteile-Zeilen ausschliesslich App\Support\PartnerUpdater -
 * dieselbe Klasse, die (seit Phase 7F) auch Api\Admin\AdminListController::
 * partner() fuer denselben fachlichen Teil nutzt (siehe dortiger
 * Kommentar).
 *
 * VERSIONIERUNG: admin.js' Partner-Schreibweg versioniert NICHT pro
 * Partner, sondern fuer die GESAMTE Liste ("partner" ist ein einziger
 * ContentVersioning-Schluessel, siehe AdminListController::
 * SECTION_PARTNER). Dieses Modul nutzt bewusst DENSELBEN Schluessel: ein
 * Konflikt hier bedeutet "die Partner-Daten wurden zwischenzeitlich
 * irgendwo anders (JSON-API ODER dieser Blade-Admin) veraendert".
 *
 * "aktiv" (Nutzer-Entscheidung/Alt-Verhalten, Auftrag Punkt 6 "Partner
 * loeschen/deaktivieren"): 1:1 wie im alten Admin ausschliesslich ueber das
 * normale Bearbeiten-Formular umschaltbar (kein zusaetzlicher
 * Ein-Klick-Umschalter in der Liste wie bei TermineController::
 * archivToggle() - das alte admin.js kennt fuer Partner ebenfalls keinen
 * solchen Schnellumschalter, nur "Bearbeiten"/"Loeschen" pro Zeile). Ein
 * deaktivierter Partner bleibt im Admin bearbeitbar, verschwindet aber aus
 * der oeffentlichen Uebersicht (siehe oeffentlicher PartnerController::
 * index(), Filter "aktiv = true").
 *
 * "rahmenvertrag" (Nutzer-Entscheidung Phase-7F-Analyse): die DB-Spalte ist
 * bereits seit Phase 2 ein echtes Boolean (siehe PartnerUpdater-
 * Klassenkommentar) - die neue Maske bildet deshalb eine Checkbox ab statt
 * das alte, historisch gewachsene Freitext-Feld nachzubauen (identisch zur
 * bereits etablierten Praxis bei TermineController::validateData(),
 * "archiviert" als Checkbox statt des alten JS-Toggle-Widgets). Keine
 * Verhaltensaenderung der Daten selbst, nur eine dem tatsaechlichen
 * Spaltentyp entsprechende neue Eingabe.
 *
 * "vorteile" (Nutzer-Entscheidung/Alt-Verhalten): bleibt bewusst Freitext,
 * eine Zeile je Vorteil - siehe PartnerUpdater-Klassenkommentar. Kein neuer
 * strukturierter Listen-Editor.
 *
 * "logo": reines Pfad-Textfeld, kein Datei-Upload - 1:1 dasselbe,
 * bereits in Phase 7C etablierte Muster ("Medienauswahl folgt in einer
 * spaeteren Phase", siehe admin/aktuelles/bearbeiten.blade.php).
 *
 * REIHENFOLGE (Nutzer-Entscheidung Phase-7F-Analyse): admin.js nutzt fuer
 * Partner Drag&Drop (SortableJS, sofortiges Speichern der neuen Position),
 * das aber im neuen, bewusst framework-freien Blade-Admin nirgends
 * eingebunden ist (siehe admin/aktuelles/bearbeiten.blade.php-Kommentar
 * "kein fetch()/keine JSON-Runtime noetig" - kein neues JS fuer dieses
 * Modul einfuehren). Funktional gleichwertiger Ersatz: Auf-/Ab-Buttons pro
 * Zeile (hoch()/runter()), die die "sortierung"-Werte der beiden
 * betroffenen Partner vertauschen und sofort speichern - exakt dieselbe
 * Wirkung wie ein Drag&Drop-Schritt um eine Position, kein neues JS.
 */
class PartnerController extends Controller
{
    private const SECTION = 'partner';

    public function index(): View
    {
        return view('admin.partner.index', [
            'partner' => Partner::orderBy('sortierung')->get(),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function neu(): View
    {
        return view('admin.partner.bearbeiten', [
            'partner' => new Partner(['aktiv' => true]),
            'istNeu' => true,
            'vorteileText' => '',
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            $partner = DB::transaction(function () use ($validated, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                // Neu angelegte Partner brauchen sofort eine external_id
                // (siehe Klassenkommentar Phase-7F-Analyse) - die oeffentliche
                // Detailseite verlinkt ausschliesslich darueber (route(
                // 'partner.show', $p->external_id)), genau wie beim
                // bisherigen JSON-Weg (admin.js' partnerAdd() generiert
                // "id":"pn-"+Date.now() clientseitig).
                $partner = new Partner(['external_id' => 'pn-'.now()->valueOf()]);
                $validated['sortierung'] = Partner::count();
                PartnerUpdater::applyFields($partner, $validated);

                ContentVersioning::bump(self::SECTION);

                return $partner;
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'name' => 'Die Partner-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.partner.bearbeiten', $partner)->with('status', 'Partner angelegt.');
    }

    public function edit(Partner $partner): View
    {
        return view('admin.partner.bearbeiten', [
            'partner' => $partner,
            'istNeu' => false,
            'vorteileText' => $partner->vorteile()->orderBy('sortierung')->pluck('text')->implode("\n"),
            'currentVersion' => ContentVersioning::current(self::SECTION),
        ]);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $this->validateData($request);

        // Preservation (siehe Klassenkommentar TermineController::update()):
        // aktuelle Werte vorbelegen, das Formular ueberschreibt nur, was es
        // tatsaechlich sendet.
        $data = array_merge($this->currentFieldValues($partner), $validated);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($partner, $data, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                PartnerUpdater::applyFields($partner, $data);
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'name' => 'Die Partner-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.partner.bearbeiten', $partner)->with('status', 'Partner gespeichert.');
    }

    public function destroy(Request $request, Partner $partner): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($partner, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);
                $partner->delete();
                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'name' => 'Die Partner-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.partner.index')->with('status', 'Partner gelöscht.');
    }

    /**
     * Siehe Klassenkommentar "Reihenfolge": vertauscht die "sortierung" mit
     * dem in der aktuellen Sortierung direkt VORHERGEHENDEN Partner. Steht
     * der Partner bereits an erster Stelle, passiert nichts (kein Fehler).
     */
    public function hoch(Request $request, Partner $partner): RedirectResponse
    {
        return $this->vertauschen($request, $partner, richtungHoch: true);
    }

    /**
     * Siehe Klassenkommentar "Reihenfolge": vertauscht die "sortierung" mit
     * dem in der aktuellen Sortierung direkt NACHFOLGENDEN Partner. Steht
     * der Partner bereits an letzter Stelle, passiert nichts (kein Fehler).
     */
    public function runter(Request $request, Partner $partner): RedirectResponse
    {
        return $this->vertauschen($request, $partner, richtungHoch: false);
    }

    private function vertauschen(Request $request, Partner $partner, bool $richtungHoch): RedirectResponse
    {
        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($partner, $richtungHoch, $expectedVersion) {
                ContentVersioning::assertNotStale(self::SECTION, $expectedVersion);

                $nachbarQuery = Partner::where('sortierung', $richtungHoch ? '<' : '>', $partner->sortierung)
                    ->orderBy('sortierung', $richtungHoch ? 'desc' : 'asc');
                $nachbar = $nachbarQuery->first();

                if ($nachbar) {
                    $eigeneSortierung = $partner->sortierung;
                    $partner->sortierung = $nachbar->sortierung;
                    $nachbar->sortierung = $eigeneSortierung;
                    $partner->save();
                    $nachbar->save();
                }

                ContentVersioning::bump(self::SECTION);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'name' => 'Die Partner-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.partner.index')->with('status', 'Reihenfolge geändert.');
    }

    /**
     * "aktiv"/"rahmenvertrag" (Preservation-Nachbesserung, siehe Klassen-
     * kommentar "aktiv"): NUR gesetzt, wenn der Schluessel tatsaechlich im
     * Request vorhanden ist ($request->has()) - eine echte, unangehakte
     * Checkbox sendet in HTML-Formularen GAR NICHTS, ist also fuer sich
     * genommen nicht von einem Teil-Request
     * unterscheidbar, der das Feld komplett wegllaesst. Die Blade-Maske
     * (admin/partner/bearbeiten.blade.php) loest das mit je einem
     * verdeckten "0"-Fallback-Feld VOR der Checkbox: ein echtes,
     * vollstaendiges Formular sendet das Feld deshalb IMMER (entweder "0"
     * vom Fallback oder "1" von der angehakten Checkbox), waehrend ein
     * Teil-Request/Test, der das Feld bewusst weglaesst, es weiterhin nicht
     * sendet - genau diese Unterscheidung wird hier ausgewertet. "vorteile"
     * bleibt bewusst Teil von only() (unveraendert): Request::only() nimmt
     * einen Schluessel nur auf, wenn er im Request tatsaechlich vorhanden
     * ist, wodurch ein weggelassenes "vorteile" hier ebenfalls keinen
     * Eintrag im Rueckgabe-Array erzeugt (siehe PartnerUpdater::
     * applyFields()-Kommentar fuer die zugehoerige Auswertung).
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->only([
            'name', 'logo', 'kurzbeschreibung', 'beschreibung', 'ansprechpartner',
            'telefon', 'email', 'website', 'weitere_infos', 'vorteile',
        ]);
        if ($request->has('rahmenvertrag')) {
            $data['rahmenvertrag'] = $request->boolean('rahmenvertrag');
        }
        if ($request->has('aktiv')) {
            $data['aktiv'] = $request->boolean('aktiv');
        }

        return $data;
    }

    /**
     * Aktuelle Werte aller von PartnerUpdater::applyFields() verwalteten
     * Felder (ausser "sortierung", siehe Klassenkommentar TermineController::
     * currentFieldValues() fuer dasselbe Prinzip - "sortierung" wird
     * ausschliesslich ueber hoch()/runter() bzw. beim Neuanlegen gesetzt,
     * ein normales Bearbeiten-Formular sendet dieses Feld nie).
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(Partner $partner): array
    {
        return [
            'name' => $partner->name,
            'logo' => $partner->logo,
            'kurzbeschreibung' => $partner->kurzbeschreibung,
            'beschreibung' => $partner->beschreibung,
            'ansprechpartner' => $partner->ansprechpartner,
            'telefon' => $partner->telefon,
            'email' => $partner->email,
            'website' => $partner->website,
            'rahmenvertrag' => $partner->rahmenvertrag,
            'weitere_infos' => $partner->weitere_infos,
            'aktiv' => $partner->aktiv,
            'sortierung' => $partner->sortierung,
        ];
    }
}
