<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ersetzt content/footer.json -> spalte_ueber_kjs/spalte_uebersicht/spalte_informationen
// (drei echte Listen von {label, href}, siehe Analysebericht Punkt 7.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('footer_links', function (Blueprint $table) {
            $table->id();
            $table->enum('spalte', ['ueber_kjs', 'uebersicht', 'informationen']);
            $table->string('label', 190);
            $table->string('href', 255);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();

            $table->index(['spalte', 'sortierung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_links');
    }
};
