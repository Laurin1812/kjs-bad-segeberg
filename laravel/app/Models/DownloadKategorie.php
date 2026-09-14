<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titel', 'sortierung'])]
class DownloadKategorie extends Model
{
    // Deutscher Plural "download_kategorien" statt der von Laravel
    // automatisch abgeleiteten (falschen) Form "download_kategories".
    protected $table = 'download_kategorien';

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
