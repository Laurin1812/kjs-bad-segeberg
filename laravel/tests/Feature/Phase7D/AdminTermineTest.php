<?php

namespace Tests\Feature\Phase7D;

use App\Models\Hegering;
use App\Models\Termin;
use App\Models\User;
use App\Support\ContentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7D (Admin-Modul "Termine"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/termine" ab (Http\Controllers\Admin\
 * TermineController) - Liste (inkl. archivierter/vergangener Termine),
 * Anlegen-/Bearbeiten-Formular, Archivieren/Wiederherstellen, Loeschen,
 * Ueberschrift/Einleitungstext. Die BESTEHENDE JSON-Schreib-API unter
 * "/api/admin/content/termine.json" (admin.js, Api\Admin\
 * AdminListController::termine()) bleibt unveraendert nutzbar - siehe
 * test_json_admin_api_*() unten, die fuer diesen Endpunkt bislang komplett
 * FEHLENDE Regressionsabdeckung ergaenzen (analog zu Phase 7C Auftrag Teil
 * 13). Ebenso wird geprueft, dass die oeffentliche "/termine"-Seite und die
 * "Naechste Termine"-Box auf der Startseite durch Phase 7D keine Regression
 * bekommen (beide nutzen weiterhin ausschliesslich App\Support\
 * TermineRules, siehe TermineController (oeffentlich)/HomeController).
 */
class AdminTermineTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['roles' => ['admin'], 'permissions' => []]);
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create(['roles' => [], 'permissions' => []]);
    }

    private function termin(array $overrides = []): Termin
    {
        return Termin::create(array_merge([
            'datum' => now()->addDays(10)->toDateString(),
            'uhrzeit' => '19:00',
            'veranstaltung' => 'Test-Termin',
            'strasse' => 'Teststraße 1',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'revier' => 'Hegering 1',
            'kategorie' => 'Hauptversammlung',
            'archiviert' => false,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_von_allen_admin_termine_routen_zum_login_umgeleitet(): void
    {
        $termin = $this->termin();

        $this->get(route('admin.termine.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.termine.neu'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.termine.store'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.termine.bearbeiten', $termin))->assertRedirect(route('admin.login'));
        $this->put(route('admin.termine.update', $termin))->assertRedirect(route('admin.login'));
        $this->put(route('admin.termine.archivieren', $termin))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.termine.loeschen', $termin))->assertRedirect(route('admin.login'));
        $this->post(route('admin.termine.einstellungen'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403_auf_allen_admin_termine_routen(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');
        $termin = $this->termin();

        $this->get(route('admin.termine.index'))->assertForbidden();
        $this->get(route('admin.termine.neu'))->assertForbidden();
        $this->post(route('admin.termine.store'))->assertForbidden();
        $this->get(route('admin.termine.bearbeiten', $termin))->assertForbidden();
        $this->put(route('admin.termine.update', $termin))->assertForbidden();
        $this->put(route('admin.termine.archivieren', $termin))->assertForbidden();
        $this->delete(route('admin.termine.loeschen', $termin))->assertForbidden();
        $this->post(route('admin.termine.einstellungen'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste
    // -----------------------------------------------------------------

    public function test_liste_zeigt_auch_vergangene_und_archivierte_termine(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->termin(['veranstaltung' => 'Vergangener Termin', 'datum' => now()->subDays(30)->toDateString()]);
        $this->termin(['veranstaltung' => 'Archivierter Termin', 'archiviert' => true]);
        $this->termin(['veranstaltung' => 'Aktueller Termin']);

        $response = $this->get(route('admin.termine.index'));

        $response->assertOk();
        // Gegenprobe zur oeffentlichen Seite (Phase 2 TermineTest): dort
        // waeren beide oben ausgeblendet - hier MUESSEN sie erscheinen.
        $response->assertSee('Vergangener Termin');
        $response->assertSee('Archivierter Termin');
        $response->assertSee('Aktueller Termin');
    }

    // -----------------------------------------------------------------
    // Anlegen
    // -----------------------------------------------------------------

    public function test_termin_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $versionVorher = ContentVersioning::current('termine');

        $response = $this->post(route('admin.termine.store'), [
            'datum' => now()->addDays(5)->toDateString(),
            'uhrzeit' => '18:00',
            'veranstaltung' => 'Neuer Termin',
            'strasse' => 'Musterweg 3',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'revier' => 'Hegering 2',
            'kategorie' => 'Jugend',
            'expected_version' => $versionVorher,
        ]);

        $termin = Termin::where('veranstaltung', 'Neuer Termin')->firstOrFail();
        $response->assertRedirect(route('admin.termine.bearbeiten', $termin));
        $this->assertSame('Musterweg 3', $termin->strasse);
        $this->assertSame('Jugend', $termin->kategorie);
        $this->assertFalse($termin->archiviert);
        $this->assertSame($versionVorher + 1, ContentVersioning::current('termine'));
    }

    // -----------------------------------------------------------------
    // Bearbeiten
    // -----------------------------------------------------------------

    public function test_termin_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin();

        $response = $this->put(route('admin.termine.update', $termin), [
            'datum' => $termin->datum->toDateString(),
            'uhrzeit' => '20:00',
            'veranstaltung' => 'Geänderter Titel',
            'strasse' => $termin->strasse,
            'plz' => $termin->plz,
            'ort' => $termin->ort,
            'revier' => $termin->revier,
            'kategorie' => $termin->kategorie,
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.bearbeiten', $termin));
        $termin->refresh();
        $this->assertSame('Geänderter Titel', $termin->veranstaltung);
        $this->assertSame('20:00', $termin->uhrzeit);
    }

    /**
     * Preservation (Auftrag Teil E): ein Teil-Request, der ein optionales
     * Feld gar nicht mitschickt, darf dessen bestehenden Wert nicht auf
     * Default zuruecksetzen - dasselbe Muster wie bei Aktuelles/Inhalte.
     */
    public function test_preservation_nicht_gesendeter_felder_beim_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin(['strasse' => 'Bleibt erhalten', 'plz' => '99999', 'revier' => 'Hegering 7']);

        $response = $this->put(route('admin.termine.update', $termin), [
            // "strasse"/"plz"/"revier" absichtlich NICHT mitgeschickt.
            'datum' => $termin->datum->toDateString(),
            'veranstaltung' => 'Nur Titel geändert',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.bearbeiten', $termin));
        $termin->refresh();
        $this->assertSame('Nur Titel geändert', $termin->veranstaltung);
        $this->assertSame('Bleibt erhalten', $termin->strasse);
        $this->assertSame('99999', $termin->plz);
        $this->assertSame('Hegering 7', $termin->revier);
    }

    // -----------------------------------------------------------------
    // Archivieren / Wiederherstellen
    // -----------------------------------------------------------------

    public function test_termin_archivieren(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin(['archiviert' => false]);

        $response = $this->put(route('admin.termine.archivieren', $termin), [
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.index'));
        $this->assertTrue($termin->fresh()->archiviert);
    }

    public function test_termin_wiederherstellen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin(['archiviert' => true]);

        $response = $this->put(route('admin.termine.archivieren', $termin), [
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.index'));
        $this->assertFalse($termin->fresh()->archiviert);
    }

    // -----------------------------------------------------------------
    // Löschen
    // -----------------------------------------------------------------

    public function test_termin_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin();

        $response = $this->delete(route('admin.termine.loeschen', $termin), [
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.index'));
        $this->assertNull(Termin::find($termin->id));
    }

    // -----------------------------------------------------------------
    // Validierung
    // -----------------------------------------------------------------

    public function test_datum_ist_pflichtfeld(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.termine.store'), [
            'veranstaltung' => 'Ohne Datum',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertSessionHasErrors('datum');
        $this->assertNull(Termin::where('veranstaltung', 'Ohne Datum')->first());
    }

    public function test_veranstaltung_darf_leer_bleiben(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.termine.store'), [
            'datum' => now()->addDays(3)->toDateString(),
            'veranstaltung' => '',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $termin = Termin::latest('id')->firstOrFail();
        // Bewusst leerer String, NICHT null - siehe TerminUpdater-
        // Klassenkommentar (DB-Spalte ist NOT NULL, erlaubt aber "").
        $this->assertSame('', $termin->veranstaltung);
    }

    public function test_revier_bleibt_freie_texteingabe(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.termine.store'), [
            'datum' => now()->addDays(3)->toDateString(),
            'veranstaltung' => 'Freitext-Revier-Test',
            'revier' => 'Ein völlig frei eingegebenes Revier',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $termin = Termin::where('veranstaltung', 'Freitext-Revier-Test')->firstOrFail();
        $this->assertSame('Ein völlig frei eingegebenes Revier', $termin->revier);
    }

    public function test_hegering_werte_werden_als_revier_vorschlaege_angeboten(): void
    {
        $this->actingAs($this->admin(), 'web');
        Hegering::create(['nummer' => '3', 'name' => 'Kükels', 'sortierung' => 0]);

        $response = $this->get(route('admin.termine.neu'));

        $response->assertOk();
        $response->assertSee('3 – Kükels');
    }

    public function test_bestehende_db_kategorie_bleibt_im_formular_auswaehlbar(): void
    {
        $this->actingAs($this->admin(), 'web');
        // Kategorie, die NICHT in den Standardwerten steckt.
        $termin = $this->termin(['kategorie' => 'Ganz Exotische Kategorie']);

        $response = $this->get(route('admin.termine.bearbeiten', $termin));

        $response->assertOk();
        $response->assertSee('Ganz Exotische Kategorie');
    }

    // -----------------------------------------------------------------
    // Versionskonflikt
    // -----------------------------------------------------------------

    public function test_versionskonflikt_beim_speichern(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin();

        $response = $this->put(route('admin.termine.update', $termin), [
            'datum' => $termin->datum->toDateString(),
            'veranstaltung' => 'Sollte nicht gespeichert werden',
            'expected_version' => ContentVersioning::current('termine') + 1,
        ]);

        $response->assertSessionHasErrors();
        $this->assertNotSame('Sollte nicht gespeichert werden', $termin->fresh()->veranstaltung);
    }

    public function test_versionskonflikt_beim_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin();

        $response = $this->delete(route('admin.termine.loeschen', $termin), [
            'expected_version' => ContentVersioning::current('termine') + 1,
        ]);

        $response->assertSessionHasErrors();
        $this->assertNotNull(Termin::find($termin->id));
    }

    // -----------------------------------------------------------------
    // Überschrift & Einleitungstext
    // -----------------------------------------------------------------

    public function test_ueberschrift_und_einleitung_speichern(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.termine.einstellungen'), [
            'ueberschrift' => 'Neuer Veranstaltungskalender',
            'einleitung' => 'Neue Einleitung.',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response->assertRedirect(route('admin.termine.index'));
        $this->assertDatabaseHas('settings', ['gruppe' => 'termine', 'key' => 'ueberschrift', 'value' => 'Neuer Veranstaltungskalender']);
        $this->assertDatabaseHas('settings', ['gruppe' => 'termine', 'key' => 'einleitung', 'value' => 'Neue Einleitung.']);
    }

    // -----------------------------------------------------------------
    // JSON-Admin-API-Regression (Api\Admin\AdminListController::termine(),
    // seit Phase 7D auf App\Support\TerminUpdater umgestellt)
    // -----------------------------------------------------------------

    public function test_json_admin_api_legt_neuen_termin_an(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => [
                'termine' => [
                    ['datum' => '24.12.2026', 'veranstaltung' => 'Weihnachtsfeier', 'kategorie' => 'Tradition'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $termin = Termin::where('veranstaltung', 'Weihnachtsfeier')->firstOrFail();
        $this->assertSame('2026-12-24', $termin->datum->toDateString());
        $this->assertSame('Tradition', $termin->kategorie);
    }

    public function test_json_admin_api_aktualisiert_bestehenden_termin_ueber_id(): void
    {
        $this->actingAs($this->admin(), 'web');
        $termin = $this->termin(['veranstaltung' => 'Alter Titel']);

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => [
                'termine' => [
                    ['_id' => $termin->id, 'datum' => '01.01.2027', 'veranstaltung' => 'Neuer Titel'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $termin->refresh();
        $this->assertSame('Neuer Titel', $termin->veranstaltung);
        $this->assertSame('2027-01-01', $termin->datum->toDateString());
    }

    public function test_json_admin_api_loescht_termine_die_nicht_mehr_im_payload_sind(): void
    {
        $this->actingAs($this->admin(), 'web');
        $bleibt = $this->termin(['veranstaltung' => 'Bleibt']);
        $verschwindet = $this->termin(['veranstaltung' => 'Verschwindet']);

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => [
                'termine' => [
                    // Alt-Admin-Format "TT.MM.JJJJ" (siehe parseDatum()) -
                    // NICHT ISO, das waere ein unparsbares Format und wuerde
                    // (siehe TerminUpdater-Kommentar) zu "datum: null" fuehren.
                    ['_id' => $bleibt->id, 'datum' => $bleibt->datum->format('d.m.Y'), 'veranstaltung' => 'Bleibt'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertNotNull(Termin::find($bleibt->id));
        $this->assertNull(Termin::find($verschwindet->id));
    }

    public function test_json_admin_api_leere_veranstaltung_bleibt_leerer_string_ohne_fehler(): void
    {
        // Gegenprobe zu test_veranstaltung_darf_leer_bleiben() oben: das
        // bestehende Vollpayload-Verhalten der JSON-API darf sich durch die
        // Umstellung auf TerminUpdater nicht aendern.
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => [
                'termine' => [
                    ['datum' => '15.03.2027', 'veranstaltung' => ''],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $termin = Termin::whereDate('datum', '2027-03-15')->firstOrFail();
        $this->assertSame('', $termin->veranstaltung);
    }

    public function test_json_admin_api_ueberschrift_einleitung_bleiben_speicherbar(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => [
                'termine' => [],
                'einstellungen' => ['ueberschrift' => 'JSON-API Überschrift', 'einleitung' => 'JSON-API Einleitung'],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('settings', ['gruppe' => 'termine', 'key' => 'ueberschrift', 'value' => 'JSON-API Überschrift']);
    }

    public function test_json_admin_api_versionskonflikt_unveraendert(): void
    {
        $this->actingAs($this->admin(), 'web');
        ContentVersioning::bump('termine');

        $response = $this->putJson('/api/admin/content/termine.json', [
            'data' => ['termine' => []],
            'expected_version' => 0,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(409);
    }

    // -----------------------------------------------------------------
    // Öffentliche Ausgabe darf keine Regression bekommen
    // -----------------------------------------------------------------

    public function test_oeffentliche_termine_seite_zeigt_ueber_blade_admin_angelegten_termin(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->post(route('admin.termine.store'), [
            'datum' => now()->addDays(2)->toDateString(),
            'veranstaltung' => 'Über Blade-Admin angelegt',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response = $this->get('/termine');

        $response->assertOk();
        $response->assertSee('Über Blade-Admin angelegt');
    }

    public function test_startseite_naechste_termine_box_unveraendert(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->post(route('admin.termine.store'), [
            'datum' => now()->addDays(1)->toDateString(),
            'veranstaltung' => 'Startseiten-Vorschau-Termin',
            'expected_version' => ContentVersioning::current('termine'),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Startseiten-Vorschau-Termin');
    }
}
