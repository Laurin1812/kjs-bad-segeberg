<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/startseite.json -> "testimonials" (+ "testimonials_sichtbar").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->string('name', 190);
            $table->string('rolle', 190)->nullable();
            $table->string('icon', 190)->nullable();
            $table->boolean('sichtbar')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
