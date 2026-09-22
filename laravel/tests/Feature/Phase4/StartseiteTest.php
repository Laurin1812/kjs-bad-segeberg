<?php

namespace Tests\Feature\Phase4;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Setting;
use App\Models\StartseiteHeroSlide;
use App\Models\Termin;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation).
 *
 * "/" muss die echte KJS-Startseite sein (kein Laravel-Welcome mehr),
 * vollstaendig aus MySQL/Eloquent aufgebaut, keine /api/content/*.json-
 * Requests mehr.
 */
class StartseiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_startseite_antwortet_200_und_zeigt_echte_kjs_inhalte_statt_welcome(): void
    {
        Setting::create(['gruppe' => 'startseite', 'key' => 'willkommen_tag', 'value' => 'Willkommen bei der KJS Testwelt']);
        Setting::create(['gruppe' => 'startseite', 'key' => 'hero_titel', 'value' => 'Einzigartiger Hero-Testtitel']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Willkommen bei der KJS Testwelt');
        $response->assertSee('Einzigartiger Hero-Testtitel');
        // Die Laravel-Standard-Willkommensseite enthaelt u.a. diesen exakten
        // Text nicht mehr auf "/" - stattdessen laeuft die Route jetzt ueber
        // HomeController (siehe routes/web.php).
        $response->assertDontSee('Laravel News');
        $this->assertSame('home', request()->route()?->getName());
    }

    public function test_startseite_zeigt_neueste_aktuelles_beitraege_und_naechste_termine_aus_der_datenbank(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Testkategorie']);
        Beitrag::create([
            'typ' => 'aktuelles', 'slug' => 'einzigartiger-test-beitrag', 'titel' => 'Einzigartiger Testbeitrag',
            'datum' => now()->subDay(), 'kategorie_id' => $kategorie->id, 'archiviert' => false, 'sortierung' => 1,
        ]);
        Termin::create([
            'datum' => now()->addDays(2), 'uhrzeit' => '18:00', 'veranstaltung' => 'Einzigartiger Testtermin',
            'ort' => 'Teststadt', 'archiviert' => false,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Einzigartiger Testbeitrag');
        $response->assertSee('Einzigartiger Testtermin');
    }

    public function test_startseite_zeigt_hero_slides_und_testimonials_aus_der_datenbank(): void
    {
        StartseiteHeroSlide::create(['bild' => '/images/test-hero-slide.jpg', 'dauer' => 5, 'sortierung' => 1]);
        Testimonial::create(['text' => 'Ein einzigartiges Test-Zitat', 'name' => 'Max Testmann', 'rolle' => 'Testrolle', 'icon' => '🧪', 'sichtbar' => true, 'sortierung' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('/images/test-hero-slide.jpg', false);
        $response->assertSee('Ein einzigartiges Test-Zitat');
        $response->assertSee('Max Testmann');
    }

    public function test_startseite_blendet_testimonials_bei_sichtbar_false_komplett_aus(): void
    {
        Testimonial::create(['text' => 'Verstecktes Test-Zitat', 'name' => 'Verstecker Name', 'sichtbar' => false, 'sortierung' => 1]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Verstecktes Test-Zitat');
        $response->assertDontSee('Verstecker Name');
    }

    public function test_startseite_nutzt_keine_api_content_json_requests(): void
    {
        Setting::create(['gruppe' => 'startseite', 'key' => 'hero_titel', 'value' => 'Test']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('/api/content/');
        $response->assertDontSee('fetchContent(');
    }
}
