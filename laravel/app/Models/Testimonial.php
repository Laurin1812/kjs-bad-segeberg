<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['text', 'name', 'rolle', 'icon', 'sichtbar', 'sortierung'])]
class Testimonial extends Model
{
    protected function casts(): array
    {
        return [
            'sichtbar' => 'boolean',
            'sortierung' => 'integer',
        ];
    }
}
