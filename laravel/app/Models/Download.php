<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Dient sowohl der zentralen Download-Bibliothek (kategorie_id gesetzt) als
// auch seiten-/beitragseigenen Downloads (owner_type/owner_id gesetzt) -
// siehe Analysebericht Punkt 13.7. Genau eines von beidem ist je Zeile belegt.
#[Fillable([
    'kategorie_id', 'owner_type', 'owner_id', 'titel', 'beschreibung', 'typ',
    'pfad', 'vorschau', 'dateigroesse', 'sortierung',
])]
class Download extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<DownloadKategorie, $this> */
    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(DownloadKategorie::class, 'kategorie_id');
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
