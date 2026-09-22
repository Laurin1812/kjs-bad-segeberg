<?php

namespace Tests\Feature\Phase3;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
 * Vollmigration): deckt HundeausbildungController ab - Hub + Kurs-
 * Uebersicht + einzelner Kurs. Siehe HundeausbildungController-
 * Klassenkommentar.
 */
class HundeausbildungTest extends TestCase
{
    use RefreshDatabase;

    private function makeHubMitKursen(): Page
    {
        $hub = Page::create([
            'section' => 'hundeausbildung',
            'slug' => 'hundeausbildung',
            'titel' => 'Jagdhundeschule',
            'untertitel' => '<p>Hundeausbildung in der KJS Segeberg</p>',
            'intro' => '<p>Gut ausgebildete Jagdhunde.</p>',
            'inhalt' => '<h2>Ausbildungsangebot</h2><p>Testinhalt Hub.</p>',
            'kontakt_name' => 'Heidi Fitzner',
            'kontakt_email' => 'heidi@example.test',
        ]);

        Page::create([
            'section' => 'hundeausbildung',
            'parent_id' => $hub->id,
            'slug' => 'kurs-1-junghunde',
            'titel' => 'Kurs 1: Junghunde',
            'nav_label' => 'Kurs 1: Junghunde',
            'gruppe' => 'Kurse 1–6',
            'vorschaubild' => '/images/kurs-1.jpg',
            'kurzbeschreibung' => 'Grundausbildung fuer Junghunde.',
            'inhalt' => '<p>Kursinhalt Junghunde.</p>',
            'sortierung' => 0,
        ]);
        $kurs2 = Page::create([
            'section' => 'hundeausbildung',
            'parent_id' => $hub->id,
            'slug' => 'hegeringhundewarte',
            'titel' => 'Hegeringhundewarte',
            'nav_label' => 'Hegeringhundewarte',
            'inhalt' => '<p>Kursinhalt Hegeringhundewarte.</p>',
            'sortierung' => 1,
        ]);
        $kurs2->downloads()->create(['titel' => 'Kursunterlagen', 'pfad' => '/downloads/kurs.pdf', 'sortierung' => 0]);
        $kurs2->galerieBilder()->create(['titel' => 'Kursbild', 'pfad' => '/images/kurs.jpg', 'sortierung' => 0]);

        // Unveroeffentlichter Kurs - darf nirgends auftauchen.
        Page::create([
            'section' => 'hundeausbildung',
            'parent_id' => $hub->id,
            'slug' => 'verstecker-kurs',
            'titel' => 'Versteckter Kurs',
            'veroeffentlicht' => false,
        ]);

        return $hub;
    }

    public function test_hub_zeigt_datenbankinhalt_ohne_json(): void
    {
        $this->makeHubMitKursen();

        $response = $this->get('/aufgaben/hundeausbildung');

        $response->assertOk();
        $response->assertSee('Testinhalt Hub.', false);
        $response->assertSee('heidi@example.test');
        $response->assertSee('Alle 2 Kurse');
        $response->assertDontSee('/api/content/aufgaben/hundeausbildung.json');
    }

    public function test_uebersicht_listet_veroeffentlichte_kurse_gruppiert(): void
    {
        $this->makeHubMitKursen();

        $response = $this->get('/aufgaben/jagdhundeschule');

        $response->assertOk();
        $response->assertSee('Kurs 1: Junghunde');
        $response->assertSee('Hegeringhundewarte');
        $response->assertSee('Kurse 1–6');
        $response->assertDontSee('Versteckter Kurs');
        $response->assertDontSee('/api/content/aufgaben/hundeausbildung-seiten.json');
    }

    public function test_einzelner_kurs_zeigt_downloads_und_galerie(): void
    {
        $this->makeHubMitKursen();

        $response = $this->get('/aufgaben/jagdhundeschule/hegeringhundewarte');

        $response->assertOk();
        $response->assertSee('Kursinhalt Hegeringhundewarte.', false);
        $response->assertSee('Kursunterlagen');
        $response->assertSee('Kursbild');
        $response->assertSee('Zurück zu allen Themen');
        $response->assertDontSee('/api/content/aufgaben/hundeausbildung/hegeringhundewarte.json');
    }

    public function test_unbekannter_kurs_slug_fuehrt_zu_echter_404(): void
    {
        $this->makeHubMitKursen();

        $this->get('/aufgaben/jagdhundeschule/dieser-kurs-existiert-nicht')->assertNotFound();
    }

    public function test_versteckter_unveroeffentlichter_kurs_ist_nicht_erreichbar(): void
    {
        $this->makeHubMitKursen();

        $this->get('/aufgaben/jagdhundeschule/verstecker-kurs')->assertNotFound();
    }
}
