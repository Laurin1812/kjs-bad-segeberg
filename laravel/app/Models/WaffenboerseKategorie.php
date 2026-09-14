<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sortierung'])]
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
