<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/vorstand.json -> "mitglieder" UND content/obleute.json ->
// "obleute" in einer gemeinsamen Tabelle, da beide identisches Feldschema
// besitzen (rolle/name/email/telefon/bild) - "gremium" unterscheidet sie
// (siehe Analysebericht Punkt 6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personen', function (Blueprint $table) {
            $table->id();
            $table->enum('gremium', ['vorstand', 'obmann']);
            $table->string('rolle', 190);
            $table->string('name', 190);
            $table->string('email', 190)->nullable();
            $table->string('telefon', 60)->nullable();
            $table->string('bild', 255)->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['gremium', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personen');
    }
};
