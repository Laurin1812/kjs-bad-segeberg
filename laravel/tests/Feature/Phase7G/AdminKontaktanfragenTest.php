<?php

namespace Tests\Feature\Phase7G;

use App\Mail\KontaktAnfrageMail;
use App\Models\KontaktAnfrage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 7G (Admin-Modul "Kontaktanfragen"): deckt den neuen, server-
 * gerenderten Bereich unter "/admin/kontaktanfragen" ab (Http\Controllers\
 * Admin\KontaktanfragenController) - Liste, Detailansicht, Status-Wechsel.
 * KEIN Löschen (siehe dortigen Klassenkommentar "KEIN LOESCHEN"): der Alt-
 * Admin kennt für Kontaktanfragen bewusst keine Löschfunktion - eine
 * Anfrage bleibt immer erhalten, "erledigt" wird ausschließlich über den
 * Status abgebildet. Siehe Klassenkommentar auch für die Begründung von
 * "kein ContentVersioning".
 *
 * Die alten PHP-Endpunkte (/api/kontakt/admin/liste.php + status.php)
 * existieren außerhalb von Laravel und werden von diesem Modul nicht
 * berührt - hierfür gibt es folgerichtig auch keinen Regressionstest in
 * dieser Suite. Der ÖFFENTLICHE Kontaktformular-Weg (KontaktController,
 * "/kontakt") bleibt vollständig unangetastet - siehe bereits bestehende,
 * umfassende Tests/Feature/Phase6C/KontaktFormularTest.php; hier wird
 * lediglich eine schlanke Regressionsprobe ergänzt (Auftrag: "öffentlicher
 * Kontaktformular-Weg bleibt regressionsfrei" / "neue Anfrage aus
 * öffentlichem Formular landet weiterhin korrekt in DB"), ohne die dortige
 * Abdeckung zu duplizieren.
 */
class AdminKontaktanfragenTest extends TestCase
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

    private function anfrage(array $overrides = []): KontaktAnfrage
    {
        return KontaktAnfrage::create(array_merge([
            'name' => 'Max Mustermann',
            'email' => 'max@example.test',
            'telefon' => '04551 123456',
            'anliegen' => 'Allgemeine Anfrage',
            'bereits_jaeger' => 'Ja',
            'hegering' => '3 – Testhegering',
            'nachricht' => 'Dies ist eine Testnachricht.',
            'status' => 'neu',
            'mail_versendet' => true,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_von_allen_admin_kontaktanfragen_routen_zum_login_umgeleitet(): void
    {
        $anfrage = $this->anfrage();

        $this->get(route('admin.kontaktanfragen.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.kontaktanfragen.anzeigen', $anfrage))->assertRedirect(route('admin.login'));
        $this->put(route('admin.kontaktanfragen.status', $anfrage))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403_auf_allen_admin_kontaktanfragen_routen(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');
        $anfrage = $this->anfrage();

        $this->get(route('admin.kontaktanfragen.index'))->assertForbidden();
        $this->get(route('admin.kontaktanfragen.anzeigen', $anfrage))->assertForbidden();
        $this->put(route('admin.kontaktanfragen.status', $anfrage))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste
    // -----------------------------------------------------------------

    public function test_liste_zeigt_kontaktanfragen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anfrage(['name' => 'Erika Musterfrau', 'email' => 'erika@example.test']);

        $response = $this->get(route('admin.kontaktanfragen.index'));

        $response->assertOk();
        $response->assertSee('Erika Musterfrau');
        $response->assertSee('erika@example.test');
    }

    public function test_liste_sortiert_neueste_anfrage_zuerst(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anfrage(['name' => 'Ältere Anfrage', 'email' => 'alt@example.test', 'erstellt_am' => now()->subDays(2)]);
        $this->anfrage(['name' => 'Neuere Anfrage', 'email' => 'neu@example.test', 'erstellt_am' => now()]);

        $response = $this->get(route('admin.kontaktanfragen.index'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Ältere Anfrage'), strpos($html, 'Neuere Anfrage'));
    }

    public function test_liste_zeigt_leeren_hinweis_ohne_anfragen(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.kontaktanfragen.index'));

        $response->assertOk();
        $response->assertSee('noch keine Kontaktanfragen vorhanden');
    }

    // -----------------------------------------------------------------
    // Detailansicht
    // -----------------------------------------------------------------

    public function test_detailansicht_zeigt_alle_felder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anfrage = $this->anfrage([
            'name' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
            'telefon' => '04551 999999',
            'anliegen' => 'Jägerausbildung (Jagdschein)',
            'bereits_jaeger' => 'Nein',
            'hegering' => '3 – Testhegering',
            'nachricht' => "Zeile eins\nZeile zwei",
        ]);

        $response = $this->get(route('admin.kontaktanfragen.anzeigen', $anfrage));

        $response->assertOk();
        $response->assertSee('Erika Musterfrau');
        $response->assertSee('mailto:erika@example.test', false);
        $response->assertSee('tel:04551 999999', false);
        $response->assertSee('Jägerausbildung (Jagdschein)');
        $response->assertSee('Nein');
        $response->assertSee('3 – Testhegering');
        $response->assertSee('Zeile eins');
        $response->assertSee('Zeile zwei');
    }

    public function test_manipulierte_id_fuehrt_zu_404(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.kontaktanfragen.anzeigen', 999999))->assertNotFound();
        $this->put(route('admin.kontaktanfragen.status', 999999))->assertNotFound();
    }

    public function test_html_in_der_nachricht_wird_escaped_und_nicht_ausgefuehrt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anfrage = $this->anfrage(['nachricht' => '<img src=x onerror=alert(1)> & "Anführungszeichen"']);

        $response = $this->get(route('admin.kontaktanfragen.anzeigen', $anfrage));

        $response->assertOk();
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_html_in_name_und_anliegen_wird_in_der_liste_escaped(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anfrage(['name' => '<script>alert(1)</script>', 'anliegen' => 'Allgemeine Anfrage']);

        $response = $this->get(route('admin.kontaktanfragen.index'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_oeffnen_der_detailansicht_aendert_den_status_nicht_automatisch(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anfrage = $this->anfrage(['status' => 'neu']);

        $this->get(route('admin.kontaktanfragen.anzeigen', $anfrage))->assertOk();

        $anfrage->refresh();
        $this->assertSame('neu', $anfrage->status);
        $this->assertNull($anfrage->bearbeitet_am);
    }

    // -----------------------------------------------------------------
    // Status-Wechsel
    // -----------------------------------------------------------------

    public function test_status_kann_auf_bearbeitet_gesetzt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anfrage = $this->anfrage(['status' => 'neu']);

        $response = $this->put(route('admin.kontaktanfragen.status', $anfrage));

        $response->assertRedirect();
        $anfrage->refresh();
        $this->assertSame('bearbeitet', $anfrage->status);
        $this->assertNotNull($anfrage->bearbeitet_am);
    }

    public function test_status_kann_wieder_auf_neu_zurueckgesetzt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anfrage = $this->anfrage(['status' => 'bearbeitet', 'bearbeitet_am' => now()->subDay()]);

        $response = $this->put(route('admin.kontaktanfragen.status', $anfrage));

        $response->assertRedirect();
        $anfrage->refresh();
        $this->assertSame('neu', $anfrage->status);
    }

    // -----------------------------------------------------------------
    // Regressionsschutz: öffentlicher Kontaktformular-Weg
    // (ausführliche Abdeckung bereits in Tests/Feature/Phase6C/
    // KontaktFormularTest.php - hier nur eine schlanke Bestätigung, dass
    // Phase 7G diesen Weg nicht beeinflusst hat)
    // -----------------------------------------------------------------

    public function test_oeffentliches_kontaktformular_speichert_weiterhin_korrekt_in_der_datenbank(): void
    {
        $response = $this->post('/kontakt', [
            'vorname' => 'Neue',
            'nachname' => 'Anfrage',
            'email' => 'neue-anfrage@example.test',
            'telefon' => '04551 000000',
            'bereits_jaeger' => 'Ja',
            'hegering' => '3 – Testhegering',
            'betreff' => 'Allgemeine Anfrage',
            'nachricht' => 'Regressionsprobe Phase 7G.',
            'datenschutz' => '1',
            '_honey' => '',
        ]);

        $response->assertRedirect(route('kontakt'));
        $this->assertDatabaseHas('kontakt_anfragen', [
            'email' => 'neue-anfrage@example.test',
            'status' => 'neu',
        ]);

        // Die neue Anfrage muss auch im neuen Admin-Postfach erscheinen.
        $this->actingAs($this->admin(), 'web');
        $this->get(route('admin.kontaktanfragen.index'))->assertSee('neue-anfrage@example.test');
    }

    public function test_mailversand_ueber_das_oeffentliche_formular_bleibt_unveraendert(): void
    {
        config(['mail.contact_recipient' => 'empfang@example-test.de']);
        Mail::fake();

        $this->post('/kontakt', [
            'vorname' => 'Neue',
            'nachname' => 'Anfrage',
            'email' => 'mail-regression@example.test',
            'betreff' => 'Allgemeine Anfrage',
            'nachricht' => 'Regressionsprobe Mailversand.',
            'datenschutz' => '1',
            '_honey' => '',
        ]);

        Mail::assertSent(KontaktAnfrageMail::class);

        $anfrage = KontaktAnfrage::where('email', 'mail-regression@example.test')->firstOrFail();
        $this->assertTrue($anfrage->mail_versendet);
    }
}
