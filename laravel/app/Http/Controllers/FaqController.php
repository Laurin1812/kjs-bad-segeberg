<?php

namespace App\Http\Controllers;

use App\Models\FaqKategorie;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt faq/index.html - reines Read-only-Akkordeon, keine Business-
 * Logik zu portieren (siehe Api\ContentController::faq() fuer das
 * identische Datenmodell).
 */
class FaqController extends Controller
{
    public function index(): View
    {
        $kategorien = FaqKategorie::with(['fragen' => function ($q) {
            $q->orderBy('sortierung');
        }])
            ->orderBy('sortierung')
            ->get();

        return view('faq', ['kategorien' => $kategorien]);
    }
}
