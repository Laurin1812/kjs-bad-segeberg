<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Phase 5B.1: Eloquent-Gegenstueck zur "medien"-Tabelle (siehe
// Migration 2026_09_18_000001_create_medien_table.php fuer die
// ausfuehrliche Begruendung des Schemas). Tabellenname analog zu
// MedienArchivEintrag ("medien" statt des von Eloquent sonst erratenen
// "medien_eintrags") explizit gesetzt.
class MedienEintrag extends Model
{
    protected $table = 'medien';

    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'media_type',
        'checksum',
        'width',
        'height',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isPdf(): bool
    {
        return $this->media_type === 'pdf';
    }
}
