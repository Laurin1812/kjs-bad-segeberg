<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// "bearbeitet_am" ist NICHT Eloquents automatisches "updated_at" (siehe
// api/kontakt/admin/status.php: nur beim Setzen des Bearbeitungsstatus
// gesetzt, nicht bei jeder Aenderung) - deshalb $timestamps = false und
// beide Felder als normale Attribute mit Cast behandelt.
#[Fillable([
    'name', 'email', 'telefon', 'anliegen', 'bereits_jaeger', 'hegering',
    'nachricht', 'status', 'mail_versendet', 'mail_fehler', 'bearbeitet_am',
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
class KontaktAnfrage extends Model
{
    protected $table = 'kontakt_anfragen';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'mail_versendet' => 'boolean',
            'erstellt_am' => 'datetime',
            'bearbeitet_am' => 'datetime',
        ];
    }
}
