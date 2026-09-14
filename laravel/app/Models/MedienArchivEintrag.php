<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Tabelle heisst "medien_archiv" (Plural waere "medien_archive" gewesen,
// bewusst am urspruenglichen content/medien-archiv.json orientiert) -
// deshalb expliziter $table-Name statt Laravel-Konvention.
#[Fillable(['dateiname', 'archiviert_am'])]
class MedienArchivEintrag extends Model
{
    protected $table = 'medien_archiv';

    protected function casts(): array
    {
        return [
            'archiviert_am' => 'datetime',
        ];
    }
}
