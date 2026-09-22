<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
// ══════════════════════════════════════════════════════════════════════
// PHASE 6A - AKTIVIERT: siehe HundeboerseAnzeige-Klassenkommentar. Waechst
// automatisch um neue Namen, sobald eine oeffentliche Einreichung
// ("Anbieten") einen bisher unbekannten Zuchtverband angibt (siehe
// HundeboerseController::store()) - dient als Vorschlagsliste (datalist)
// im Formular, genau wie zuvor im Admin-Editor.
// ══════════════════════════════════════════════════════════════════════
class HundeboerseZuchtverband extends Model
{
    protected $table = 'hundeboerse_zuchtverbaende';

    public $timestamps = false;
}
