<?php

namespace Tests\Feature\Phase3;

use App\Models\Page;
use App\Models\PageLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
 * Vollmigration): deckt FesteSeiteController + RegistrySeiteController ab -
 * feste Vorlagen-Seiten und per Registry hinzugefuegte Zusatzseiten von
 * jaeger/aufgaben/verbraucher, "weitere"-Seiten sowie dynamisch angelegte
 * Unterseiten. Siehe FesteSeiteController-/RegistrySeiteController-
 * Klassenkommentare fuer die Architektur.
 */
class SeitenfamilienTest extends TestCase
{
    use RefreshDatabase;

    public function test_feste_jaeger_seite_zeigt_datenbankinhalt_ohne_json(): void
    {
        $page = Page::create([
            'section' => 'jaeger',
            'slug' => 'hochwild',
            'titel' => 'Hochwild',
            'untertitel' => '<p>Hochwildringe im Kreis Bad Segeberg</p>',
            'intro' => '<p>Testintro Hochwild.</p>',
            'inhalt' => '<h2>Hochwildringe</h2><p>Testinhalt Hochwild.</p>',
            'kontakt_name' => 'Max Mustermann',
            'kontakt_email' => 'max@example.test',
        ]);
        PageLink::create(['page_id' => $page->id, 'label' => 'Externer Link', 'href' => 'https://example.test/', 'sortierung' => 0]);

        $response = $this->get('/jaeger/hochwild');

        $response->assertOk();
        $response->assertSee('Testinhalt Hochwild.', false);
        $response->assertSee('max@example.test');
        $response->assertSee('Externer Link');
        $response->assertSee('Jetzt Kontakt aufnehmen');
        $response->assertDontSee('/api/content/jaeger/hochwild.json');
        $response->assertDontSee('content/jaeger/hochwild.json');
    }

    public function test_feste_aufgaben_und_verbraucher_seiten_funktionieren(): void
    {
        Page::create(['section' => 'aufgaben', 'slug' => 'jagdhorn', 'titel' => 'Jagdhorn', 'inhalt' => '<p>Jagdhorn-Inhalt.</p>']);
        Page::create(['section' => 'verbraucher', 'slug' => 'wildfleisch', 'titel' => 'Wildfleisch', 'inhalt' => '<p>Wildfleisch-Inhalt.</p>']);

        $aufgaben = $this->get('/aufgaben/jagdhorn');
        $aufgaben->assertOk();
        $aufgaben->assertSee('Jagdhorn-Inhalt.', false);
        $aufgaben->assertSee('Termine ansehen');
        $aufgaben->assertDontSee('/api/content/aufgaben/jagdhorn.json');

        $verbraucher = $this->get('/verbraucher/wildfleisch');
        $verbraucher->assertOk();
        $verbraucher->assertSee('Wildfleisch-Inhalt.', false);
        $verbraucher->assertDontSee('/api/content/verbraucher/wildfleisch.json');
    }

    public function test_mitglied_werden_zeigt_antrag_url_button(): void
    {
        Page::create([
            'section' => 'jaeger',
            'slug' => 'mitglied-werden',
            'titel' => 'Mitglied werden',
            'antrag_url' => 'https://formulare.example.test/mitgliedsantrag',
        ]);

        $response = $this->get('/jaeger/mitglied-werden');

        $response->assertOk();
        $response->assertSee('Mitgliedsantrag online');
        $response->assertSee('https://formulare.example.test/mitgliedsantrag', false);
    }

