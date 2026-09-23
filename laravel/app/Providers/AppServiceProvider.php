<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\View\Composers\AllgemeineKontaktEmailComposer;
use App\View\Composers\DesignComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\NavigationComposer;
use App\View\Composers\TopbarComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Netlify Identity -> Laravel Fortify: Ersatz fuer die bisherige
        // Netlify-Function "record-last-login.js" (schrieb bei jedem Login
        // user_metadata.last_login, siehe admin/admin.js recordLastLogin()).
        // Laravel feuert dieses Event bereits automatisch bei jedem
        // erfolgreichen Auth::attempt()/guard->attempt() (u.a. innerhalb der
        // Fortify-Login-Pipeline, siehe Laravel\Fortify\Actions\
        // AttemptToAuthenticate) - kein eigener Aufruf in admin.js noetig.
        Event::listen(function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->save();
            }
        });

        // Phase 7A (Laravel-Admin-Grundlage): Laravels eingebaute
        // "ResetPassword"-Benachrichtigung (von Illuminate\Auth\Passwords\
        // PasswordBroker::sendResetLink() ueber Fortifys
        // PasswordResetLinkController ausgeloest, siehe config/fortify.php)
        // baut ihren Link standardmaessig ueber route('password.reset', ...).
        // Diese Route gibt es bei uns bewusst NICHT unter diesem Namen -
        // Fortifys eigene GET-View-Route ist deaktiviert (config('fortify.
        // views') = false) und unsere neue Blade-Seite heisst stattdessen
        // "admin.password.reset" (siehe routes/web.php) - ohne diese
        // Ueberschreibung wuerde der Versand mit einer
        // RouteNotFoundException abbrechen. Offiziell von Laravel
        // vorgesehener Erweiterungspunkt (ResetPassword::createUrlUsing),
        // keine Eigenkonstruktion.
        ResetPassword::createUrlUsing(function (object $user, string $token) {
            return route('admin.password.reset', ['token' => $token, 'email' => $user->email]);
        });

        // Phase 7A (Laravel-Admin-Grundlage): siehe App\Actions\Fortify\
        // ResetUserPassword-Klassenkommentar - der fehlende Bind fuer
        // Laravel\Fortify\Contracts\ResetsUserPasswords, ohne den
        // "Passwort zuruecksetzen" (Features::resetPasswords(), bereits in
        // config/fortify.php aktiviert) bislang gar nicht funktionsfaehig
        // war.
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Phase 3 Nacharbeit (100%-Laravel-Architektur-Korrektur): ersetzt
        // den bisherigen "fetch('/api/content/design.json')"-Aufruf im
        // Haupt-Layout - siehe DesignComposer-Klassenkommentar.
        View::composer('components.layouts.app', DesignComposer::class);

        // Phase 4 (Startseite + komplette Laravel-Navigation): ersetzt die
        // bisherigen "ZENTRALE NAVIGATION"-/"Topbar & Geschäftsstelle
        // dynamisch laden"-/"ZENTRALER FOOTER"-Module in resources/js/
        // app.js - siehe jeweiliger Composer-Klassenkommentar. Zwei
        // Composer fuer dieselbe View (components.site-header) sind in
        // Laravel unproblematisch, beide haengen lediglich weitere Daten
        // an dieselbe View an.
        View::composer('components.site-header', NavigationComposer::class);
        View::composer('components.site-header', TopbarComposer::class);
        View::composer('components.site-footer', FooterComposer::class);

        // Phase 4 Korrektur: letzte hart codierte allgemeine KJS-Mailadresse
        // in Fliesstexten - siehe AllgemeineKontaktEmailComposer-Klassen-
        // kommentar.
        View::composer(['faq', 'downloads', 'jaeger.vorstand'], AllgemeineKontaktEmailComposer::class);
    }
}
