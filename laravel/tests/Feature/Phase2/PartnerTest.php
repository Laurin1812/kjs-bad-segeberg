<?php

namespace Tests\Feature\Phase2;

use App\Models\Partner;
use App\Models\PartnerVorteil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt
 * Partner-Liste + -Detail ab. Detail-Lookup laeuft ueber "external_id"
 * (siehe PartnerController-Klassenkommentar), inaktive Partner sind weder
 * in der Liste noch per Detail-URL erreichbar.
 */
class PartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_partners(): void
    {
        Partner::create(['external_id' => 'pn-aktiv', 'name' => 'Aktiver Partner', 'aktiv' => true, 'sortierung' => 0]);
        Partner::create(['external_id' => 'pn-inaktiv', 'name' => 'Inaktiver Partner', 'aktiv' => false, 'sortierung' => 1]);

        $response = $this->get('/partner');

        $response->assertOk();
        $response->assertSee('Aktiver Partner');
        $response->assertDontSee('Inaktiver Partner');
        $response->assertDontSee('/api/content/partner.json');
    }

    public function test_show_renders_partner_by_external_id_with_typed_vorteile(): void
    {
        $partner = Partner::create([
            'external_id' => 'pn-detail-test', 'name' => 'Detail Testpartner',
            'beschreibung' => 'Ein Testpartner.', 'rahmenvertrag' => true, 'aktiv' => true,
        ]);
        PartnerVorteil::create(['partner_id' => $partner->id, 'text' => 'Sonderkonditionen', 'sortierung' => 0]);

        $response = $this->get('/partner/detail/pn-detail-test');

        $response->assertOk();
        $response->assertSee('Detail Testpartner');
        $response->assertSee('Sonderkonditionen');
        $response->assertSee('Rahmenvertrag');
        $response->assertDontSee('/api/content/partner.json');
    }

    public function test_unknown_external_id_returns_404(): void
    {
        $response = $this->get('/partner/detail/pn-existiert-nicht');

        $response->assertNotFound();
    }

    public function test_inactive_partner_returns_404_even_with_valid_external_id(): void
    {
        Partner::create(['external_id' => 'pn-inaktiv-detail', 'name' => 'Inaktiv', 'aktiv' => false]);

        $response = $this->get('/partner/detail/pn-inaktiv-detail');

        $response->assertNotFound();
    }
}
