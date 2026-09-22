<?php

namespace Tests\Feature\Phase4;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 Korrektur (allgemeine KJS-Kontaktadresse in
 * Fliesstexten).
 *
 * Die zuletzt noch hart codierten Vorkommen der allgemeinen KJS-Mailadresse
 * ("info@kjs-bad-segeberg.de") auf faq/downloads/jaeger.vorstand kommen
 * jetzt aus derselben zentralen Quelle wie Topbar/Kontaktbox: settings,
 * Gruppe "einstellungen", Key "email" (siehe
 * App\View\Composers\AllgemeineKontaktEmailComposer).
 */
class AllgemeineKontaktEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_downloads_und_vorstand_zeigen_die_zentrale_email_aus_der_datenbank(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'zentrale-test@kjs-segeberg.example']);

        foreach (['/faq', '/downloads', '/jaeger/vorstand'] as $uri) {
            $response = $this->get($uri);

            $response->assertOk();
            $response->assertSee('zentrale-test@kjs-segeberg.example');
        }
    }

    public function test_die_alte_hart_codierte_kjs_adresse_kommt_im_markup_nicht_mehr_vor(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'zentrale-test@kjs-segeberg.example']);

        foreach (['/faq', '/downloads', '/jaeger/vorstand'] as $uri) {
            $response = $this->get($uri);
            $html = $response->getContent();

            $response->assertOk();
            $this->assertSame(0, substr_count($html, 'info@kjs-bad-segeberg.de'), "info@kjs-bad-segeberg.de darf auf {$uri} nicht mehr hart codiert vorkommen.");
        }
    }

    public function test_fehlt_die_zentrale_email_entfaellt_der_mail_verweis_ohne_erfundenen_wert(): void
    {
        // Keine Settings der Gruppe "einstellungen" angelegt.
        foreach (['/faq', '/downloads', '/jaeger/vorstand'] as $uri) {
            $response = $this->get($uri);
            $html = $response->getContent();

            $response->assertOk();
            $response->assertDontSee('mailto:', false);
            $this->assertSame(0, substr_count($html, 'info@kjs-bad-segeberg.de'));
        }
    }
}
