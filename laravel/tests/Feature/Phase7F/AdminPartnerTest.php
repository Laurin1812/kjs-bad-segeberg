<?php

namespace Tests\Feature\Phase7F;

use App\Models\Partner;
use App\Models\PartnerVorteil;
use App\Models\User;
use App\Support\ContentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7F (Admin-Modul "Partner"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/partner" ab (Http\Controllers\Admin\
 * PartnerController) - Liste, Anlegen, Bearbeiten (inkl. Preservation),
 * Löschen, Reihenfolge (Auf-/Ab-Buttons statt Drag&Drop). Die BESTEHENDE
 * JSON-Schreib-API unter "/api/admin/content/partner.json" (admin.js,
 * Api\Admin\AdminListController::partner()) bleibt unveraendert nutzbar -
 * siehe test_json_admin_api_*() unten (jetzt auf App\Support\
 * PartnerUpdater umgestellt, siehe dortiger Kommentar).
 */
class AdminPartnerTest extends TestCase
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

    private function partner(array $overrides = []): Partner
    {
        return Partner::create(array_merge([
            'external_id' => 'pn-'.random_int(1000000000000, 9999999999999),
            'name' => 'Landesjagdverband',
            'logo' => '/images/ljv.png',
            'kurzbeschreibung' => 'Verband',
            'beschreibung' => 'Ausführliche Beschreibung.',
            'ansprechpartner' => 'Max Mustermann',
            'telefon' => '04551 123456',
            'email' => 'info@ljv-sh.de',
            'website' => 'https://www.ljv-sh.de/',
            'rahmenvertrag' => false,
            'weitere_infos' => null,
            'aktiv' => true,
            'sortierung' => 0,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_von_allen_admin_partner_routen_zum_login_umgeleitet(): void
    {
        $partner = $this->partner();

        $this->get(route('admin.partner.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.partner.neu'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.partner.store'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.partner.bearbeiten', $partner))->assertRedirect(route('admin.login'));
        $this->put(route('admin.partner.aktualisieren', $partner))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.partner.loeschen', $partner))->assertRedirect(route('admin.login'));
        $this->put(route('admin.partner.hoch', $partner))->assertRedirect(route('admin.login'));
        $this->put(route('admin.partner.runter', $partner))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403_auf_allen_admin_partner_routen(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');
        $partner = $this->partner();

        $this->get(route('admin.partner.index'))->assertForbidden();
        $this->get(route('admin.partner.neu'))->assertForbidden();
        $this->post(route('admin.partner.store'))->assertForbidden();
        $this->get(route('admin.partner.bearbeiten', $partner))->assertForbidden();
        $this->put(route('admin.partner.aktualisieren', $partner))->assertForbidden();
        $this->delete(route('admin.partner.loeschen', $partner))->assertForbidden();
        $this->put(route('admin.partner.hoch', $partner))->assertForbidden();
        $this->put(route('admin.partner.runter', $partner))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste
    // -----------------------------------------------------------------

    public function test_liste_zeigt_partner_in_richtiger_sortierung(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->partner(['name' => 'Zweiter Partner', 'sortierung' => 1]);
        $this->partner(['name' => 'Erster Partner', 'sortierung' => 0]);

        $response = $this->get(route('admin.partner.index'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Zweiter Partner'), strpos($html, 'Erster Partner'));
    }

    public function test_liste_zeigt_auch_inaktive_partner(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->partner(['name' => 'Deaktivierter Partner', 'aktiv' => false]);

        $response = $this->get(route('admin.partner.index'));

        $response->assertOk();
        $response->assertSee('Deaktivierter Partner');
        $response->assertSee('Inaktiv');
    }

    // -----------------------------------------------------------------
    // Anlegen
    // -----------------------------------------------------------------

    public function test_partner_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $versionVorher = ContentVersioning::current('partner');

        $response = $this->post(route('admin.partner.store'), [
            'name' => 'Neuer Partner',
            'website' => 'https://neuer-partner.de/',
            'aktiv' => '1',
            'expected_version' => $versionVorher,
        ]);

        $partner = Partner::where('name', 'Neuer Partner')->firstOrFail();
        $response->assertRedirect(route('admin.partner.bearbeiten', $partner));
        $this->assertNotNull($partner->external_id);
        $this->assertTrue($partner->aktiv);
        $this->assertSame(0, $partner->sortierung);
        $this->assertSame($versionVorher + 1, ContentVersioning::current('partner'));
    }

    public function test_neu_angelegter_partner_wird_ans_ende_der_liste_angehaengt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->partner(['name' => 'Erster', 'sortierung' => 0]);
        $this->partner(['name' => 'Zweiter', 'sortierung' => 1]);

        $this->post(route('admin.partner.store'), [
            'name' => 'Dritter',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame(2, Partner::where('name', 'Dritter')->firstOrFail()->sortierung);
    }

    public function test_vorteile_werden_zeilenweise_gespeichert(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.partner.store'), [
            'name' => 'Partner mit Vorteilen',
            'vorteile' => "10% Rabatt\nKostenlose Beratung\n\n",
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $partner = Partner::where('name', 'Partner mit Vorteilen')->firstOrFail();
        $this->assertSame(['10% Rabatt', 'Kostenlose Beratung'], $partner->vorteile()->orderBy('sortierung')->pluck('text')->all());
    }

    public function test_partner_anlegen_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.partner.store'), [
            'name' => 'Sollte nicht angelegt werden',
            'expected_version' => ContentVersioning::current('partner') + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('partner', ['name' => 'Sollte nicht angelegt werden']);
    }

    // -----------------------------------------------------------------
    // Bearbeiten
    // -----------------------------------------------------------------

    public function test_partner_bearbeiten_zeigt_aktuelle_werte_und_vorteile(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['name' => 'Zu bearbeitender Partner']);
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil A', 'sortierung' => 0]);
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil B', 'sortierung' => 1]);

        $response = $this->get(route('admin.partner.bearbeiten', $partner));

        $response->assertOk();
        $response->assertSee('Zu bearbeitender Partner');
        $response->assertSee('Vorteil A');
        $response->assertSee('Vorteil B');
    }

    public function test_partner_aktualisieren(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['name' => 'Alter Name']);

        $response = $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => 'Neuer Name',
            'website' => $partner->website,
            'aktiv' => '1',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response->assertRedirect(route('admin.partner.bearbeiten', $partner));
        $this->assertSame('Neuer Name', $partner->fresh()->name);
    }

    public function test_rahmenvertrag_checkbox_wird_als_boolean_gespeichert(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['rahmenvertrag' => false]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => $partner->name,
            'rahmenvertrag' => '1',
            'aktiv' => '1',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertTrue($partner->fresh()->rahmenvertrag);
    }

    public function test_preservation_nicht_gesendeter_felder_beim_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner([
            'logo' => '/images/bleibt-erhalten.png',
            'telefon' => '04551 999999',
        ]);

        $response = $this->put(route('admin.partner.aktualisieren', $partner), [
            // "logo"/"telefon" absichtlich NICHT mitgeschickt.
            'name' => $partner->name,
            'kurzbeschreibung' => 'Nur die Kurzbeschreibung geändert',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response->assertRedirect(route('admin.partner.bearbeiten', $partner));
        $partner->refresh();
        $this->assertSame('Nur die Kurzbeschreibung geändert', $partner->kurzbeschreibung);
        $this->assertSame('/images/bleibt-erhalten.png', $partner->logo);
        $this->assertSame('04551 999999', $partner->telefon);
    }

    /**
     * Preservation-Korrektur: "aktiv" darf bei einem Teil-Update, das das
     * Feld gar nicht mitsendet, nicht stillschweigend auf false fallen
     * (PartnerController::validateData() setzt den Schluessel jetzt nur,
     * wenn er im Request tatsaechlich vorhanden ist).
     */
    public function test_preservation_aktiv_bleibt_bei_teil_update_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['aktiv' => true]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            // "aktiv" absichtlich NICHT mitgeschickt.
            'name' => $partner->name,
            'kurzbeschreibung' => 'Nur die Kurzbeschreibung geändert',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertTrue($partner->fresh()->aktiv);
    }

    /**
     * Preservation-Korrektur: "rahmenvertrag" darf bei einem Teil-Update
     * ebenfalls nicht stillschweigend auf false fallen.
     */
    public function test_preservation_rahmenvertrag_bleibt_bei_teil_update_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['rahmenvertrag' => true]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            // "rahmenvertrag" absichtlich NICHT mitgeschickt.
            'name' => $partner->name,
            'kurzbeschreibung' => 'Nur die Kurzbeschreibung geändert',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertTrue($partner->fresh()->rahmenvertrag);
    }

    /**
     * Preservation-Korrektur: bestehende partner_vorteile-Zeilen duerfen bei
     * einem Teil-Update, das "vorteile" gar nicht mitsendet, nicht geloescht
     * werden (PartnerUpdater::applyFields() ruft replaceVorteile() jetzt nur
     * noch auf, wenn der Schluessel im uebergebenen Daten-Array tatsaechlich
     * vorhanden ist).
     */
    public function test_preservation_vorteile_bleiben_bei_teil_update_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner();
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil A', 'sortierung' => 0]);
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil B', 'sortierung' => 1]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            // "vorteile" absichtlich NICHT mitgeschickt.
            'name' => $partner->name,
            'kurzbeschreibung' => 'Nur die Kurzbeschreibung geändert',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame(
            ['Vorteil A', 'Vorteil B'],
            $partner->vorteile()->orderBy('sortierung')->pluck('text')->all()
        );
    }

    /**
     * Gegenprobe zu den drei Preservation-Tests oben: ein echtes,
     * vollstaendiges Formular MUSS eine bewusst unangehakte Checkbox
     * weiterhin als "false" speichern koennen. Die Blade-Maske sendet dafuer
     * einen verdeckten "0"-Fallback vor der Checkbox (siehe admin/partner/
     * bearbeiten.blade.php) - dieser Test simuliert genau dessen Ergebnis
     * (Formular ohne angehakte Checkbox sendet "aktiv" => "0").
     */
    public function test_vollstaendiges_formular_ohne_angehakte_aktiv_checkbox_setzt_aktiv_auf_false(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['aktiv' => true]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => $partner->name,
            'aktiv' => '0',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertFalse($partner->fresh()->aktiv);
    }

    public function test_vollstaendiges_formular_ohne_angehakte_rahmenvertrag_checkbox_setzt_rahmenvertrag_auf_false(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['rahmenvertrag' => true]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => $partner->name,
            'rahmenvertrag' => '0',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertFalse($partner->fresh()->rahmenvertrag);
    }

    /**
     * Gegenprobe zur Vorteile-Preservation: ein bewusst LEER gesendetes
     * "vorteile"-Feld (der Redakteur hat den Text im Formular geloescht)
     * muss die bestehenden Vorteile weiterhin leeren - nur ein komplett
     * FEHLENDES Feld schuetzt sie.
     */
    public function test_bewusst_leer_gesendetes_vorteile_feld_leert_bestehende_vorteile(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner();
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil A', 'sortierung' => 0]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => $partner->name,
            'vorteile' => '',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame([], $partner->vorteile()->pluck('text')->all());
    }

    public function test_preservation_erhaelt_sortierung_beim_normalen_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['sortierung' => 3]);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => 'Anderer Name',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame(3, $partner->fresh()->sortierung);
    }

    public function test_partner_versionskonflikt_beim_speichern(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['name' => 'Unveraendert']);

        $response = $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => 'Sollte nicht gespeichert werden',
            'expected_version' => ContentVersioning::current('partner') + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertSame('Unveraendert', $partner->fresh()->name);
    }

    // -----------------------------------------------------------------
    // Löschen
    // -----------------------------------------------------------------

    public function test_partner_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner();

        $response = $this->delete(route('admin.partner.loeschen', $partner), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response->assertRedirect(route('admin.partner.index'));
        $this->assertDatabaseMissing('partner', ['id' => $partner->id]);
    }

    public function test_partner_loeschen_nimmt_vorteile_per_cascade_mit(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner();
        $vorteil = PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Vorteil', 'sortierung' => 0]);

        $this->delete(route('admin.partner.loeschen', $partner), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertDatabaseMissing('partner_vorteile', ['id' => $vorteil->id]);
    }

    public function test_partner_loeschen_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner();

        $response = $this->delete(route('admin.partner.loeschen', $partner), [
            'expected_version' => ContentVersioning::current('partner') + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('partner', ['id' => $partner->id]);
    }

    public function test_manipulierte_id_beim_bearbeiten_fuehrt_zu_404(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.partner.bearbeiten', ['partner' => 999999]))->assertNotFound();
        $this->put(route('admin.partner.aktualisieren', ['partner' => 999999]), [
            'expected_version' => ContentVersioning::current('partner'),
        ])->assertNotFound();
        $this->delete(route('admin.partner.loeschen', ['partner' => 999999]), [
            'expected_version' => ContentVersioning::current('partner'),
        ])->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Reihenfolge
    // -----------------------------------------------------------------

    public function test_reihenfolge_hoch_vertauscht_mit_vorgaenger(): void
    {
        $this->actingAs($this->admin(), 'web');
        $erster = $this->partner(['name' => 'Erster', 'sortierung' => 0]);
        $zweiter = $this->partner(['name' => 'Zweiter', 'sortierung' => 1]);

        $response = $this->put(route('admin.partner.hoch', $zweiter), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response->assertRedirect(route('admin.partner.index'));
        $this->assertSame(0, $zweiter->fresh()->sortierung);
        $this->assertSame(1, $erster->fresh()->sortierung);
    }

    public function test_reihenfolge_runter_vertauscht_mit_nachfolger(): void
    {
        $this->actingAs($this->admin(), 'web');
        $erster = $this->partner(['name' => 'Erster', 'sortierung' => 0]);
        $zweiter = $this->partner(['name' => 'Zweiter', 'sortierung' => 1]);

        $this->put(route('admin.partner.runter', $erster), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame(1, $erster->fresh()->sortierung);
        $this->assertSame(0, $zweiter->fresh()->sortierung);
    }

    public function test_reihenfolge_hoch_bei_erstem_eintrag_aendert_nichts(): void
    {
        $this->actingAs($this->admin(), 'web');
        $erster = $this->partner(['name' => 'Erster', 'sortierung' => 0]);

        $response = $this->put(route('admin.partner.hoch', $erster), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response->assertRedirect(route('admin.partner.index'));
        $this->assertSame(0, $erster->fresh()->sortierung);
    }

    public function test_reihenfolge_runter_bei_letztem_eintrag_aendert_nichts(): void
    {
        $this->actingAs($this->admin(), 'web');
        $letzter = $this->partner(['name' => 'Letzter', 'sortierung' => 0]);

        $this->put(route('admin.partner.runter', $letzter), [
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $this->assertSame(0, $letzter->fresh()->sortierung);
    }

    // -----------------------------------------------------------------
    // JSON-Admin-API-Regression (Api\Admin\AdminListController::partner(),
    // seit Phase 7F auf App\Support\PartnerUpdater umgestellt)
    // -----------------------------------------------------------------

    public function test_json_admin_api_legt_partner_an(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/partner.json', [
            'data' => [
                'partner' => [
                    ['id' => 'pn-1234567890123', 'name' => 'JSON-Partner', 'website' => 'https://json-partner.de/', 'aktiv' => true],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $partner = Partner::where('name', 'JSON-Partner')->firstOrFail();
        $this->assertSame('pn-1234567890123', $partner->external_id);
    }

    public function test_json_admin_api_aktualisiert_partner_ueber_id(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['name' => 'Alter Name']);

        $response = $this->putJson('/api/admin/content/partner.json', [
            'data' => [
                'partner' => [
                    ['_id' => $partner->id, 'id' => $partner->external_id, 'name' => 'Neuer Name', 'aktiv' => true],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame('Neuer Name', $partner->fresh()->name);
    }

    public function test_json_admin_api_speichert_rahmenvertrag_und_vorteile(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/partner.json', [
            'data' => [
                'partner' => [
                    [
                        'id' => 'pn-9999999999999',
                        'name' => 'Partner mit Rahmenvertrag',
                        'rahmenvertrag' => true,
                        'vorteile' => "Vorteil eins\nVorteil zwei",
                        'aktiv' => true,
                    ],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $partner = Partner::where('name', 'Partner mit Rahmenvertrag')->firstOrFail();
        $this->assertTrue($partner->rahmenvertrag);
        $this->assertSame(['Vorteil eins', 'Vorteil zwei'], $partner->vorteile()->orderBy('sortierung')->pluck('text')->all());
    }

    public function test_json_admin_api_loescht_nicht_mehr_im_payload_enthaltene_partner(): void
    {
        $this->actingAs($this->admin(), 'web');
        $bleibt = $this->partner(['name' => 'Bleibt']);
        $verschwindet = $this->partner(['name' => 'Verschwindet']);

        $response = $this->putJson('/api/admin/content/partner.json', [
            'data' => [
                'partner' => [
                    ['_id' => $bleibt->id, 'id' => $bleibt->external_id, 'name' => 'Bleibt', 'aktiv' => true],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('partner', ['id' => $bleibt->id]);
        $this->assertDatabaseMissing('partner', ['id' => $verschwindet->id]);
    }

    public function test_json_admin_api_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');
        ContentVersioning::bump('partner');

        $response = $this->putJson('/api/admin/content/partner.json', [
            'data' => ['partner' => []],
            'expected_version' => 0,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(409);
    }

    // -----------------------------------------------------------------
    // Öffentliche Ausgabe darf keine Regression bekommen
    // -----------------------------------------------------------------

    public function test_ueber_blade_admin_angelegter_partner_erscheint_auf_oeffentlicher_seite(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.partner.store'), [
            'name' => 'Über Blade-Admin angelegt',
            'aktiv' => '1',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response = $this->get(route('partner.index'));
        $response->assertOk();
        $response->assertSee('Über Blade-Admin angelegt');
    }

    public function test_ueber_blade_admin_deaktivierter_partner_verschwindet_von_oeffentlicher_seite(): void
    {
        $this->actingAs($this->admin(), 'web');
        $partner = $this->partner(['name' => 'Wird deaktiviert']);

        $this->put(route('admin.partner.aktualisieren', $partner), [
            'name' => $partner->name,
            // "aktiv" => "0": das entspricht dem verdeckten Fallback-Feld,
            // das ein echtes Formular bei einer unangehakten Checkbox sendet
            // (siehe PartnerController::validateData()-Kommentar) - ein
            // komplett fehlendes "aktiv" wuerde seit der Preservation-
            // Korrektur den bestehenden Wert stattdessen erhalten.
            'aktiv' => '0',
            'expected_version' => ContentVersioning::current('partner'),
        ]);

        $response = $this->get(route('partner.index'));
        $response->assertOk();
        $response->assertDontSee('Wird deaktiviert');
    }
}
