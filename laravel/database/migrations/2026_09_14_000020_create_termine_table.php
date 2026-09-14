<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/termine.json -> "termine". Die "einstellungen"
// (ueberschrift/einleitung) wandern in "settings" (Gruppe "termine").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('termine', function (Blueprint $table) {
            $table->id();
            $table->date('datum');
            $table->string('uhrzeit', 20)->nullable();
            $table->string('veranstaltung', 190);
            $table->string('strasse', 190)->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort', 190)->nullable();
            $table->string('revier', 190)->nullable();
            $table->string('kategorie', 100)->nullable();
            $table->boolean('archiviert')->default(false);
            $table->timestamps();

            $table->index(['datum', 'archiviert']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termine');
    }
};
