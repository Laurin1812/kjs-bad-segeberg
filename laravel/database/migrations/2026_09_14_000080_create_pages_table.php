<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Vereinheitlicht die gesamte bisherige "seiten-*"-Registry-Familie
// (jaeger/*.json, aufgaben/*.json, verbraucher/*.json, alle
// seiten-sub-*.json + -Verzeichnisse, seiten-aufgaben.json,
// seiten-weitere.json, aufgaben/hundeausbildung-seiten.json sowie
// kreisjjaegermeister.json als Singleton-Sonderfall) in EINER Tabelle
// (siehe Analysebericht Punkt 7.2).
//
// "section" ersetzt die bisherige Trennung "welche Registry-Datei", "slug"
// den Dateinamen, "parent_id" bildet ab, was heute durch "Datei liegt im
// Unterverzeichnis der Registry" ausgedrueckt wird (self-referencing statt
// Dateisystem-Konvention - siehe Punkt 13.9 zum inkonsistenten
// seiten-sub-waidmannssprache-Fall).
//
// WICHTIG: bewusst noch OHNE Datenmigration - diese Migration legt nur die
// Struktur an. Der Phase-2-Importer muss aus den vorhandenen JSON-Dateien
// (siehe Analysebericht Punkt 6) befuellen, inkl. Sonderbehandlung der
// "True"/"False"-Strings in einigen Unterseiten-Dateien (Punkt 13.4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->enum('section', [
                'jaeger', 'aufgaben', 'verbraucher', 'weitere',
                'hundeausbildung', 'kreisjaegermeister',
            ]);
            $table->foreignId('parent_id')->nullable()
                ->constrained('pages')->nullOnDelete();
            $table->string('slug', 190);
            $table->string('titel', 190)->nullable();
            $table->string('untertitel', 190)->nullable();
            $table->string('nav_label', 190)->nullable();
            $table->text('intro')->nullable();
            $table->longText('inhalt')->nullable();
            $table->string('hero_bild', 255)->nullable();
            $table->string('bild', 255)->nullable();
            $table->string('bild_alt', 255)->nullable();
            $table->string('bild_groesse', 40)->nullable();
            $table->string('kontakt_name', 190)->nullable();
            $table->string('kontakt_email', 190)->nullable();
            $table->string('unterseiten_titel', 190)->nullable();
            $table->string('linkliste_titel', 190)->nullable();
            $table->boolean('in_navigation')->default(true);
            $table->boolean('veroeffentlicht')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->unique(['section', 'parent_id', 'slug']);
            $table->index(['section', 'veroeffentlicht']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
