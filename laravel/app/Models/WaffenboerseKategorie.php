<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sortierung'])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6B - AKTIVIERT: siehe WaffenboerseAnzeige-Klassenkommentar. Feste,
// kuratierte Kategorienliste (die 7 Standardkategorien werden bereits von
// der Migration selbst per insertOrIgnore angelegt, siehe dort) - anders
// als Hundeboerse-Zuchtverbaende waechst diese Liste NICHT automatisch
// durch oeffentliche Einreichungen (siehe WaffenboerseAnbietenRequest::
// rules(): "kategorie" muss ein bereits bekannter Name sein, exakt wie im
// PHP-Original api/waffenboerse/anzeigen.php::kjs_wb_handle_submit()).
// ══════════════════════════════════════════════════════════════════════
class WaffenboerseKategorie extends Model
{
    protected $table = 'waffenboerse_kategorien';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
