<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Netlify Identity -> Laravel Fortify: deckt die im Auftrag geforderten
 * Mindest-Tests fuer den neuen Login/Logout-Ablauf ab (Auftragspunkt
 * "Tests"): korrekter Login, falsches Passwort, Logout, Zugriff nach
 * Login, Rate-Limiting.
 *
 * HINWEIS: konnte in dieser Sandbox nicht ausgefuehrt werden, weil
 * "composer install" hier keinen Zugriff auf Packagist/GitHub-Zip-Downloads
 * hat (siehe Abschlussbericht Punkt 9) - vendor/ existiert dadurch nicht.
 * Auf einer Maschine mit normalem Internetzugang: "composer install" bzw.
 * "composer require laravel/fortify", danach "php artisan test".
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'frank@example.test',
            'password' => Hash::make('correct-password'),
            'roles' => ['admin'],
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'frank@example.test',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'frank@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'frank@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest('web');
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'does-not-exist@example.test',
            'password' => 'whatever',
        ]);

        $response->assertStatus(422);
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'frank@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/auth/login', [
                'email' => 'frank@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // Sechster Versuch (auch mit dem RICHTIGEN Passwort) wird von
        // Fortifys eingebautem Rate-Limiter (5 Versuche/60s je E-Mail+IP,
        // siehe Laravel\Fortify\LoginRateLimiter) abgeblockt.
        $this->postJson('/api/admin/auth/login', [
            'email' => 'frank@example.test',
            'password' => 'correct-password',
        ])->assertStatus(429);

        $this->assertGuest('web');
    }

    public function test_authenticated_session_check_reflects_logged_in_user(): void
    {
        $user = User::factory()->create([
            'roles' => ['admin'],
            'permissions' => [],
        ]);

        $this->actingAs($user, 'web')
            ->getJson('/api/admin/auth/user')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'email' => $user->email,
                'roles' => ['admin'],
            ]);
    }

    public function test_session_check_returns_401_when_not_logged_in(): void
    {
        $this->getJson('/api/admin/auth/user')->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');
        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson('/api/admin/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_login_regenerates_the_session_id(): void
    {
        // Sitzungsfixierung: nach erfolgreichem Login MUSS eine neue
        // Sitzungs-ID vergeben werden (siehe Laravel\Fortify\Actions\
        // PrepareAuthenticatedSession, ruft $request->session()->
        // regenerate() auf) - sonst könnte ein Angreifer eine ihm bekannte
        // Sitzungs-ID vor dem Login "unterschieben" und nach dessen Login
        // mitbenutzen.
        User::factory()->create([
            'email' => 'frank@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->startSession();
        $idBeforeLogin = $this->app['session']->getId();

        $this->postJson('/api/admin/auth/login', [
            'email' => 'frank@example.test',
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertNotSame($idBeforeLogin, $this->app['session']->getId());
    }
}
