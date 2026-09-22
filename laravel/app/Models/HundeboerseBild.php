<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anzeige_id', 'pfad', 'titel', 'sortierung'])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6A - AKTIVIERT: siehe HundeboerseAnzeige-Klassenkommentar. Bildert
// die Galerie einer Anzeige ab (sortierung = Reihenfolge, "pfad" = Laravel-
// eigener /uploads/boersen/hundeboerse/...-Pfad, siehe HundeboerseController).
// ══════════════════════════════════════════════════════════════════════
class HundeboerseBild extends Model
{
    protected $table = 'hundeboerse_bilder';

    // Nur "erstellt_am" vorhanden, kein "updated_at" - Eloquents
    // automatische Timestamp-Verwaltung passt hier nicht.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
            'erstellt_am' => 'datetime',
        ];
    }

    /** @return BelongsTo<HundeboerseAnzeige, $this> */
    public function anzeige(): BelongsTo
    {
        return $this->belongsTo(HundeboerseAnzeige::class, 'anzeige_id');
    }
}
