<?php

namespace Tests\Feature\Phase7H;

use App\Models\Page;
use App\Models\User;
use App\Support\ContentVersioning;
use App\Support\PageUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7H (Admin-Modul "Hundeausbildung/Jagdhundeschule"): deckt den neuen,
 * server-gerenderten Bereich unter "/admin/hundeausbildung" ab
 * (Http\Controllers\Admin\HundeausbildungController) sowie die im Zuge
 * dieser Phase vorgenommene Erweiterung von Http\Controllers\Admin\
 * InhalteController::IN_SCOPE_SECTIONS um 'hundeausbildung'.
 *
 * Kurzanalyse-Ergebnis (siehe HundeausbildungController-Klassenkommentar):
 * Hundeausbildung ist KEIN eigenstaendiges Datenmodell, sondern eine weitere
 * Page-Familie (section = 'hundeausbildung', Hub + Kurs-Unterseiten). Dieser
 * Controller bietet daher AUSSCHLIESSLICH eine gefilterte Liste - Bearbeiten
 * laeuft ueber die bereits bestehenden, in Phase 7B getesteten Routen
 * admin.inhalte.bearbeiten/admin.inhalte.update (siehe dortige
 * Tests/Feature/Phase7B/AdminInhalteTest.php fuer die generische
 * Bearbeiten-/Speichern-Abdeckung, hier NICHT dupliziert).
 */
class AdminHundeausbildungTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['roles' => ['admin'], 'permissions' => []]);
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create(['roles' => [], 'permissions' => []]);
    }

    /** @return array{0: Page, 1: Page} [$hub, $kurs] */
    private function hubMitKurs(array $kursOverrides = []): array
    {
        $hub = Page::create([
            'section' => 'hundeausbildung',
            'slug' => 'hundeausbildung',
            'titel' => 'Jagdhundeschule',
            'veroeffentlicht' => true,
        ]);
        $kurs = Page::create(array_merge([
            'section' => 'hundeausbildung',
            'parent_id' => $hub->id,
            'slug' => 'kurs-1-junghunde',
            'titel' => 'Kurs 1: Junghunde',
            'veroeffentlicht' => true,
            'sortierung' => 0,
        ], $kursOverrides));

        return [$hub, $kurs];
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.hundeausbildung.index'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.hundeausbildung.index'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste
    // -----------------------------------------------------------------

    public function test_index_zeigt_hub_und_kurse(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();

        $response = $this->get(route('admin.hundeausbildung.index'));

        $response->assertOk();
        $response->assertSee('Jagdhundeschule');
        $response->assertSee('Kurs 1: Junghunde');
    }

    public function test_fremde_seiten_erscheinen_nicht_in_der_liste(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->hubMitKurs();
        Page::create(['section' => 'aufgaben', 'slug' => 'schiessen', 'titel' => 'Schießwesen']);
        Page::create(['section' => 'kreisjaegermeister', 'slug' => 'kreisjaegermeister', 'titel' => 'Kreisjägermeister-Grußwort']);

        $response = $this->get(route('admin.hundeausbildung.index'));

        $response->assertOk();
        $response->assertDontSee('Schießwesen');
        $response->assertDontSee('Kreisjägermeister-Grußwort');
    }

    public function test_bearbeiten_link_zeigt_auf_bestehende_inhalte_route(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();

        $response = $this->get(route('admin.hundeausbildung.index'));

        $response->assertOk();
        $response->assertSee(route('admin.inhalte.bearbeiten', $hub), false);
        $response->assertSee(route('admin.inhalte.bearbeiten', $kurs), false);
    }

    public function test_oeffnen_link_zeigt_auf_echte_oeffentliche_route(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();

        $response = $this->get(route('admin.hundeausbildung.index'));

        $response->assertOk();
        $response->assertSee(route('hundeausbildung.hub'), false);
        $response->assertSee(route('hundeausbildung.show', $kurs->slug), false);
    }

    public function test_sidebar_link_ist_aktiv(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.hundeausbildung.index'));

        $response->assertOk();
        $response->assertSee('Hundeausbildung');
    }

    // -----------------------------------------------------------------
    // Wiederverwendung der bestehenden Seitenbearbeitung (Phase 7B)
    // -----------------------------------------------------------------

    public function test_hub_ist_ueber_bestehende_inhalte_bearbeiten_route_erreichbar(): void
    {
        // Bis Phase 7H gab es hierfuer bewusst ein 404 (siehe Tests/Feature/
        // Phase7B/AdminInhalteTest.php, damalige test_bearbeiten_fuer_
        // hundeausbildung_oder_kreisjaegermeister_seite_404()) - seit dieser
        // Phase ist 'hundeausbildung' Teil von InhalteController::
        // IN_SCOPE_SECTIONS.
        $this->actingAs($this->admin(), 'web');
        [$hub] = $this->hubMitKurs();

        $response = $this->get(route('admin.inhalte.bearbeiten', $hub));

        $response->assertOk();
        $response->assertSee('value="Jagdhundeschule"', false);
    }

    public function test_kurs_kann_ueber_bestehende_inhalte_route_gespeichert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();
        $versionVorher = ContentVersioning::current(PageUpdater::versionSectionFor($kurs));

        $response = $this->put(route('admin.inhalte.update', $kurs), [
            'titel' => 'Kurs 1: Junghunde (überarbeitet)',
            'expected_version' => $versionVorher,
        ]);

        $response->assertRedirect(route('admin.inhalte.bearbeiten', $kurs));
        $this->assertDatabaseHas('pages', ['id' => $kurs->id, 'titel' => 'Kurs 1: Junghunde (überarbeitet)']);
    }

    public function test_kachel_vorschau_felder_werden_nur_fuer_kurse_angezeigt(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();

        $hubResponse = $this->get(route('admin.inhalte.bearbeiten', $hub));
        $hubResponse->assertOk();
        $hubResponse->assertDontSee('name="vorschaubild"', false);
        $hubResponse->assertDontSee('name="kurzbeschreibung"', false);
        $hubResponse->assertDontSee('name="bild_flat"', false);

        $kursResponse = $this->get(route('admin.inhalte.bearbeiten', $kurs));
        $kursResponse->assertOk();
        $kursResponse->assertSee('name="vorschaubild"', false);
        $kursResponse->assertSee('name="kurzbeschreibung"', false);
        $kursResponse->assertSee('name="bild_flat"', false);
    }

    public function test_kachel_vorschau_felder_werden_gespeichert_und_bleiben_bei_teil_update_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        [$hub, $kurs] = $this->hubMitKurs();
        $versionVorher = ContentVersioning::current(PageUpdater::versionSectionFor($kurs));

        $this->put(route('admin.inhalte.update', $kurs), [
            'titel' => $kurs->titel,
            'vorschaubild' => '/images/kurs-1.jpg',
            'kurzbeschreibung' => 'Der erste Kurs für junge Hunde.',
            'bild_flat' => '1',
            'expected_version' => $versionVorher,
        ]);

        $kurs->refresh();
        $this->assertSame('/images/kurs-1.jpg', $kurs->vorschaubild);
        $this->assertSame('Der erste Kurs für junge Hunde.', $kurs->kurzbeschreibung);
        $this->assertTrue($kurs->bild_flat);

        // Teil-Update ohne diese Felder (z.B. weil ein aelterer Tab noch
        // offen war) darf sie nicht stillschweigend loeschen/zuruecksetzen.
        $versionNachher = ContentVersioning::current(PageUpdater::versionSectionFor($kurs));
        $this->put(route('admin.inhalte.update', $kurs), [
            'titel' => 'Kurs 1: Junghunde',
            'expected_version' => $versionNachher,
        ]);

        $kurs->refresh();
        $this->assertSame('/images/kurs-1.jpg', $kurs->vorschaubild);
        $this->assertSame('Der erste Kurs für junge Hunde.', $kurs->kurzbeschreibung);
        $this->assertTrue($kurs->bild_flat, 'bild_flat darf durch ein Teil-Update ohne dieses Feld nicht auf false zurückfallen.');
    }

    // -----------------------------------------------------------------
    // ContentVersioning-Schluessel (Korrektheits-Nachweis Phase 7H)
    // -----------------------------------------------------------------

    public function test_versionsschluessel_stimmt_mit_dem_bestehenden_json_schreibweg_ueberein(): void
    {
        // PageUpdater::versionSectionFor() muss fuer Hundeausbildungs-Seiten
        // exakt denselben Schluessel liefern wie Api\Admin\
        // AdminPageController::versionSection('hundeausbildung', 'hub'|slug)
        // - siehe PageUpdater-Klassenkommentar. Ohne den Phase-7H-Sonderfall
        // wuerde hier "page:hundeausbildung:hundeausbildung" (Hub) bzw.
        // "page:sub:hundeausbildung:kurs-1-junghunde" (Kurs) herauskommen -
        // beides NICHT das, was der bestehende JSON-Weg verwendet.
        [$hub, $kurs] = $this->hubMitKurs();

        $this->assertSame('page:hundeausbildung:hub', PageUpdater::versionSectionFor($hub));
        $this->assertSame('page:hundeausbildung:kurs-1-junghunde', PageUpdater::versionSectionFor($kurs));
    }

    // -----------------------------------------------------------------
    // Regressionsschutz: öffentliche Hundeausbildungs-Seiten
    // -----------------------------------------------------------------

    public function test_oeffentliche_hundeausbildungsseiten_bleiben_regressionsfrei(): void
    {
        [$hub, $kurs] = $this->hubMitKurs();

        $hubResponse = $this->get('/aufgaben/hundeausbildung');
        $hubResponse->assertOk();
        $hubResponse->assertSee('Jagdhundeschule');

        $indexResponse = $this->get('/aufgaben/jagdhundeschule');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Kurs 1: Junghunde');

        $showResponse = $this->get('/aufgaben/jagdhundeschule/kurs-1-junghunde');
        $showResponse->assertOk();
        $showResponse->assertSee('Kurs 1: Junghunde');
    }

    public function test_bestehende_phase7b_seitenbearbeitung_bleibt_fuer_andere_sections_unveraendert_nutzbar(): void
    {
        $this->actingAs($this->admin(), 'web');
        $seite = Page::create(['section' => 'aufgaben', 'slug' => 'schiessen', 'titel' => 'Schießwesen']);

        $response = $this->get(route('admin.inhalte.bearbeiten', $seite));

        $response->assertOk();
        $response->assertSee('value="Schießwesen"', false);
        // Die Kachel-Vorschau-Felder sind eine Hundeausbildung-Besonderheit
        // und duerfen fuer andere Sections nicht auftauchen.
        $response->assertDontSee('name="vorschaubild"', false);
    }
}
