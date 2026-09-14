<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Bildet die bestehende, bereits produktiv genutzte Tabelle
// hundeboerse_anzeigen ab (siehe database/schema.sql). "id" ist ein vom
// bestehenden PHP-Code vergebener String (keine Auto-Increment-Zahl).
#[Fillable([
    'id', 'status', 'type', 'title', 'breed', 'color', 'coat', 'price_type',
    'price', 'postal_code', 'city', 'description', 'father', 'father_tests',
    'mother', 'mother_tests', 'hunting_tests', 'training_level',
    'provider_name', 'contact_person', 'email', 'phone', 'contact_notes',
    'dog_name', 'birth_date', 'gender', 'litter_date', 'male_count',
    'female_count', 'gallery_title', 'has_zuchtverband', 'zuchtverband',
    'lat', 'lng',
])]
class HundeboerseAnzeige extends Model
{
    protected $table = 'hundeboerse_anzeigen';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'has_zuchtverband' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    /** @return HasMany<HundeboerseBild, $this> */
    public function bilder(): HasMany
    {
        return $this->hasMany(HundeboerseBild::class, 'anzeige_id');
    }
}
