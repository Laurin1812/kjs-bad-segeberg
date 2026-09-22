<?php

namespace App\Providers;

use App\Models\User;
use App\View\Composers\DesignComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        // Phase 3 Nacharbeit (100%-Laravel-Architektur-Korrektur): ersetzt
        // den bisherigen "fetch('/api/content/design.json')"-Aufruf im
        // Haupt-Layout - siehe DesignComposer-Klassenkommentar.
        View::composer('components.layouts.app', DesignComposer::class);
    }
}
