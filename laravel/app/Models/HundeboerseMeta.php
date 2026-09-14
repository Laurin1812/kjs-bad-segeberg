<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Genau 1 Zeile (id=1): Hero-Bild + optimistischer Versionszaehler.
#[Fillable(['version', 'hero_bild'])]
class HundeboerseMeta extends Model
{
    protected $table = 'hundeboerse_meta';

    public $incrementing = false;

    public $timestamps = false;
}
