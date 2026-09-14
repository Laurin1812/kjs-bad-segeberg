<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Ersetzt content/aktuelles.json -> "beitraege" sowie content/service.json ->
// "beitraege" ("typ" unterscheidet beide Bereiche).
#[Fillable([
    'typ', 'slug', 'titel', 'datum', 'jahr', 'kategorie_id', 'bild', 'text',
    'link', 'galerie_titel', 'archiviert', 'sortierung',
])]
class Beitrag extends Model
{
    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'jahr' => 'integer',
            'archiviert' => 'boolean',
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<BeitragKategorie, $this> */
    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(BeitragKategorie::class, 'kategorie_id');
    }

    /** @return MorphMany<GalerieBild, $this> */
    public function galerieBilder(): MorphMany
    {
        return $this->morphMany(GalerieBild::class, 'owner');
    }

    /** @return MorphMany<Download, $this> */
    public function downloads(): MorphMany
    {
        return $this->morphMany(Download::class, 'owner');
    }
}
