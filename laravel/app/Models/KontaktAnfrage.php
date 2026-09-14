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
