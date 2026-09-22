<?php

namespace Tests\Feature\Phase2;

use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\FaqFrage;
use App\Models\FaqKategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt
 * FAQ und Downloads ab - beide reine Read-only-Listen ohne
 * Sichtbarkeits-Business-Logik.
 */
class FaqDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_renders_categories_and_questions_from_database(): void
    {
        $kat = FaqKategorie::create(['titel' => 'Mitgliedschaft', 'sortierung' => 0]);
        FaqFrage::create([
            'faq_kategorie_id' => $kat->id,
            'frage' => 'Wie werde ich Mitglied?',
            'antwort' => 'Über das Aufnahmeformular.',
            'sortierung' => 0,
        ]);

        $response = $this->get('/faq');

        $response->assertOk();
        $response->assertSee('Mitgliedschaft');
        $response->assertSee('Wie werde ich Mitglied?');
        $response->assertDontSee('/api/content/faq.json');
    }

    public function test_downloads_page_lists_only_central_library_entries(): void
    {
        $kat = DownloadKategorie::create(['titel' => 'Satzung & Ordnungen', 'sortierung' => 0]);
        Download::create([
            'kategorie_id' => $kat->id,
            'titel' => 'Satzung 2026',
            'pfad' => '/downloads/satzung-2026.pdf',
            'sortierung' => 0,
        ]);
        // Seiteneigener Download (owner_type gesetzt) darf hier NICHT
        // auftauchen - siehe DownloadsController-Klassenkommentar.
        Download::create([
            'owner_type' => 'page', 'owner_id' => 1,
            'titel' => 'Seiteneigenes Dokument',
            'pfad' => '/downloads/seite.pdf',
            'sortierung' => 0,
        ]);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Satzung & Ordnungen');
        $response->assertSee('Satzung 2026');
        $response->assertDontSee('Seiteneigenes Dokument');
        $response->assertDontSee('/api/content/downloads.json');
    }
}
