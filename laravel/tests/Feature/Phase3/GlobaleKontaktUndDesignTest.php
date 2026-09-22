<?php

namespace Tests\Feature\Phase3;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 Nacharbeit (100%-Laravel-Architektur-Korrektur).
 *
 * Deckt zwei Korrekturen ab, die nach dem ersten Phase-3-Review noetig
 * wurden (siehe components/kontaktbox.blade.php und
 * App\View\Composers\DesignComposer):
 * 1. Die globale Kontakt-/Geschaeftsstellenbox (<x-kontaktbox>) laedt
 *    E-Mail/Telefon aus der settings-Tabelle (Gruppe "einstellungen")
 *    statt hart codierter Platzhalterwerte zu zeigen.
 * 2. Farb-/Schrift-Design kommt serverseitig aus der settings-Tabelle
 *    (Gruppe "design") ueber DesignComposer - kein
 *    "/api/content/design.json"-Request mehr, auf KEINER Seite.
 */
class GlobaleKontaktUndDesignTest extends TestCase
{
    use RefreshDatabase;

    private function seedEinstellungen(string $email, string $telefon): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'email', 'value' => $email]);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => $telefon]);
    }

    private function seedDesign(): void
    {
        Setting::create(['gruppe' => 'design', 'key' => 'farbe_gruen', 'value' => '#123456']);
        Setting::create(['gruppe' => 'design', 'key' => 'schrift_ueberschrift', 'value' => 'TestFont']);
    }

    public function test_globale_kontaktdaten_kommen_aus_der_datenbank_statt_hart_codiert(): void
    {
        // Bewusst ANDERE Werte als die frueher hart codierten
        // ("info@kjs-bad-segeberg.de" / "04551 / 12 34 56") - nur so zeigt
        // der Test zuverlaessig, dass die Box tatsaechlich aus der DB liest
        // und nicht zufaellig denselben String hart codiert enthaelt.
        //
        // Hinweis: die site-weite Topbar (components/site-header.blade.php,
        // Phase 1, NICHT Teil dieser Korrektur) zeigt weiterhin denselben
        // statischen Platzhalter als Lade-Zustand fuer ihre eigene, per JS
        // zur Laufzeit befuellte Anzeige (siehe dortiger Kommentar) - das
        // ist hier bewusst kein Pruefgegenstand. Die Topbar allein liefert
        // fuer die E-Mail ZWEI Vorkommen (href="mailto:..." UND sichtbarer
        // Linktext) und fuer die Telefonnummer EIN Vorkommen (die Topbar
        // verlinkt "tel:+494551123456", zeigt als Text aber "04551 / 12 34
        // 56") - je ein weiteres Vorkommen waere die alte hart codierte
        // Kontaktbox.
        $this->seedEinstellungen('kontakt@testverein.example', '05551 999999');
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get('/jaeger/hochwild');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('kontakt@testverein.example');
        $response->assertSee('05551 999999');
        $this->assertSame(2, substr_count($html, 'info@kjs-bad-segeberg.de'), 'info@kjs-bad-segeberg.de darf nur noch in der Topbar (Phase 1) vorkommen, nicht mehr in der Kontaktbox.');
        $this->assertSame(1, substr_count($html, '04551 / 12 34 56'), '04551 / 12 34 56 darf nur noch in der Topbar (Phase 1) vorkommen, nicht mehr in der Kontaktbox.');
    }

    public function test_fehlende_globale_kontaktdaten_zeigen_keinen_platzhalter(): void
    {
        // Keine Settings der Gruppe "einstellungen" angelegt - die
        // Kontaktbox (<x-kontaktbox>, class="contact-box") darf dann gar
        // nicht gerendert werden (weder mit Platzhalter noch leer).
        Page::create(['section' => 'aufgaben', 'slug' => 'jagdhorn', 'titel' => 'Jagdhorn']);

        $response = $this->get('/aufgaben/jagdhorn');

        $response->assertOk();
        $response->assertDontSee('class="contact-box"', false);
    }

    public function test_design_werte_kommen_serverseitig_aus_der_datenbank(): void
    {
        $this->seedDesign();
        Page::create(['section' => 'verbraucher', 'slug' => 'wildfleisch', 'titel' => 'Wildfleisch']);

        $response = $this->get('/verbraucher/wildfleisch');

        $response->assertOk();
        $response->assertSee('--green-main: #123456', false);
        $response->assertSee('"TestFont", serif', false);
    }

    /**
     * Auftrag: "keine Seite enthaelt /api/content/design.json" UND "keine
     * neuen Phase-3-Seiten enthalten /api/content/*.json" - deckt je einen
     * repraesentativen Fall pro neuem Controller ab.
     */
    public function test_keine_phase3_seite_nutzt_die_design_json_oder_sonstige_json_api(): void
    {
        $this->seedEinstellungen('kontakt@testverein.example', '05551 999999');
        $this->seedDesign();

        $hub = Page::create(['section' => 'hundeausbildung', 'slug' => 'hundeausbildung', 'titel' => 'Jagdhundeschule']);
        Page::create(['section' => 'hundeausbildung', 'parent_id' => $hub->id, 'slug' => 'kurs-1', 'titel' => 'Kurs 1', 'veroeffentlicht' => true]);
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        Page::create(['section' => 'weitere', 'slug' => 'jagdhornblasen', 'titel' => 'Jagdhornblasen']);

        foreach ([
            '/jaeger/hochwild',
            '/weitere/jagdhornblasen',
            '/aufgaben/hundeausbildung',
            '/aufgaben/jagdhundeschule',
            '/aufgaben/jagdhundeschule/kurs-1',
        ] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertDontSee('/api/content/design.json');
            $response->assertDontSee('/api/content/');
        }
    }
}
