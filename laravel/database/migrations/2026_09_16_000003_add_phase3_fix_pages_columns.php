<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Phase-3-Nachbesserung, zwei getrennte Funde:
//
// 1) pages.antrag_url (kjs:compare-content meldete "jaeger/mitglied-werden.
//    antrag_url fehlt in der API-Antwort"). content/jaeger/mitglied-
//    werden.json enthaelt "antrag_url" (externer Link zur Online-
//    Mitgliedsantrag-Formularseite memberline.de), und jaeger/mitglied-
//    werden.html rendert ihn direkt als href des "Mitgliedsantrag
//    online"-Buttons (mit Downloads-Seite als Fallback). Das Feld war
//    weder im Phase-1/2-Schema noch in PageContentController::
//    pageToJson()s Superset-Feldliste vorgesehen, weil die urspruengliche
//    Analyse nur jaeger/hochwild.json als repraesentative Stichprobe fuer
//    die "feste jaeger-Seite"-Form herangezogen hatte - dieses Feld
//    kommt aber nur bei dieser einen Seite vor. Ohne die Spalte wuerde
//    der Button beim Umschalten auf die Read-API auf die Downloads-Seite
//    statt auf den echten Online-Antrag verlinken (stille Regression).
//
// 2) pages.galerie_titel - ZUSAETZLICHER, bei dieser Nachbesserung selbst
//    entdeckter Fund (nicht Teil der von kjs:compare-content gemeldeten
//    24 Abweichungen, da das Vergleichstool nur Datentypen vergleicht,
//    nie tatsaechliche skalare Werte - ein struktureller blinder Fleck,
//    siehe Abschlussbericht). PageContentController::pageToJson() las
//    bereits vor dieser Migration "$page->galerie_titel", obwohl die
//    Spalte nie existierte - der Zugriff lieferte deshalb bei JEDER
//    Seite stillschweigend null (-> "" im JSON). In 31 der 20+
//    festen jaeger/aufgaben/verbraucher-Seiten steht dort aber ein
//    echter Wert (meist "Bildergalerie", eine Seite hat einen
//    individuellen Titel) - dieser wurde bislang komplett verschluckt.
//    Rein additiv, schliesst diese Luecke.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('antrag_url', 500)->nullable()->after('kontakt_telefon');
            $table->string('galerie_titel', 190)->nullable()->after('unterseiten_titel');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['antrag_url', 'galerie_titel']);
        });
    }
};
