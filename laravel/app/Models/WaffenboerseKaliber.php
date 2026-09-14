<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anzeige_id', 'kaliber', 'sortierung'])]
class WaffenboerseKaliber extends Model
{
    protected $table = 'waffenboerse_kaliber';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<WaffenboerseAnzeige, $this> */
    public function anzeige(): BelongsTo
    {
        return $this->belongsTo(WaffenboerseAnzeige::class, 'anzeige_id');
    }
}
