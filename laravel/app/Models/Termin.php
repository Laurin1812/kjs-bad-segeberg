<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'datum', 'uhrzeit', 'veranstaltung', 'strasse', 'plz', 'ort', 'revier',
    'kategorie', 'archiviert',
])]
class Termin extends Model
{
    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'archiviert' => 'boolean',
        ];
    }
}
