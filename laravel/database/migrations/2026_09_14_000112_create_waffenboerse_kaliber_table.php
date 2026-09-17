<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Bewusst eine eigene Zeile pro Kaliber statt
// komma-getrennt in einer Spalte, da Kaliberwerte selbst Kommas enthalten
// koennen (z.B. "7x65R / 12/70 / 5,6x52R").
// PHASE 8B - DORMANT / BEWUSST UNGENUTZT: diese Migration legt die
// Tabellenstruktur in der LARAVEL-DB an, aber KEIN Controller/KEINE Route
// nutzt sie aktuell (siehe Phase-7/8B-Analyse). Produktive Wahrheit fuer
// Hundeboerse/Waffenboerse/Kontakt bleiben die bestehenden PHP-
// Sondermodule mit ihrer EIGENEN, separaten MySQL-Datenbank. NICHT
// loeschen/zurueckrollen - siehe docs/deployment/dormante-boersen-tabellen.md.

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
