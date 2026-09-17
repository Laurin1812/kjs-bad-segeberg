<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KJS Bad Segeberg - Laravel-Migration Phase 1 (Analysebericht Punkt 7.4/8).
//
// Bildet database/schema.sql (bestehende, bereits produktiv genutzte
// Hundeboerse-/Waffenboerse-/Kontakt-Datenbank auf dem separaten PHP-Host)
// 1:1 als Laravel-Migration nach - KEINE fachliche Neuentwicklung, keine
// "sauberere" Umbenennung von Spalten. Preis-/Datumsfelder bleiben bewusst
// freie Strings (siehe Kommentar in schema.sql: der bestehende Admin
// erlaubt z.B. "1.850" oder "Verhandlungsbasis" als Preistext).
//
// Diese Migration ist fuer eine NEUE/leere Datenbank gedacht. Beim Anschluss
// der tatsaechlichen Produktionsdatenbank (auf der diese Tabellen bereits
// existieren) wird stattdessen eine Baseline-/Adoption-Strategie benoetigt,
// damit Laravel die vorhandenen Tabellen nicht erneut anlegt oder ueberschreibt
// (siehe Abschlussbericht dieser Phase, Punkt "Baseline-Strategie").
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
        Schema::create('hundeboerse_anzeigen', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->enum('status', ['pending', 'published', 'rejected', 'archived'])->default('pending');
            $table->enum('type', ['single', 'litter'])->default('single');
            $table->string('title', 190)->default('');
            $table->string('breed', 190)->default('');
            $table->string('color', 190)->default('');
            $table->string('coat', 190)->default('');
            $table->string('price_type', 20)->default('on_request');
            $table->string('price', 40)->default('');
            $table->string('postal_code', 10)->default('');
            $table->string('city', 190)->default('');
            $table->mediumText('description')->nullable();
            $table->string('father', 190)->default('');
            $table->string('father_tests', 190)->default('');
            $table->string('mother', 190)->default('');
            $table->string('mother_tests', 190)->default('');
            $table->string('hunting_tests', 190)->default('');
            $table->mediumText('training_level')->nullable();
            $table->string('provider_name', 190)->default('');
            $table->string('contact_person', 190)->default('');
            $table->string('email', 190)->default('');
            $table->string('phone', 60)->default('');
            $table->mediumText('contact_notes')->nullable();
            $table->string('dog_name', 190)->default('');
            $table->string('birth_date', 20)->default('');
            $table->string('gender', 10)->default('');
            $table->string('litter_date', 20)->default('');
            $table->string('male_count', 10)->default('');
            $table->string('female_count', 10)->default('');
            $table->string('gallery_title', 190)->default('Bilder');
            $table->boolean('has_zuchtverband')->default(false);
            $table->string('zuchtverband', 190)->default('');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('created_at', 3)->useCurrent();
            $table->dateTime('updated_at', 3)->useCurrent()->useCurrentOnUpdate();

            $table->index('status', 'idx_hb_status');
            $table->index('created_at', 'idx_hb_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hundeboerse_anzeigen');
    }
};
