<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/wunschliste.json -> "aufgaben" (interne Aufgaben-/
// Wunschliste des Admin-Bereichs selbst, niedrige Prioritaet - siehe
// Analysebericht Punkt 6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wunschliste_eintraege', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 190);
            $table->text('beschreibung')->nullable();
            $table->string('bild', 255)->nullable();
            $table->string('status', 40)->default('offen');
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wunschliste_eintraege');
    }
};
