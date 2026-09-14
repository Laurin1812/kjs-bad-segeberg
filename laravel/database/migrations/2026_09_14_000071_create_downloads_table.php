<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/downloads.json -> "kategorien[].downloads" (zentrale
// Bibliothek, ueber kategorie_id) UND die vielen seiteneigenen
// "downloads": [...]-Arrays, die heute in vielen Seiten-/Beitrags-JSON-
// Dateien eingebettet sind (jaeger/*.json, aufgaben/*.json, aktuelles.json
// -Beitraege, kreisjjaegermeister.json, ...).
//
// Bewusste Vereinheitlichung (siehe Analysebericht Punkt 6/13.7): eine
// gemeinsame, polymorphe Tabelle statt einer eigenen Downloads-Tabelle pro
// Objekttyp. "kategorie_id" wird nur fuer Eintraege der zentralen
// Bibliothek gesetzt; "owner_type"/"owner_id" nur fuer seiten-/
// beitragseigene Downloads. Genau eines von beidem ist je Zeile belegt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategorie_id')->nullable()
                ->constrained('download_kategorien')->cascadeOnDelete();
            $table->nullableMorphs('owner');
            $table->string('titel', 190);
            $table->string('pfad', 255);
            $table->string('dateigroesse', 40)->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
