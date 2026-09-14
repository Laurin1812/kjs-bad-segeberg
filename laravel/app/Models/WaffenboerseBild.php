<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anzeige_id', 'pfad', 'titel', 'sortierung'])]
class WaffenboerseBild extends Model
{
    protected $table = 'waffenboerse_bilder';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
            'erstellt_am' => 'datetime',
        ];
    }

    /** @return BelongsTo<WaffenboerseAnzeige, $this> */
    public function anzeige(): BelongsTo
    {
        return $this->belongsTo(WaffenboerseAnzeige::class, 'anzeige_id');
    }
}
