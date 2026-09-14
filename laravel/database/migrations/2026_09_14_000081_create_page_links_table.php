<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt die "linkliste": [...]-Arrays (z.B. content/jaeger/hochwild.json ->
// "linkliste"/"linkliste_titel") - nur bei "pages" vorhanden, daher eine
// eigene (nicht polymorphe) Tabelle statt Wiederverwendung von
// galerie_bilder/downloads.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('label', 190);
            $table->string('href', 255);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['page_id', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_links');
    }
};
