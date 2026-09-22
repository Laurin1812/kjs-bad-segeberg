<?php

namespace Tests\Feature\Phase2;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): deckt
 * Impressum/Datenschutz ab - beide lesen ausschliesslich aus der
 * "settings"-Tabelle (Gruppen "impressum"/"einstellungen"), kein JSON zur
 * Laufzeit.
 */
class ImpressumDatenschutzTest extends TestCase
{
    use RefreshDatabase;

    public function test_impressum_renders_settings_from_database(): void
    {
        Setting::create(['gruppe' => 'impressum', 'key' => 'verein', 'value' => 'Kreisjägerschaft Testort e.V.']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'vertreten_durch', 'value' => 'Max Mustermann']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'registergericht', 'value' => 'Amtsgericht Testort']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'registernummer', 'value' => 'VR 12345']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'verantwortlich', 'value' => 'Max Mustermann']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'adresse', 'value' => 'Musterstraße 1']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => '04551 123456']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'info@example.test']);

        $response = $this->get('/impressum');

        $response->assertOk();
        $response->assertSee('Kreisjägerschaft Testort e.V.');
        $response->assertSee('VR 12345');
        $response->assertSee('Musterstraße 1', false);
        $response->assertDontSee('/api/content/impressum.json');
        $response->assertDontSee('/api/content/einstellungen.json');
    }

    public function test_datenschutz_renders_address_from_database(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'adresse', 'value' => 'Teststraße 9']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => 'datenschutz@example.test']);

        $response = $this->get('/datenschutz');

        $response->assertOk();
        $response->assertSee('Teststraße 9', false);
        $response->assertSee('datenschutz@example.test');
        $response->assertDontSee('/api/content/einstellungen.json');
    }
}
