<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1:1 aus database/schema.sql. Optimistischer Versionszaehler, analog zu
// hundeboerse_meta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waffenboerse_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(0);
        });

        DB::table('waffenboerse_meta')->insertOrIgnore([
            'id' => 1, 'version' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('waffenboerse_meta');
    }
};
