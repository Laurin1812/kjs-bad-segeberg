<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['titel', 'beschreibung', 'bild', 'status', 'sortierung'])]
class WunschlisteEintrag extends Model
{
    // Laravel leitet automatisch nur die englische Pluralform "eintrags" ab
    // (kennt "eintrag" -> "eintraege" nicht) - die Migration nutzt aber den
    // echten deutschen Plural "wunschliste_eintraege". Von genau diesem
    // Mismatch kam der erste Fehler beim lokalen Testlauf.
    protected $table = 'wunschliste_eintraege';

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
