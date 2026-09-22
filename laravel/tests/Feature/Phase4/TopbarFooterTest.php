<?php

namespace Tests\Feature\Phase4;

use App\Models\FooterLink;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation).
 *
 * Topbar UND Footer laden ihre Werte jetzt direkt serverseitig aus der
 * settings-Tabelle (+ footer_links) - siehe App\View\Composers\
 * TopbarComposer/FooterComposer - kein content/einstellungen.json-/
 * content/footer.json-Fetch mehr, keine hart codierten Platzhalter.
 */
class TopbarFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_zeigt_email_und_telefon_aus_der_datenbank(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'topbar-test@kjs-segeberg.example']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => '05551 000000']);
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');

        $response->assertOk();
        $response->assertSee('topbar-test@kjs-segeberg.example');
        $response->assertSee('05551 000000');
    }

    public function test_topbar_zeigt_keinen_link_wenn_wert_in_der_datenbank_fehlt(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');

        $response->assertOk();
        $response->assertDontSee('mailto:', false);
    }

    public function test_footer_zeigt_copyright_und_links_aus_der_datenbank(): void
    {
        Setting::create(['gruppe' => 'footer', 'key' => 'copyright', 'value' => 'Test-Copyright KJS Segeberg']);
        FooterLink::create(['spalte' => 'uebersicht', 'label' => 'Test-Wildfleisch-Link', 'href' => '/verbraucher/wildfleisch.html', 'sortierung' => 1]);
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('Test-Copyright KJS Segeberg');
        $response->assertSee('Test-Wildfleisch-Link');
        // Auftrag Punkt 5: Footer-Links muessen auf die neuen Laravel-Routen
        // zeigen, nicht mehr auf die alten ".html"-Pfade.
        $this->assertStringContainsString('href="/verbraucher/wildfleisch"', $html);
        $this->assertStringNotContainsString('wildfleisch.html', $html);
    }

    public function test_footer_und_topbar_nutzen_keine_api_content_json_requests(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');

        $response->assertOk();
        $response->assertDontSee('/api/content/footer');
        $response->assertDontSee('/api/content/einstellungen');
    }
}
