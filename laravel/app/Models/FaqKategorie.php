<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titel', 'sortierung'])]
class FaqKategorie extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return HasMany<FaqFrage, $this> */
    public function fragen(): HasMany
    {
        return $this->hasMany(FaqFrage::class);
    }
}
