<?php

namespace Tests\Feature\Phase4;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 (Auftrag Punkt 7, "Jäger-Übersicht").
 *
 * Das in Phase 3 bewusst nicht nachgebaute Kachel-Raster von
 * jaeger/index.html wird jetzt aus MySQL/Nav-Daten erzeugt (siehe
 * App\Support\Navigation::jaegerUebersichtKacheln()) statt aus einer hart
 * codierten Liste.
 */
class JaegerUebersichtTest extends TestCase
{
    use RefreshDatabase;

    public function test_jaeger_uebersicht_zeigt_geschwisterseiten_aus_settings_und_datenbank(): void
    {
        Setting::create(['gruppe' => 'navigation', 'key' => 'data', 'value' => json_encode([
            'kjs' => [
                ['label' => 'Vorstand', 'href' => '/jaeger/vorstand.html'],
                ['label' => 'Hochwild', 'href' => '/jaeger/hochwild.html'],
            ],
        ])]);
        Page::create(['section' => 'jaeger', 'slug' => 'uebersicht', 'titel' => 'Jäger']);
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild', 'kurzbeschreibung' => 'Alles über Rot- und Damwild.']);

        $response = $this->get('/jaeger/uebersicht');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('Vorstand');
        $response->assertSee('Hochwild');
        $response->assertSee('Alles über Rot- und Damwild.');
        $this->assertStringContainsString('href="/jaeger/vorstand"', $html);
        $this->assertStringContainsString('href="/jaeger/hochwild"', $html);
    }

    public function test_jaeger_uebersicht_zeigt_sich_selbst_nicht_als_geschwisterseite(): void
    {
        Setting::create(['gruppe' => 'navigation', 'key' => 'data', 'value' => json_encode([
            'kjs' => [
                ['label' => 'Übersicht (sollte nicht als Kachel erscheinen)', 'href' => '/jaeger/uebersicht.html'],
                ['label' => 'Vorstand', 'href' => '/jaeger/vorstand.html'],
            ],
        ])]);
        Page::create(['section' => 'jaeger', 'slug' => 'uebersicht', 'titel' => 'Jäger']);

        $response = $this->get('/jaeger/uebersicht');

        $response->assertOk();
        $response->assertDontSee('sollte nicht als Kachel erscheinen');
    }

    public function test_andere_feste_seiten_zeigen_kein_geschwister_kachel_raster(): void
    {
        Setting::create(['gruppe' => 'navigation', 'key' => 'data', 'value' => json_encode([
            'kjs' => [['label' => 'Vorstand', 'href' => '/jaeger/vorstand.html']],
        ])]);
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');

        $response->assertOk();
        // Nur jaeger/uebersicht bekommt das Kachel-Raster - andere feste
        // Seiten (z.B. hochwild) bleiben unveraendert (siehe
        // FesteSeiteController).
        $response->assertDontSee('service-card__title');
    }
}
