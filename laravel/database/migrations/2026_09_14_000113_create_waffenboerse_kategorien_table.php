<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql, inkl. der bestehenden Standardkategorien.
// Im Admin ueber "+ Neu"/Papierkorb verwaltet (waffenboerseKategorieAdd/
// -Delete in admin/admin.js) - bleibt unveraendert, schreibt kuenftig nur
// nach MySQL statt in die JSON-Datei.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waffenboerse_kategorien', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 190);
            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->unique('name', 'uniq_wb_kategorie_name');
        });

        DB::table('waffenboerse_kategorien')->insertOrIgnore([
            ['name' => 'Büchsen', 'sortierung' => 1],
            ['name' => 'Flinten', 'sortierung' => 2],
            ['name' => 'Kombinierte Waffen', 'sortierung' => 3],
            ['name' => 'Kurzwaffen', 'sortierung' => 4],
            ['name' => 'Optik', 'sortierung' => 5],
            ['name' => 'Zubehör', 'sortierung' => 6],
            ['name' => 'Sonstiges', 'sortierung' => 7],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_kategorien');
    }
};
