<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/partner.json -> "partner".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('logo', 255)->nullable();
            $table->string('kurzbeschreibung', 255)->nullable();
            $table->text('beschreibung')->nullable();
            $table->string('ansprechpartner', 190)->nullable();
            $table->string('telefon', 60)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('rahmenvertrag')->default(false);
            $table->text('weitere_infos')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index('aktiv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner');
    }
};
