<?php

namespace Tests\Feature\Phase2;

use App\Models\Hegering;
use App\Models\Page;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt
 * Vorstand, Obleute, Hegeringe und Kreisjägermeister ab - vier reine
 * Read-only-Seiten ohne Sichtbarkeits-Business-Logik.
 */
class PersonenHegeringeKreisjaegermeisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_vorstand_shows_only_vorstand_gremium(): void
    {
        Person::create(['gremium' => 'vorstand', 'rolle' => 'Vorsitzender', 'name' => 'Vorstand Testperson', 'sortierung' => 0]);
        Person::create(['gremium' => 'obmann', 'rolle' => 'Obmann', 'name' => 'Obmann Testperson', 'sortierung' => 0]);

        $response = $this->get('/jaeger/vorstand');

        $response->assertOk();
        $response->assertSee('Vorstand Testperson');
        $response->assertDontSee('Obmann Testperson');
        $response->assertDontSee('/api/content/vorstand.json');
    }

    public function test_obleute_shows_only_obmann_gremium(): void
    {
        Person::create(['gremium' => 'vorstand', 'rolle' => 'Vorsitzender', 'name' => 'Vorstand Testperson', 'sortierung' => 0]);
        Person::create(['gremium' => 'obmann', 'rolle' => 'Kreisschießobmann', 'name' => 'Obmann Testperson', 'sortierung' => 0]);

        $response = $this->get('/jaeger/obleute');

        $response->assertOk();
        $response->assertSee('Obmann Testperson');
        $response->assertDontSee('Vorstand Testperson');
        $response->assertDontSee('/api/content/obleute.json');
    }

    public function test_hegeringe_renders_database_entries(): void
    {
        Hegering::create(['nummer' => 'VIII', 'name' => 'Testhegering', 'obmann' => 'Max Mustermann', 'sortierung' => 0]);

        $response = $this->get('/jaeger/hegeringe');

        $response->assertOk();
        $response->assertSee('Testhegering');
        $response->assertSee('Max Mustermann');
        $response->assertDontSee('/api/content/hegeringe.json');
    }

    public function test_kreisjaegermeister_renders_page_data(): void
    {
        Page::create([
            'section' => 'kreisjaegermeister',
            'slug' => 'kreisjaegermeister',
            'titel' => 'Kreisjägermeister',
            'kontakt_name' => 'Testjägermeister',
            'kontakt_email' => 'kjm@example.test',
            'inhalt' => 'Seine Aufgaben im Überblick.',
            'grusswort' => "Liebe Jägerinnen und Jäger,\n\nherzlich willkommen.",
        ]);

        $response = $this->get('/kreisjjaegermeister');

        $response->assertOk();
        $response->assertSee('Testjägermeister');
        $response->assertSee('kjm@example.test');
        $response->assertSee('herzlich willkommen');
        $response->assertDontSee('/api/content/kreisjjaegermeister.json');
    }
}
