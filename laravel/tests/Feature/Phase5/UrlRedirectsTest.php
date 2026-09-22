<?php

namespace Tests\Feature\Phase5;

use App\Models\Page;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 5 (URL-Erhalt / alte Pfade / Redirects): deckt die in routes/
 * web.php ergaenzten permanenten (301) Weiterleitungen der alten ".html"-
 * URLs sowie die beiden dynamischen LegacyUrlController-Routen ab. Prueft
 * außerdem, dass unbekannte alte URLs weiterhin echte 404en bleiben (kein
 * pauschaler Catch-all) und dass Redirect-Ziele nicht selbst nochmal
 * weiterleiten (keine Ketten).
 */
class UrlRedirectsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function statischeRedirects(): array
    {
        return [
            'Startseite' => ['/index.html', '/'],
            'Impressum' => ['/impressum.html', '/impressum'],
            'Datenschutz' => ['/datenschutz.html', '/datenschutz'],
            'Service' => ['/service.html', '/service'],
            'Aktuelles-Liste' => ['/aktuelles/index.html', '/aktuelles'],
            'Termine' => ['/termine/index.html', '/termine'],
            'FAQ' => ['/faq/index.html', '/faq'],
            'Downloads' => ['/downloads/index.html', '/downloads'],
            'Partner-Liste' => ['/partner/index.html', '/partner'],
            'Kontakt' => ['/kontakt/index.html', '/kontakt'],
            'Kreisjaegermeister' => ['/kreisjjaegermeister/index.html', '/kreisjjaegermeister'],
            'Jaeger-Uebersicht (Sonderfall)' => ['/jaeger/index.html', '/jaeger/uebersicht'],
            'Jaeger-Vorstand' => ['/jaeger/vorstand.html', '/jaeger/vorstand'],
            'Jaeger-Obleute' => ['/jaeger/obleute.html', '/jaeger/obleute'],
            'Jaeger-Hegeringe' => ['/jaeger/hegeringe.html', '/jaeger/hegeringe'],
            'Jaeger-Infomobil' => ['/jaeger/infomobil.html', '/jaeger/infomobil'],
            'Aufgaben-Hundeausbildung' => ['/aufgaben/hundeausbildung.html', '/aufgaben/hundeausbildung'],
            'Aufgaben-Jagdhundeschule' => ['/aufgaben/jagdhundeschule.html', '/aufgaben/jagdhundeschule'],
            'Verbraucher-Wildfleisch' => ['/verbraucher/wildfleisch.html', '/verbraucher/wildfleisch'],
            'Aktuelles-Beitrag (ohne stabile ID)' => ['/aktuelles/beitrag.html', '/aktuelles'],
        ];
    }

    #[DataProvider('statischeRedirects')]
    public function test_alte_html_url_leitet_permanent_auf_kanonische_route_weiter(string $alt, string $neu): void
    {
        $response = $this->get($alt);

        $response->assertStatus(301);
        $response->assertRedirect($neu);
    }

    public function test_seiten_index_html_mit_slug_leitet_auf_registry_seite_weiter(): void
    {
        Page::create(['section' => 'aufgaben', 'slug' => 'entschaedigungsfonds', 'titel' => 'Entschädigungsfonds', 'inhalt' => '<p>Test.</p>']);

        $response = $this->get('/seiten/index.html?s=entschaedigungsfonds');

        $response->assertStatus(301);
        $response->assertRedirect('/aufgaben/entschaedigungsfonds');
    }

    public function test_seiten_index_html_mit_unterseite_leitet_auf_dreisegment_url_weiter(): void
    {
        $eltern = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        Page::create(['section' => 'jaeger', 'parent_id' => $eltern->id, 'slug' => 'rotwild', 'titel' => 'Rotwild']);

        $response = $this->get('/seiten/index.html?s=rotwild');

        $response->assertStatus(301);
        $response->assertRedirect('/jaeger/hochwild/rotwild');
    }

    public function test_seiten_index_html_fuer_hundeausbildung_kurs_leitet_auf_eigenes_flaches_url_schema_weiter(): void
    {
        $hub = Page::create(['section' => 'hundeausbildung', 'slug' => 'hundeausbildung', 'titel' => 'Hundeausbildung']);
        Page::create(['section' => 'hundeausbildung', 'parent_id' => $hub->id, 'slug' => 'kurs-1-junghunde', 'titel' => 'Kurs 1', 'veroeffentlicht' => true]);

        $response = $this->get('/seiten/index.html?s=kurs-1-junghunde');

        $response->assertStatus(301);
        // NICHT "/hundeausbildung/hundeausbildung/kurs-1-junghunde" (die
        // generische Formel) - Hundeausbildung hat ein eigenes flaches
        // URL-Schema, siehe LegacyUrlController::seiten()-Kommentar.
        $response->assertRedirect('/aufgaben/jagdhundeschule/kurs-1-junghunde');
    }

    public function test_seiten_index_html_fuer_hundeausbildung_hub_leitet_auf_hub_seite_weiter(): void
    {
        Page::create(['section' => 'hundeausbildung', 'slug' => 'hundeausbildung', 'titel' => 'Hundeausbildung']);

        $response = $this->get('/seiten/index.html?s=hundeausbildung');

        $response->assertStatus(301);
        $response->assertRedirect('/aufgaben/hundeausbildung');
    }

    public function test_seiten_index_html_fuer_kreisjaegermeister_leitet_auf_singleton_seite_weiter(): void
    {
        Page::create(['section' => 'kreisjaegermeister', 'slug' => 'kreisjaegermeister', 'titel' => 'Kreisjägermeister']);

        $response = $this->get('/seiten/index.html?s=kreisjaegermeister');

        $response->assertStatus(301);
        // NICHT "/kreisjaegermeister/kreisjaegermeister" (die generische
        // Formel) - Kreisjaegermeister ist eine Singleton-Seite ohne
        // Slug-URL, siehe LegacyUrlController::seiten()-Kommentar.
        $response->assertRedirect('/kreisjjaegermeister');
    }

    public function test_seiten_index_html_ohne_treffer_bleibt_echte_404(): void
    {
        $response = $this->get('/seiten/index.html?s=gibt-es-nicht');

        $response->assertNotFound();
    }

    public function test_seiten_index_html_ohne_parameter_bleibt_echte_404(): void
    {
        $response = $this->get('/seiten/index.html');

        $response->assertNotFound();
    }

    public function test_partner_detail_html_mit_id_leitet_auf_neue_partner_url_weiter(): void
    {
        $response = $this->get('/partner/detail.html?id=pn-1234567890');

        $response->assertStatus(301);
        $response->assertRedirect('/partner/detail/pn-1234567890');
    }

    public function test_partner_detail_html_ohne_id_leitet_auf_partner_liste_weiter(): void
    {
        $response = $this->get('/partner/detail.html');

        $response->assertStatus(301);
        $response->assertRedirect('/partner');
    }

    public function test_redirect_ziele_sind_selbst_echte_200_seiten_ohne_weitere_weiterleitung(): void
    {
        Partner::create(['external_id' => 'pn-1234567890', 'name' => 'Testpartner', 'aktiv' => true]);
        Page::create(['section' => 'jaeger', 'slug' => 'uebersicht', 'titel' => 'Jäger-Übersicht']);

        foreach (['/impressum', '/datenschutz', '/aktuelles', '/partner', '/kontakt', '/jaeger/uebersicht'] as $ziel) {
            $response = $this->get($ziel);
            $response->assertOk();
        }

        $partnerDetail = $this->get('/partner/detail/pn-1234567890');
        $partnerDetail->assertOk();
    }

    #[DataProvider('unbekannteAlteUrls')]
    public function test_unbekannte_alte_urls_bleiben_echte_404_statt_umgeleitet(string $pfad): void
    {
        $response = $this->get($pfad);

        $response->assertNotFound();
    }

    /** @return array<string, array{0: string}> */
    public static function unbekannteAlteUrls(): array
    {
        return [
            // Das aus Google-Ergebnissen bekannte, historisch nicht mehr
            // rekonstruierbare Formulare-Sondermodul (siehe Abschlussbericht) -
            // bewusst KEIN Redirect, bleibt eine echte 404.
            'Formulare-Sondermodul' => ['/formulare/index.php?form_id=9452'],
            'Voellig unbekannter Pfad' => ['/dieser-pfad-hat-nie-existiert.html'],
            // Waffenboerse ist weiterhin nicht migriert (siehe Phase-6A-
            // Abschlussbericht) - bewusst nicht auf irgendeine Laravel-Seite
            // umgebogen. Hundeboerse selbst ist seit Phase 6A migriert (siehe
            // tests/Feature/Phase6A/HundeboerseTest.php fuer deren Redirects) -
            // hier daher NICHT mehr als "unbekannte URL" gelistet.
            'Waffenboerse-Detail (noch nicht migriert)' => ['/waffenboerse/detail.html?id=abc'],
        ];
    }
}
