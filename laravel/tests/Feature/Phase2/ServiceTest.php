<?php

namespace Tests\Feature\Phase2;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt die
 * Service-Seite ab (Settings-Gruppe "service" + Beitragsliste, geteiltes
 * Datenmodell mit Aktuelles ueber "typ").
 */
class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_page_renders_settings_and_beitraege(): void
    {
        Setting::create(['gruppe' => 'service', 'key' => 'titel', 'value' => 'Unser Service']);
        $kat = BeitragKategorie::create(['typ' => 'service', 'name' => 'Formulare', 'sortierung' => 0]);
        Beitrag::create([
            'typ' => 'service', 'slug' => 'service-testbeitrag', 'titel' => 'Service Testbeitrag',
            'datum' => now()->toDateString(), 'kategorie_id' => $kat->id, 'text' => 'Servicetext.',
            'archiviert' => false,
        ]);

        $response = $this->get('/service');

        $response->assertOk();
        $response->assertSee('Unser Service');
        $response->assertSee('Service Testbeitrag');
        $response->assertSee('Formulare');
        $response->assertDontSee('/api/content/service.json');
    }

    public function test_archived_service_beitrag_is_not_shown(): void
    {
        Beitrag::create([
            'typ' => 'service', 'slug' => 'archiviert', 'titel' => 'Archivierter Service Beitrag',
            'datum' => now()->toDateString(), 'archiviert' => true,
        ]);

        $response = $this->get('/service');

        $response->assertOk();
        $response->assertDontSee('Archivierter Service Beitrag');
    }
}
