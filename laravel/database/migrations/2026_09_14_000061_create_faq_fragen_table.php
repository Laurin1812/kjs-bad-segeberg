<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/faq.json -> "kategorien[].fragen" (Ebene 2 von 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_fragen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_kategorie_id')->constrained('faq_kategorien')->cascadeOnDelete();
            $table->string('frage', 255);
            $table->longText('antwort')->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['faq_kategorie_id', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_fragen');
    }
};
