<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Siehe Migration 2026_09_17_000001_create_content_versions_table - eine
// Zeile pro admin-editierbarem Phase-4-Modul, "version" ersetzt dort die
// bisherige Git-SHA fuer die optimistische Konflikterkennung beim Speichern.
#[Fillable(['section', 'version'])]
class ContentVersion extends Model
{
    protected $primaryKey = 'section';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }
}
