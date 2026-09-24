<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql.
// PHASE 6B - AKTIVIERT (Korrektur in Phase 7J): siehe Klassenkommentar der
// Migration 2026_09_14_000110_create_waffenboerse_anzeigen_table.php fuer
// die vollstaendige Begruendung - diese Tabelle bildet die Bildergalerie
// der dortigen Anzeigen ab und ist seit Phase 6B ebenfalls aktiv genutzt.

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
