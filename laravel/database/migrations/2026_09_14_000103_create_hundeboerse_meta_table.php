<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Genau 1 Zeile: Hero-Bild + optimistischer
// Versionszaehler fuer die Konfliktpruefung beim Admin-Speichern (ersetzt
// die bisherige Git-SHA-Konflikterkennung aus admin.js/doSave()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hundeboerse_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->string('hero_bild', 255)->nullable();
        });

        DB::table('hundeboerse_meta')->insertOrIgnore([
            'id' => 1, 'version' => 0, 'hero_bild' => null,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hundeboerse_meta');
    }
};
