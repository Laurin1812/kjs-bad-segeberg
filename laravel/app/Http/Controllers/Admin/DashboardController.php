<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beitrag;
use App\Models\HundeboerseAnzeige;
use App\Models\KontaktAnfrage;
use App\Models\Page;
use App\Models\Termin;
use App\Models\WaffenboerseAnzeige;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Erstes/einziges echte Modul der neuen Blade-Admin-Shell in dieser Phase
 * (Auftragspunkt "Teil 4 - Admin-Shell"). Zeigt AUSSCHLIESSLICH Zahlen, die
 * bereits 1:1 ueber bestehende Eloquent-Models verfuegbar sind (siehe
 * Auftrag: "Nur wenn diese Werte bereits sauber ueber Eloquent verfuegbar
 * sind. Keine Fake-Zahlen.") - kein einziges neues Model/keine neue Tabelle
 * fuer diese Phase:
 *   - Seiten:                Page::count() (Phase-3-Vereinheitlichung der
 *                             gesamten "seiten-*"-Registry-Familie).
 *   - Aktuelles:              Beitrag::where('typ','aktuelles') - siehe
 *                             AktuellesController::index().
 *   - Termine:                Termin::count() - siehe TermineController.
 *   - Offene Kontaktanfragen: KontaktAnfrage::where('status','neu') -
 *                             derselbe Status-Wert, den KontaktController::
 *                             store() beim Anlegen hart hinterlegt (siehe
 *                             dortiger Kommentar); "offen" == "neu" (Status
 *                             "bearbeitet" ist das einzige Gegenstueck, per
 *                             Migrations-Enum ['neu','bearbeitet']).
 *   - Pending Hundeboerse/
 *     Waffenboerse:           status='pending' - derselbe Wert, den
 *                             HundeboerseController::store()/
 *                             WaffenboerseController::store() beim Anlegen
 *                             hart hinterlegen (Gegenstueck zu 'published',
 *                             siehe dortige Klassenkommentare).
 *
 * Die eigentliche Freigabe/Bearbeitung dieser Anfragen/Anzeigen ist
 * explizit NICHT Teil von Phase 7A (siehe Auftrag "NICHT JETZT") - das
 * Dashboard zeigt bewusst nur die Zahl, keinen Link zu einer noch nicht
 * existierenden Verwaltungsseite.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'anzahlSeiten' => Page::count(),
            'anzahlAktuelles' => Beitrag::where('typ', 'aktuelles')->count(),
            'anzahlTermine' => Termin::count(),
            'anzahlOffeneKontaktanfragen' => KontaktAnfrage::where('status', 'neu')->count(),
            'anzahlPendingHundeboerse' => HundeboerseAnzeige::where('status', 'pending')->count(),
            'anzahlPendingWaffenboerse' => WaffenboerseAnzeige::where('status', 'pending')->count(),
        ]);
    }
}
