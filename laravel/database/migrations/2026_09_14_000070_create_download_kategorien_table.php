<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/downloads.json -> "kategorien" (zentrale Download-Bibliothek).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_kategorien', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 190);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_kategorien');
    }
};
