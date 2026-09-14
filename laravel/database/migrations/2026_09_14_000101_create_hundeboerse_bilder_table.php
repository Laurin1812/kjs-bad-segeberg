<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. "pfad" ist eine server-relative URL, NIE
// Bilddaten selbst (siehe Kommentar dort).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hundeboerse_bilder', function (Blueprint $table) {
            $table->id();
            $table->string('anzeige_id', 40);
            $table->string('pfad', 255);
            $table->string('titel', 190)->default('');
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->dateTime('erstellt_am', 3)->useCurrent();

            $table->index(['anzeige_id', 'sortierung'], 'idx_hb_bilder_anzeige');
            $table->foreign('anzeige_id', 'fk_hb_bilder_anzeige')
                ->references('id')->on('hundeboerse_anzeigen')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hundeboerse_bilder');
    }
};
