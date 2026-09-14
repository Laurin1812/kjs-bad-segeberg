<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/startseite.json -> "hero_slides" (Liste von {bild, dauer}
// fuer die Startseiten-Slideshow). Die uebrigen startseite.json-Felder
// (Fliesstexte, Statistiken, Quicklinks) sind echte Singleton-Konfiguration
// und wandern stattdessen in "settings" (Gruppe "startseite").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startseite_hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('bild', 255);
            $table->string('dauer', 20)->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index('sortierung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startseite_hero_slides');
    }
};
