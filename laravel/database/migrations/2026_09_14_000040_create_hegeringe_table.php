<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/hegeringe.json -> "hegeringe" (die 13 Hegeringe des Kreises).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hegeringe', function (Blueprint $table) {
            $table->id();
            $table->string('nummer', 20);
            $table->string('name', 190);
            $table->string('obmann', 190)->nullable();
            $table->text('gemeinden')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('telefon', 60)->nullable();
            $table->string('geschlecht', 20)->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hegeringe');
    }
};
