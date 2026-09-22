<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt partner/index.html + partner/detail.html. Detail-Lookup laeuft
 * ueber "external_id" (das urspruengliche "id"-Feld aus content/
 * partner.json, Format "pn-<timestamp>") statt der internen Eloquent-ID -
 * das entspricht 1:1 der bisherigen "?id="-Verlinkung und bleibt dadurch
 * fuer bestehende externe Links/Lesezeichen kompatibel.
 *
 * "rahmenvertrag"/"vorteile" werden hier als echte typisierte Werte
 * (boolean/Collection) an die View gereicht statt wie in der JSON-Read-API
 * (Api\ContentController::partner()) kuenstlich auf '' zurueckgemappt zu
 * werden - Blades @if()-Wahrheitspruefung behandelt false/leeres Array
 * ohnehin identisch zu einem leeren String, der String-Hack war nur fuer
 * den strikten "wert === ''"-Vergleich der alten Vanilla-JS-Detailseite
 * noetig (siehe dortiger Kommentar).
 */
class PartnerController extends Controller
{
    public function index(): View
    {
        $partner = Partner::where('aktiv', true)
            ->orderBy('sortierung')
            ->get();

        return view('partner.index', ['partner' => $partner]);
    }

    public function show(string $externalId): View
    {
        $partner = Partner::with('vorteile')
            ->where('external_id', $externalId)
            ->where('aktiv', true)
            ->firstOrFail();

        return view('partner.show', ['partner' => $partner]);
    }
}
