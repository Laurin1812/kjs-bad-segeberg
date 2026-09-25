<?php

namespace Tests\Feature\Phase7L;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 7L (Admin-Modul "Benutzer"), Teil B - deckt den neuen,
 * server-gerenderten Bereich unter "/admin/benutzer" ab
 * (Http\Controllers\Admin\BenutzerController) - siehe dortiger
 * Klassenkommentar fuer die vollstaendige Analyse/Schutzregeln.
 */
class AdminBenutzerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['roles' => ['admin'], 'permissions' => []], $overrides));
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create(['roles' => [], 'permissions' => []]);
    }

    // ── Zugriff ──────────────────────────────────────────────────────

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.benutzer.index'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.benutzer.index'))->assertForbidden();
    }

    // ── Liste ────────────────────────────────────────────────────────

    public function test_liste_zeigt_bestehende_benutzer_und_ihre_rolle(): void
    {
        $admin = $this->admin(['name' => 'Frank Admin', 'email' => 'frank@example.test']);
        User::factory()->create(['name' => 'Nicole Redakteurin', 'email' => 'nicole@example.test', 'roles' => [], 'permissions' => []]);
        $this->actingAs($admin, 'web');

        $this->get(route('admin.benutzer.index'))
            ->assertOk()
            ->assertSee('Frank Admin')
            ->assertSee('Nicole Redakteurin')
            ->assertSee('Administrator');
    }

    // ── Anlegen ──────────────────────────────────────────────────────

    public function test_neuer_benutzer_kann_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.benutzer.speichern'), [
            'name' => 'Neuer Redakteur',
            'email' => 'neu@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertRedirect();

        $benutzer = User::where('email', 'neu@example.test')->first();
        $this->assertNotNull($benutzer);
        $this->assertSame([], $benutzer->roles);
        $this->assertTrue(Hash::check('ein-sicheres-passwort', $benutzer->password));
    }

    public function test_neuer_benutzer_kann_als_administrator_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.benutzer.speichern'), [
            'name' => 'Neuer Admin',
            'email' => 'neuadmin@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
            'ist_admin' => '1',
        ])->assertRedirect();

        $this->assertSame(['admin'], User::where('email', 'neuadmin@example.test')->first()->roles);
    }

    public function test_email_muss_eindeutig_sein(): void
    {
        User::factory()->create(['email' => 'belegt@example.test']);
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.benutzer.speichern'), [
            'name' => 'Doppelt',
            'email' => 'belegt@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertSessionHasErrors('email');
    }

    public function test_rohe_rollen_aus_dem_request_werden_ignoriert(): void
    {
        $this->actingAs($this->admin(), 'web');

        // Ein manipulierter Request schickt ein rohes "roles"-Array direkt
        // mit - das darf niemals uebernommen werden (siehe Klassenkommentar
        // "keine frei erfundenen Rollen"), nur die "ist_admin"-Checkbox
        // zaehlt.
        $this->post(route('admin.benutzer.speichern'), [
            'name' => 'Manipuliert',
            'email' => 'manipuliert@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
            'roles' => ['superadmin', 'redakteur'],
        ])->assertRedirect();

        $this->assertSame([], User::where('email', 'manipuliert@example.test')->first()->roles);
    }

    // ── Bearbeiten ───────────────────────────────────────────────────

    public function test_name_und_email_koennen_bearbeitet_werden(): void
    {
        $benutzer = User::factory()->create(['name' => 'Alt', 'email' => 'alt@example.test', 'roles' => [], 'permissions' => []]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.benutzer.aktualisieren', $benutzer), [
            'name' => 'Neu',
            'email' => 'neu2@example.test',
        ])->assertRedirect();

        $benutzer->refresh();
        $this->assertSame('Neu', $benutzer->name);
        $this->assertSame('neu2@example.test', $benutzer->email);
    }

    public function test_leeres_passwortfeld_behaelt_den_bestehenden_hash(): void
    {
        $benutzer = User::factory()->create(['password' => Hash::make('urspruenglich'), 'roles' => [], 'permissions' => []]);
        $bestehenderHash = $benutzer->password;
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.benutzer.aktualisieren', $benutzer), [
            'name' => $benutzer->name,
            'email' => $benutzer->email,
        ])->assertRedirect();

        $this->assertSame($bestehenderHash, $benutzer->refresh()->password);
        $this->assertTrue(Hash::check('urspruenglich', $benutzer->password));
    }

    public function test_neues_passwort_ersetzt_den_hash(): void
    {
        $benutzer = User::factory()->create(['password' => Hash::make('urspruenglich'), 'roles' => [], 'permissions' => []]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.benutzer.aktualisieren', $benutzer), [
            'name' => $benutzer->name,
            'email' => $benutzer->email,
            'password' => 'ganz-neues-passwort',
            'password_confirmation' => 'ganz-neues-passwort',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('ganz-neues-passwort', $benutzer->refresh()->password));
    }

    public function test_rollen_koennen_ueber_die_checkbox_geaendert_werden(): void
    {
        $benutzer = User::factory()->create(['roles' => [], 'permissions' => []]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.benutzer.aktualisieren', $benutzer), [
            'name' => $benutzer->name,
            'email' => $benutzer->email,
            'ist_admin' => '1',
        ])->assertRedirect();

        $this->assertSame(['admin'], $benutzer->refresh()->roles);
    }

    // ── Schutzregeln ─────────────────────────────────────────────────

    public function test_eigener_account_kann_sich_nicht_selbst_die_admin_rolle_entziehen(): void
    {
        $this->admin(); // zweiter Admin, damit "letzter Admin" hier NICHT greift
        $eigenerAdmin = $this->admin();
        $this->actingAs($eigenerAdmin, 'web');

        $this->put(route('admin.benutzer.aktualisieren', $eigenerAdmin), [
            'name' => $eigenerAdmin->name,
            'email' => $eigenerAdmin->email,
            // "ist_admin" bewusst weggelassen -> Checkbox unangehakt -> Versuch, sich selbst zu entadministrisieren.
        ])->assertSessionHasErrors('ist_admin');

        $this->assertSame(['admin'], $eigenerAdmin->refresh()->roles);
    }

    /**
     * Gegenprobe zum Selbst-Lockout-Schutz oben: solange NACH der
     * Herabstufung noch mindestens ein anderer Administrator uebrig bleibt,
     * darf die Herabstufung eines ANDEREN Benutzers ganz normal
     * funktionieren - die Schutzregel darf nicht ueber ihr eigentliches Ziel
     * hinausschiessen.
     */
    public function test_admin_kann_von_einem_anderen_admin_herabgestuft_werden_wenn_ein_admin_uebrig_bleibt(): void
    {
        $herabzustufenderAdmin = $this->admin();
        $andererAdmin = $this->admin();
        $this->actingAs($andererAdmin, 'web');

        $this->put(route('admin.benutzer.aktualisieren', $herabzustufenderAdmin), [
            'name' => $herabzustufenderAdmin->name,
            'email' => $herabzustufenderAdmin->email,
        ])->assertRedirect();

        $this->assertSame([], $herabzustufenderAdmin->refresh()->roles);
    }

    public function test_eigener_account_kann_nicht_geloescht_werden(): void
    {
        $this->admin(); // zweiter Admin, damit "letzter Admin" hier NICHT greift
        $eigenerAdmin = $this->admin();
        $this->actingAs($eigenerAdmin, 'web');

        $this->delete(route('admin.benutzer.loeschen', $eigenerAdmin))->assertSessionHasErrors();

        $this->assertNotNull($eigenerAdmin->fresh());
    }

    public function test_nicht_letzter_administrator_kann_von_anderem_admin_geloescht_werden(): void
    {
        $zuLoeschenderAdmin = $this->admin();
        $andererAdmin = $this->admin();
        $this->actingAs($andererAdmin, 'web');

        $this->delete(route('admin.benutzer.loeschen', $zuLoeschenderAdmin))->assertRedirect(route('admin.benutzer.index'));

        $this->assertNull($zuLoeschenderAdmin->fresh());
    }

    public function test_manipulierte_id_ergibt_404(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.benutzer.bearbeiten', ['benutzer' => 999999]))->assertNotFound();
    }
}
