<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Bewusst eine eigene Zeile pro Kaliber statt
// komma-getrennt in einer Spalte, da Kaliberwerte selbst Kommas enthalten
// koennen (z.B. "7x65R / 12/70 / 5,6x52R").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waffenboerse_kaliber', function (Blueprint $table) {
            $table->id();
            $table->string('anzeige_id', 40);
            $table->string('kaliber', 100);
            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->index(['anzeige_id', 'sortierung'], 'idx_wb_kaliber_anzeige');
            $table->foreign('anzeige_id', 'fk_wb_kaliber_anzeige')
                ->references('id')->on('waffenboerse_anzeigen')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_kaliber');
    }
};
