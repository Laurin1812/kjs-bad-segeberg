<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['titel', 'beschreibung', 'bild', 'status', 'sortierung'])]
class WunschlisteEintrag extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
