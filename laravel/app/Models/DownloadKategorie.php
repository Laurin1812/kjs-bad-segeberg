<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titel', 'sortierung'])]
class DownloadKategorie extends Model
{
    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return HasMany<Download, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class, 'kategorie_id');
    }
}
