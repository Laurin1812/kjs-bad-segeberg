<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseKategorie;
use App\Support\BoerseUploads;
use App\Support\WaffenboerseUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7J (Admin-Modul "Waffenboerse").
 *
 * Neuntes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (nach
 * Inhalte/Aktuelles/Termine/Downloads/Partner/Kontaktanfragen/
 * Hundeausbildung/Hundeboerse, Phasen 7B-7I) - strukturell an
 * Admin\HundeboerseController (Phase 7I) angelehnt (Moderations-Workflow,
 * Datei-Upload/-Loeschen), aber NICHT blind kopiert: die Waffenboerse hat
 * eigene Fachfelder/eine eigene Kaliberliste und eine echte, admin-
 * verwaltete Kategorienliste statt einer nur automatisch wachsenden
 * Vorschlagsliste (siehe unten).
 *
 * KURZANALYSE-ERGEBNIS (Auftrag Punkt 1): Waffenboerse ist bereits seit
 * Phase 6B vollstaendig auf Laravel/MySQL migriert (App\Models\
 * WaffenboerseAnzeige/-Bild/-Kaliber/-Kategorie, oeffentlicher
 * WaffenboerseController - siehe dessen Klassenkommentar sowie
 * docs/deployment/dormante-boersen-tabellen.md, Nachtrag "Phase 6B/7J") -
 * fehlend war bislang AUSSCHLIESSLICH die Admin-Oberflaeche fuer diese
 * bereits lebende Datenquelle. Es wird hier KEINE neue Datenhaltung
 * eingefuehrt, sondern exakt dieselben Models/Tabellen bearbeitet, die auch
 * die oeffentliche Seite bereits liest. NUR waffenboerse_meta bleibt
 * tatsaechlich ungenutzt (siehe WaffenboerseMeta-Klassenkommentar) - hat
 * nicht einmal eine Hero-Bild-Spalte, also auch kein Admin-Bedarf dafuer.
 *
 * Bei der urspruenglichen Phase-7-Dokumentation (docs/deployment/dormante-
 * boersen-tabellen.md) war dieser Sachverhalt fuer Waffenboerse fehlerhaft
 * als "weiterhin dormant" vermerkt (ein in Phase 7I ungeprueft
 * uebernommener Fehler, siehe dortiger Nachtrag) - wurde in dieser Phase
 * korrigiert (Migrations-/Model-Kommentare + Dokument), bevor auf dieser
 * Grundlage weitergebaut wurde.
 *
 * KONKURRIERENDE SCHREIBWEGE: admin.js hat fuer Waffenboerse HEUTE genau
 * EINEN Schreibweg - content/waffenboerse.json (git-versioniert, ENTHAELT
 * anders als Hundeboerse ECHTE Bestandsanzeigen, siehe unten). Das
 * urspruenglich vorbereitete PHP/MySQL-Backend (api/waffenboerse/admin/
 * {liste,speichern}.php) wurde per Hotfix vom 11.09.2026 ("Hundeboerse/
 * Waffenboerse 404 im Admin") wieder abgeschaltet - admin.js liest/
 * schreibt seitdem wieder ausschliesslich die JSON-Datei. Diese JSON-Datei
 * ist eine komplett eigenstaendige, von Laravels eigener DB UNABHAENGIGE
 * Datenquelle (kein gemeinsamer Tabellen-/Zeilenzugriff) - es gibt daher
 * KEINE zwei Schreibwege, die dieselben MySQL-Zeilen aendern koennten, und
 * folglich auch keinen Bedarf fuer ContentVersioning (siehe unten) oder
 * eine gemeinsame *Updater-Klasse mit einem zweiten Admin-Endpunkt.
 *
 * WICHTIGER UNTERSCHIED ZU HUNDEBOERSE (Auftrag "nicht blind kopieren"):
 * content/waffenboerse.json enthaelt aktuell KEINE leere Liste, sondern
 * zwei echte, veroeffentlichte Bestandsanzeigen (Bildpfade unter dem alten
 * "/images/..."-Webroot). Der bereits vorhandene `php artisan
 * kjs:import-waffenboerse` (Phase 6B, additiv/idempotent, siehe dessen
 * Klassenkommentar) uebernimmt sie unveraendert in die Laravel-Tabellen,
 * inkl. Kopieren der Bilddateien nach public/uploads/boersen/waffenboerse/
 * - das ist fuer den echten Cutover noch auszufuehren (siehe Abschluss-
 * bericht), aber NICHT Teil dieser Phase (reine Admin-Oberflaeche fuer die
 * bereits bestehenden Tabellen, kein Datenimport-Trigger in diesem
 * Controller).
 *
 * CONTENTVERSIONING: bewusst NICHT verwendet, aus demselben Grund wie bei
 * Admin\HundeboerseController/Admin\KontaktanfragenController (siehe deren
 * Klassenkommentare) - kein zweiter Laravel-Schreibweg auf dieselben
 * Zeilen. Die oeffentliche Einreichung (WaffenboerseController::store())
 * erzeugt ausschliesslich NEUE Zeilen mit frischer ID.
 *
 * ALT-ADMIN-ANALYSE (admin.js, Abschnitt "WAFFENBÖRSE"):
 * - Statusfilter-Reiter: Alle/Wartet/Veroeffentlicht/Abgelehnt/Archiviert
 *   (WB_STATUS) - 1:1 uebernommen (siehe index()), identisches Vokabular
 *   wie Hundeboerse.
 * - Liste: Thumbnail, Titel, Kategorie/Hersteller+Modell/Kaliber/Preis/Ort,
 *   Status-Badge, "Erwerbsberechtigung erforderlich"-Badge falls gesetzt.
 *   Aktionen: Bearbeiten (immer), Freigeben (nur "pending", mit
 *   Bestaetigung), Archivieren (nur "published"/"rejected", OHNE
 *   Bestaetigung), Loeschen (immer, mit Bestaetigung) - exakt wie
 *   Hundeboerse. "Vorschau" (Modal) existiert im alten Admin ebenfalls,
 *   wird hier aus demselben Grund wie bei Hundeboerse NICHT nachgebaut
 *   (zeigt nur bereits im Formular sichtbare Felder erneut an, echte
 *   Detailseite per "Ansehen"-Link fuer veroeffentlichte Anzeigen
 *   erreichbar).
 * - "Wiederherstellen": existiert im alten Admin NICHT als eigener Button -
 *   genau wie bei Hundeboerse laeuft eine archiviert->andere-Status-
 *   Aenderung ueber das ganz normale Status-Dropdown im Bearbeiten-
 *   Formular.
 * - Editor: Status-Dropdown, Grunddaten (Titel/Kategorie/Hersteller/
 *   Modell/Kaliber-Liste/Zustand), Preis & Versand, Standort,
 *   Erwerbsberechtigung (Toggle), Beschreibung (echtes HTML, siehe unten),
 *   Bildergalerie (bis 10 Bilder), Anbieter-/Kontaktdaten. Speichern-
 *   Varianten identisch zu Hundeboerse: "Speichern" (Status bleibt wie im
 *   Dropdown gewaehlt), "Speichern & Freigeben" (status=published),
 *   "Anzeige ablehnen" (status=rejected) - admin.js fuehrt bei den letzten
 *   beiden IMMER zuerst ein volles Feld-Speichern durch
 *   (waffenboerseCollect()), erst DANACH den Statuswechsel - hier 1:1
 *   nachgebildet als EIN update()-Aufruf mit optionalem "aktion"-Feld.
 * - KALIBER (Auftrag Punkt 9, NICHT wie eine reine Vorschlagsliste
 *   behandelt): im Datenmodell eine eigene Zeile pro Kaliber
 *   (waffenboerse_kaliber, siehe Migrations-Kommentar "Kombiwaffen koennen
 *   mehrere Kaliber haben; Kaliberwerte selbst enthalten teils Kommas").
 *   admin.js bildet das bereits als mehrere Text-Zeilen mit "+ Weiteres
 *   Kaliber"-Button ab (KEIN Komma-Trennfeld). Der neue Blade-Admin
 *   erreicht dieselbe Ein-Zeile-pro-Kaliber-Struktur OHNE neues JS: ein
 *   einzelnes Textarea-Feld, eine Kaliberangabe pro Zeile (siehe
 *   WaffenboerseUpdater::parseKaliberEingabe()) - ersetzt bei jedem
 *   Speichern die komplette Liste (WaffenboerseUpdater::replaceKaliber()),
 *   exakt wie admin.js' waffenboerseKaliberCollect().
 * - KATEGORIE (Auftrag Punkt 9, ECHTE Verwaltung noetig - anders als
 *   Hundeboerse-Zuchtverbaende): admin.js bietet neben dem Kategorie-
 *   Dropdown im Editor "+ Neu" (legt sofort eine neue Kategorie an) und
 *   "🗑 Loeschen" (nur moeglich, wenn keine Anzeige die Kategorie mehr
 *   verwendet) - siehe waffenboerseKategorieAdd()/-Delete(). Der neue
 *   Blade-Admin bildet das als EIGENE, separate Seite ab
 *   (admin.waffenboerse.kategorien.*) statt als in den Editor eingebettete
 *   Mini-Formulare: in der Server-gerenderten Admin-Oberflaeche (kein SPA-
 *   Zustand wie im alten admin.js) wuerde ein eingebettetes Mini-Formular
 *   beim Absenden zwangslaeufig die komplette Seite neu laden und damit
 *   alle sonstigen, noch ungespeicherten Aenderungen der gerade
 *   bearbeiteten Anzeige verwerfen - besonders schmerzhaft beim Anlegen
 *   einer NEUEN, noch gar nicht gespeicherten Anzeige. Eine eigene Seite
 *   ist ein bewusster, gezielter Navigationsschritt (wie "Zurueck zur
 *   Liste" bereits heute) statt eines ueberraschenden Nebeneffekts der
 *   Kategorieverwaltung.
 *
 * BILDER (Auftrag Punkt E/5): nutzt ausschliesslich die bereits bestehende
 * App\Support\BoerseUploads (store()/delete(), siehe Phase 6A/6B/7I) - keine
 * neue Upload-Architektur. TRANSAKTIONSSICHERHEIT: 1:1 dieselbe, in Phase 7I
 * fuer Hundeboerse korrigierte Reihenfolge (siehe HundeboerseUpdater::
 * removeBilder()-Klassenkommentar) - Bildpfade werden VOR/waehrend der
 * DB-Transaktion gesammelt, physische Dateien werden ausschliesslich NACH
 * einem erfolgreichen Commit geloescht (siehe update()/destroy() unten).
 *
 * BESCHREIBUNG: anders als Hundeboerse (dortiges "description" ist reiner
 * Freitext/Markdown-aehnlich, siehe HundeboerseUpdater) ist Waffenboerses
 * "beschreibung" bereits ECHTES, sanitisiertes HTML (TipTap-Editor des
 * alten Admins bzw. Text::freeTextToSafeParagraphs() bei oeffentlichen
 * Einreichungen, siehe WaffenboerseController::store()/ImportWaffenboerse).
 * Der neue Blade-Admin baut deshalb bewusst KEINEN neuen Markdown-/
 * Freitext-Umgang, sondern nutzt das bereits bestehende, wiederverwendbare
 * Rich-Text-Feld aus Phase 7B (resources/views/admin/inhalte/
 * _richtext-field.blade.php + resources/js/admin-inhalte-editor.js) - genau
 * die "bestehende Loesung bevorzugen"-Vorgabe, keine zweite Editor-
 * Bibliothek. Das Feld wird UNVERAENDERT wie von admin/inhalte/
 * bearbeiten.blade.php eingebunden; wie dort erfolgt KEINE zusaetzliche
 * Server-Sanitisierung des abgeschickten HTML (siehe InhalteController -
 * derselbe, bereits produktive Vertrauensrahmen fuer authentifizierte
 * Admin-Eingaben). Text::freeTextToSafeParagraphs() wird hier bewusst NICHT
 * verwendet (das wuerde bereits vorhandenes HTML kaputt escapen, siehe
 * dortiger Klassenkommentar).
 */
class WaffenboerseController extends Controller
{
    private const STATUS_OPTIONEN = [
        'pending' => 'Wartet auf Freigabe',
        'published' => 'Veröffentlicht',
        'rejected' => 'Abgelehnt',
        'archived' => 'Archiviert',
    ];

    private const ZUSTAND_OPTIONEN = [
        'neu' => 'Neu',
        'gebraucht' => 'Gebraucht',
        // "vorfuehrwaffe" ist im aktuell erreichbaren admin.js-Prototyp
        // (WB_ZUSTAND) nicht waehlbar, aber ein echter, vom Datenmodell
        // (WaffenboerseAnzeige::ZUSTAND_LABEL) und vom vorbereiteten, nur
        // per Hotfix abgeschalteten PHP-Admin ($allowedZustand in
        // api/waffenboerse/admin/speichern.php) unterstuetzter Wert - wird
        // deshalb hier ergaenzt, damit eine bereits so gespeicherte/
        // importierte Anzeige im neuen Admin korrekt bearbeitbar bleibt.
        'vorfuehrwaffe' => 'Vorführwaffe',
    ];

    private const PREIS_TYP_OPTIONEN = [
        'festpreis' => 'Festpreis',
        'vb' => 'Verhandlungsbasis (VB)',
        // "auf_anfrage" ist im aktuell erreichbaren admin.js-Prototyp
        // (WB_PREIS_TYP) nicht waehlbar, wird aber vom Datenmodell explizit
        // unterstuetzt (WaffenboerseAnzeige::preisText(), siehe dortigen
        // Kommentar "admin-only moeglicher Wert") und ist in den alten,
        // statischen oeffentlichen Seiten (waffenboerse/index.html +
        // detail.html) real verarbeitet - Aequivalent zu Hundeboerses
        // "on_request", siehe dessen Preisart-Dropdown.
        'auf_anfrage' => 'Preis auf Anfrage',
    ];

    public function index(Request $request): View
    {
        $alle = WaffenboerseAnzeige::with('bilder')->orderByDesc('erstellt_am')->get();

        $filter = (string) $request->query('status', '');
        $anzeigen = ($filter !== '' && array_key_exists($filter, self::STATUS_OPTIONEN))
            ? $alle->where('status', $filter)->values()
            : $alle;

        return view('admin.waffenboerse.index', [
            'anzeigen' => $anzeigen,
            'aktuellerFilter' => $filter,
            'statusOptionen' => self::STATUS_OPTIONEN,
            'zaehler' => [
                '' => $alle->count(),
                'pending' => $alle->where('status', 'pending')->count(),
                'published' => $alle->where('status', 'published')->count(),
                'rejected' => $alle->where('status', 'rejected')->count(),
                'archived' => $alle->where('status', 'archived')->count(),
            ],
        ]);
    }

    public function neu(): View
    {
        return view('admin.waffenboerse.bearbeiten', [
            'anzeige' => new WaffenboerseAnzeige([
                'status' => 'pending',
                'zustand' => 'gebraucht',
                'preis_typ' => 'festpreis',
            ]),
            'istNeu' => true,
            'statusOptionen' => self::STATUS_OPTIONEN,
            'zustandOptionen' => self::ZUSTAND_OPTIONEN,
            'preisTypOptionen' => self::PREIS_TYP_OPTIONEN,
            'kategorien' => WaffenboerseKategorie::orderBy('sortierung')->orderBy('name')->get(),
            'kaliberText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validierteDaten($request);
        $kaliberListe = WaffenboerseUpdater::parseKaliberEingabe((string) $request->input('kaliber', ''));

        $anzeige = DB::transaction(function () use ($request, $data, $kaliberListe) {
            $neu = new WaffenboerseAnzeige(['id' => WaffenboerseUpdater::neueId()]);
            WaffenboerseUpdater::applyFields($neu, $data);
            WaffenboerseUpdater::replaceKaliber($neu, $kaliberListe);
            WaffenboerseUpdater::addBilder($neu, $request->file('images', []));

            return $neu;
        });

        return redirect()->route('admin.waffenboerse.bearbeiten', $anzeige)->with('status', 'Anzeige angelegt.');
    }

    public function edit(WaffenboerseAnzeige $waffenboerseAnzeige): View
    {
        return view('admin.waffenboerse.bearbeiten', [
            'anzeige' => $waffenboerseAnzeige->load('bilder'),
            'istNeu' => false,
            'statusOptionen' => self::STATUS_OPTIONEN,
            'zustandOptionen' => self::ZUSTAND_OPTIONEN,
            'preisTypOptionen' => self::PREIS_TYP_OPTIONEN,
            'kategorien' => WaffenboerseKategorie::orderBy('sortierung')->orderBy('name')->get(),
            'kaliberText' => $waffenboerseAnzeige->kaliber()->orderBy('sortierung')->pluck('kaliber')->implode("\n"),
        ]);
    }

    /**
     * Siehe Klassenkommentar "Editor": ein einziger Endpunkt fuer alle drei
     * Speichern-Varianten der Bearbeiten-Maske - identisches Muster wie
     * Admin\HundeboerseController::update().
     *
     * TRANSAKTIONSSICHERHEIT: siehe Klassenkommentar "Bilder" - Pfade
     * werden innerhalb der Transaktion gesammelt (removeBilder()), die
     * physische Loeschung passiert ERST NACH erfolgreichem Commit.
     */
    public function update(Request $request, WaffenboerseAnzeige $waffenboerseAnzeige): RedirectResponse
    {
        $data = array_merge($this->currentFieldValues($waffenboerseAnzeige), $this->validierteDaten($request));

        $aktion = $request->input('aktion');
        if ($aktion === 'freigeben') {
            $data['status'] = 'published';
        } elseif ($aktion === 'ablehnen') {
            $data['status'] = 'rejected';
        }

        $bildIdsEntfernen = array_map('intval', (array) $request->input('bild_entfernen', []));

        $anzahlNachher = $waffenboerseAnzeige->bilder()->whereNotIn('id', $bildIdsEntfernen)->count()
            + count($request->file('images', []));
        if ($anzahlNachher > WaffenboerseUpdater::MAX_BILDER) {
            return back()->withInput()->withErrors([
                'images' => 'Es können maximal '.WaffenboerseUpdater::MAX_BILDER.' Bilder pro Anzeige gespeichert werden.',
            ]);
        }

        $kaliberVorhanden = $request->has('kaliber');
        $kaliberListe = $kaliberVorhanden
            ? WaffenboerseUpdater::parseKaliberEingabe((string) $request->input('kaliber', ''))
            : [];

        $pfadeZumLoeschen = DB::transaction(function () use ($request, $waffenboerseAnzeige, $data, $bildIdsEntfernen, $kaliberVorhanden, $kaliberListe) {
            $pfade = WaffenboerseUpdater::removeBilder($waffenboerseAnzeige, $bildIdsEntfernen);
            WaffenboerseUpdater::applyFields($waffenboerseAnzeige, $data);
            if ($kaliberVorhanden) {
                WaffenboerseUpdater::replaceKaliber($waffenboerseAnzeige, $kaliberListe);
            }
            WaffenboerseUpdater::addBilder($waffenboerseAnzeige, $request->file('images', []));

            return $pfade;
        });

        foreach ($pfadeZumLoeschen as $pfad) {
            BoerseUploads::delete($pfad);
        }

        $meldung = match ($aktion) {
            'freigeben' => 'Anzeige gespeichert und freigegeben.',
            'ablehnen' => 'Anzeige gespeichert und abgelehnt.',
            default => 'Anzeige gespeichert.',
        };

        return redirect()->route('admin.waffenboerse.bearbeiten', $waffenboerseAnzeige)->with('status', $meldung);
    }

    /**
     * Listen-Schnellaktion, 1:1 wie admin.js' waffenboerseFreigeben() -
     * OHNE Formularfelder, mit Bestaetigungsdialog (siehe index.blade.php).
     */
    public function freigeben(WaffenboerseAnzeige $waffenboerseAnzeige): RedirectResponse
    {
        $waffenboerseAnzeige->update(['status' => 'published']);

        return back()->with('status', 'Anzeige freigegeben.');
    }

    /**
     * Listen-Schnellaktion, 1:1 wie admin.js' waffenboerseArchivieren() -
     * bewusst OHNE Bestaetigungsdialog (der alte Admin fragt hier ebenfalls
     * nicht nach).
     */
    public function archivieren(WaffenboerseAnzeige $waffenboerseAnzeige): RedirectResponse
    {
        $waffenboerseAnzeige->update(['status' => 'archived']);

        return back()->with('status', 'Anzeige archiviert.');
    }

    /**
     * 1:1 wie admin.js' waffenboerseDelete() (mit Bestaetigungsdialog, siehe
     * View) - loescht zusaetzlich die physischen Bilddateien (Original +
     * Varianten); die waffenboerse_bilder-/waffenboerse_kaliber-Zeilen
     * selbst werden durch die bestehende Fremdschluessel-Kaskade
     * (cascadeOnDelete, siehe Migrationen) automatisch mitentfernt.
     *
     * TRANSAKTIONSSICHERHEIT: 1:1 wie Admin\HundeboerseController::
     * destroy() (Phase 7I-Korrektur) - Bildpfade werden VOR der Transaktion
     * gesammelt, physische Dateien werden ERST NACH erfolgreichem Commit
     * entfernt.
     */
    public function destroy(WaffenboerseAnzeige $waffenboerseAnzeige): RedirectResponse
    {
        $pfade = $waffenboerseAnzeige->bilder->pluck('pfad')->all();

        DB::transaction(function () use ($waffenboerseAnzeige) {
            $waffenboerseAnzeige->delete();
        });

        foreach ($pfade as $pfad) {
            BoerseUploads::delete($pfad);
        }

        return redirect()->route('admin.waffenboerse.index')->with('status', 'Anzeige gelöscht.');
    }

    /**
     * Siehe Klassenkommentar "Kategorie" - eigene, separate Seite statt
     * eines in den Editor eingebetteten Mini-Formulars.
     */
    public function kategorienIndex(): View
    {
        return view('admin.waffenboerse.kategorien', [
            'kategorien' => WaffenboerseKategorie::orderBy('sortierung')->orderBy('name')->get(),
        ]);
    }

    /**
     * 1:1 wie admin.js' waffenboerseKategorieAdd() - neue Kategorie ans
     * Ende der Sortierung anhaengen, keine Dubletten (per Unique-Index der
     * Migration ohnehin serverseitig abgesichert, hier zusaetzlich
     * freundliche Fehlermeldung statt hartem SQL-Fehler).
     */
    public function kategorieHinzufuegen(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ], [
            'name.required' => 'Bitte einen Namen für die neue Kategorie angeben.',
        ]);

        $name = trim((string) $request->input('name'));
        if (WaffenboerseKategorie::where('name', $name)->exists()) {
            return back()->withInput()->withErrors(['name' => 'Diese Kategorie existiert bereits.']);
        }

        $naechsteSortierung = ((int) WaffenboerseKategorie::max('sortierung')) + 1;
        WaffenboerseKategorie::create(['name' => $name, 'sortierung' => $naechsteSortierung]);

        return redirect()->route('admin.waffenboerse.kategorien.index')->with('status', 'Kategorie „'.$name.'“ hinzugefügt.');
    }

    /**
     * 1:1 wie admin.js' waffenboerseKategorieDelete() - Loeschung nur
     * moeglich, wenn keine Anzeige die Kategorie mehr verwendet (dieselbe
     * Schutzregel, hier serverseitig statt nur clientseitig geprueft, da es
     * in einer Mehrbenutzer-Admin-Oberflaeche ohnehin die einzig verlaessliche
     * Stelle ist).
     */
    public function kategorieLoeschen(WaffenboerseKategorie $waffenboerseKategorie): RedirectResponse
    {
        $verwendetVon = WaffenboerseAnzeige::where('kategorie', $waffenboerseKategorie->name)->count();
        if ($verwendetVon > 0) {
            return back()->withErrors([
                'name' => 'Kategorie „'.$waffenboerseKategorie->name.'“ wird noch von '.$verwendetVon.' Anzeige(n) verwendet und kann nicht gelöscht werden.',
            ]);
        }

        $waffenboerseKategorie->delete();

        return redirect()->route('admin.waffenboerse.kategorien.index')->with('status', 'Kategorie „'.$waffenboerseKategorie->name.'“ gelöscht.');
    }

    /**
     * Siehe Klassenkommentar "Validierung" (identisches Prinzip wie
     * Admin\HundeboerseController::validierteDaten()): nur strukturelle
     * Regeln (Status-/Zustand-/Preisart-Enum, Bildanzahl/-typ), keine
     * inhaltlichen Pflichtfelder - der alte Admin validiert Waffenboerse-
     * Felder ebenfalls nicht (reine JS-Objekt-Zuweisung). "kategorie" wird
     * bewusst NUR strukturell (String/Laenge) statt per "exists:..."
     * geprueft - die Dropdown-Optionen kommen ohnehin ausschliesslich aus
     * der echten Kategorienliste, ein zusaetzliches "exists" wuerde nur
     * einen Randfall (eine zwischenzeitlich geloeschte, aber noch an dieser
     * Anzeige haengende Kategorie) unnoetig blockieren, ohne echten Nutzen.
     *
     * @return array<string, mixed>
     */
    private function validierteDaten(Request $request): array
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,published,rejected,archived'],
            'zustand' => ['required', 'string', 'in:neu,gebraucht,vorfuehrwaffe'],
            'preis_typ' => ['required', 'string', 'in:festpreis,vb,auf_anfrage'],
            'anbieter_email' => ['nullable', 'email', 'max:190'],
            'images' => ['nullable', 'array', 'max:'.WaffenboerseUpdater::MAX_BILDER],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $data = $request->only([
            'status', 'titel', 'kategorie', 'hersteller', 'modell', 'zustand',
            'preis', 'preis_typ', 'plz', 'ort',
            'anbieter_name', 'anbieter_email', 'anbieter_telefon',
            'beschreibung',
        ]);

        // "erwerbsberechtigung_erforderlich" (echte Checkbox mit verdecktem
        // "0"-Fallback, wie HundeboerseController::validierteDaten()'
        // "has_zuchtverband") - nur anfassen, wenn tatsaechlich im Request
        // vorhanden (Preservation bei Teil-Requests/Tests).
        if ($request->has('erwerbsberechtigung_erforderlich')) {
            $data['erwerbsberechtigung_erforderlich'] = $request->boolean('erwerbsberechtigung_erforderlich');
        }

        // "versand_moeglich"/"versandkosten": 1:1 dieselbe Kopplung wie im
        // alten admin.js (waffenboerseCollect(): "a.versandkosten =
        // a.versand_moeglich ? gv(...) : ''") UND der oeffentlichen
        // Einreichung (WaffenboerseController::store()) - Versandkosten
        // werden geleert, sobald Versand nicht mehr moeglich ist, damit nie
        // ein "unmoeglicher" Versandkosten-Wert ohne Versandoption stehen
        // bleibt.
        if ($request->has('versand_moeglich')) {
            $data['versand_moeglich'] = $request->boolean('versand_moeglich');
            $data['versandkosten'] = $data['versand_moeglich']
                ? (string) ($request->input('versandkosten') ?? '')
                : '';
        }

        return $data;
    }

    /**
     * Aktuelle Werte aller von WaffenboerseUpdater::applyFields() verwalteten
     * Felder - siehe Klassenkommentar "Preservation"-Prinzip (identisch zu
     * Admin\HundeboerseController::currentFieldValues()).
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(WaffenboerseAnzeige $anzeige): array
    {
        return [
            'status' => $anzeige->status,
            'titel' => $anzeige->titel,
            'kategorie' => $anzeige->kategorie,
            'hersteller' => $anzeige->hersteller,
            'modell' => $anzeige->modell,
            'zustand' => $anzeige->zustand,
            'preis' => $anzeige->preis,
            'preis_typ' => $anzeige->preis_typ,
            'erwerbsberechtigung_erforderlich' => $anzeige->erwerbsberechtigung_erforderlich,
            'beschreibung' => $anzeige->beschreibung,
            'plz' => $anzeige->plz,
            'ort' => $anzeige->ort,
            'versand_moeglich' => $anzeige->versand_moeglich,
            'versandkosten' => $anzeige->versandkosten,
            'anbieter_name' => $anzeige->anbieter_name,
            'anbieter_email' => $anzeige->anbieter_email,
            'anbieter_telefon' => $anzeige->anbieter_telefon,
        ];
    }
}
