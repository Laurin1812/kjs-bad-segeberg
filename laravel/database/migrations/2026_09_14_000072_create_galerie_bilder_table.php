<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt die "galerie": [...]-Arrays, die heute in aktuelles.json-
// Beitraegen, den festen Inhaltsseiten (jaeger/*.json, aufgaben/*.json,
// verbraucher/*.json) und kreisjjaegermeister.json eingebettet sind.
//
// Gemeinsame, polymorphe Tabelle (owner_type/owner_id) analog zu
// "downloads" - dasselbe Anhaenge-Muster wird bereits erfolgreich von den
// bestehenden hundeboerse_bilder/waffenboerse_bilder-Tabellen verwendet
// (siehe database/schema.sql), hier nur ueber mehrere Owner-Typen hinweg
// geteilt statt pro Boerse eine eigene Tabelle.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galerie_bilder', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('pfad', 255);
            $table->string('titel', 190)->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galerie_bilder');
    }
};
