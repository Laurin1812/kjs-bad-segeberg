<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Ersetzt jaeger/vorstand.html + die analoge Obleute-Seite. Beide teilen
 * dasselbe Datenmodell ("personen"-Tabelle, "gremium" unterscheidet
 * vorstand/obmann - siehe Api\ContentController::personenGremium()) und
 * dieselbe Kartenoptik (.persons-grid/.person-card, siehe app.css) -
 * deshalb ein gemeinsamer Controller mit zwei duennen Actions statt zweier
 * fast identischer Klassen.
 */
class PersonenGremiumController extends Controller
{
    public function vorstand(): View
    {
        return view('jaeger.vorstand', [
            'mitglieder' => $this->personen('vorstand'),
        ]);
    }

    public function obleute(): View
    {
        return view('jaeger.obleute', [
            'mitglieder' => $this->personen('obmann'),
        ]);
    }

    private function personen(string $gremium)
    {
        return Person::where('gremium', $gremium)
            ->orderBy('sortierung')
            ->get();
    }
}
