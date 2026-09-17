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
// ══════════════════════════════════════════════════════════════════════
// PHASE 8B - WICHTIG: DORMANT / BEWUSST UNGENUTZT
// Dieses Model existiert seit Phase 1 (Migrationen+Models fuers neue
// CMS-Schema), wird aber von KEINEM Controller/KEINER Route in
// routes/api.php verwendet (siehe Phase-7-Analyse: grep nach
// "Hundeboerse\\|Waffenboerse" in app/Http/Controllers/ findet nichts).
// Die produktive Wahrheit fuer Hundeboerse/Waffenboerse/Kontakt bleiben
// AUSSCHLIESSLICH die bestehenden PHP-Sondermodule (api/hundeboerse/*.php,
// api/waffenboerse/*.php, api/kontakt/*.php) mit ihrer EIGENEN, separaten
// MySQL-Datenbank (siehe database/schema.sql, config/db.example.php) -
// NICHT diese Laravel-DB/dieses Model. Siehe
// docs/deployment/dormante-boersen-tabellen.md fuer die vollstaendige
// Begruendung, bevor hier jemals eine Verbindung zum echten Schreibweg
// hergestellt wird.
// ══════════════════════════════════════════════════════════════════════
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
