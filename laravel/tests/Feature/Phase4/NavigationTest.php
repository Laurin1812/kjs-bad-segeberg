<?php

namespace Tests\Feature\Phase4;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation).
 *
 * Die komplette Desktop- UND Mobile-Navigation wird jetzt serverseitig aus
 * der settings-Tabelle (Gruppe "navigation") + dynamischen Registry-Seiten
 * (Tabelle "pages") gerendert - siehe App\Support\Navigation/
 * App\View\Composers\NavigationComposer. Kein navigation.json/
 * navigation-extra.json/seiten-*.json/anderer /api/content/*.json-Request
 * mehr.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function seedNavigationBlob(): void
    {
        Setting::create(['gruppe' => 'navigation', 'key' => 'data', 'value' => json_encode([
            'sektionsnamen' => ['jaeger' => 'Jäger', 'verbraucher' => 'Verbraucher'],
            'hauptmenu' => ['startseite', 'verbraucher', 'aktuelles', 'faq'],
            'hauptmenu_meta' => [
                'startseite' => ['label' => 'Startseite', 'href' => '/'],
                'aktuelles' => ['label' => 'Aktuelles', 'href' => '/aktuelles/index.html'],
                'faq' => ['label' => 'FAQ', 'href' => '/faq/index.html'],
            ],
            'jaeger_dropdown' => [],
            'jaeger_dropdown_meta' => [],
            'kjs' => [],
            'aufgaben' => [],
            'verbraucher' => [
                ['label' => 'Statische Verbraucherseite', 'href' => '/verbraucher/wildfleisch.html'],
            ],
        ])]);
    }

    public function test_desktop_und_mobile_navigation_enthalten_dieselben_db_seiten(): void
    {
        $this->seedNavigationBlob();

        $response = $this->get('/');
        $html = $response->getContent();

        $response->assertOk();
        // Beide Container muessen den statischen Verbraucher-Eintrag
        // enthalten - Desktop UND Mobile teilen sich dieselbe Datenquelle
        // (siehe components/site-header.blade.php).
        $this->assertStringContainsString('id="mainNav"', $html);
        $this->assertStringContainsString('id="mobileNavList"', $html);
        $mainNav = substr($html, strpos($html, 'id="mainNav"'), strpos($html, '</nav>', strpos($html, 'id="mainNav"')) - strpos($html, 'id="mainNav"'));
        $mobileNav = substr($html, strpos($html, 'id="mobileNavList"'));
        $this->assertStringContainsString('Statische Verbraucherseite', $mainNav);
        $this->assertStringContainsString('/verbraucher/wildfleisch', $mainNav);
        $this->assertStringContainsString('Statische Verbraucherseite', $mobileNav);
    }

    public function test_dynamisch_angelegte_seite_erscheint_automatisch_in_der_navigation(): void
    {
        $this->seedNavigationBlob();
        Page::create([
            'section' => 'verbraucher', 'slug' => 'dynamische-testseite', 'titel' => 'Dynamische Testseite',
            'veroeffentlicht' => true, 'in_navigation' => true, 'sortierung' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Dynamische Testseite');
        $response->assertSee('/verbraucher/dynamische-testseite', false);
    }

    public function test_in_navigation_false_erscheint_nicht_in_der_navigation(): void
    {
        $this->seedNavigationBlob();
        Page::create([
            'section' => 'verbraucher', 'slug' => 'versteckte-testseite', 'titel' => 'Versteckte Testseite',
            'veroeffentlicht' => true, 'in_navigation' => false, 'sortierung' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Versteckte Testseite');
    }

    public function test_unveroeffentlichte_seite_erscheint_nicht_in_der_navigation(): void
    {
        $this->seedNavigationBlob();
        Page::create([
            'section' => 'verbraucher', 'slug' => 'unveroeffentlichte-testseite', 'titel' => 'Unveröffentlichte Testseite',
            'veroeffentlicht' => false, 'in_navigation' => true, 'sortierung' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Unveröffentlichte Testseite');
    }

    public function test_sortierung_der_dynamischen_seiten_entspricht_der_datenbank(): void
    {
        $this->seedNavigationBlob();
        Page::create(['section' => 'verbraucher', 'slug' => 'zweite-seite', 'titel' => 'Zweite Testseite', 'veroeffentlicht' => true, 'in_navigation' => true, 'sortierung' => 2]);
        Page::create(['section' => 'verbraucher', 'slug' => 'erste-seite', 'titel' => 'Erste Testseite', 'veroeffentlicht' => true, 'in_navigation' => true, 'sortierung' => 1]);

        $html = $this->get('/')->getContent();

        $posErste = strpos($html, 'Erste Testseite');
        $posZweite = strpos($html, 'Zweite Testseite');

        $this->assertNotFalse($posErste);
        $this->assertNotFalse($posZweite);
        $this->assertLessThan($posZweite, $posErste, 'Seite mit sortierung=1 muss vor sortierung=2 gerendert werden.');
    }

    public function test_navigation_nutzt_keine_api_content_json_requests(): void
    {
        $this->seedNavigationBlob();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('/api/content/navigation');
        $response->assertDontSee('/api/content/seiten-');
        $response->assertDontSee('/api/content/');
    }
}
