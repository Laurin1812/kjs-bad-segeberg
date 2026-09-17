<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Wachsende Vorschlagsliste, wird beim
// Admin-Speichern automatisch um neue Werte ergaenzt.
// PHASE 8B - DORMANT / BEWUSST UNGENUTZT: diese Migration legt die
// Tabellenstruktur in der LARAVEL-DB an, aber KEIN Controller/KEINE Route
// nutzt sie aktuell (siehe Phase-7/8B-Analyse). Produktive Wahrheit fuer
// Hundeboerse/Waffenboerse/Kontakt bleiben die bestehenden PHP-
// Sondermodule mit ihrer EIGENEN, separaten MySQL-Datenbank. NICHT
// loeschen/zurueckrollen - siehe docs/deployment/dormante-boersen-tabellen.md.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hundeboerse_zuchtverbaende', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 190);

            $table->unique('name', 'uniq_hb_zuchtverband_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hundeboerse_zuchtverbaende');
    }
};
