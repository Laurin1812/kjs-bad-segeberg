<?php

namespace App\Http\Controllers;

use App\Http\Requests\HundeboerseAnbietenRequest;
use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseBild;
use App\Models\HundeboerseMeta;
use App\Models\HundeboerseZuchtverband;
use App\Support\BoerseUploads;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 6A (Sondermodule inventarisieren + Hundeboerse
 * auf Laravel/MySQL).
 *
 * Ersetzt hundeboerse/index.html + hundeboerse/detail.html + hundeboerse/
 * anbieten.html - "Route -> Controller -> Eloquent -> MySQL -> Blade" fuer
 * die komplette oeffentliche Hundeboerse (siehe HundeboerseAnzeige-
 * Klassenkommentar). Keine Laufzeit-Abhaengigkeit mehr von content/
 * hundeboerse.json oder api/hundeboerse/anzeigen.php - beide waren bisher
 * die tatsaechliche Datenquelle (siehe Abschlussbericht Punkt "Bestandsdaten"
 * fuer die vollstaendige Einordnung: content/hundeboerse.json enthielt zum
 * Zeitpunkt dieser Migration keine echten Anzeigen, die separate PHP+MySQL-
 * Produktivdatenbank ist von dieser Sandbox aus nicht erreichbar).
 *
 * Oeffentlich sichtbar sind ausschliesslich Anzeigen mit status=published -
 * "wartet"/"abgelehnt"/"archiviert" (pending/rejected/archived) liefern hier
 * konsequent eine echte Laravel-404, nie einen leeren/verschleierten Zustand
 * (siehe index()/show()). Die Admin-Bearbeitung (Freigeben/Ablehnen/
 * Archivieren) ist ausdruecklich NICHT Teil dieser Phase (siehe Auftrag) -
 * die Datenstruktur (status-Enum, siehe Migration) unterstuetzt sie bereits
 * vollstaendig fuer eine spaetere Laravel-Admin-Migration.
 */
class HundeboerseController extends Controller
{
    public function index(): View
    {
        $anzeigen = HundeboerseAnzeige::where('status', 'published')
            ->with('bilder')
            ->orderByDesc('created_at')
            ->get();

        return view('hundeboerse.index', [
            'anzeigen' => $anzeigen,
            'heroBild' => $this->heroBild(),
        ]);
    }

    public function show(string $id): View
    {
        $anzeige = HundeboerseAnzeige::where('id', $id)
            ->where('status', 'published')
            ->with('bilder')
            ->firstOrFail();

        return view('hundeboerse.show', [
            'anzeige' => $anzeige,
            'heroBild' => $this->heroBild(),
        ]);
    }

