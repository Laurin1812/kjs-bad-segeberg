<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['faq_kategorie_id', 'frage', 'antwort', 'sortierung'])]
class FaqFrage extends Model
{
    // Deutscher Plural "faq_fragen" statt der von Laravel automatisch
    // abgeleiteten (falschen) Form "faq_frages".
    protected $table = 'faq_fragen';

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<FaqKategorie, $this> */
    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(FaqKategorie::class, 'faq_kategorie_id');
    }
}
