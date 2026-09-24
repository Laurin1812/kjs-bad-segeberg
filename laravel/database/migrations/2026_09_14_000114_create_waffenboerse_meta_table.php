<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Optimistischer Versionszaehler, analog zu
// hundeboerse_meta.
// WEITERHIN DORMANT (Korrektur in Phase 7J, anders als die uebrigen
// waffenboerse_*-Migrationen): die Schwestertabellen (Anzeigen/Bilder/
// Kaliber/Kategorien) sind seit Phase 6B aktiv genutzt (siehe deren
// Migrationskommentare), diese Tabelle bleibt aber tatsaechlich ungenutzt -
// anders als hundeboerse_meta (liefert dort "hero_bild" fuer den
// oeffentlichen Seitenkopf, siehe HundeboerseController::heroBild())
// liest/schreibt aktuell KEIN Laravel-Code diese Tabelle: sie hat nicht
// einmal eine "hero_bild"-Spalte, nur den ungenutzten Versionszaehler. NICHT
// loeschen/zurueckrollen - siehe docs/deployment/dormante-boersen-tabellen.md.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waffenboerse_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(0);
        });

        DB::table('waffenboerse_meta')->insertOrIgnore([
            'id' => 1, 'version' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_meta');
    }
};
