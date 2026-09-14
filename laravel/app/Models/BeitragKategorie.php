<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['typ', 'name', 'sortierung'])]
class BeitragKategorie extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return HasMany<Beitrag, $this> */
    public function beitraege(): HasMany
    {
        return $this->hasMany(Beitrag::class, 'kategorie_id');
    }
}
