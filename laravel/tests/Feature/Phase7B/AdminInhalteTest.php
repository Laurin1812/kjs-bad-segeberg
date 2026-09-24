<?php

namespace Tests\Feature\Phase7B;

use App\Models\Download;
use App\Models\Page;
use App\Models\PageLink;
use App\Models\User;
use App\Support\ContentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7B (Admin-Modul "Inhalte/Seiten"): deckt den neuen, server-
 * gerenderten Bereich unter "/admin/inhalte" ab (Http\Controllers\Admin\
 * InhalteController) - Liste, Bearbeiten-Formular und Speichern. Die
 * BESTEHENDE JSON-Schreib-API unter "/api/admin/content/*" (admin.js) bleibt
 * unveraendert von tests/Feature/Admin/* abgedeckt; siehe
 * test_json_admin_api_schreibweg_regressiert_nicht() unten fuer den
 * expliziten Nachweis, dass Phase 7B (PageUpdater-Extraktion) diesen Weg
 * nicht veraendert hat.
 */
class AdminInhalteTest extends TestCase
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

    // -----------------------------------------------------------------
    // Zugriffsschutz (Teil 9)
    // -----------------------------------------------------------------

    public function test_seitenliste_ohne_anmeldung_leitet_zur_login_seite_um(): void
    {
        $response = $this->get(route('admin.inhalte.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_seitenliste_fuer_admin_erreichbar(): void
    {
        Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.index'));

        $response->assertOk();
    }

    public function test_seitenliste_fuer_nicht_admin_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $response = $this->get(route('admin.inhalte.index'));

        $response->assertForbidden();
    }

    public function test_bearbeiten_ohne_anmeldung_leitet_zur_login_seite_um(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);

        $response = $this->get(route('admin.inhalte.bearbeiten', $page));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_bearbeiten_fuer_nicht_admin_403(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->nichtAdmin(), 'web');

        $response = $this->get(route('admin.inhalte.bearbeiten', $page));

        $response->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste zeigt echte Daten (Teil 2 / Teil 9)
    // -----------------------------------------------------------------

    public function test_seitenliste_zeigt_echte_seiten_aus_der_datenbank(): void
    {
        $hochwild = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        Page::create(['section' => 'jaeger', 'parent_id' => $hochwild->id, 'slug' => 'ansprache', 'titel' => 'Wildansprache']);
        Page::create(['section' => 'weitere', 'slug' => 'jagdhornblasen', 'titel' => 'Jagdhornblasen']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.index'));

        $response->assertOk();
        $response->assertSee('Hochwild');
        $response->assertSee('Wildansprache');
        $response->assertSee('Jagdhornblasen');
    }

    public function test_seitenliste_zeigt_keine_kreisjaegermeister_seiten(): void
    {
        // "hundeausbildung" ist seit Phase 7H bewusst Teil dieser Liste
        // (siehe InhalteController::IN_SCOPE_SECTIONS) - eigener
        // Regressionsnachweis dafuer in Tests/Feature/Phase7H/
        // AdminHundeausbildungTest.php, u.a. test_seitenliste_zeigt_jetzt_auch_hundeausbildung_seiten().
        Page::create(['section' => 'kreisjaegermeister', 'slug' => 'kreisjaegermeister', 'titel' => 'Kreisjägermeister-Grußwort']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.index'));

        $response->assertOk();
        $response->assertDontSee('Kreisjägermeister-Grußwort');
    }

    // -----------------------------------------------------------------
    // Bearbeiten laedt bestehende Werte (Teil 3 / Teil 9)
    // -----------------------------------------------------------------

    public function test_bearbeiten_seite_laedt_bestehende_werte(): void
    {
        $page = Page::create([
            'section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild',
            'intro' => '<p>Einleitung zum Hochwild.</p>', 'kontakt_name' => 'Max Mustermann',
        ]);
        Download::create(['owner_type' => $page->getMorphClass(), 'owner_id' => $page->id, 'titel' => 'Merkblatt', 'pfad' => '/downloads/merkblatt.pdf', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.bearbeiten', $page));

        $response->assertOk();
        $response->assertSee('value="Hochwild"', false);
        $response->assertSee('Einleitung zum Hochwild.', false);
        $response->assertSee('Max Mustermann', false);
        $response->assertSee('/downloads/merkblatt.pdf', false);
    }

    public function test_bearbeiten_fuer_kreisjaegermeister_seite_404(): void
    {
        // "hundeausbildung" ist seit Phase 7H bewusst NICHT mehr Teil dieser
        // 404-Regel (siehe InhalteController::IN_SCOPE_SECTIONS) - eigener
        // Regressionsnachweis dafuer in Tests/Feature/Phase7H/
        // AdminHundeausbildungTest.php.
        $kjm = Page::create(['section' => 'kreisjaegermeister', 'slug' => 'kreisjaegermeister', 'titel' => 'KJM']);
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.inhalte.bearbeiten', $kjm))->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Speichern (Teil 4 / Teil 9)
    // -----------------------------------------------------------------

    public function test_gueltiges_update_speichert(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild (überarbeitet)',
            'intro' => '<p>Neuer Einleitungstext.</p>',
            'kontakt_email' => 'kontakt@example.test',
        ]);

        $response->assertRedirect(route('admin.inhalte.bearbeiten', $page));
        $response->assertSessionHas('status');
        $page->refresh();
        $this->assertSame('Hochwild (überarbeitet)', $page->titel);
        $this->assertSame('<p>Neuer Einleitungstext.</p>', $page->intro);
        $this->assertSame('kontakt@example.test', $page->kontakt_email);
    }

    public function test_ungueltiges_update_wird_abgelehnt(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.inhalte.update', $page), [
            'titel' => '',
        ]);

        $response->assertSessionHasErrors('titel');
        $page->refresh();
        $this->assertSame('Hochwild', $page->titel, 'Seite darf bei ungueltigem Payload nicht veraendert werden.');
    }

    public function test_ungueltige_kontakt_email_wird_abgelehnt(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild',
            'kontakt_email' => 'keine-email',
        ]);

        $response->assertSessionHasErrors('kontakt_email');
    }

    public function test_geschuetzte_felder_koennen_nicht_ueber_das_formular_veraendert_werden(): void
    {
        $andereSeite = Page::create(['section' => 'aufgaben', 'slug' => 'jagdhorn', 'titel' => 'Jagdhorn']);
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        // section/parent_id/slug/id/sortierung sind bewusst NICHT Teil der
        // Formular-Feldliste in InhalteController::update() (siehe dortiges
        // $request->only(...)) - selbst wenn ein manipuliertes Formular sie
        // mitschickt, duerfen sie keine Wirkung haben (Auftrag Phase 6 Punkt
        // 12 "keine Mass-Assignment-Luecke", hier fuer den Blade-Weg erneut
        // geprueft).
        $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild',
            'section' => 'weitere',
            'slug' => 'jagdhorn',
            'parent_id' => $andereSeite->id,
            'id' => $andereSeite->id,
            'sortierung' => 999,
        ]);

        $page->refresh();
        $this->assertSame('jaeger', $page->section);
        $this->assertSame('hochwild', $page->slug);
        $this->assertNull($page->parent_id);
        $this->assertNotEquals($andereSeite->id, $page->id);
    }

    public function test_downloads_galerie_und_linkliste_werden_gespeichert(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild',
            'downloads' => [['titel' => 'Merkblatt', 'datei' => '/downloads/merkblatt.pdf', 'vorschau' => '']],
            'galerie' => [['bild' => '/images/galerie/1.jpg', 'titel' => 'Ansitz']],
            'linkliste' => [['titel' => 'Landesjagdverband', 'url' => 'https://ljv-sh.de']],
        ])->assertRedirect();

        $this->assertDatabaseHas('downloads', ['owner_id' => $page->id, 'pfad' => '/downloads/merkblatt.pdf']);
        $this->assertDatabaseHas('galerie_bilder', ['owner_id' => $page->id, 'pfad' => '/images/galerie/1.jpg']);
        $this->assertDatabaseHas('page_links', ['page_id' => $page->id, 'href' => 'https://ljv-sh.de']);
    }

    public function test_veralteter_expected_version_wird_mit_fehler_abgelehnt(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        // Simuliert eine zwischenzeitliche Aenderung ueber die JSON-API (admin.js).
        ContentVersioning::bump('page:jaeger:hochwild');
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild (Konflikt)',
            'expected_version' => 0,
        ]);

        $response->assertSessionHasErrors('titel');
        $page->refresh();
        $this->assertSame('Hochwild', $page->titel);
    }

    // -----------------------------------------------------------------
    // Seitentypen (Teil 3 / Teil 9): feste Seite, Registry-Zusatzseite,
    // Unterseite, "Weitere Themen"-Seite - alle vier ueber dieselbe Route/
    // denselben Controller editierbar.
    // -----------------------------------------------------------------

    public function test_registry_zusatzseite_ist_editierbar(): void
    {
        // 'sonderaktion' ist KEIN fixedSlug von 'jaeger' - also eine per
        // Registry hinzugefuegte Zusatzseite, keine feste Vorlagen-Seite.
        $page = Page::create(['section' => 'jaeger', 'slug' => 'sonderaktion', 'titel' => 'Sonderaktion']);
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.inhalte.bearbeiten', $page))->assertOk();
        $this->put(route('admin.inhalte.update', $page), ['titel' => 'Sonderaktion 2026'])->assertRedirect();
        $this->assertSame('Sonderaktion 2026', $page->fresh()->titel);
    }

    public function test_unterseite_ist_editierbar_und_zeigt_menue_bezeichnung_feld(): void
    {
        $parent = Page::create(['section' => 'verbraucher', 'slug' => 'wildfleisch', 'titel' => 'Wildfleisch']);
        $sub = Page::create(['section' => 'verbraucher', 'parent_id' => $parent->id, 'slug' => 'rezepte', 'titel' => 'Rezepte', 'nav_label' => 'Rezepte']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.bearbeiten', $sub));

        $response->assertOk();
        $response->assertSee('name="nav_label"', false);

        $this->put(route('admin.inhalte.update', $sub), ['titel' => 'Rezepte', 'nav_label' => 'Leckere Rezepte'])->assertRedirect();
        $this->assertSame('Leckere Rezepte', $sub->fresh()->nav_label);
    }

    public function test_feste_seite_zeigt_kein_menue_bezeichnung_feld(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.inhalte.bearbeiten', $page));

        $response->assertOk();
        $response->assertDontSee('name="nav_label"', false);
    }

    public function test_weitere_themen_seite_ist_editierbar(): void
    {
        $page = Page::create(['section' => 'weitere', 'slug' => 'jagdhornblasen', 'titel' => 'Jagdhornblasen']);
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.inhalte.bearbeiten', $page))->assertOk();
        $this->put(route('admin.inhalte.update', $page), ['titel' => 'Jagdhornblasen (neu)'])->assertRedirect();
        $this->assertSame('Jagdhornblasen (neu)', $page->fresh()->titel);
    }

    public function test_mitglied_werden_zeigt_antrag_url_feld_andere_seiten_nicht(): void
    {
        $mitgliedWerden = Page::create(['section' => 'jaeger', 'slug' => 'mitglied-werden', 'titel' => 'Mitglied werden']);
        $hochwild = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.inhalte.bearbeiten', $mitgliedWerden))->assertSee('name="antrag_url"', false);
        $this->get(route('admin.inhalte.bearbeiten', $hochwild))->assertDontSee('name="antrag_url"', false);
    }

    // -----------------------------------------------------------------
    // Preservation-Fix (Nutzer-Feedback nach Erstauslieferung): ein
    // partielles Blade-Update darf ausschliesslich Felder veraendern, die
    // das Formular tatsaechlich verwaltet - alle anderen bestehenden
    // Page-Werte muessen 1:1 erhalten bleiben (siehe InhalteController::
    // update()/currentFieldValues()).
    // -----------------------------------------------------------------

    public function test_partielles_blade_update_erhaelt_alle_vom_formular_nicht_verwalteten_felder(): void
    {
        $page = Page::create([
            'section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild',
            'intro' => '<p>Alte Einleitung.</p>',
            'kontakt_telefon' => '04551 123456',
            'vorschaubild' => '/images/vorschau/hochwild.jpg',
            'kurzbeschreibung' => 'Kurze Beschreibung des Hochwilds.',
            'bild_flat' => true,
            'gruppe' => 'wild',
            'in_navigation' => false,
            'veroeffentlicht' => false,
        ]);
        $this->actingAs($this->admin(), 'web');

        // Formular sendet bewusst NUR titel + intro - genau wie ein echter
        // Browser-Submit dieses Formulars (die sieben oben gesetzten Felder
        // sind entweder in diesem Blade-Modul gar nicht editierbar, oder -
        // wie bei Hundeausbildung/Jagdhundeschule - schlicht nicht Teil
        // dieses Formulars, siehe InhalteController-Klassenkommentar).
        $response = $this->put(route('admin.inhalte.update', $page), [
            'titel' => 'Hochwild (überarbeitet)',
            'intro' => '<p>Neue Einleitung.</p>',
        ]);

        $response->assertRedirect(route('admin.inhalte.bearbeiten', $page));
        $page->refresh();

        $this->assertSame('Hochwild (überarbeitet)', $page->titel);
        $this->assertSame('<p>Neue Einleitung.</p>', $page->intro);

        $this->assertFalse($page->in_navigation, 'in_navigation=false muss erhalten bleiben.');
        $this->assertFalse($page->veroeffentlicht, 'veroeffentlicht=false muss erhalten bleiben.');
        $this->assertSame('04551 123456', $page->kontakt_telefon, 'kontakt_telefon muss erhalten bleiben.');
        $this->assertSame('/images/vorschau/hochwild.jpg', $page->vorschaubild, 'vorschaubild muss erhalten bleiben.');
        $this->assertSame('Kurze Beschreibung des Hochwilds.', $page->kurzbeschreibung, 'kurzbeschreibung muss erhalten bleiben.');
        $this->assertTrue($page->bild_flat, 'bild_flat=true muss erhalten bleiben.');
        $this->assertSame('wild', $page->gruppe, 'gruppe muss erhalten bleiben.');
    }

    public function test_partielles_blade_update_erhaelt_bedingt_gerenderte_felder_wenn_nicht_zutreffend(): void
    {
        // 'hochwild' zeigt weder das antrag_url- noch das Hundeboerse-CTA-Feld
        // (nur 'mitglied-werden' bzw. 'hundevermittlung' tun das) - trotzdem
        // vorbelegte Werte muessen erhalten bleiben, falls sie z.B. per JSON-
        // API oder Altdaten-Import vorher gesetzt wurden.
        $page = Page::create([
            'section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild',
            'antrag_url' => 'https://example.test/alter-antrag',
            'hundeboerse_cta_titel' => 'Alter CTA-Titel',
            'hundeboerse_cta_text' => 'Alter CTA-Text',
            'hundeboerse_cta_button' => 'Alter Button',
        ]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.inhalte.update', $page), ['titel' => 'Hochwild'])->assertRedirect();

        $page->refresh();
        $this->assertSame('https://example.test/alter-antrag', $page->antrag_url);
        $this->assertSame('Alter CTA-Titel', $page->hundeboerse_cta_titel);
        $this->assertSame('Alter CTA-Text', $page->hundeboerse_cta_text);
        $this->assertSame('Alter Button', $page->hundeboerse_cta_button);
    }

    public function test_partielles_blade_update_auf_unterseite_loescht_bestehende_linkliste_nicht(): void
    {
        // Nur Top-Level-Seiten (parent_id === null) zeigen die Linkliste-
        // Formularzeilen (siehe edit(), $hatUnterseitenSystem) - eine
        // Unterseite sendet das Feld also nie. PageUpdater::replacePageLinks()
        // loescht aber intern IMMER zuerst - ohne den Preservation-Fix in
        // InhalteController::update() wuerden hier bestehende Links verloren
        // gehen, obwohl die UI sie nie angezeigt hat.
        $parent = Page::create(['section' => 'verbraucher', 'slug' => 'wildfleisch', 'titel' => 'Wildfleisch']);
        $sub = Page::create(['section' => 'verbraucher', 'parent_id' => $parent->id, 'slug' => 'rezepte', 'titel' => 'Rezepte']);
        PageLink::create(['page_id' => $sub->id, 'label' => 'Externe Rezeptsammlung', 'href' => 'https://example.test/rezepte', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.inhalte.update', $sub), ['titel' => 'Rezepte (neu)'])->assertRedirect();

        $this->assertDatabaseHas('page_links', [
            'page_id' => $sub->id,
            'href' => 'https://example.test/rezepte',
            'label' => 'Externe Rezeptsammlung',
        ]);
        $this->assertSame(1, PageLink::where('page_id', $sub->id)->count());
    }

    // -----------------------------------------------------------------
    // Kein Regress der bestehenden JSON-Admin-API (Teil 4 / Teil 9)
    // -----------------------------------------------------------------

    public function test_json_admin_api_schreibweg_regressiert_nicht(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $admin = $this->admin();
        $this->actingAs($admin, 'web');

        // Identischer Aufruf wie admin.js' apiPutLaravel() gegen den
        // bestehenden Endpunkt - siehe routes/api.php "content/{section}/
        // {slug}.json" -> AdminPageController::festeSeite(), die seit Phase
        // 7B intern PageUpdater::saveWithVersionCheck() aufruft.
        $response = $this->putJson('/api/admin/content/jaeger/hochwild.json', [
            'data' => ['titel' => 'Hochwild (per JSON-API)'],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('Hochwild (per JSON-API)', $page->fresh()->titel);
    }

    // -----------------------------------------------------------------
    // Oeffentliche Seite zeigt neue Daten (Teil 9)
    // -----------------------------------------------------------------

    public function test_oeffentliche_seite_zeigt_nach_blade_update_die_neuen_daten(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.inhalte.update', $page), ['titel' => 'Hochwild ganz neu'])->assertRedirect();

        $oeffentlich = $this->get('/jaeger/hochwild');
        $oeffentlich->assertOk();
        $oeffentlich->assertSee('Hochwild ganz neu');
    }

    // -----------------------------------------------------------------
    // CSRF (Teil 9)
    // -----------------------------------------------------------------

    public function test_update_ist_csrf_geschuetzt(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->call('PUT', route('admin.inhalte.update', $page), ['titel' => 'Sollte nicht gespeichert werden']);
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertSame('Hochwild', $page->fresh()->titel);
    }

    // -----------------------------------------------------------------
    // Keine JSON-Runtime noetig (Teil 9): Liste und Bearbeiten-Formular
    // sind normale, server-gerenderte HTML-Seiten mit klassischem
    // <form method="POST">, keine XHR/JSON-Endpunkte.
    // -----------------------------------------------------------------

    public function test_liste_und_formular_sind_gewoehnliches_server_gerendertes_html(): void
    {
        $page = Page::create(['section' => 'jaeger', 'slug' => 'hochwild', 'titel' => 'Hochwild']);
        $this->actingAs($this->admin(), 'web');

        $liste = $this->get(route('admin.inhalte.index'));
        $liste->assertOk();
        $this->assertStringContainsString('text/html', $liste->headers->get('Content-Type'));

        $formular = $this->get(route('admin.inhalte.bearbeiten', $page));
        $formular->assertOk();
        $formular->assertSee('<form method="POST"', false);
        $formular->assertSee('name="_method" value="PUT"', false);
    }

    // -----------------------------------------------------------------
    // Sidebar-Navigation (Teil 8)
    // -----------------------------------------------------------------

    public function test_sidebar_inhalte_seiten_ist_jetzt_ein_echter_link(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.inhalte.index').'"', false);
    }
}
