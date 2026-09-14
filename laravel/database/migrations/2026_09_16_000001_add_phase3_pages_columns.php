<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Laravel-Migration Phase 3 ("Read-API / Frontend liest
// aus MySQL").
//
// Waehrend der fuer Phase 3 Punkt 1 geforderten vollstaendigen Analyse, welche
// vom Frontend erwarteten JSON-Felder aus der DB rekonstruierbar sein muessen,
// sind zwei reale Luecken im Phase-1/2-Schema aufgefallen - beide erst beim
// Lesen des tatsaechlichen Rendering-Codes (nicht nur der Datenstruktur)
// sichtbar geworden:
//
// - pages.grusswort: kreisjjaegermeister/index.html rendert "aufgaben" und
//   "grußwort" in zwei separaten, optisch unterschiedlichen DOM-Bloecken
//   (grußwort in einer eigens gestalteten Box mit eigener Ueberschrift,
//   nur wenn nicht leer). Phase 2 hat "grußwort"+"aufgaben" bewusst als
//   EIN Feld (inhalt) verkettet gespeichert - das war fuer Phase 2s
//   Datenerhaltungs-Ziel richtig, verhindert aber, dass die Read-API in
//   Phase 3 die urspruengliche optische Trennung wiederherstellen kann.
//   Diese Spalte nimmt "grußwort" separat auf; der zugehoerige Importer-Fix
//   liegt in ImportContent::importKreisjaegermeister().
// - pages.hundeboerse_cta_titel / _text / _button: content/seiten-aufgaben/
//   hundevermittlung.json enthaelt dieses Feld-Tripel real (verifiziert),
//   und seiten/index.html liest es in renderHundeboerseCtaSidebar() aus, um
//   eine Sidebar-CTA-Box zur Hundeboerse anzuzeigen. Ohne diese Spalten
//   wuerde die Box beim Umschalten auf die Read-API kommentarlos
//   verschwinden (stille optische Regression). Fuer alle anderen Seiten,
//   die dieses Feld-Tripel nicht besitzen, bleiben die Spalten schlicht
//   NULL (no-op).
// - pages.bild_flat: echtes Boolean-Feld in genau zwei Kursseiten der
//   Hundeausbildung (aufgaben/hundeausbildung/vps-preistraeger.json und
//   .../entschaedigungsfond.json, jeweils "bild_flat": true) - steuert in
//   seiten/index.html UND aufgaben/jagdhundeschule.html, ob ein Bild ohne
//   Rahmen/Schatten dargestellt wird (fuer Logos/Grafiken mit eigenem
//   weissen Hintergrund). War in Phase 1/2 nicht vorgesehen; ohne diese
//   Spalte wuerden genau diese zwei Bilder beim Umschalten auf die
//   Read-API optisch minimal abweichen (Rahmen erscheint faelschlich).
//
// Rein additiv, keine bestehenden Spalten/Tabellen umbenannt oder entfernt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->longText('grusswort')->nullable()->after('inhalt');
            $table->string('hundeboerse_cta_titel', 190)->nullable()->after('linkliste_titel');
            $table->text('hundeboerse_cta_text')->nullable()->after('hundeboerse_cta_titel');
            $table->string('hundeboerse_cta_button', 190)->nullable()->after('hundeboerse_cta_text');
            $table->boolean('bild_flat')->default(false)->after('bild_groesse');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn([
                'grusswort',
                'hundeboerse_cta_titel',
                'hundeboerse_cta_text',
                'hundeboerse_cta_button',
                'bild_flat',
            ]);
        });
    }
};