    public function createForm(): View
    {
        return view('hundeboerse.anbieten', [
            'heroBild' => $this->heroBild(),
            'zuchtverbaende' => HundeboerseZuchtverband::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Oeffentliche Einreichung "Hund / Wurf anbieten". Neue Anzeigen landen
     * IMMER zunaechst im Status "pending" (Wartet) - niemals direkt
     * veroeffentlicht (siehe Auftrag) - der Client kann diesen Wert nicht
     * beeinflussen, da "status" bewusst NICHT Teil von
     * HundeboerseAnbietenRequest::rules() ist und hier fest gesetzt wird.
     */
    public function store(HundeboerseAnbietenRequest $request): RedirectResponse
    {
        $daten = $request->validated();

        // Honeypot (siehe api/hundeboerse/anzeigen.php-Vorbild): ein
        // ausgefuelltes, fuer Menschen unsichtbares Feld deutet auf einen
        // Bot hin - stillschweigende Erfolgsmeldung OHNE zu speichern,
        // damit ein Bot keinen Unterschied zwischen "abgelehnt" und
        // "angenommen" erkennen kann.
        if (trim((string) ($daten['_honey'] ?? '')) !== '') {
            return redirect()->route('hundeboerse.anbieten')->with('hb_success', true);
        }

        $typ = $daten['type'];
        $hatZuchtverband = (bool) ($daten['hasZuchtverband'] ?? false);
        $zuchtverband = $hatZuchtverband ? trim((string) ($daten['zuchtverband'] ?? '')) : '';

        $anzeige = DB::transaction(function () use ($request, $daten, $typ, $hatZuchtverband, $zuchtverband) {
            $anzeige = HundeboerseAnzeige::create([
                'id' => 'hb-'.round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
                'status' => 'pending',
                'type' => $typ,
                'title' => $daten['title'],
                'breed' => $daten['breed'],
                'color' => (string) ($daten['color'] ?? ''),
                'coat' => (string) ($daten['coat'] ?? ''),
                'price_type' => $daten['priceType'],
                'price' => isset($daten['price']) && $daten['price'] !== '' ? (string) $daten['price'] : '',
                'postal_code' => $daten['postalCode'],
                'city' => $daten['city'],
                'description' => $daten['description'],
                'father' => (string) ($daten['father'] ?? ''),
                'father_tests' => (string) ($daten['fatherTests'] ?? ''),
                'mother' => (string) ($daten['mother'] ?? ''),
                'mother_tests' => (string) ($daten['motherTests'] ?? ''),
                'hunting_tests' => (string) ($daten['huntingTests'] ?? ''),
                'training_level' => (string) ($daten['trainingLevel'] ?? ''),
                'provider_name' => $daten['providerName'],
                'contact_person' => (string) ($daten['contactPerson'] ?? ''),
                'email' => $daten['email'],
                'phone' => (string) ($daten['phone'] ?? ''),
                'contact_notes' => (string) ($daten['contactNotes'] ?? ''),
                'dog_name' => $typ === 'single' ? (string) ($daten['dogName'] ?? '') : '',
                'birth_date' => $typ === 'single' ? $this->normalizeDatum($daten['birthDate'] ?? null) : '',
                'gender' => $typ === 'single' ? (string) ($daten['gender'] ?? '') : '',
                'litter_date' => $typ === 'litter' ? $this->normalizeDatum($daten['litterDate'] ?? null) : '',
                'male_count' => $typ === 'litter' ? (string) ($daten['maleCount'] ?? '') : '',
                'female_count' => $typ === 'litter' ? (string) ($daten['femaleCount'] ?? '') : '',
                'gallery_title' => 'Bilder',
                'has_zuchtverband' => $hatZuchtverband,
                'zuchtverband' => $zuchtverband,
            ]);

            $bilder = BoerseUploads::store($request->file('images', []), 'hundeboerse');
            foreach (array_values($bilder) as $i => $bild) {
                HundeboerseBild::create([
                    'anzeige_id' => $anzeige->id,
                    'pfad' => $bild['pfad'],
                    'titel' => $bild['titel'],
                    'sortierung' => $i,
                ]);
            }

            // Wachsende Zuchtverband-Vorschlagsliste (1:1 wie im PHP-
            // Original, siehe HundeboerseZuchtverband-Klassenkommentar) -
            // firstOrCreate statt create verhindert Dubletten bei
            // mehrfacher Nennung desselben Namens.
            if ($zuchtverband !== '') {
                HundeboerseZuchtverband::firstOrCreate(['name' => $zuchtverband]);
            }

            return $anzeige;
        });

        unset($anzeige);

        return redirect()->route('hundeboerse.anbieten')->with('hb_success', true);
    }

    private function heroBild(): ?string
    {
        return HundeboerseMeta::find(1)?->hero_bild;
    }

    /**
     * Wandelt ein Datum aus einem <input type="date"> (ISO "YYYY-MM-DD")
     * in das im bestehenden Datenmodell verwendete Format "DD.MM.YYYY" um
     * (1:1 Port von kjs_boerse_iso_date_to_de() aus api/lib/response.php).
     */
    private function normalizeDatum(?string $iso): string
    {
        if (! $iso) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $iso)->format('d.m.Y');
        } catch (\Throwable) {
            return '';
        }
    }
}
