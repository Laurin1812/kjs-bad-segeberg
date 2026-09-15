<?php

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
