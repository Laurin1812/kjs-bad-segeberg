<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. "beschreibung" enthaelt bereits serverseitig
// sanitisiertes HTML (TipTap-Editor bzw. api/lib/html_sanitize.php) - siehe
// Analysebericht Punkt 8 zur Portierung dieses Sanitizers nach Laravel.
// PHASE 6B - AKTIVIERT (Korrektur in Phase 7J): der urspruengliche "PHASE
// 8B - DORMANT"-Hinweis stammte aus einer Analyse VOR Phase 6B und war
// spaetestens seit der dortigen Aktivierung der oeffentlichen Waffenboerse
// (App\Http\Controllers\WaffenboerseController, siehe routes/web.php)
// ueberholt - diese Tabelle ist seitdem die EINZIGE Datenquelle der
// oeffentlichen Waffenboerse UND (seit Phase 7J) des neuen Blade-Admins
// (App\Http\Controllers\Admin\WaffenboerseController). Siehe
// App\Models\WaffenboerseAnzeige-Klassenkommentar sowie
// docs/deployment/dormante-boersen-tabellen.md (Nachtrag) fuer die
// vollstaendige, aktuelle Einordnung. Weiterhin NICHT loeschen/
// zurueckrollen.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waffenboerse_anzeigen', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->enum('status', ['pending', 'published', 'rejected', 'archived'])->default('pending');
            $table->string('titel', 190)->default('');
            $table->string('kategorie', 190)->default('');
            $table->string('hersteller', 190)->default('');
            $table->string('modell', 190)->default('');
            $table->string('zustand', 20)->default('');
            $table->string('preis', 40)->default('');
            $table->string('preis_typ', 20)->default('');
            $table->boolean('erwerbsberechtigung_erforderlich')->default(false);
            $table->mediumText('beschreibung')->nullable();
            $table->string('plz', 10)->default('');
            $table->string('ort', 190)->default('');
            $table->boolean('versand_moeglich')->default(false);
            $table->string('versandkosten', 40)->default('');
            $table->string('anbieter_name', 190)->default('');
            $table->string('anbieter_email', 190)->default('');
            $table->string('anbieter_telefon', 60)->default('');
            $table->dateTime('erstellt_am', 3)->useCurrent();
            $table->dateTime('aktualisiert_am', 3)->useCurrent()->useCurrentOnUpdate();

            $table->index('status', 'idx_wb_status');
            $table->index('erstellt_am', 'idx_wb_erstellt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_anzeigen');
    }
};
