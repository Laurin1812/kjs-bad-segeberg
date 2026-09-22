<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaffenboerseAnbietenRequest;
use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseBild;
use App\Models\WaffenboerseKaliber;
use App\Models\WaffenboerseKategorie;
use App\Support\BoerseUploads;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 6B (Waffenboerse auf Laravel/MySQL).
 *
 * Ersetzt waffenboerse/index.html + waffenboerse/detail.html +
 * waffenboerse/anbieten.html - "Route -> Controller -> Eloquent -> MySQL
 * -> Blade" fuer die komplette oeffentliche Waffenboerse, analog zu
 * HundeboerseController (Phase 6A). Keine Laufzeit-Abhaengigkeit mehr von
 * content/waffenboerse.json oder api/waffenboerse/anzeigen.php - beide
 * waren bisher die tatsaechliche Datenquelle (siehe Abschlussbericht Punkt
 * "Bestandsanzeigen" fuer die vollstaendige Einordnung).
 *
 * Oeffentlich sichtbar sind ausschliesslich Anzeigen mit status=published -
 * "pending"/"rejected"/"archived" liefern hier konsequent eine echte
 * Laravel-404, nie einen leeren/verschleierten Zustand (siehe index()/
 * show()). Die Admin-Bearbeitung (Freigeben/Ablehnen/Archivieren) ist
 * ausdruecklich NICHT Teil dieser Phase (siehe Auftrag) - die
 * Datenstruktur (status-Enum, siehe Migration) unterstuetzt sie bereits
 * vollstaendig fuer eine spaetere Laravel-Admin-Migration.
 *
 * Der client-seitige Filter-/Sortier-Leiste der alten Uebersicht
 * (Kategorie/Hersteller/Kaliber/Zustand/Sortierung, ".wb-filter-bar") wird
 * bewusst NICHT uebernommen - eine serverseitige Variante waere eine neue,
 * ueber den Auftrag ("keine neue komplexe Generalisierung") hinausgehende
 * Funktion; dieselbe Vereinfachung wurde bereits bei der Hundeboerse
 * (Phase 6A, Wegfall der Bild-Drag&Drop-Neusortierung) vorgenommen. Die
 * Uebersicht zeigt weiterhin ALLE veroeffentlichten Anzeigen, neueste
 * zuerst.
 */
class WaffenboerseController extends Controller
{
    public function index(): View
    {
        $anzeigen = WaffenboerseAnzeige::where('status', 'published')
            ->with(['bilder', 'kaliber'])
            ->orderByDesc('erstellt_am')
            ->get();

        return view('waffenboerse.index', [
            'anzeigen' => $anzeigen,
        ]);
    }

    public function show(string $id): View
    {
        $anzeige = WaffenboerseAnzeige::where('id', $id)
            ->where('status', 'published')
            ->with(['bilder', 'kaliber'])
            ->firstOrFail();

        return view('waffenboerse.show', [
            'anzeige' => $anzeige,
        ]);
    }

    public function createForm(): View
    {
        return view('waffenboerse.anbieten', [
            'kategorien' => WaffenboerseKategorie::orderBy('sortierung')->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Oeffentliche Einreichung "Waffe anbieten". Neue Anzeigen landen
     * IMMER zunaechst im Status "pending" - niemals direkt veroeffentlicht
     * (siehe Auftrag) - der Client kann diesen Wert nicht beeinflussen, da
     * "status" bewusst NICHT Teil von WaffenboerseAnbietenRequest::rules()
     * ist und hier fest gesetzt wird.
     */
    public function store(WaffenboerseAnbietenRequest $request): RedirectResponse
    {
        $daten = $request->validated();

        // Honeypot (siehe api/waffenboerse/anzeigen.php-Vorbild): ein
        // ausgefuelltes, fuer Menschen unsichtbares Feld deutet auf einen
        // Bot hin - stillschweigende Erfolgsmeldung OHNE zu speichern.
        if (trim((string) ($daten['_honey'] ?? '')) !== '') {
            return redirect()->route('waffenboerse.anbieten')->with('wb_success', true);
        }

        $versandMoeglich = (bool) ($daten['versand_moeglich'] ?? false);
        $erwerbErforderlich = (bool) ($daten['erwerbsberechtigung_erforderlich'] ?? false);

        // Freitext -> sicheres Absatz-HTML, exakt wie im PHP-Original
        // (kjs_wb_text_to_safe_html() - siehe Text::freeTextToSafeParagraphs()).
        // NIE das rohe Freitext-Feld direkt speichern.
        $beschreibung = Text::freeTextToSafeParagraphs((string) $daten['beschreibung']);

        DB::transaction(function () use ($request, $daten, $versandMoeglich, $erwerbErforderlich, $beschreibung) {
            $anzeige = WaffenboerseAnzeige::create([
                'id' => 'wb-'.round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
                'status' => 'pending',
                'titel' => $daten['titel'],
                'kategorie' => $daten['kategorie'],
                'hersteller' => $daten['hersteller'],
                'modell' => (string) ($daten['modell'] ?? ''),
                'zustand' => $daten['zustand'],
                'preis' => (string) $daten['preis'],
                'preis_typ' => $daten['preis_typ'],
                'erwerbsberechtigung_erforderlich' => $erwerbErforderlich,
                'beschreibung' => $beschreibung,
                'plz' => $daten['plz'],
                'ort' => $daten['ort'],
                'versand_moeglich' => $versandMoeglich,
                'versandkosten' => $versandMoeglich ? (string) ($daten['versandkosten'] ?? '') : '',
                'anbieter_name' => $daten['anbieter_name'],
                'anbieter_email' => $daten['anbieter_email'],
                'anbieter_telefon' => (string) ($daten['anbieter_telefon'] ?? ''),
            ]);

            $bilder = BoerseUploads::store($request->file('images', []), 'waffenboerse');
            foreach (array_values($bilder) as $i => $bild) {
                WaffenboerseBild::create([
                    'anzeige_id' => $anzeige->id,
                    'pfad' => $bild['pfad'],
                    'titel' => $bild['titel'],
                    'sortierung' => $i,
                ]);
            }

            $kaliberListe = array_values(array_filter(array_map(
                static fn ($k) => trim((string) $k),
                $daten['kaliber'] ?? []
            ), static fn ($k) => $k !== ''));
            foreach ($kaliberListe as $i => $kaliber) {
                WaffenboerseKaliber::create([
                    'anzeige_id' => $anzeige->id,
                    'kaliber' => $kaliber,
                    'sortierung' => $i,
                ]);
            }
        });

        return redirect()->route('waffenboerse.anbieten')->with('wb_success', true);
    }
}
