<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Ziel: keine Kontaktanfrage darf verloren
// gehen, auch wenn SMTP (noch) nicht konfiguriert ist oder ausfaellt -
// api/contact.php speichert IMMER zuerst hier, der Mailversand ist danach
// eine zusaetzliche, vom Speichern unabhaengige Benachrichtigung.
// "mail_fehler" enthaelt bewusst nur eine kurze interne Fehlerkategorie,
// NIE die rohe SMTP-Fehlermeldung (koennte Zugangsdaten enthalten).
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
        Schema::create('kontakt_anfragen', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190)->default('');
            $table->string('email', 190)->default('');
            $table->string('telefon', 60)->default('');
            $table->string('anliegen', 190)->default('');
            $table->string('bereits_jaeger', 20)->default('');
            $table->string('hegering', 190)->default('');
            $table->mediumText('nachricht')->nullable();
            $table->enum('status', ['neu', 'bearbeitet'])->default('neu');
            $table->boolean('mail_versendet')->default(false);
            $table->string('mail_fehler', 190)->nullable();
            $table->dateTime('erstellt_am', 3)->useCurrent();
            $table->dateTime('bearbeitet_am', 3)->nullable();

            $table->index('status', 'idx_ka_status');
            $table->index('erstellt_am', 'idx_ka_erstellt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontakt_anfragen');
    }
};
