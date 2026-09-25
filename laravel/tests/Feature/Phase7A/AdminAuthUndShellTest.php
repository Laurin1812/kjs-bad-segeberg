<?php

namespace Tests\Feature\Phase7A;

use App\Models\Beitrag;
use App\Models\HundeboerseAnzeige;
use App\Models\KontaktAnfrage;
use App\Models\Page;
use App\Models\Termin;
use App\Models\User;
use App\Models\WaffenboerseAnzeige;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell): deckt den neuen,
 * server-gerenderten Blade-Admin unter "/admin" ab - Login/Logout/Passwort-
 * Reset (siehe AuthPageController, alle drei Endpunkte selbst bleiben
 * unveraendert Laravel Fortify, siehe config/fortify.php), den Zugriffs-
 * schutz (App\Http\Middleware\EnsureAdminWebSession) und das Dashboard
 * (DashboardController). Bewusst NICHT Teil dieser Datei: die bereits
 * VOR Phase 7A bestehende JSON-Admin-API unter "/api/admin/*"
 * (routes/api.php, "Phase 4 Admin-Schreib-API") - die wird unveraendert
 * von den bestehenden Tests (siehe tests/Feature/Admin/) abgedeckt und ist
 * in Phase 7A nicht angefasst worden.
 *
 * Admin-Testnutzer werden ausschliesslich per User::factory() angelegt
 * (siehe Auftrag: "Fuer Tests duerfen Factory-/Testnutzer verwendet
 * werden. Keine echten Passwoerter oder E-Mail-Adressen hardcoden.") -
 * E-Mail/Passwort kommen aus Faker (fake()->unique()->safeEmail()) bzw.
 * einem zufaelligen Str::random()-Passwort, nie einem festen String.
 */
class AdminAuthUndShellTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'roles' => ['admin'],
            'permissions' => [],
        ], $overrides));
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create([
            'roles' => [],
            'permissions' => [],
        ]);
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz (Teil 3 / Teil 8)
    // -----------------------------------------------------------------

    public function test_dashboard_ohne_anmeldung_leitet_zur_login_seite_um(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_dashboard_leitet_nach_login_automatisch_zur_urspruenglich_gewuenschten_seite(): void
    {
        // redirect()->guest() in EnsureAdminWebSession setzt "url.intended" -
        // Fortifys eigene LoginResponse nutzt redirect()->intended(...), das
        // genau diesen Wert wieder ausliest (siehe Klassenkommentar).
        $this->get(route('admin.dashboard'));

        $admin = $this->admin();

        $response = $this->post(url(config('fortify.paths.login')), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_seite_ist_ohne_anmeldung_erreichbar(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertSee('KJS Admin');
        $response->assertSee(url(config('fortify.paths.login')), false);
    }

    public function test_bereits_angemeldeter_admin_wird_von_der_login_seite_zum_dashboard_geleitet(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_ungueltiger_login_wird_abgelehnt(): void
    {
        $admin = $this->admin();

        $response = $this->post(url(config('fortify.paths.login')), [
            'email' => $admin->email,
            'password' => 'falsches-passwort',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_gueltiger_admin_login_funktioniert_und_nutzt_die_sitzung(): void
    {
        $admin = $this->admin();

        $response = $this->post(url(config('fortify.paths.login')), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');

        // Sitzung wird tatsaechlich verwendet: das Dashboard ist jetzt ohne
        // erneuten Login erreichbar.
        $dashboard = $this->get(route('admin.dashboard'));
        $dashboard->assertOk();
    }

    public function test_logout_funktioniert(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'web');

        $response = $this->post(url(config('fortify.paths.logout')));

        // Phase-7A-Anpassung in config/fortify.php ("redirects.logout"):
        // fuehrt zur neuen Login-Seite statt zur oeffentlichen Startseite.
        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('web');

        $dashboard = $this->get(route('admin.dashboard'));
        $dashboard->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $response = $this->get(route('admin.dashboard'));

        $response->assertForbidden();
        $response->assertSee('Kein Zugriff');
    }

    public function test_keine_oeffentliche_registrierung(): void
    {
        $this->assertFalse(Route::has('register'));

        $response = $this->get('/register');
        $response->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Dashboard (Teil 4 / Teil 8)
    // -----------------------------------------------------------------

    public function test_admin_dashboard_zeigt_echte_zahlen_aus_der_datenbank(): void
    {
        // Keine Factories fuer diese Models (nur UserFactory existiert im
        // Projekt) - stattdessen direkte ::create()-Aufrufe mit denselben
        // Pflichtfeldern, die die jeweiligen Controller selbst verwenden
        // (siehe HundeboerseController::store()/WaffenboerseController::
        // store() fuer die "id"-Vergabe der beiden String-Primaerschluessel).
        for ($i = 1; $i <= 3; $i++) {
            Page::create(['section' => 'jaeger', 'slug' => 'testseite-'.$i, 'titel' => 'Testseite '.$i]);
        }
        for ($i = 1; $i <= 2; $i++) {
            Beitrag::create(['typ' => 'aktuelles', 'slug' => 'testbeitrag-'.$i, 'titel' => 'Testbeitrag '.$i]);
        }
        for ($i = 1; $i <= 4; $i++) {
            Termin::create(['datum' => now()->addDays($i)->toDateString(), 'veranstaltung' => 'Testtermin '.$i]);
        }
        KontaktAnfrage::create(['status' => 'neu']);
        KontaktAnfrage::create(['status' => 'bearbeitet']);
        HundeboerseAnzeige::create(['id' => 'hb-test-1', 'status' => 'pending']);
        HundeboerseAnzeige::create(['id' => 'hb-test-2', 'status' => 'published']);
        WaffenboerseAnzeige::create(['id' => 'wb-test-1', 'status' => 'pending']);

        $this->actingAs($this->admin(), 'web');
        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('anzahlSeiten', 3);
        $response->assertViewHas('anzahlAktuelles', 2);
        $response->assertViewHas('anzahlTermine', 4);
        $response->assertViewHas('anzahlOffeneKontaktanfragen', 1);
        $response->assertViewHas('anzahlPendingHundeboerse', 1);
        $response->assertViewHas('anzahlPendingWaffenboerse', 1);
    }

    // -----------------------------------------------------------------
    // Passwort-Reset-Grundweg (Teil 2 / Teil 8)
    // -----------------------------------------------------------------

    public function test_passwort_vergessen_formular_ist_erreichbar(): void
    {
        $response = $this->get(route('admin.password.request'));

        $response->assertOk();
        $response->assertSee(url(config('fortify.paths.password.email')), false);
    }

    public function test_passwort_vergessen_legt_bei_bekannter_adresse_einen_reset_token_an(): void
    {
        $admin = $this->admin();

        $response = $this->post(url(config('fortify.paths.password.email')), [
            'email' => $admin->email,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_passwort_zuruecksetzen_formular_ist_erreichbar(): void
    {
        $response = $this->get(route('admin.password.reset', ['token' => 'irgendein-token', 'email' => 'test@example.test']));

        $response->assertOk();
        $response->assertSee(url(config('fortify.paths.password.update')), false);
    }

    public function test_passwort_zuruecksetzen_mit_gueltigem_token_funktioniert(): void
    {
        $admin = $this->admin();

        $token = Password::broker(config('fortify.passwords'))->createToken($admin);

        $response = $this->post(url(config('fortify.paths.password.update')), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'ein-ganz-neues-passwort',
            'password_confirmation' => 'ein-ganz-neues-passwort',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check(
            'ein-ganz-neues-passwort',
            $admin->fresh()->password
        ));
    }

    // -----------------------------------------------------------------
    // CSRF (Teil 8) - siehe Phase6C/KontaktFormularTest.php fuer dasselbe
    // Vorgehen (app['env'] vorübergehend aus "testing" herausnehmen, weil
    // PreventRequestForgery::runningUnitTests() CSRF sonst planmaessig
    // uebergeht).
    // -----------------------------------------------------------------

    public function test_login_ist_csrf_geschuetzt(): void
    {
        $admin = $this->admin();
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->post(url(config('fortify.paths.login')), [
                'email' => $admin->email,
                'password' => 'password',
            ]);
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertGuest('web');
    }

    // -----------------------------------------------------------------
    // Login-Rate-Limit (Teil 2 / Teil 8) - Fortifys eingebauter Standard-
    // Limiter (Laravel\Fortify\LoginRateLimiter): 5 Fehlversuche je
    // E-Mail+IP, danach 60 Sekunden Sperre unabhaengig vom Passwort.
    // -----------------------------------------------------------------

    public function test_login_wird_nach_fuenf_fehlversuchen_gedrosselt(): void
    {
        Cache::flush();
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post(url(config('fortify.paths.login')), [
                'email' => $admin->email,
                'password' => 'falsches-passwort',
            ]);
        }

        $response = $this->post(url(config('fortify.paths.login')), [
            'email' => $admin->email,
            'password' => 'password', // sogar mit dem RICHTIGEN Passwort gesperrt.
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Zu viele',
            session('errors')->get('email')[0]
        );
        $this->assertGuest('web');
    }

    // -----------------------------------------------------------------
    // Navigation (Teil 5) - keine 404-Navigation.
    // -----------------------------------------------------------------

    /**
     * Nachbesserung Phase 7L (letzter Baustein "Einstellungen"/"Benutzer",
     * siehe Admin\EinstellungenController-/BenutzerController-
     * Klassenkommentar): bis einschliesslich Phase 7K zeigte dieser Test
     * noch, dass NOCH NICHT migrierte Module als nicht-klickbare "Folgt"-
     * Platzhalter erscheinen (siehe Klassenkommentar admin.blade.php,
     * Auftrag "keine 404-Navigation erzeugen") - seit Phase 7L ist die
     * Migration aller Sidebar-Punkte abgeschlossen, es gibt keinen einzigen
     * "Folgt"-Platzhalter mehr. Die Kernaussage des Tests bleibt (keine
     * 404-Navigation), nur die erwartete Ausgangslage hat sich geaendert -
     * dieselbe Umbenennung/Anpassung wie bei jedem vorherigen Fachmodul, das
     * einen bis dahin offenen Platzhalter geschlossen hat.
     */
    public function test_admin_shell_zeigt_alle_module_als_echte_links_ohne_platzhalter(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Folgt');
        $response->assertSee('href="'.route('admin.aktuelles.index').'"', false);
        $response->assertSee('href="'.route('admin.einstellungen.index').'"', false);
        $response->assertSee('href="'.route('admin.benutzer.index').'"', false);
    }
}
