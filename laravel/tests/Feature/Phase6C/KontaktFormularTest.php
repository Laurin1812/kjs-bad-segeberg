<?php

namespace Tests\Feature\Phase6C;

use App\Mail\KontaktAnfrageMail;
use App\Models\Hegering;
use App\Models\KontaktAnfrage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 6C (Kontaktformular vollstaendig auf Laravel): deckt
 * KontaktController::show()/store() sowie KontaktRequest ab - ersetzt
 * api/contact.php fuer NEUE Einreichungen. Kernprinzip (siehe Auftrag
 * Punkt 3): eine Kontaktanfrage wird IMMER ZUERST in der Datenbank
 * gespeichert, ein Mailversand-Fehler darf sie niemals lautlos verlieren.
 */
class KontaktFormularTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function gueltigeDaten(array $overrides = []): array
    {
        return array_merge([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.test',
            'telefon' => '04551 123456',
            'bereits_jaeger' => 'Ja',
            'hegering' => '3 – Testhegering',
            'betreff' => 'Allgemeine Anfrage',
            'nachricht' => 'Dies ist eine Testnachricht.',
            'datenschutz' => '1',
            '_honey' => '',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Oeffentliche Seite / Formularanzeige
    // ---------------------------------------------------------------

    public function test_get_kontakt_ist_erreichbar_und_zeigt_das_formular(): void
    {
        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('Nachricht senden');
        $response->assertSee('name="vorname"', false);
        $response->assertSee('name="nachname"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="nachricht"', false);
        $response->assertSee('name="datenschutz"', false);
        // Honeypot ist vorhanden, aber fuer Menschen unsichtbar.
        $response->assertSee('name="_honey"', false);
        // Alle 11 alten Betreff-Optionen 1:1 uebernommen.
        $response->assertSee('Jägerausbildung (Jagdschein)');
        $response->assertSee('Pressenanfrage');
    }

    public function test_bestehende_phase4_kontaktdaten_werden_weiterhin_gerendert(): void
    {
        \App\Models\Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'info@kjs-bad-segeberg.de']);

        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('info@kjs-bad-segeberg.de');
    }

    public function test_hegering_auswahl_wird_aus_der_datenbank_befuellt(): void
    {
        Hegering::create(['nummer' => '3', 'name' => 'Testhegering', 'sortierung' => 1]);

        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('3 – Testhegering');
    }

    // ---------------------------------------------------------------
    // Google-Maps-Embed (Nachtrag Phase-6C-Review: war in kontakt/
    // index.html vorhanden und wurde beim ersten Durchgang uebersehen -
    // siehe kontakt.blade.php-Kommentar). Zwei-Klick-Einbindung wie bei
    // termine.blade.php/service.blade.php - kein automatischer Request an
    // Google bei jedem Seitenaufruf, erst nach Klick auf den Button.
    // ---------------------------------------------------------------

    public function test_maps_embed_erscheint_als_zwei_klick_platzhalter_wenn_adresse_gepflegt_ist(): void
    {
        \App\Models\Setting::create(['gruppe' => 'einstellungen', 'key' => 'adresse', 'value' => "Am Schießstand 1\n24640 Hasenmoor"]);

        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('kjs-embed-placeholder', false);
        $response->assertSee('data-embed-src="https://maps.google.com/maps?q=Am+Schie%C3%9Fstand+1%2C+24640+Hasenmoor&amp;output=embed&amp;z=15&amp;hl=de"', false);
        $response->assertSee('🗺️ Karte anzeigen');
        // Zwei-Klick-Schutz: kein rohes <iframe> im initial ausgelieferten
        // HTML - das baut erst window.kjsActivateEmbed() nach einem Klick.
        $response->assertDontSee('<iframe', false);
    }

    public function test_maps_embed_erscheint_nicht_ohne_gepflegte_adresse(): void
    {
        // Keine Settings angelegt - wie im bestehenden Phase-4-Test: keine
        // erfundenen Kontaktdaten, auch keine Karte fuer eine nicht
        // gepflegte Adresse.
        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertDontSee('kjs-embed-placeholder', false);
        $response->assertDontSee('maps.google.com');
    }

    // ---------------------------------------------------------------
    // Erfolgreiche Einreichung
    // ---------------------------------------------------------------

    public function test_gueltige_einreichung_wird_gespeichert_mit_initialstatus_neu(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten());

        $response->assertRedirect(route('kontakt'));
        $response->assertSessionHas('kontakt_success', true);

        $this->assertDatabaseHas('kontakt_anfragen', [
            'name' => 'Max Mustermann',
            'email' => 'max@example.test',
            'telefon' => '04551 123456',
            'anliegen' => 'Allgemeine Anfrage',
            'bereits_jaeger' => 'Ja',
            'hegering' => '3 – Testhegering',
            'nachricht' => 'Dies ist eine Testnachricht.',
            'status' => 'neu',
        ]);
    }

    public function test_erfolgsmeldung_wird_nach_redirect_auf_kontaktseite_angezeigt(): void
    {
        $this->post('/kontakt', $this->gueltigeDaten());

        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('Vielen Dank für Ihre Nachricht!');
    }

    public function test_telefon_hegering_bereits_jaeger_sind_serverseitig_nicht_pflicht(): void
    {
        // Client markiert "bereits_jaeger" zwar als "required", das
        // PHP-Original (api/contact.php) erzwingt es serverseitig aber
        // nicht - siehe KontaktRequest-Klassenkommentar.
        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'telefon' => '',
            'hegering' => '',
            'bereits_jaeger' => '',
        ]));

        $response->assertRedirect(route('kontakt'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('kontakt_anfragen', ['email' => 'max@example.test']);
    }

    // ---------------------------------------------------------------
    // Validierung
    // ---------------------------------------------------------------

    public function test_pflichtfelder_werden_validiert(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'vorname' => '',
            'nachname' => '',
            'email' => '',
            'betreff' => '',
            'nachricht' => '',
            'datenschutz' => '',
        ]));

        $response->assertSessionHasErrors(['vorname', 'nachname', 'email', 'betreff', 'nachricht', 'datenschutz']);
        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    public function test_ungueltige_email_wird_abgelehnt(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten(['email' => 'keine-email-adresse']));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    public function test_ungueltiges_betreff_wird_abgelehnt(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten(['betreff' => 'Erfundenes Anliegen']));

        $response->assertSessionHasErrors('betreff');
        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    public function test_maximale_feldlaengen_werden_durchgesetzt(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'vorname' => str_repeat('a', 191),
        ]));
        $response->assertSessionHasErrors('vorname');

        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'telefon' => str_repeat('1', 61),
        ]));
        $response->assertSessionHasErrors('telefon');

        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'nachricht' => str_repeat('a', 5001),
        ]));
        $response->assertSessionHasErrors('nachricht');

        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    public function test_formulardaten_bleiben_bei_validierungsfehlern_erhalten(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'vorname' => 'Erika',
            'nachricht' => '',
        ]));

        $response->assertSessionHasErrors('nachricht');
        $response->assertSessionHasInput('vorname', 'Erika');

        $folgeAnfrage = $this->get('/kontakt');
        $folgeAnfrage->assertSee('value="Erika"', false);
    }

    public function test_freitext_mit_html_wird_beim_wiederanzeigen_sicher_escaped(): void
    {
        $payload = '<script>alert(1)</script> & "Anführungszeichen"';

        $response = $this->post('/kontakt', $this->gueltigeDaten([
            'vorname' => '',
            'nachricht' => $payload,
        ]));

        $response->assertSessionHasErrors('vorname');

        $folgeAnfrage = $this->get('/kontakt');
        // Blades {{ old('nachricht') }} escaped HTML-Sonderzeichen - das
        // rohe <script>-Tag darf nicht unescaped im Seiten-HTML landen.
        $folgeAnfrage->assertDontSee('<script>alert(1)</script>', false);
        $folgeAnfrage->assertSee('&lt;script&gt;', false);
    }

    // ---------------------------------------------------------------
    // Honeypot / Rate-Limit / CSRF
    // ---------------------------------------------------------------

    public function test_honeypot_verwirft_die_anfrage_still_ohne_speicherung(): void
    {
        $response = $this->post('/kontakt', $this->gueltigeDaten(['_honey' => 'ich-bin-ein-bot']));

        // Wie im PHP-Original: nach aussen sieht es wie ein Erfolg aus,
        // damit ein Bot keinen Unterschied erkennen kann - aber es wird
        // nichts gespeichert.
        $response->assertRedirect(route('kontakt'));
        $response->assertSessionHas('kontakt_success', true);
        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    public function test_rate_limit_greift_nach_fuenf_einreichungen(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/kontakt', $this->gueltigeDaten(['email' => "test{$i}@example.test"]));
            $response->assertStatus(302);
        }

        $sechste = $this->post('/kontakt', $this->gueltigeDaten(['email' => 'test5@example.test']));
        $sechste->assertStatus(429);

        $this->assertDatabaseCount('kontakt_anfragen', 5);
    }

    public function test_formular_ist_csrf_geschuetzt(): void
    {
        // Laravels ValidateCsrfToken-Middleware ueberspringt die Pruefung
        // automatisch waehrend PHPUnit-Laeufen (runningUnitTests()) - fuer
        // diesen einen Test wird das bewusst ausgehebelt, um die echte
        // Absicherung zu verifizieren, statt sie nur anzunehmen.
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->post('/kontakt', $this->gueltigeDaten());
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertDatabaseCount('kontakt_anfragen', 0);
    }

    // ---------------------------------------------------------------
    // Mail
    // ---------------------------------------------------------------

    public function test_mail_wird_bei_konfiguriertem_empfaenger_ausgeloest(): void
    {
        config(['mail.contact_recipient' => 'empfang@example-test.de']);
        Mail::fake();

        $this->post('/kontakt', $this->gueltigeDaten());

        $anfrage = KontaktAnfrage::firstOrFail();

        Mail::assertSent(KontaktAnfrageMail::class, function (KontaktAnfrageMail $mail) use ($anfrage) {
            return $mail->hasTo('empfang@example-test.de')
                && $mail->anfrage->is($anfrage)
                && $mail->envelope()->subject === 'Neue Kontaktanfrage – Allgemeine Anfrage';
        });

        $anfrage->refresh();
        $this->assertTrue($anfrage->mail_versendet);
        $this->assertNull($anfrage->mail_fehler);
    }

    public function test_reply_to_ist_die_adresse_des_besuchers(): void
    {
        config(['mail.contact_recipient' => 'empfang@example-test.de']);
        Mail::fake();

        $this->post('/kontakt', $this->gueltigeDaten(['email' => 'besucher@example.test', 'vorname' => 'Erika', 'nachname' => 'Musterfrau']));

        Mail::assertSent(KontaktAnfrageMail::class, function (KontaktAnfrageMail $mail) {
            $replyTo = $mail->envelope()->replyTo[0];

            return $replyTo->address === 'besucher@example.test' && $replyTo->name === 'Erika Musterfrau';
        });
    }

    public function test_ohne_konfigurierten_empfaenger_wird_die_anfrage_trotzdem_gespeichert(): void
    {
        config(['mail.contact_recipient' => null]);
        Mail::fake();

        $response = $this->post('/kontakt', $this->gueltigeDaten());

        $response->assertRedirect(route('kontakt'));
        Mail::assertNothingSent();

        $anfrage = KontaktAnfrage::firstOrFail();
        $this->assertFalse($anfrage->mail_versendet);
        $this->assertSame('server_not_configured', $anfrage->mail_fehler);
    }

    public function test_mailversandfehler_verliert_die_anfrage_nicht(): void
    {
        // Bewusst KEIN Mail::fake() hier - ein echter, aber garantiert
        // fehlschlagender SMTP-Zielport (127.0.0.1:1, "connection
        // refused") erzeugt eine echte Transport-Exception beim Versand,
        // um zu verifizieren, dass genau diese Situation (siehe Auftrag
        // Punkt 3) die bereits gespeicherte Anfrage NICHT verschwinden
        // laesst und die Anfrage weiterhin per PRG erfolgreich beantwortet
        // wird (keine 500-Fehlerseite).
        config(['mail.contact_recipient' => 'empfang@example-test.de']);
        config(['mail.default' => 'smtp']);
        config(['mail.mailers.smtp.host' => '127.0.0.1']);
        config(['mail.mailers.smtp.port' => 1]);
        config(['mail.mailers.smtp.username' => null]);
        config(['mail.mailers.smtp.password' => null]);

        $response = $this->post('/kontakt', $this->gueltigeDaten());

        $response->assertRedirect(route('kontakt'));
        $response->assertSessionHas('kontakt_success', true);

        $anfrage = KontaktAnfrage::firstOrFail();
        $this->assertFalse($anfrage->mail_versendet);
        $this->assertSame('send_failed', $anfrage->mail_fehler);
    }

    public function test_mailtext_gibt_nutzereingaben_unveraendert_ohne_html_kodierung_aus(): void
    {
        config(['mail.contact_recipient' => 'empfang@example-test.de']);

        $anfrage = KontaktAnfrage::create([
            'name' => 'Max & Erika Mustermann',
            'email' => 'max@example.test',
            'telefon' => '',
            'anliegen' => 'Allgemeine Anfrage',
            'bereits_jaeger' => '',
            'hegering' => '',
            'nachricht' => 'Betrifft "Wildschäden" & <Anfrage>',
            'status' => 'neu',
        ]);

        $rendered = (new KontaktAnfrageMail($anfrage))->render();

        // Reines Text-Mailable: "&"/Anfuehrungszeichen duerfen NICHT als
        // HTML-Entities (z.B. "&amp;") kodiert sein - siehe Kommentar in
        // resources/views/emails/kontakt-anfrage.blade.php.
        $this->assertStringContainsString('Max & Erika Mustermann', $rendered);
        $this->assertStringContainsString('Betrifft "Wildschäden" & <Anfrage>', $rendered);
        $this->assertStringNotContainsString('&amp;', $rendered);
    }

    // ---------------------------------------------------------------
    // Legacy-Redirects
    // ---------------------------------------------------------------

    public function test_alte_kontakt_index_html_leitet_weiterhin_permanent_auf_kontakt_weiter(): void
    {
        $response = $this->get('/kontakt/index.html');

        $response->assertStatus(301);
        $response->assertRedirect('/kontakt');
    }

    // ---------------------------------------------------------------
    // Regressionsschutz: bisherige Phase-4-Tests bleiben unberuehrt
    // ---------------------------------------------------------------

    public function test_phase4_platzhalter_test_bleibt_gueltig_kein_erfundener_mailto_link_ohne_settings(): void
    {
        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertDontSee('info@kjs-bad-segeberg.de');
        $response->assertDontSee('mailto:');
        // "tel:" darf ausserhalb des neuen Formulars (das selbst kein
        // "tel:" enthaelt) weiterhin nicht ohne Settings auftauchen.
        $response->assertDontSee('tel:');
    }
}
