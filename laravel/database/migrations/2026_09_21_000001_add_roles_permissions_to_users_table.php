<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Netlify Identity -> Laravel Fortify (Session/"web"-Guard): die
 * bestehende "users"-Tabelle (siehe 0001_01_01_000000_create_users_table)
 * wird bewusst NUR ERGAENZT, nicht ersetzt oder neu aufgebaut ("vorhandene
 * users-Tabelle verwenden bzw. sauber ergaenzen", Auftragspunkt 3).
 *
 * "roles"/"permissions" bilden 1:1 dieselbe Datenstruktur ab, die bisher
 * im app_metadata eines Netlify-Identity-JWT stand (siehe App\Support\
 * NetlifyIdentity::currentUser(), jetzt App\Support\AdminIdentity) - eine
 * Liste von Strings, z.B. roles: ["admin"] oder permissions:
 * ["hundeboerse","waffenboerse"]. NULL wird von App\Support\AdminIdentity
 * wie eine leere Liste behandelt (frisch angelegte Redakteure ohne
 * zugewiesene Rechte).
 *
 * "last_login_at" ersetzt die bisherige Netlify-Function
 * "record-last-login.js" (schrieb user_metadata.last_login) - dieselbe
 * Anzeige in der Benutzerverwaltung, jetzt aus der eigenen users-Tabelle
 * befuellt (siehe App\Providers\AppServiceProvider: Listener auf das
 * Illuminate\Auth\Events\Login-Event).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('password');
            $table->json('permissions')->nullable()->after('roles');
            $table->timestamp('last_login_at')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['roles', 'permissions', 'last_login_at']);
        });
    }
};
