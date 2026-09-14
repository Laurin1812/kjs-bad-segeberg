<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['version'])]
class WaffenboerseMeta extends Model
{
    protected $table = 'waffenboerse_meta';

    public $incrementing = false;

    public $timestamps = false;
}
