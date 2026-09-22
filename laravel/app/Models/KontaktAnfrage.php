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
// PHASE 6C - AKTIVIERT (Kontaktformular vollstaendig auf Laravel)
// Dieses Model war seit Phase 1 angelegt, aber bis Phase 6C DORMANT (siehe
// Git-Historie dieses Kommentars). Seit Phase 6C ist es die produktive
// Datenquelle fuer neue Kontaktanfragen: KontaktController::store()
// erzeugt hier echte Zeilen ueber Eloquent/MySQL, die alte JSON-/PHP-
// Laufzeit (api/contact.php) wird fuer NEUE Einreichungen nicht mehr
// verwendet. Die Admin-Verwaltung (Liste/Status aendern, bisher
// api/kontakt/admin/liste.php + status.php) ist weiterhin NICHT Teil
// dieses Models/dieser Phase - siehe KontaktController-Klassenkommentar
// fuer die vollstaendige Abgrenzung und die fuer Phase 7/8 offenen Punkte.
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
