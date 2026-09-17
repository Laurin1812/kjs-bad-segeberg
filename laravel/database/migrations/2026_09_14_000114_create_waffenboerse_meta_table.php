<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Optimistischer Versionszaehler, analog zu
// hundeboerse_meta.
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
