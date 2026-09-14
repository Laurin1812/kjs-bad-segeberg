<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partner_id', 'text', 'sortierung'])]
class PartnerVorteil extends Model
{
    // Deutscher Plural "partner_vorteile" statt der von Laravel automatisch
    // abgeleiteten (falschen) Form "partner_vorteils".
    protected $table = 'partner_vorteile';

    protected function casts(): array
    {
        return [
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
