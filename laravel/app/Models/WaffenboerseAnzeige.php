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
    // Ergaenzt in Phase 6B: der Importer (kjs:import-waffenboerse) muss die
    // echten historischen Bestandsdatumswerte aus content/waffenboerse.json
    // ("erstellt_am"/"aktualisiert_am") verlustfrei uebernehmen koennen,
    // statt dass Eloquents automatisches CREATED_AT/UPDATED_AT-Timestamping
    // (siehe unten) sie stillschweigend durch "jetzt" ersetzt - dafuer
    // muessen beide Spalten hier im Fillable-Set stehen (siehe
    // ImportWaffenboerse::handle()).
    'erstellt_am', 'aktualisiert_am',
])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6B - AKTIVIERT (Waffenboerse auf Laravel/MySQL): dieses Model war
// seit Phase 1 angelegt, aber bis Phase 6B dormant (siehe Git-Historie /
// docs/deployment/dormante-boersen-tabellen.md fuer die vollstaendige
// Vorgeschichte, analog zu HundeboerseAnzeige seit Phase 6A). Ab Phase 6B
// ist dies die EINZIGE Datenquelle der oeffentlichen Waffenboerse
// (WaffenboerseController) - keine Laufzeit-Abhaengigkeit mehr von
// content/waffenboerse.json oder der separaten PHP+MySQL-
// Produktivdatenbank (api/waffenboerse/*.php). Die PHP-Sondermodule
// (api/waffenboerse/*.php) selbst bleiben unveraendert bestehen (nicht Teil
// dieses Auftrags), verlieren aber ihre Rolle als Datenquelle fuer diese
// Laravel-Seite. Kontaktanfragen bleiben WEITERHIN dormant (siehe dessen
// Model-Klassenkommentare) - Phase 6B betrifft ausdruecklich nur die
// Waffenboerse.
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

    private const ZUSTAND_LABEL = [
        'neu' => 'Neu',
        'gebraucht' => 'Gebraucht',
        'vorfuehrwaffe' => 'Vorführwaffe',
    ];

    /**
     * Server-seitiger Port von zustandText() aus waffenboerse/index.html +
     * detail.html.
     */
    public function zustandText(): string
    {
        return self::ZUSTAND_LABEL[$this->zustand] ?? ($this->zustand ?: '');
    }

    /**
     * Server-seitiger Port von preisText() - Felder ("preis_typ"/"preis")
     * 1:1 identisch, inkl. des admin-only moeglichen Werts "auf_anfrage"
     * (siehe waffenboerse/index.html-Kommentar zur JS-Vorlage).
     */
    public function preisText(): string
    {
        return match ($this->preis_typ) {
            'festpreis' => $this->preis !== '' && $this->preis !== null ? $this->preis.' €' : 'Festpreis',
            'vb' => $this->preis !== '' && $this->preis !== null ? $this->preis.' € VB' : 'VB',
            'auf_anfrage' => 'Preis auf Anfrage',
            default => $this->preis !== '' && $this->preis !== null ? $this->preis.' €' : 'Keine Preisangabe',
        };
    }

    /**
     * Server-seitiger Port von kaliberText() - verbindet alle Kaliber-Werte
     * der Anzeige mit " · " (siehe kaliber()-Relation oben).
     */
    public function kaliberText(): string
    {
        return $this->kaliber->pluck('kaliber')->filter()->implode(' · ');
    }

    /**
     * Server-seitiger Port von versandkostenText() (rein kosmetische "€"-
     * Ergaenzung fuer ein reines Zahlenfeld, siehe dortiger Kommentar in
     * detail.html - Admin-Feld/Datenmodell bleiben unveraendert Freitext).
     */
    public function versandkostenText(): string
    {
        $wert = trim((string) $this->versandkosten);
        if ($wert === '') {
            return '';
        }
        if (preg_match('/€|EUR/i', $wert) === 1) {
            return $wert;
        }
        if (preg_match('/^\d+([.,]\d+)?$/', $wert) === 1) {
            return $wert.' €';
        }

        return $wert;
    }
}
