<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/aktuelles.json -> "einstellungen.kategorien" (bzw. das
// Aequivalent in service.json). "typ" unterscheidet, zu welchem der beiden
// Beitragsbereiche (Aktuelles/Service) eine Kategorie gehoert.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beitrag_kategorien', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['aktuelles', 'service']);
            $table->string('name', 190);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['typ', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beitrag_kategorien');
    }
};
