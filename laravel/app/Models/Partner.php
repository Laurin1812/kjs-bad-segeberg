<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'logo', 'kurzbeschreibung', 'beschreibung', 'ansprechpartner',
    'telefon', 'email', 'website', 'rahmenvertrag', 'weitere_infos', 'aktiv',
    'sortierung',
])]
class Partner extends Model
{
    protected function casts(): array
    {
        return [
            'rahmenvertrag' => 'boolean',
            'aktiv' => 'boolean',
            'sortierung' => 'integer',
        ];
    }

    /** @return HasMany<PartnerVorteil, $this> */
    public function vorteile(): HasMany
    {
        return $this->hasMany(PartnerVorteil::class);
    }
}
