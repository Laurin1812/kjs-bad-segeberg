<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/partner.json -> "partner[].vorteile" (Liste von
// Stichpunkten je Partner) - eigene Tabelle statt Text-Blob, siehe
// Analysebericht Punkt 6.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_vorteile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partner')->cascadeOnDelete();
            $table->string('text', 255);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['partner_id', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_vorteile');
    }
};
