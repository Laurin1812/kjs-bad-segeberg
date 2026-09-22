<?php

namespace Tests\Feature\Phase2;

use App\Models\Termin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt die
 * Termine-Uebersicht ab. Sichtbarkeitsregel (App\Support\TermineRules) 1:1
 * aus js/content.js portiert: archiviert ODER Datum + 7 Tage in der
 * Vergangenheit -> ausgeblendet.
 */
class TermineTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_termin_is_visible(): void
    {
        Termin::create([
            'datum' => now()->addDays(10)->toDateString(),
            'uhrzeit' => '19:00',
            'veranstaltung' => 'Testversammlung',
            'ort' => 'Bad Segeberg',
            'kategorie' => 'Hauptversammlung',
            'archiviert' => false,
        ]);

        $response = $this->get('/termine');

        $response->assertOk();
        $response->assertSee('Testversammlung');
        $response->assertDontSee('/api/content/termine.json');
    }

    public function test_archived_termin_is_hidden(): void
    {
        Termin::create([
            'datum' => now()->addDays(10)->toDateString(),
            'veranstaltung' => 'Archivierter Termin',
            'archiviert' => true,
        ]);

        $response = $this->get('/termine');

        $response->assertOk();
        $response->assertDontSee('Archivierter Termin');
    }

    public function test_termin_more_than_seven_days_in_the_past_is_hidden(): void
    {
        Termin::create([
            'datum' => now()->subDays(30)->toDateString(),
            'veranstaltung' => 'Laengst vorbeier Termin',
            'archiviert' => false,
        ]);

        $response = $this->get('/termine');

        $response->assertOk();
        $response->assertDontSee('Laengst vorbeier Termin');
    }
}
