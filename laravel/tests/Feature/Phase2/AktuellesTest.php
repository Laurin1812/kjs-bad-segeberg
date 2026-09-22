<?php

namespace Tests\Feature\Phase2;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt
 * Aktuelles-Liste + -Detail ab. Deckt insbesondere ab: DB-Inhalt im
 * gerenderten HTML, archivierte Beitraege bleiben in der Standardansicht
 * verborgen, unbekannter Slug -> echte Laravel-404, kein JSON zur Laufzeit.
 */
class AktuellesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_visible_beitraege_and_hides_archived(): void
    {
        $kat = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Pressemitteilung', 'sortierung' => 0]);

        Beitrag::create([
            'typ' => 'aktuelles', 'slug' => 'sichtbarer-beitrag', 'titel' => 'Sichtbarer Testbeitrag',
            'datum' => now()->toDateString(), 'jahr' => (int) now()->format('Y'),
            'kategorie_id' => $kat->id, 'text' => 'Inhalt des sichtbaren Beitrags.', 'archiviert' => false,
        ]);
        Beitrag::create([
            'typ' => 'aktuelles', 'slug' => 'archivierter-beitrag', 'titel' => 'Archivierter Testbeitrag',
            'datum' => now()->toDateString(), 'jahr' => (int) now()->format('Y'),
            'kategorie_id' => $kat->id, 'text' => 'Sollte nicht erscheinen.', 'archiviert' => true,
        ]);

        $response = $this->get('/aktuelles');

        $response->assertOk();
        $response->assertSee('Sichtbarer Testbeitrag');
        $response->assertDontSee('Archivierter Testbeitrag');
        $response->assertDontSee('/api/content/aktuelles.json');
    }

    public function test_show_renders_beitrag_by_slug_with_markdown_and_no_json_fetch(): void
    {
        $beitrag = Beitrag::create([
            'typ' => 'aktuelles', 'slug' => 'markdown-test-beitrag', 'titel' => 'Markdown Test Beitrag',
            'datum' => now()->toDateString(), 'jahr' => (int) now()->format('Y'),
            'text' => "## Zwischenüberschrift\n\nEin **fetter** Testabsatz.", 'archiviert' => false,
        ]);

        $response = $this->get('/aktuelles/beitrag/'.$beitrag->slug);

        $response->assertOk();
        $response->assertSee('Markdown Test Beitrag');
        $response->assertSee('<h2>Zwischenüberschrift</h2>', false);
        $response->assertSee('<strong>fetter</strong>', false);
        $response->assertDontSee('/api/content/aktuelles.json');
    }

    public function test_unknown_slug_returns_404(): void
    {
        $response = $this->get('/aktuelles/beitrag/gibt-es-nicht');

        $response->assertNotFound();
    }

    public function test_typ_is_respected_so_service_beitraege_do_not_leak_into_aktuelles(): void
    {
        // Regressionsschutz fuer die legacy_index-Kollisionsgefahr (siehe
        // AktuellesController-Kommentar): "aktuelles" und "service" teilen
        // sich dieselbe Tabelle, ein Slug-Lookup muss zwingend nach "typ"
        // filtern.
        Beitrag::create([
            'typ' => 'service', 'slug' => 'geteilter-slug', 'titel' => 'Service Beitrag',
            'datum' => now()->toDateString(),
        ]);

        $response = $this->get('/aktuelles/beitrag/geteilter-slug');

        $response->assertNotFound();
    }
}
