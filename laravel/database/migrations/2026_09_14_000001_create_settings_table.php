<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Laravel-Migration Phase 1 (siehe Analysebericht Punkt 7.3).
//
// Ersetzt alle heutigen Singleton-Konfigurationsdateien (design.json,
// einstellungen.json, footer.json-Fließtexte, impressum.json,
// navigation.json, navigation-extra.json, startseite.json-Fließfelder), die
// jeweils nur EINEN Datensatz mit wenigen Feldern besitzen. Statt für jede
// dieser Dateien eine eigene Ein-Zeilen-Tabelle mit eigener Migration
// anzulegen, gibt es eine schlanke Key-Value-Tabelle je "Gruppe"
// (gruppe = ehemaliger Dateiname ohne .json, key = ehemaliger JSON-Schlüssel).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('gruppe', 60);
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['gruppe', 'key']);
            $table->index('gruppe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
