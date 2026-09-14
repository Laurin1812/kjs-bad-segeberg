<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Wachsende Vorschlagsliste, wird beim
// Admin-Speichern automatisch um neue Werte ergaenzt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hundeboerse_zuchtverbaende', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 190);

            $table->unique('name', 'uniq_hb_zuchtverband_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hundeboerse_zuchtverbaende');
    }
};
