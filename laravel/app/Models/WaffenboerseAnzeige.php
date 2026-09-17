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
