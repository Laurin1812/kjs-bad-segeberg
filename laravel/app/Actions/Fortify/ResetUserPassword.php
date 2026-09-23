<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Rules\Password;

/**
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Fehlender Baustein der bereits VOR Phase 7A bestehenden Fortify-
 * Konfiguration: config/fortify.php aktiviert Features::resetPasswords()
 * bereits (siehe dortiger Kommentar "die Routen existieren dadurch
 * bereits"), aber Laravel Fortifys eigener Contracts\ResetsUserPasswords
 * hat OHNE eine konkrete Implementierung keinen gebundenen Klassennamen
 * (dieser Bind entsteht normalerweise erst durch das offizielle
 * "php artisan fortify:install"-Scaffolding, das eine eigene
 * FortifyServiceProvider mit "Fortify::resetUserPasswordsUsing(...)"
 * anlegt - dieser Schritt fehlte bislang, siehe Alt-Admin-Inventur: kein
 * "app/Actions/Fortify"-Verzeichnis vorhanden). Ohne diese Klasse haette
 * "Passwort zuruecksetzen" NIE funktioniert, auch nicht fuer admin.js -
 * kein Verhaltensunterschied zwischen alter und neuer Oberflaeche, nur ein
 * bislang fehlender Baustein derselben, schon vorhandenen Funktion. 1:1
 * Standard-Fortify-Vorlage (siehe Fortify-Dokumentation), keine
 * Eigenkonstruktion - Registrierung in App\Providers\AppServiceProvider::
 * boot() ueber Fortify::resetUserPasswordsUsing(self::class).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', new Password, 'confirmed'],
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
