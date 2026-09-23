<?php

use App\Http\Middleware\EnsureAdminWebSession;
use App\Http\Middleware\EnsureIdentityPermission;
use App\Http\Middleware\EnsurePagePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL): serverseitige
        // Modul-Rechtepruefung fuer die neuen Admin-Schreib-Endpunkte, siehe
        // App\Http\Middleware\EnsureIdentityPermission und deren Verwendung
        // in routes/api.php ("identity.permission:<key>").
        $middleware->alias([
            'identity.permission' => EnsureIdentityPermission::class,
            // Phase 4 (Fortsetzung): dieselbe Absicherung wie oben, aber fuer
            // die Seiten-Routen (AdminPageController), deren Recht vom Slug
            // in der URL abhaengt - siehe EnsurePagePermission/PagePermissions.
            'identity.page_permission' => EnsurePagePermission::class,
            // Phase 7A (Laravel-Admin-Grundlage): Zugriffsschutz fuer die
            // neuen, server-gerenderten Blade-Admin-Routen - siehe
            // EnsureAdminWebSession-Klassenkommentar fuer die Abgrenzung
            // zu den beiden JSON-Middlewares oben.
            'admin.web' => EnsureAdminWebSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "api/*" rendert IMMER JSON (auch ohne Accept-Header) - noetig,
        // damit admin.js' zahlreiche fetch()-Aufrufe ohne eigenen
        // 'Accept'-Header (siehe z.B. apiPutLaravel()/apiGetLaravel() in
        // admin/admin.js) bei einer Fehler-/Validierungs-Exception
        // trotzdem verlaesslich JSON statt einer HTML-Fehlerseite
        // bekommen - r.json() wuerde sonst fehlschlagen.
        //
        // Phase 7A (Laravel-Admin-Grundlage): "api/admin/auth/*"
        // (Fortifys Login/Logout/Passwort-Endpunkte, siehe
        // config/fortify.php "paths") ist davon bewusst AUSGENOMMEN und
        // faellt stattdessen auf das normale expectsJson() zurueck. Grund:
        // genau diese drei Endpunkte werden jetzt von ZWEI Oberflaechen
        // gemeinsam genutzt - dem bestehenden admin.js (das fuer seine
        // Login/Logout-Aufrufe bereits EXPLIZIT 'Accept: application/json'
        // sendet, siehe dortige fetch()-Aufrufe - fuer admin.js aendert
        // sich durch expectsJson() also nichts) UND den NEUEN, klassischen
        // <form method="POST">-Seiten (siehe resources/views/admin/
        // login.blade.php etc.), die bei falschen Zugangsdaten die
        // uebliche Blade-Redirect-mit-$errors-Behandlung erwarten statt
        // einer 422-JSON-Antwort. Alle anderen "api/admin/*"-Endpunkte
        // (Content-Schreib-API) bleiben unveraendert bei der Blanko-JSON-
        // Regel.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ($request->is('api/*') && ! $request->is('api/admin/auth/*'))
                || $request->expectsJson(),
        );
    })->create();
