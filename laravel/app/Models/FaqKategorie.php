<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titel', 'sortierung'])]
class FaqKategorie extends Model
{
    // Deutscher Plural "faq_kategorien" statt der von Laravel automatisch
    // abgeleiteten (falschen) Form "faq_kategories".
    protected $table = 'faq_kategorien';

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
