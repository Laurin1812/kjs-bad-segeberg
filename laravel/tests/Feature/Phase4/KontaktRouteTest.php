<?php

namespace Tests\Feature\Phase4;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 Abschluss-Nacharbeit ("Kontakt-Route"): "/kontakt" war bislang
 * eine echte 404, obwohl die Hauptnavigation (und mehrere bereits
 * migrierte Seiten) bereits dorthin verlinken. Deckt ab: Route existiert
 * und rendert 200; Kontaktdaten kommen aus der settings-Tabelle (Gruppe
 * "einstellungen", dieselbe Quelle wie Topbar/Kontaktbox/Impressum/
 * Datenschutz), kein JSON zur Laufzeit; fehlende Felder werden ohne
 * erfundenen Platzhalter weggelassen; der Navigationspunkt "Kontakt"
 * verweist tatsaechlich auf diese Route.
 */
class KontaktRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_kontakt_seite_rendert_kontaktdaten_aus_der_datenbank(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'kontakt_ueberschrift', 'value' => 'Sprechen Sie uns an']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'kontakt_text', 'value' => 'Wir freuen uns auf Ihre Nachricht.']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'adresse', 'value' => "Am Schießstand 1\n24640 Hasenmoor"]);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => '01556-0020001']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'info@kjs-segeberg.test']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'postadresse', 'value' => "Frank Hülser\nSchniedertwiete 1\n24629 Kisdorf"]);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'postadresse_telefon', 'value' => '0152-21591669']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'postadresse_email', 'value' => 'frank.huelser@kjs-segeberg.test']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'oeffnungszeiten', 'value' => '[{"tage":"Montag bis Freitag","zeiten":"10:00 – 17:00 Uhr"}]']);

        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertSee('Sprechen Sie uns an');
        $response->assertSee('Wir freuen uns auf Ihre Nachricht.');
        $response->assertSee('Am Schießstand 1', false);
        $response->assertSee('info@kjs-segeberg.test');
        $response->assertSee('Schniedertwiete 1', false);
        $response->assertSee('frank.huelser@kjs-segeberg.test');
        $response->assertSee('Montag bis Freitag', false);
        $response->assertDontSee('/api/content/einstellungen.json');
        $response->assertDontSee('kontakt/index.html');
    }

    public function test_kontakt_seite_erfindet_keine_platzhalterdaten_bei_fehlenden_feldern(): void
    {
        // Bewusst keine Settings angelegt - die Seite darf trotzdem 200
        // liefern und keine erfundenen Kontaktdaten anzeigen.
        $response = $this->get('/kontakt');

        $response->assertOk();
        $response->assertDontSee('info@kjs-bad-segeberg.de');
        $response->assertDontSee('mailto:');
        $response->assertDontSee('tel:');
    }

    public function test_hauptnavigation_verweist_auf_die_kontakt_route(): void
    {
        Setting::create([
            'gruppe' => 'navigation',
            'key' => 'data',
            'value' => json_encode([
                'hauptmenu' => ['kontakt'],
                'hauptmenu_meta' => [
                    'kontakt' => ['label' => 'Kontakt', 'href' => '/kontakt/index.html'],
                ],
            ]),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="/kontakt"', false);

        $kontaktResponse = $this->get('/kontakt');
        $kontaktResponse->assertOk();
    }
}
