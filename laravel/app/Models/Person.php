<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Ersetzt content/vorstand.json + content/obleute.json gemeinsam
// ("gremium" unterscheidet vorstand/obmann).
#[Fillable(['gremium', 'rolle', 'name', 'email', 'telefon', 'bild', 'sortierung'])]
class Person extends Model
{
    // Laravel wuerde fuer "Person" automatisch die englische Unregelmaessig-
    // keit "people" ableiten - die Migration nutzt aber den deutschen
    // Plural "personen".
    protected $table = 'personen';

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
