<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseZuchtverband;
use App\Support\BoerseUploads;
use App\Support\HundeboerseUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7I (Admin-Modul "Hundeboerse").
 *
 * Siebtes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (nach
 * Inhalte/Aktuelles/Termine/Downloads/Partner/Kontaktanfragen, Phasen
 * 7B-7G/7H) - erstes Modul mit echtem Moderations-Workflow (Status-Freigabe)
 * und echtem Datei-Upload.
 *
 * KURZANALYSE-ERGEBNIS (Auftrag Punkt 1, siehe unten fuer die Details):
 * Hundeboerse ist bereits seit Phase 6A vollstaendig auf Laravel/MySQL
 * migriert (App\Models\HundeboerseAnzeige/-Bild/-Zuchtverband/-Meta,
 * oeffentlicher App\Http\Controllers\HundeboerseController) - fehlend war
 * bislang AUSSCHLIESSLICH die Admin-Oberflaeche fuer diese bereits lebende
 * Datenquelle. Es wird hier KEINE neue Datenhaltung eingefuehrt, sondern
 * exakt dieselben Models/Tabellen bearbeitet, die auch die oeffentliche
 * Seite bereits liest.
 *
 * KONKURRIERENDE SCHREIBWEGE (Auftrag Punkt "mehrere konkurrierende
 * Schreibwege mit unterschiedlichen Versioning-Schluesseln?"): admin.js hat
 * fuer Hundeboerse HEUTE genau EINEN Schreibweg - content/hundeboerse.json
 * (git-versioniert, siehe dortige Commit-Historie "🐕 Hundebörse: ..."). Das
 * urspruenglich vorbereitete PHP/MySQL-Backend (api/hundeboerse/admin/
 * {liste,speichern}.php) wurde per Hotfix vom 11.09.2026 ("Hundeboerse/
 * Waffenboerse 404 im Admin") wieder abgeschaltet, siehe admin.js-Kommentar
 * an der jeweiligen Stelle - admin.js liest/schreibt seitdem wieder
 * ausschliesslich die JSON-Datei. DIESE JSON-Datei ist eine komplett
 * eigenstaendige, von Laravels eigener DB UNABHAENGIGE Datenquelle (kein
 * gemeinsamer Tabellen-/Zeilenzugriff) - es gibt daher KEINE zwei
 * Schreibwege, die dieselben MySQL-Zeilen aendern koennten, und folglich
 * auch keinen Bedarf fuer ContentVersioning (siehe dort unten) oder eine
 * gemeinsame *Updater-Klasse mit einem zweiten Admin-Endpunkt (anders als
 * Partner/Termine/Downloads/Aktuelles, die ueber Api\Admin\
 * AdminListController einen echten zweiten MySQL-Schreibweg haben).
 * content/hundeboerse.json enthaelt aktuell "anzeigen": [] (keine echten
 * Daten) - ein spaeterer Cutover uebernimmt Altdaten einmalig und additiv
 * per `php artisan kjs:import-hundeboerse` (siehe ImportHundeboerse-
 * Klassenkommentar), danach ist diese Laravel-Tabelle die einzige
 * Datenquelle - exakt das bereits etablierte Muster dieses Projekts.
 *
 * CONTENTVERSIONING: bewusst NICHT verwendet, aus demselben Grund wie bei
 * Admin\KontaktanfragenController (siehe dortiger Klassenkommentar
 * "CONTENTVERSIONING"): es schuetzt einen gemeinsamen Schreibweg vor
 * gleichzeitigen, sich gegenseitig ueberschreibenden Aenderungen an anderer
 * Stelle - hier gibt es (siehe oben) keinen zweiten Laravel-Schreibweg auf
 * dieselben Zeilen. Die oeffentliche Einreichung (HundeboerseController::
 * store()) erzeugt ausschliesslich NEUE Zeilen mit frischer ID, kollidiert
 * also nie mit einer hier bearbeiteten bestehenden Anzeige.
 *
 * ALT-ADMIN-ANALYSE (admin.js, Abschnitt "HUNDEBOERSE"): eigener Bereich mit
 * - Statusfilter-Reiter: Alle/Wartet/Veroeffentlicht/Abgelehnt/Archiviert
 *   (HB_STATUS) - 1:1 uebernommen (siehe index()).
 * - Liste: Thumbnail (erstes Galeriebild), Titel, Typ-Badge (Einzelhund/
 *   Wurf), Status-Badge, Rasse, PLZ/Ort, Erstelldatum. Aktionen pro Zeile:
 *   Bearbeiten (immer), Freigeben (nur bei "pending", mit Bestaetigung),
 *   Archivieren (nur bei "published"/"rejected", OHNE Bestaetigung),
 *   Loeschen (immer, mit Bestaetigung). "Vorschau" (Modal) existiert im
 *   alten Admin ebenfalls, wird hier bewusst NICHT nachgebaut - sie zeigt
 *   nur bereits im Formular sichtbare Felder erneut an und liefert keinen
 *   Mehrwert gegenueber der echten oeffentlichen Detailseite (fuer bereits
 *   veroeffentlichte Anzeigen per "Ansehen"-Link erreichbar, siehe View).
 * - "Wiederherstellen" (archiviert -> anderer Status): existiert im alten
 *   Admin NICHT als eigener Button - archivierte Anzeigen werden ueber das
 *   ganz normale Bearbeiten-Formular samt Status-Dropdown zurueckgestuft
 *   (HB_STATUS ist im Editor IMMER frei waehlbar, siehe hundeboerseEdit()).
 *   Genau dieses Verhalten wird hier 1:1 uebernommen: es gibt keine eigene
 *   "wiederherstellen()"-Route, sondern eine ganz normale update() ueber das
 *   Status-Dropdown (Auftrag: "Nur Funktionen bauen, die tatsaechlich
 *   existieren").
 * - Editor (Anlegen UND Bearbeiten teilen sich dieselbe Maske, siehe
 *   admin.js' hundeboerseNeu() -> hundeboerseEdit(0)): Status-Dropdown,
 *   Einzelhund/Wurf-Umschalter mit bedingten Feldern, Rasse/Farbe/Haarart,
 *   Preis(-art)/Standort, jagdliche Angaben, Abstammung inkl. Zuchtverband-
 *   Vorschlagsliste, Markdown-Beschreibung (hier: Freitext-Textarea - kein
 *   neuer Markdown-Editor, siehe admin/aktuelles/bearbeiten.blade.php-
 *   Praezedenzfall "kein neues JS"), Bildergalerie (bis 10 Bilder),
 *   Anbieter-/Kontaktdaten. Speichern-Varianten: "Speichern" (Status bleibt
 *   wie im Dropdown gewaehlt), "Speichern & Freigeben" (setzt zusaetzlich
 *   status=published), "Anzeige ablehnen" (setzt zusaetzlich
 *   status=rejected) - admin.js fuehrt bei den letzten beiden IMMER zuerst
 *   ein volles Feld-Speichern durch (hundeboerseCollect()), erst DANACH den
 *   Statuswechsel - hier 1:1 nachgebildet als EIN update()-Aufruf mit
 *   optionalem "aktion"-Feld (siehe update()), damit gleichzeitig bearbeitete
 *   Felder dabei nie verloren gehen.
 * - Geokodierung (lat/lng via Nominatim beim Speichern im alten Admin):
 *   WEDER die oeffentliche Einreichung noch dieses Admin-Modul fuehren das
 *   fort - HundeboerseController::store() (oeffentlich, Phase 6A) setzt
 *   lat/lng bereits gar nicht mehr, es gibt aktuell KEINE Laravel-seitige
 *   Geokodierungs-Pipeline. Das Nachruesten waere eine neue Fachfunktion
 *   ueber das hinaus, was die oeffentliche Seite bereits tut - bewusst nicht
 *   Teil dieser Phase (Auftrag "keine neue Architektur"). Die Karten-Anzeige
 *   auf der Detailseite faellt fuer Admin-gepflegte/neu eingereichte
 *   Anzeigen ohne lat/lng schlicht weg (vorbestehende Luecke, keine neue
 *   Regression durch dieses Modul).
 * - Validierung: der alte Admin validiert Hundeboerse-Felder UEBERHAUPT
 *   NICHT (reine JS-Objekt-Zuweisung ohne validate()-Aufruf, anders als die
 *   oeffentliche Einreichung/anbieten.html) - dieses Modul uebernimmt das:
 *   nur strukturelle Regeln (Status-/Typ-/Preisart-Enum, Bildanzahl/-typ)
 *   werden erzwungen, keine Pflichtfelder fuer Titel/Rasse/Beschreibung/etc.
 *   (Auftrag "bestehendes Alt-Verhalten exakt uebernehmen", nicht strenger
 *   machen als es je war).
 *
 * BILDER (Auftrag Punkt E/7): nutzt ausschliesslich die bereits bestehende
 * App\Support\BoerseUploads (store() fuer neue Bilder, in dieser Phase um
 * delete() ergaenzt, siehe dortiger Klassenkommentar) - keine neue Upload-
 * Architektur, keine allgemeine Medienbibliothek. Entfernen einzelner Bilder
 * ist NEU (bisher loeschte kein Laravel-Code jemals ein Boersen-Bild) und
 * wird zusammen mit dem restlichen Formular in EINER update()-Transaktion
 * verarbeitet (Preservation: nichts passiert vor dem Klick auf "Speichern",
 * exakt wie im alten JSON-Admin, wo eine entfernte Galerie-Zeile ebenfalls
 * erst beim naechsten Speichern persistiert wird).
 */
class HundeboerseController extends Controller
{
    private const STATUS_OPTIONEN = [
        'pending' => 'Wartet auf Freigabe',
        'published' => 'Veröffentlicht',
        'rejected' => 'Abgelehnt',
        'archived' => 'Archiviert',
    ];

    public function index(Request $request): View
    {
        $alle = HundeboerseAnzeige::with('bilder')->orderByDesc('created_at')->get();

        $filter = (string) $request->query('status', '');
        $anzeigen = ($filter !== '' && array_key_exists($filter, self::STATUS_OPTIONEN))
            ? $alle->where('status', $filter)->values()
            : $alle;

        return view('admin.hundeboerse.index', [
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
        return view('admin.hundeboerse.bearbeiten', [
            'anzeige' => new HundeboerseAnzeige([
                'status' => 'pending',
                'type' => 'single',
                'price_type' => 'on_request',
                'has_zuchtverband' => false,
                'gallery_title' => 'Bilder',
            ]),
            'istNeu' => true,
            'statusOptionen' => self::STATUS_OPTIONEN,
            'zuchtverbaende' => HundeboerseZuchtverband::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validierteDaten($request);

        $anzeige = DB::transaction(function () use ($request, $data) {
            $neu = new HundeboerseAnzeige(['id' => HundeboerseUpdater::neueId()]);
            HundeboerseUpdater::applyFields($neu, $data);
            HundeboerseUpdater::addBilder($neu, $request->file('images', []));

            return $neu;
        });

        return redirect()->route('admin.hundeboerse.bearbeiten', $anzeige)->with('status', 'Anzeige angelegt.');
    }

    public function edit(HundeboerseAnzeige $hundeboerseAnzeige): View
    {
        return view('admin.hundeboerse.bearbeiten', [
            'anzeige' => $hundeboerseAnzeige->load('bilder'),
            'istNeu' => false,
            'statusOptionen' => self::STATUS_OPTIONEN,
            'zuchtverbaende' => HundeboerseZuchtverband::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Siehe Klassenkommentar "Editor": ein einziger Endpunkt fuer alle drei
     * Speichern-Varianten der Bearbeiten-Maske. "aktion" kommt aus dem Namen
     * des jeweils geklickten Submit-Buttons (siehe bearbeiten.blade.php) und
     * ueberschreibt den per Dropdown gewaehlten Status ERST NACH dem
     * Preservation-Merge - identisch zur Reihenfolge in admin.js'
     * hundeboerseFreigebenAusEdit()/hundeboerseAblehnen() (erst
     * hundeboerseCollect(), dann Status setzen), nur als ein Request/eine
     * Transaktion statt zwei sequenzieller Schritte.
     */
    public function update(Request $request, HundeboerseAnzeige $hundeboerseAnzeige): RedirectResponse
    {
        $data = array_merge($this->currentFieldValues($hundeboerseAnzeige), $this->validierteDaten($request));

        $aktion = $request->input('aktion');
        if ($aktion === 'freigeben') {
            $data['status'] = 'published';
        } elseif ($aktion === 'ablehnen') {
            $data['status'] = 'rejected';
        }

        $bildIdsEntfernen = array_map('intval', (array) $request->input('bild_entfernen', []));

        $anzahlNachher = $hundeboerseAnzeige->bilder()->whereNotIn('id', $bildIdsEntfernen)->count()
            + count($request->file('images', []));
        if ($anzahlNachher > HundeboerseUpdater::MAX_BILDER) {
            return back()->withInput()->withErrors([
                'images' => 'Es können maximal '.HundeboerseUpdater::MAX_BILDER.' Bilder pro Anzeige gespeichert werden.',
            ]);
        }

        // TRANSAKTIONSSICHERHEIT (Korrektur nach Auslieferung, siehe
        // HundeboerseUpdater::removeBilder()-Klassenkommentar): physische
        // Dateien werden bewusst NICHT innerhalb der DB-Transaktion
        // geloescht, weil ein Dateisystem-Loeschen bei einem spaeteren
        // Rollback nicht rueckgaengig gemacht werden kann. Stattdessen
        // liefert removeBilder() nur die Pfade zurueck; die physische
        // Loeschung passiert unten ERST, nachdem DB::transaction()
        // erfolgreich (ohne Exception) zurueckgekehrt ist.
        $pfadeZumLoeschen = DB::transaction(function () use ($request, $hundeboerseAnzeige, $data, $bildIdsEntfernen) {
            $pfade = HundeboerseUpdater::removeBilder($hundeboerseAnzeige, $bildIdsEntfernen);
            HundeboerseUpdater::applyFields($hundeboerseAnzeige, $data);
            HundeboerseUpdater::addBilder($hundeboerseAnzeige, $request->file('images', []));

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

        return redirect()->route('admin.hundeboerse.bearbeiten', $hundeboerseAnzeige)->with('status', $meldung);
    }

    /**
     * Listen-Schnellaktion (siehe Klassenkommentar "Alt-Admin-Analyse") -
     * OHNE Formularfelder, 1:1 wie admin.js' hundeboerseFreigeben()
     * (Bestaetigungsdialog clientseitig, siehe index.blade.php). Nur aus der
     * Liste erreichbar - die Editor-Variante laeuft ueber update() (siehe
     * dort).
     */
    public function freigeben(HundeboerseAnzeige $hundeboerseAnzeige): RedirectResponse
    {
        $hundeboerseAnzeige->update(['status' => 'published']);

        return back()->with('status', 'Anzeige freigegeben.');
    }

    /**
     * Listen-Schnellaktion, 1:1 wie admin.js' hundeboerseArchivieren() -
     * bewusst OHNE Bestaetigungsdialog (der alte Admin fragt hier ebenfalls
     * nicht nach, anders als bei Freigeben/Loeschen).
     */
    public function archivieren(HundeboerseAnzeige $hundeboerseAnzeige): RedirectResponse
    {
        $hundeboerseAnzeige->update(['status' => 'archived']);

        return back()->with('status', 'Anzeige archiviert.');
    }

    /**
     * 1:1 wie admin.js' hundeboerseDelete() (mit Bestaetigungsdialog, siehe
     * View) - loescht zusaetzlich die physischen Bilddateien (Original +
     * Varianten); die hundeboerse_bilder-Zeilen selbst werden durch die
     * bestehende Fremdschluessel-Kaskade (cascadeOnDelete, siehe Migration)
     * automatisch mitentfernt.
     *
     * TRANSAKTIONSSICHERHEIT (Korrektur nach Auslieferung, vom Auftraggeber
     * gefunden): die Bildpfade werden VOR der Transaktion eingesammelt, die
     * Transaktion selbst loescht ausschliesslich die Anzeige-Zeile (die
     * hundeboerse_bilder-Zeilen verschwinden dabei automatisch per FK-
     * Kaskade) - physische Dateien werden erst NACH einem erfolgreichen
     * Commit entfernt. Vorher wurden die Dateien noch INNERHALB der
     * Transaktion geloescht; da ein Dateisystem-Loeschen bei einem
     * Rollback nicht rueckgaengig gemacht werden kann, waere bei einem
     * spaeteren DB-Fehler in derselben Transaktion eine physische Datei
     * unwiederbringlich verloren gegangen, obwohl die DB-Zeile dank
     * Rollback weiterhin existiert haette (Datenverlust-Risiko bei echten
     * Kundendateien).
     */
    public function destroy(HundeboerseAnzeige $hundeboerseAnzeige): RedirectResponse
    {
        $pfade = $hundeboerseAnzeige->bilder->pluck('pfad')->all();

        DB::transaction(function () use ($hundeboerseAnzeige) {
            $hundeboerseAnzeige->delete();
        });

        foreach ($pfade as $pfad) {
            BoerseUploads::delete($pfad);
        }

        return redirect()->route('admin.hundeboerse.index')->with('status', 'Anzeige gelöscht.');
    }

    /**
     * Siehe Klassenkommentar "Validierung": nur strukturelle Regeln, keine
     * inhaltlichen Pflichtfelder (1:1 wie der alte, ungeprueft schreibende
     * JSON-Admin). $request->validate() dient hier ausschliesslich der
     * Fehlerpruefung - die eigentlichen Werte kommen bewusst per only() aus
     * dem Request (siehe TermineController::validateData()-Vorbild), damit
     * ein im Teil-Request fehlendes Feld beim Preservation-Merge in update()
     * nicht faelschlich NULL setzt.
     *
     * @return array<string, mixed>
     */
    private function validierteDaten(Request $request): array
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,published,rejected,archived'],
            'type' => ['required', 'string', 'in:single,litter'],
            'price_type' => ['required', 'string', 'in:fixed,negotiable,on_request,none'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'litter_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:190'],
            'images' => ['nullable', 'array', 'max:'.HundeboerseUpdater::MAX_BILDER],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $data = $request->only([
            'status', 'type', 'title', 'breed', 'color', 'coat',
            'price_type', 'price', 'postal_code', 'city', 'description',
            'father', 'father_tests', 'mother', 'mother_tests',
            'hunting_tests', 'training_level',
            'provider_name', 'contact_person', 'email', 'phone', 'contact_notes',
            'dog_name', 'birth_date', 'gender',
            'litter_date', 'male_count', 'female_count',
            'gallery_title', 'zuchtverband',
        ]);

        // "has_zuchtverband" (echte Checkbox, siehe bearbeiten.blade.php'
        // verdeckter "0"-Fallback-Guard, wie PartnerController::
        // validateData()) - nur anfassen, wenn tatsaechlich im Request
        // vorhanden (Preservation bei Teil-Requests/Tests).
        if ($request->has('has_zuchtverband')) {
            $data['has_zuchtverband'] = $request->boolean('has_zuchtverband');
            $data['zuchtverband'] = $data['has_zuchtverband'] ? trim((string) ($data['zuchtverband'] ?? '')) : '';
        } else {
            unset($data['zuchtverband']);
        }

        if (array_key_exists('birth_date', $data)) {
            $data['birth_date'] = HundeboerseUpdater::normalizeDatum($data['birth_date']);
        }
        if (array_key_exists('litter_date', $data)) {
            $data['litter_date'] = HundeboerseUpdater::normalizeDatum($data['litter_date']);
        }

        return $data;
    }

    /**
     * Aktuelle Werte aller von HundeboerseUpdater::applyFields() verwalteten
     * Felder - siehe Klassenkommentar "Preservation".
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(HundeboerseAnzeige $anzeige): array
    {
        return [
            'status' => $anzeige->status,
            'type' => $anzeige->type,
            'title' => $anzeige->title,
            'breed' => $anzeige->breed,
            'color' => $anzeige->color,
            'coat' => $anzeige->coat,
            'price_type' => $anzeige->price_type,
            'price' => $anzeige->price,
            'postal_code' => $anzeige->postal_code,
            'city' => $anzeige->city,
            'description' => $anzeige->description,
            'father' => $anzeige->father,
            'father_tests' => $anzeige->father_tests,
            'mother' => $anzeige->mother,
            'mother_tests' => $anzeige->mother_tests,
            'hunting_tests' => $anzeige->hunting_tests,
            'training_level' => $anzeige->training_level,
            'provider_name' => $anzeige->provider_name,
            'contact_person' => $anzeige->contact_person,
            'email' => $anzeige->email,
            'phone' => $anzeige->phone,
            'contact_notes' => $anzeige->contact_notes,
            'dog_name' => $anzeige->dog_name,
            'birth_date' => $anzeige->birth_date,
            'gender' => $anzeige->gender,
            'litter_date' => $anzeige->litter_date,
            'male_count' => $anzeige->male_count,
            'female_count' => $anzeige->female_count,
            'gallery_title' => $anzeige->gallery_title,
            'has_zuchtverband' => $anzeige->has_zuchtverband,
            'zuchtverband' => $anzeige->zuchtverband,
        ];
    }
}