    public function test_registry_zusatzseite_zeigt_downloads_und_galerie(): void
    {
        $page = Page::create([
            'section' => 'jaeger',
            'slug' => 'eine-neu-angelegte-seite',
            'titel' => 'Eine neu angelegte Seite',
            'inhalt' => '<p>Per Registry hinzugefuegter Inhalt.</p>',
        ]);
        $page->downloads()->create(['titel' => 'Testdokument', 'pfad' => '/downloads/test.pdf', 'sortierung' => 0]);
        $page->galerieBilder()->create(['titel' => 'Testbild', 'pfad' => '/images/test.jpg', 'sortierung' => 0]);

        $response = $this->get('/jaeger/eine-neu-angelegte-seite');

        $response->assertOk();
        $response->assertSee('Per Registry hinzugefuegter Inhalt.', false);
        $response->assertSee('Testdokument');
        $response->assertSee('Testbild');
        // Registry-Seiten haben (anders als feste Seiten) keine CTA-Buttons.
        $response->assertDontSee('Jetzt Kontakt aufnehmen');
        // "/api/content/design.json" (Farb-/Schrift-Theming, seit Phase 1
        // unveraendert auf jeder Seite vorhanden) ist bewusst KEIN
        // Seiteninhalt-Endpunkt und daher hier erlaubt - siehe
        // components/layouts/app.blade.php.
        $response->assertDontSee('/api/content/jaeger/');
        $response->assertDontSee('/api/content/seiten-kjs/');
    }

    public function test_weitere_seite_funktioniert(): void
    {
        Page::create([
            'section' => 'weitere',
            'slug' => 'jagdhornblasen',
            'titel' => 'Jagdhornblasen',
            'intro' => '<p>Musik bei der Jagd.</p>',
            'in_navigation' => true,
            'veroeffentlicht' => true,
        ]);

        $response = $this->get('/weitere/jagdhornblasen');

        $response->assertOk();
        $response->assertSee('Jagdhornblasen');
        $response->assertSee('Musik bei der Jagd.', false);
        $response->assertDontSee('/api/content/seiten-weitere/jagdhornblasen.json');
    }

    public function test_dynamisch_angelegte_unterseite_einer_festen_seite_funktioniert(): void
    {
        $parent = Page::create(['section' => 'verbraucher', 'slug' => 'wildfleisch', 'titel' => 'Wildfleisch']);
        Page::create([
            'section' => 'verbraucher',
            'parent_id' => $parent->id,
            'slug' => 'lagerung-wildfleisch',
            'titel' => 'Lagerung von Wildfleisch',
            'inhalt' => '<p>So lagern Sie Wildfleisch richtig.</p>',
        ]);

        // Die Elternseite muss die veroeffentlichte Unterseite in ihrer
        // Sidebar ("Unterseiten"-Kachelbox) verlinken...
        $elternResponse = $this->get('/verbraucher/wildfleisch');
        $elternResponse->assertOk();
        $elternResponse->assertSee('Lagerung von Wildfleisch');
        $elternResponse->assertSee('/verbraucher/wildfleisch/lagerung-wildfleisch', false);

        // ...und die Unterseite selbst muss unter der modernisierten
        // 3-Segment-Route erreichbar sein und den DB-Inhalt zeigen.
        $response = $this->get('/verbraucher/wildfleisch/lagerung-wildfleisch');
        $response->assertOk();
        $response->assertSee('So lagern Sie Wildfleisch richtig.', false);
        $response->assertDontSee('/api/content/seiten-sub-wildfleisch/lagerung-wildfleisch.json');
    }

    public function test_unbekannter_slug_fuehrt_zu_echter_404(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $this->get('/jaeger/dieser-slug-existiert-nicht')->assertNotFound();
        $this->get('/aufgaben/dieser-slug-existiert-nicht')->assertNotFound();
        $this->get('/verbraucher/dieser-slug-existiert-nicht')->assertNotFound();
        $this->get('/weitere/dieser-slug-existiert-nicht')->assertNotFound();
        $this->get('/jaeger/hochwild/keine-unterseite')->assertNotFound();
        $this->get('/keine-section/hochwild')->assertNotFound();
    }

    public function test_unveroeffentlichte_seite_ist_nicht_erreichbar(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild', 'veroeffentlicht' => false]);

        $this->get('/jaeger/hochwild')->assertNotFound();
    }
}
