<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql.
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
        Schema::create('waffenboerse_bilder', function (Blueprint $table) {
            $table->id();
            $table->string('anzeige_id', 40);
            $table->string('pfad', 255);
            $table->string('titel', 190)->default('');
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->dateTime('erstellt_am', 3)->useCurrent();

            $table->index(['anzeige_id', 'sortierung'], 'idx_wb_bilder_anzeige');
            $table->foreign('anzeige_id', 'fk_wb_bilder_anzeige')
                ->references('id')->on('waffenboerse_anzeigen')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_bilder');
    }
};
