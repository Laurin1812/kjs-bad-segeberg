<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id', 'status', 'titel', 'kategorie', 'hersteller', 'modell', 'zustand',
    'preis', 'preis_typ', 'erwerbsberechtigung_erforderlich', 'beschreibung',
    'plz', 'ort', 'versand_moeglich', 'versandkosten', 'anbieter_name',
    'anbieter_email', 'anbieter_telefon',
])]
class WaffenboerseAnzeige extends Model
{
    protected $table = 'waffenboerse_anzeigen';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'erstellt_am';

    const UPDATED_AT = 'aktualisiert_am';

    protected function casts(): array
    {
        return [
            'erwerbsberechtigung_erforderlich' => 'boolean',
            'versand_moeglich' => 'boolean',
        ];
    }

    /** @return HasMany<WaffenboerseBild, $this> */
    public function bilder(): HasMany
    {
        return $this->hasMany(WaffenboerseBild::class, 'anzeige_id');
    }

    /** @return HasMany<WaffenboerseKaliber, $this> */
    public function kaliber(): HasMany
    {
        return $this->hasMany(WaffenboerseKaliber::class, 'anzeige_id');
    }
}
