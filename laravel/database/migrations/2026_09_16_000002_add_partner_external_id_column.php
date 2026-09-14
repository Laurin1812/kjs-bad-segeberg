<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Phase-3-Nachbesserung (kjs:compare-content meldete
// 14x "partner.partner[i].id fehlt in der API-Antwort").
//
// content/partner.json fuehrt pro Partner ein Feld "id" (Format
// "pn-<13-stelliger-Timestamp>", z.B. "pn-1788451279453") - beim
// Phase-1/2-Analysebericht wurde dieses Feld nicht erfasst, weder in der
// "partner"-Tabelle noch im Partner-Model noch im Importer.
//
// Das Feld ist NICHT nur ein optionaler Zusatzwert: partner/index.html
// verlinkt jede Partnerkachel als "detail.html?id=" + p.id, und
// partner/detail.html sucht den passenden Datensatz client-seitig per
// "liste.find(x => x.id === pnId)". Ohne dieses Feld waeren nach der
// Umstellung auf die Read-API ALLE Partner-Detailseiten-Links defekt
// (id waere undefined fuer jeden Partner). Deshalb wird hier eine neue
// Spalte ergaenzt statt das Feld als "harmlosen Hinweis" zu behandeln.
//
// Bewusst NICHT "id" genannt, um nicht mit Eloquents eigenem Primary Key
// zu kollidieren; "external_id", weil der Wert vom Frontend/Admin-Panel
// vergeben wird, nicht von der DB. Rein additiv.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner', function (Blueprint $table) {
            $table->string('external_id', 60)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('partner', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
