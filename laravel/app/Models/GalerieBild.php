<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Gemeinsame Galerie-Tabelle fuer Beitraege, Seiten etc. (owner_type/owner_id).
#[Fillable(['owner_type', 'owner_id', 'pfad', 'titel', 'sortierung'])]
class GalerieBild extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
