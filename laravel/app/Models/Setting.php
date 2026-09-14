<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Ersetzt alle Singleton-Konfigurationsdateien (design.json, einstellungen.json,
// footer.json-Fliesstexte, impressum.json, navigation.json,
// navigation-extra.json, startseite.json-Fliessfelder). "gruppe" entspricht
// dem ehemaligen Dateinamen ohne .json, "key" dem ehemaligen JSON-Schluessel.
//
// Bewusst noch ohne Zugriffs-Hilfsmethoden (z.B. ein Settings::group()-
// Helfer) - das ist Business-Logik fuer Phase 3/4 (Read-/Schreib-API), die
// in Phase 1 noch nicht gebraucht wird.
#[Fillable(['gruppe', 'key', 'value'])]
class Setting extends Model
{
    //
}
