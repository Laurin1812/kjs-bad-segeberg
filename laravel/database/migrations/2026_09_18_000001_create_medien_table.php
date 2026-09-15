<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 5B.1 (sichere Laravel-Medienarchitektur): generische Metadaten-
// Tabelle fuer Dateien, die ab jetzt ueber die neue, authentifizierte
// Laravel-Upload-API (App\Http\Controllers\Api\Admin\AdminMediaController)
// hochgeladen werden - siehe Analysebericht Phase 5A Punkt 5 ("es gibt
// bislang KEINE generische Media-Tabelle, jede Verwendung ist nur ein
// Pfad-String an der jeweiligen Content-Zeile").
//
// BEWUSST KEIN rueckwirkender Import der ca. 250 bereits vorhandenen
// Bilder/17 Downloads (Auftrag Punkt 5: "keine bestehenden historischen
// Bilder/PDFs zwingend rueckwirkend importieren") - diese bleiben
// unveraendert als reine Pfad-Strings in den content/*.json-Feldern bzw.
// den Phase-1-Tabellen "downloads"/"galerie_bilder" referenziert und tragen
// keine Zeile hier. Diese Tabelle beschreibt nur, WAS ab jetzt neu
// hochgeladen wird - nicht, WO eine Datei ueberall im Content verwendet
// wird (keine automatische Referenzverfolgung, siehe Auftrag Punkt 7 und
// AdminMediaController::destroy()-Kommentar).
//
// "path" speichert bewusst NUR den Dateinamen (z.B.
// "1734567890-ab12cd34ef56.jpg"), NICHT einen vollen Pfad und NICHT den
// URL-Praefix - welches Wurzelverzeichnis/welcher URL-Praefix gilt, ergibt
// sich allein aus "media_type" ueber App\Support\MediaStorage (config/
// kjs_media.php) - EIN Ort kennt die Zuordnung Typ->Pfad/Praefix, nicht
// zwei (siehe Auftrag Punkt 2 "Pfade zentral konfigurierbar"). Die
// Vorschau-Varianten (thumb/card) tragen KEINE eigene Zeile - genau wie im
// bestehenden System (admin.js: Original/Thumb/Card teilen sich immer
// denselben Dateinamen, nur der Ordner unterscheidet sich) leiten sie sich
// rein aus dem Dateinamen des Originals ab.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medien', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->enum('media_type', ['image', 'pdf']);
            // sha256 (64 Hex-Zeichen) - optional (Auftrag Punkt 5 "nur wenn
            // sinnvoll"): guenstig zu berechnen, nuetzlich fuer spaetere
            // Duplikaterkennung/Integritaetspruefung ohne Zusatzaufwand beim
            // Upload.
            $table->string('checksum', 64)->nullable();
            // Nur bei media_type=image gesetzt (aus getimagesize() beim
            // Upload, kostenlos verfuegbar).
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Netlify-Identity-E-Mail (oder "sub", falls keine E-Mail im
            // Token) des hochladenden Redakteurs/Admins - bewusst KEIN
            // Fremdschluessel, da Benutzer ausschliesslich in Netlify
            // Identity verwaltet werden, nicht in dieser Datenbank (siehe
            // App\Support\NetlifyIdentity).
            $table->string('uploaded_by', 190)->nullable();
            $table->timestamps();

            $table->index('media_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medien');
    }
};
