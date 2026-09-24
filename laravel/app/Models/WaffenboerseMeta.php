<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['version'])]
// ══════════════════════════════════════════════════════════════════════
// WEITERHIN DORMANT (Korrektur in Phase 7J): dieser Klassenkommentar
// behauptete urspruenglich, die GESAMTE Waffenboerse sei noch PHP-only -
// das ist seit Phase 6B ueberholt (siehe WaffenboerseAnzeige-
// Klassenkommentar: die oeffentliche Waffenboerse UND seit Phase 7J der
// Blade-Admin nutzen bereits aktiv waffenboerse_anzeigen/-bilder/-kaliber/
// -kategorien). NUR DIESE Tabelle bleibt tatsaechlich ungenutzt: anders als
// HundeboerseMeta (liefert "hero_bild" fuer den oeffentlichen Seitenkopf,
// siehe HundeboerseController::heroBild()) hat waffenboerse_meta nicht
// einmal eine "hero_bild"-Spalte - nur den ungenutzten Versionszaehler
// "version". Kein Controller/keine Route liest oder schreibt dieses Model.
// Siehe docs/deployment/dormante-boersen-tabellen.md fuer die
// vollstaendige, aktuelle Einordnung.
// ══════════════════════════════════════════════════════════════════════
class WaffenboerseMeta extends Model
{
    protected $table = 'waffenboerse_meta';

    public $incrementing = false;

    public $timestamps = false;
}
