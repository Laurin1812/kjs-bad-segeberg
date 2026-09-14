<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/medien-archiv.json ("archiviert": [ "dateiname1.jpg", ... ]).
// Bewusst als eigene, einfache Tabelle belassen (nicht vorschnell mit einer
// spaeteren allgemeinen Medienverwaltung verschmolzen) - siehe Analysebericht
// Punkt 13 ("Risiken", Medienarchiv): die endgueltige Einordnung erfolgt erst
// zusammen mit Phase 5 (Medien/Uploads).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medien_archiv', function (Blueprint $table) {
            $table->id();
            $table->string('dateiname', 255);
            $table->timestamp('archiviert_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medien_archiv');
    }
};
