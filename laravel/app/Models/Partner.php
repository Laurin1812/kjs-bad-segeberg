<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    // Phase-3-Fix: "external_id" = das urspruengliche "id"-Feld aus
    // content/partner.json ("pn-<timestamp>") - wird fuer die
    // Partner-Detailseiten-Verlinkung (partner/index.html -> detail.html?id=)
    // 1:1 benoetigt, siehe Migration 2026_09_16_000002.
    'external_id',
    'name', 'logo', 'kurzbeschreibung', 'beschreibung', 'ansprechpartner',
    'telefon', 'email', 'website', 'rahmenvertrag', 'weitere_infos', 'aktiv',
    'sortierung',
])]
class Partner extends Model
{
    // "Partner" ist im Deutschen in Singular und Plural identisch - Laravel
    // wuerde automatisch "partners" ableiten (englische Regel), die
    // Migration nutzt aber bewusst "partner".
    protected $table = 'partner';

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
