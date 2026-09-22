<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Genau 1 Zeile (id=1): Hero-Bild + optimistischer Versionszaehler.
#[Fillable(['version', 'hero_bild'])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6A - AKTIVIERT: siehe HundeboerseAnzeige-Klassenkommentar. Genau 1
// Zeile (id=1), liefert aktuell nur "hero_bild" fuer den Seitenkopf der
// oeffentlichen Hundeboerse-Seiten; "version" bleibt ungenutzt (keine
// Laravel-Admin-Oberflaeche in dieser Phase, siehe Auftrag).
// ══════════════════════════════════════════════════════════════════════
class HundeboerseMeta extends Model
{
    protected $table = 'hundeboerse_meta';

    public $incrementing = false;

    public $timestamps = false;
}
