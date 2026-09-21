<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Netlify Identity -> Laravel Fortify: Ersatz fuer die bisherige
 * Benutzeranlage ueber die Netlify-Identity-Admin-API (siehe vorher
 * netlify/functions/admin-users.js, POST-Zweig "Benutzer einladen").
 *
 * Auftragspunkt 9 ("Admin-Benutzer werden manuell/administrativ angelegt"):
 * bewusst KEIN "Registrierung"/"Einladung per E-Mail"-Weg (Features::
 * registration() ist in config/fortify.php absichtlich nicht aktiviert,
 * MAIL_MAILER ist aktuell nicht konfiguriert) - stattdessen dieses
 * Artisan-Kommando fuer Carsten/den Admin, direkt auf dem Server
 * auszufuehren:
 *
 *   php artisan admin:create-user frank@example.com "Frank Mustermann" --admin
 *   php artisan admin:create-user nicole@example.com "Nicole Redakteurin" --permission=aktuelles --permission=termine
 *
 * Legt einen neuen Benutzer an ODER aktualisiert einen bestehenden (per
 * E-Mail gefunden) - fragt das Passwort interaktiv ab (nie als Klartext-
 * Kommandozeilenargument, landet dadurch nicht in der Shell-History/in
 * Prozesslisten). "--admin" entspricht der bisherigen Netlify-Identity-
 * Rolle "admin" (voller Zugriff, siehe App\Support\AdminIdentity::isAdmin());
 * "--permission=<key>" (wiederholbar) entspricht den bisherigen granularen
 * app_metadata.permissions-Eintraegen (siehe admin/admin.js' PERM_BY_KEY
 * fuer die gueltigen Schluessel, z.B. "aktuelles", "termine", "hundeboerse").
 */
class CreateAdminUser extends Command
{
    protected $signature = 'admin:create-user
        {email : E-Mail-Adresse (Login-Name)}
        {name : Anzeigename}
        {--admin : Rolle "admin" zuweisen (voller Zugriff auf alle Module)}
        {--permission=* : Einzelne Modul-Berechtigung zuweisen (wiederholbar), z.B. --permission=aktuelles}';

    protected $description = 'Legt einen Admin-/Redakteur-Benutzer fuer den Laravel-Admin-Login an oder aktualisiert dessen Rollen/Rechte.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $name = trim((string) $this->argument('name'));
        $isAdmin = (bool) $this->option('admin');
        $permissions = array_values(array_unique(array_filter(array_map('trim', $this->option('permission')))));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            ['email' => ['required', 'email'], 'name' => ['required', 'string']]
        );
        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        $password = $this->secret($existing
            ? 'Neues Passwort fuer '.$email.' (leer lassen, um das bestehende Passwort zu behalten)'
            : 'Passwort fuer '.$email);

        if (! $existing && ($password === null || $password === '')) {
            $this->error('Fuer einen neuen Benutzer ist ein Passwort erforderlich.');

            return self::FAILURE;
        }

        if ($password !== null && $password !== '') {
            $confirm = $this->secret('Passwort wiederholen');
            if ($password !== $confirm) {
                $this->error('Die beiden Passwoerter stimmen nicht ueberein.');

                return self::FAILURE;
            }
        }

        $roles = $isAdmin ? ['admin'] : [];

        $attributes = [
            'name' => $name,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        if ($password !== null && $password !== '') {
            $attributes['password'] = Hash::make($password);
        }

        $user = User::updateOrCreate(['email' => $email], $attributes);

        $this->info(($existing ? 'Benutzer aktualisiert: ' : 'Benutzer angelegt: ').$user->email);
        $this->line('Rolle admin: '.($isAdmin ? 'ja' : 'nein'));
        $this->line('Berechtigungen: '.($permissions === [] ? '(keine einzelnen - nur relevant ohne --admin)' : implode(', ', $permissions)));

        return self::SUCCESS;
    }
}
