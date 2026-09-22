<?php

namespace Tests\Feature\Phase4;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation).
 *
 * Breadcrumbs werden jetzt vollstaendig serverseitig aus der aktuellen
 * Route/Page aufgebaut (siehe components/breadcrumbs.blade.php,
 * <x-page-hero :breadcrumbs="...">) - kein "Wird geladen …"-Platzhalter
 * mehr, keine clientseitige Pfad-/navigation.json-Auswertung.
 */
class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_breadcrumb_fuer_feste_seite_zeigt_startseite_jaeger_hochwild(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('id="siteBreadcrumb"', $html);
        $breadcrumbHtml = substr($html, strpos($html, 'id="siteBreadcrumb"'), 400);
        $this->assertStringContainsString('Startseite', $breadcrumbHtml);
        $this->assertStringContainsString('Jäger', $breadcrumbHtml);
        $this->assertStringContainsString('Hochwild', $breadcrumbHtml);
        $response->assertDontSee('Wird geladen');
    }

    public function test_breadcrumb_fuer_jagdhundeschule_uebersicht_zeigt_keine_wird_geladen_platzhalter(): void
    {
        Page::create(['section' => 'hundeausbildung', 'slug' => 'hundeausbildung', 'titel' => 'Jagdhundeschule']);

        $response = $this->get('/aufgaben/jagdhundeschule');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertDontSee('Wird geladen');
        $breadcrumbHtml = substr($html, strpos($html, 'id="siteBreadcrumb"'), 400);
        $this->assertStringContainsString('Startseite', $breadcrumbHtml);
        $this->assertStringContainsString('Aufgaben', $breadcrumbHtml);
        $this->assertStringContainsString('Jagdhundeschule', $breadcrumbHtml);
    }

    public function test_breadcrumb_fuer_jagdhundeschule_kurs_zeigt_vier_ebenen_inklusive_elternseite(): void
    {
        $hub = Page::create(['section' => 'hundeausbildung', 'slug' => 'hundeausbildung', 'titel' => 'Jagdhundeschule']);
        Page::create(['section' => 'hundeausbildung', 'parent_id' => $hub->id, 'slug' => 'kurs-1', 'titel' => 'Kurs 1', 'veroeffentlicht' => true]);

        $response = $this->get('/aufgaben/jagdhundeschule/kurs-1');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertDontSee('Wird geladen');
        $breadcrumbHtml = substr($html, strpos($html, 'id="siteBreadcrumb"'), 500);
        $this->assertStringContainsString('Startseite', $breadcrumbHtml);
        $this->assertStringContainsString('Aufgaben', $breadcrumbHtml);
        $this->assertStringContainsString('Jagdhundeschule', $breadcrumbHtml);
        $this->assertStringContainsString('Kurs 1', $breadcrumbHtml);
    }

    public function test_breadcrumb_fuer_dynamische_unterseite_bezieht_elternseite_ein(): void
    {
        $eltern = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        Page::create(['section' => 'jaeger', 'parent_id' => $eltern->id, 'slug' => 'unterseite-test', 'titel' => 'Unterseite Test', 'veroeffentlicht' => true, 'nav_label' => 'Unterseite Test']);

        $response = $this->get('/jaeger/hochwild/unterseite-test');
        $html = $response->getContent();

        $response->assertOk();
        $breadcrumbHtml = substr($html, strpos($html, 'id="siteBreadcrumb"'), 500);
        $this->assertStringContainsString('Jäger', $breadcrumbHtml);
        $this->assertStringContainsString('Hochwild', $breadcrumbHtml);
        $this->assertStringContainsString('Unterseite Test', $breadcrumbHtml);
    }
}
