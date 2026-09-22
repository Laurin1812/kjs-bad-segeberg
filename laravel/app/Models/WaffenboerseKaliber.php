<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anzeige_id', 'kaliber', 'sortierung'])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6B - AKTIVIERT: siehe WaffenboerseAnzeige-Klassenkommentar. Eine
// eigene Zeile pro Kaliber statt Komma-Trennfeld (siehe Migrations-
// Kommentar) - "kaliber()"-Relation dort liefert die sortierte Liste.
// ══════════════════════════════════════════════════════════════════════
class WaffenboerseKaliber extends Model
{
    protected $table = 'waffenboerse_kaliber';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<WaffenboerseAnzeige, $this> */
    public function anzeige(): BelongsTo
    {
        return $this->belongsTo(WaffenboerseAnzeige::class, 'anzeige_id');
    }
}
