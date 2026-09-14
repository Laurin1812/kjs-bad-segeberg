<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Phase-3-Nachbesserung (kjs:compare-content meldete
// "seiten-weitere.seiten[0].veroeffentlicht: Wert-Unterschied (Original
// false vs. API true)").
//
// Ursache: content/seiten-weitere.json (die Registry-Datei) und
// content/seiten-weitere/jagdhornblasen.json (die Seite selbst) fuehren
// BEIDE ein eigenes "veroeffentlicht"-Feld, die im echten Datenbestand
// widerspruechlich sind (Registry: false, Seite selbst: true) - das war
// bereits in ImportContent::importWeitere() bekannt und wird dort geloggt
// ("Es wurde der Wert der Seite selbst uebernommen"). Da "pages" bisher
// nur EINE "veroeffentlicht"-Spalte hatte, konnte diese Entscheidung nur
// EINEN der beiden Werte korrekt abbilden - die Registry-Rekonstruktion
// (/api/content/seiten-weitere.json) zeigte dadurch faelschlich den
// Seiten-eigenen statt den Registry-eigenen Wert.
//
// Diese Spalte nimmt den Registry-eigenen Wert zusaetzlich und getrennt
// auf, damit Registry-Listing und Einzelseiten-Endpunkt weiterhin jeweils
// ihre EIGENE historische Quelle korrekt widerspiegeln koennen. NULL fuer
// alle Seiten, die nicht ueber eine Registry mit eigenem
// veroeffentlicht-Feld importiert wurden (unveraendertes Verhalten dort).
// Rein additiv.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('registry_veroeffentlicht')->nullable()->after('veroeffentlicht');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('registry_veroeffentlicht');
        });
    }
};
