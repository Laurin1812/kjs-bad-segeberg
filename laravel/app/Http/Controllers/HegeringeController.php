<?php

namespace App\Http\Controllers;

use App\Models\Hegering;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt die Hegeringe-Uebersichtsseite (jaeger/hegeringe.html), reines
 * Read-only-Kartenraster ohne Business-Logik - siehe Api\ContentController::
 * hegeringe() fuer das identische Datenmodell.
 */
class HegeringeController extends Controller
{
    public function index(): View
    {
        $hegeringe = Hegering::orderBy('sortierung')->get();

        return view('jaeger.hegeringe', ['hegeringe' => $hegeringe]);
    }
}
