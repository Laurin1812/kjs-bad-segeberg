<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Netlify Identity -> Laravel Fortify: bestaetigt, dass CSRF-Schutz fuer die
 * Admin-Routen ueberhaupt EINGERICHTET ist.
 *
 * WICHTIG: Laravels eigene PreventRequestForgery-Middleware deaktiviert
 * sich in der automatisierten Testumgebung ABSICHTLICH selbst
 * (runningUnitTests(), siehe Illuminate\Foundation\Http\Middleware\
 * PreventRequestForgery::handle()) - ein funktionaler "POST ohne Token wird
 * abgelehnt"-Test wuerde deshalb in PHPUnit IMMER gruen sein, unabhaengig
 * davon, ob CSRF tatsaechlich aktiv ist. Dieser Test prueft deshalb
 * STRUKTURELL, dass die Admin-Routen durch die "web"-Middleware-Gruppe
 * laufen (die PreventRequestForgery enthaelt) - das reale CSRF-Verhalten
 * wurde stattdessen manuell/per Code-Review verifiziert (siehe
 * Abschlussbericht Punkt 9: 419 bei fehlendem/falschem X-XSRF-TOKEN, 200 im
 * Live-Betrieb mit gueltigem Token aus dem XSRF-TOKEN-Cookie).
 */
class CsrfConfigurationTest extends TestCase
{
    public function test_admin_routes_run_through_the_web_middleware_group(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->uri() === 'api/admin/content/vorstand.json' && in_array('PUT', $r->methods(), true)
        );

        $this->assertNotNull($route, 'Erwartete Admin-Route wurde nicht gefunden.');
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_fortify_login_route_runs_through_the_web_middleware_group(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->uri() === 'api/admin/auth/login' && in_array('POST', $r->methods(), true)
        );

        $this->assertNotNull($route, 'Fortify-Login-Route wurde nicht gefunden - config/fortify.php pruefen.');
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
