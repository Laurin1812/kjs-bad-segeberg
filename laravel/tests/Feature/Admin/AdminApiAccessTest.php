<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Netlify Identity -> Laravel Fortify: deckt Auftragspunkt "Tests" fuer die
 * Rechteprüfung ab - Zugriff auf /admin (repräsentativ: ein Laravel-Admin-
 * API-Endpunkt) ohne Login, mit Login aber ohne passendes Recht, mit
 * passendem Recht, und mit der Rolle "admin" (immer voller Zugriff,
 * unabhängig von "permissions").
 *
 * "content/vorstand.json" (identity.permission:vorstand, siehe routes/
 * api.php) dient hier stellvertretend fuer JEDEN "identity.permission:
 * <key>"-Endpunkt - die Middleware-Logik ist fuer alle identisch (siehe
 * App\Http\Middleware\EnsureIdentityPermission).
 */
class AdminApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_api_rejects_unauthenticated_requests(): void
    {
        $this->putJson('/api/admin/content/vorstand.json', ['data' => []])
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => 'not_authenticated']);
    }

    public function test_editor_without_matching_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'roles' => [],
            'permissions' => ['aktuelles'], // NICHT 'vorstand'
        ]);

        $this->actingAs($user, 'web')
            ->putJson('/api/admin/content/vorstand.json', ['data' => []])
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => 'forbidden']);
    }

    public function test_editor_with_matching_permission_is_allowed_through_the_auth_layer(): void
    {
        $user = User::factory()->create([
            'roles' => [],
            'permissions' => ['vorstand'],
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/admin/content/vorstand.json', ['data' => []]);

        // Nicht 401/403 - die Auth-/Rechte-Middleware laesst durch (ein
        // 4xx/5xx AUS DEM CONTROLLER selbst, z.B. wegen des absichtlich
        // leeren Test-Bodys, ist fuer DIESEN Test irrelevant - es geht nur
        // um die Auth-Schicht davor).
        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_admin_role_bypasses_individual_permission_checks(): void
    {
        $user = User::factory()->create([
            'roles' => ['admin'],
            'permissions' => [], // bewusst LEER - admin braucht keine Einzelrechte
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/admin/content/vorstand.json', ['data' => []]);

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_page_permission_family_also_rejects_unauthenticated_requests(): void
    {
        // Stellvertretend fuer "identity.page_permission:<kind>" (siehe
        // App\Http\Middleware\EnsurePagePermission) - eigener Mechanismus,
        // eigener Test.
        $this->putJson('/api/admin/content/aufgaben/hundeausbildung.json', ['data' => []])
            ->assertStatus(401);
    }
}
