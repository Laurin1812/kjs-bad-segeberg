<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/faq.json -> "kategorien" (Ebene 1 von 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_kategorien', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 190);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_kategorien');
    }
};
