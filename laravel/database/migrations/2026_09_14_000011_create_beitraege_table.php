<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/aktuelles.json -> "beitraege" (61 Eintraege) sowie
// content/service.json -> "beitraege" (aktuell nur Platzhalterdaten, siehe
// Analysebericht Punkt 13.1). "typ" unterscheidet beide Bereiche, damit sie
// nicht zwei fast identische Tabellen brauchen.
//
// "slug" ist NEU gegenueber dem bisherigen JSON-Modell: das heutige Frontend
// adressiert Beitraege ueber den Array-Index (aktuelles/beitrag.html?i=42),
// was sich mit Auto-Increment-IDs nicht vertraegt (siehe Analysebericht
// Punkt 13.5). Der Phase-2-Importer muss beim Erstimport fuer jeden
// bestehenden Beitrag einmalig einen stabilen Slug vergeben.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beitraege', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['aktuelles', 'service']);
            $table->string('slug', 220);
            $table->string('titel', 190);
            $table->date('datum')->nullable();
            $table->smallInteger('jahr')->nullable();
            $table->foreignId('kategorie_id')->nullable()
                ->constrained('beitrag_kategorien')->nullOnDelete();
            $table->string('bild', 255)->nullable();
            $table->longText('text')->nullable();
            $table->string('link', 255)->nullable();
            $table->string('galerie_titel', 190)->nullable();
            $table->boolean('archiviert')->default(false);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->unique(['typ', 'slug']);
            $table->index(['typ', 'archiviert', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beitraege');
    }
};
