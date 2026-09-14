<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['spalte', 'label', 'href', 'sortierung'])]
class FooterLink extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }
}
