<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL): generischer Ersatz
// fuer die bisherige Git-SHA-basierte Konflikterkennung in admin.js/doSave()
// - siehe dortigen Kommentar bei trackSha()/saveConflictError(). Fuer
// Hundeboerse/Waffenboerse gibt es dafuer bereits je eine eigene
// "*_meta"-Tabelle mit einer festen Zeile + "version"-Zaehler (siehe
// hundeboerse_meta/waffenboerse_meta) - dieselbe Idee, aber EINE gemeinsame
// Tabelle mit einer Zeile PRO admin-editierbarem Modul ("section", z.B.
// "footer", "vorstand", "aktuelles"), statt fuer jedes der ca. 16 neuen
// Phase-4-Module eine eigene *_meta-Tabelle anzulegen - inhaltlich
// gleichwertig, aber ohne 16 fast identische Migrationen/Modelle.
//
// "section" entspricht dabei NICHT zwingend admin.js' PERM_BY_KEY-Schluessel,
// sondern einem stabilen, in AdminSettingsController/AdminListController/
// AdminPageController fest vergebenen Bezeichner je Schreib-Endpunkt (siehe
// dortige SECTION-Konstanten) - das ist unabhaengig von Rechten/Navigation
// und aendert sich daher nicht, wenn spaeter einmal ein Recht umbenannt wird.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->string('section', 64)->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
