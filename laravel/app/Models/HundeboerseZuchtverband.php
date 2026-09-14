<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class HundeboerseZuchtverband extends Model
{
    protected $table = 'hundeboerse_zuchtverbaende';

    public $timestamps = false;
}
