<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nummer', 'name', 'obmann', 'gemeinden', 'email', 'telefon', 'geschlecht', 'sortierung'])]
class Hegering extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
