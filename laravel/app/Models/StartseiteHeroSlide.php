<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bild', 'dauer', 'sortierung'])]
class StartseiteHeroSlide extends Model
{
    protected function casts(): array
    {
        return [
            // Phase-3-Fix (kjs:compare-content meldete 9x Typ-Unterschied
            // startseite.hero_slides[i].dauer: Original ist ein echter JSON-
            // Integer, "dauer" wird aber als string-Spalte gespeichert
            // (siehe Migration) und importiert (siehe ImportContent::
            // importStartseite(): "(string) $slide['dauer']"). Der Cast hier
            // sorgt dafuer, dass JEDER Lesezugriff auf $slide->dauer (auch
            // in SettingsContentController::startseite()) wieder einen
            // echten int liefert - ohne die Spalte selbst oder den Import
            // anzufassen.
            'dauer' => 'integer',
            'sortierung' => 'integer',
        ];
    }
}
