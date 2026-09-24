<?php

namespace App\Models;

use Carbon\Carbon;
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
// PHASE 6A - AKTIVIERT (Sondermodule inventarisieren + Hundeboerse auf
// Laravel/MySQL): dieses Model war seit Phase 1 angelegt, aber bis Phase 6A
// dormant (siehe Git-Historie / docs/deployment/dormante-boersen-tabellen.md
// fuer die vollstaendige Vorgeschichte). Ab Phase 6A ist dies die EINZIGE
// Datenquelle der oeffentlichen Hundeboerse (HundeboerseController) - keine
// Laufzeit-Abhaengigkeit mehr von content/hundeboerse.json oder der
// separaten PHP+MySQL-Produktivdatenbank (api/hundeboerse/*.php). Die
// PHP-Sondermodule (api/hundeboerse/*.php) selbst bleiben unveraendert
// bestehen (nicht Teil dieses Auftrags), verlieren aber ihre Rolle als
// Datenquelle fuer diese Laravel-Seite. Waffenboerse/Kontakt-Anfragen
// bleiben WEITERHIN dormant (siehe deren Model-Klassenkommentare) - Phase 6A
// betrifft ausdruecklich nur die Hundeboerse.
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

    /**
     * Server-seitiger Port von preisText() aus hundeboerse/index.html +
     * detail.html - Felder ("price_type"/"price") 1:1 identisch.
     */
    public function preisText(): string
    {
        return match ($this->price_type) {
            'fixed' => $this->price !== '' && $this->price !== null ? $this->price.' €' : 'Festpreis',
            'negotiable' => $this->price !== '' && $this->price !== null ? $this->price.' € VB' : 'VB',
            'on_request' => 'Preis auf Anfrage',
            default => 'Keine Preisangabe',
        };
    }

    public function typLabel(): string
    {
        return $this->type === 'litter' ? 'WURF' : 'EINZELHUND';
    }

    /**
     * Server-seitiger Port von typZeile() (Kachel/Detailseiten-Unterzeile:
     * "Wurf vom ... · X Rüden, Y Hündinnen" bzw. "Rüde · 8 Monate").
     */
    public function typZeile(): string
    {
        if ($this->type === 'litter') {
            $teile = [];
            if ($this->litter_date) {
                $teile[] = 'Wurf vom '.$this->formatiertesDatum($this->litter_date);
            }
            $anzahl = [];
            if ($this->male_count) {
                $anzahl[] = $this->male_count.' Rüden';
            }
            if ($this->female_count) {
                $anzahl[] = $this->female_count.' Hündinnen';
            }
            if ($anzahl) {
                $teile[] = implode(', ', $anzahl);
            }

            return implode(' · ', $teile);
        }

        $teile = [];
        if ($this->gender) {
            $teile[] = $this->gender === 'male' ? 'Rüde' : 'Hündin';
        }
        $alter = $this->altersText();
        if ($alter) {
            $teile[] = $alter;
        }

        return implode(' · ', $teile);
    }

    /**
     * Server-seitiger Port von formatDatum() - wandelt ein gespeichertes
     * "DD.MM.YYYY" (siehe HundeboerseController::normalizeDatum()) in die
     * deutsche Langform ("12. März 2026") um. Unbekanntes/leeres Format
     * wird unveraendert zurueckgegeben statt zu raten.
     */
    public function formatiertesDatum(?string $roh): string
    {
        if (! $roh) {
            return '';
        }

        $monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $roh, $m)) {
            return ((int) $m[1]).'. '.$monate[((int) $m[2]) - 1].' '.$m[3];
        }

        return $roh;
    }

    /**
     * Phase 7I (Admin-Modul "Hundeboerse"): ISO-Form ("YYYY-MM-DD") von
     * "birth_date"/"litter_date" fuer <input type="date">-Felder im
     * Bearbeiten-Formular - Gegenstueck zu HundeboerseUpdater::
     * normalizeDatum(), das umgekehrt aus ISO das hier gespeicherte
     * "DD.MM.YYYY" erzeugt. Liefert null bei leerem/unbekanntem Format,
     * damit das Datumsfeld dann einfach leer gerendert wird statt eines
     * kaputten Werts.
     */
    public function birthDateIso(): ?string
    {
        return $this->isoDatum($this->birth_date);
    }

    public function litterDateIso(): ?string
    {
        return $this->isoDatum($this->litter_date);
    }

    private function isoDatum(?string $roh): ?string
    {
        if (! $roh || ! preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $roh, $m)) {
            return null;
        }

        $geburt = Carbon::createSafe((int) $m[3], (int) $m[2], (int) $m[1]);

        return $geburt?->format('Y-m-d');
    }

    /**
     * Server-seitiger Port von alterText() - Alter in Monaten/Jahren aus
     * dem gespeicherten Geburtsdatum ("DD.MM.YYYY").
     */
    public function altersText(): string
    {
        if (! $this->birth_date || ! preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $this->birth_date, $m)) {
            return '';
        }

        $geburt = Carbon::createSafe((int) $m[3], (int) $m[2], (int) $m[1]);
        if (! $geburt) {
            return '';
        }

        $monate = $geburt->diffInMonths(now());
        if ($monate < 24) {
            return $monate.($monate === 1 ? ' Monat' : ' Monate');
        }

        $jahre = intdiv($monate, 12);

        return $jahre.($jahre === 1 ? ' Jahr' : ' Jahre');
    }
}
