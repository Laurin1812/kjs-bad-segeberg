<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Laravel-Migration Phase 2 ("JSON-Importer +
// vollstaendige Datenmigration").
//
// Beim genauen Durcharbeiten der echten content/*.json-Inhalte (nicht nur
// der Struktur) sind drei reale Datenfelder aufgefallen, fuer die das in
// Phase 1 entworfene Schema noch keine Spalte hatte - keine Neuerfindung,
// sondern Nachtrag auf Basis der tatsaechlichen Produktivdaten:
//
// - pages.kontakt_telefon: content/kreisjjaegermeister.json enthaelt ein
//   echtes "telefon"-Feld, das sonst beim Import verloren ginge.
// - pages.vorschaubild / pages.kurzbeschreibung / pages.gruppe: sowohl die
//   Registry aufgaben/hundeausbildung-seiten.json als auch die einzelnen
//   Kurs-Unterseiten selbst (z.B. aufgaben/hundeausbildung/kurs-1-*.json)
//   fuehren diese drei zusaetzlichen Felder (Vorschaubild/Kurzbeschreibung
//   fuer die Kursuebersicht, "gruppe" fuer die visuelle Gruppierung z.B.
//   "Kurse 1-6").
// - downloads.beschreibung / downloads.typ / downloads.vorschau: beim
//   genauen Vergleich stellte sich heraus, dass die zentrale
//   Download-Bibliothek (content/downloads.json, Felder
//   name/beschreibung/url/typ) UND die seiteneigenen Downloads-Arrays
//   (Felder titel/datei/vorschau, z.B. in content/aktuelles.json) zwei
//   unterschiedliche, bisher nicht dokumentierte Feldschemata verwenden -
//   siehe Abschlussbericht Phase 2. Beide werden auf dieselbe Tabelle
//   abgebildet (titel/pfad decken beide ab), die jeweils nur bei einer
//   Quelle vorkommenden Felder ergaenzen wir hier zusaetzlich.
// - beitraege.legacy_index: siehe Analysebericht Punkt 13.5 / Auftrag
//   Phase 2 Punkt 3E - der bisherige Array-Index aus aktuelles.json wird
//   als Uebergangswert mitgespeichert, damit alte "?i=42"-Links in einer
//   spaeteren Phase weiterhin aufloesbar bleiben.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('kontakt_telefon', 60)->nullable()->after('kontakt_email');
            $table->string('vorschaubild', 255)->nullable()->after('bild_alt');
            $table->string('kurzbeschreibung', 255)->nullable()->after('vorschaubild');
            $table->string('gruppe', 190)->nullable()->after('unterseiten_titel');
        });

        Schema::table('downloads', function (Blueprint $table) {
            $table->text('beschreibung')->nullable()->after('titel');
            $table->string('typ', 40)->nullable()->after('beschreibung');
            $table->string('vorschau', 255)->nullable()->after('pfad');
        });

        Schema::table('beitraege', function (Blueprint $table) {
            $table->unsignedInteger('legacy_index')->nullable()->after('slug');
            $table->index(['typ', 'legacy_index']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['kontakt_telefon', 'vorschaubild', 'kurzbeschreibung', 'gruppe']);
        });

        Schema::table('downloads', function (Blueprint $table) {
            $table->dropColumn(['beschreibung', 'typ', 'vorschau']);
        });

        Schema::table('beitraege', function (Blueprint $table) {
            $table->dropIndex(['typ', 'legacy_index']);
            $table->dropColumn('legacy_index');
        });
    }
};
