<?php

namespace Tests\Feature\Phase7C;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Download;
use App\Models\GalerieBild;
use App\Models\User;
use App\Support\ContentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7C (Admin-Modul "Aktuelles"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/aktuelles" ab (Http\Controllers\Admin\
 * AktuellesController) - Liste, Anlegen-/Bearbeiten-Formular, Speichern,
 * Loeschen. Die BESTEHENDE JSON-Schreib-API unter
 * "/api/admin/content/aktuelles.json" (admin.js, Api\Admin\
 * AdminListController::aktuelles()) bleibt unveraendert nutzbar - siehe
 * test_json_admin_api_*() unten, die fuer diesen Endpunkt bislang komplett
 * FEHLENDE Regressionsabdeckung ergaenzen (Auftrag Teil 13: "neuer
 * API-Regressionstest, da keiner existiert").
 */
class AdminAktuellesTest extends TestCase
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

    private function beitrag(array $overrides = []): Beitrag
    {
        return Beitrag::create(array_merge([
            'typ' => 'aktuelles',
            'slug' => 'test-beitrag',
            'legacy_index' => 1,
            'titel' => 'Test-Beitrag',
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz (Teil 10): admin.web schuetzt weiterhin ausschliesslich
    // ueber die Rolle "admin" - kein Ausbau auf einzelne Modul-Rechte in
    // dieser Phase (siehe AktuellesController-Klassenkommentar
    // "Berechtigungen").
    // -----------------------------------------------------------------

    public function test_liste_ohne_anmeldung_leitet_zur_login_seite_um(): void
    {
        $this->get(route('admin.aktuelles.index'))->assertRedirect(route('admin.login'));
    }

    public function test_liste_fuer_nicht_admin_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.aktuelles.index'))->assertForbidden();
    }

    public function test_bearbeiten_ohne_anmeldung_leitet_zur_login_seite_um(): void
    {
        $beitrag = $this->beitrag();

        $this->get(route('admin.aktuelles.bearbeiten', $beitrag))->assertRedirect(route('admin.login'));
    }

    public function test_bearbeiten_fuer_nicht_admin_403(): void
    {
        $beitrag = $this->beitrag();
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.aktuelles.bearbeiten', $beitrag))->assertForbidden();
    }

    public function test_neu_store_und_loeschen_fuer_nicht_admin_403(): void
    {
        $beitrag = $this->beitrag();
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.aktuelles.neu'))->assertForbidden();
        $this->post(route('admin.aktuelles.store'), ['titel' => 'Neu'])->assertForbidden();
        $this->put(route('admin.aktuelles.update', $beitrag), ['titel' => 'Geaendert'])->assertForbidden();
        $this->delete(route('admin.aktuelles.loeschen', $beitrag))->assertForbidden();
    }

    public function test_editor_mit_aktuelles_berechtigung_aber_ohne_admin_rolle_bekommt_trotzdem_403(): void
    {
        // Auftrag Teil 10: die "aktuelles"-Berechtigung (admin.js'
        // PERM_BY_KEY, EnsureIdentityPermission) ist NUR fuer die JSON-API
        // dokumentiert/relevant - der neue Blade-Admin prueft ausschliesslich
        // die Rolle "admin" (EnsureAdminWebSession), Phase 8 kann das spaeter
        // erweitern, hier aber noch nicht.
        $editor = User::factory()->create(['roles' => [], 'permissions' => ['aktuelles']]);
        $this->actingAs($editor, 'web');

        $this->get(route('admin.aktuelles.index'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste zeigt echte Daten (Teil 3)
    // -----------------------------------------------------------------

    public function test_liste_zeigt_echte_beitraege_aus_der_datenbank(): void
    {
        $this->beitrag(['slug' => 'jagdhornblasen-2026', 'legacy_index' => 1, 'titel' => 'Jagdhornblasen 2026']);
        $this->beitrag(['slug' => 'jahreshauptversammlung', 'legacy_index' => 2, 'titel' => 'Jahreshauptversammlung']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.index'));

        $response->assertOk();
        $response->assertSee('Jagdhornblasen 2026');
        $response->assertSee('Jahreshauptversammlung');
    }

    public function test_liste_zeigt_keine_service_beitraege(): void
    {
        $this->beitrag(['typ' => 'service', 'slug' => 'hundefuehrerschein', 'legacy_index' => 1, 'titel' => 'Hundeführerschein-Kurs']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.index'));

        $response->assertOk();
        $response->assertDontSee('Hundeführerschein-Kurs');
    }

    public function test_liste_zeigt_auch_archivierte_beitraege(): void
    {
        // Frank muss archivierte Beitraege im Admin weiterhin sehen/bearbeiten
        // koennen (z.B. um sie wieder zu entarchivieren) - siehe Auftrag Teil
        // 8 "kein neuer Entwurfs-Workflow erfinden", die admin-Liste filtert
        // deshalb bewusst NICHT auf archiviert=false (anders als die
        // oeffentliche Hauptseite).
        $this->beitrag(['slug' => 'altmeldung', 'legacy_index' => 1, 'titel' => 'Alte Meldung', 'archiviert' => true]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.index'));

        $response->assertOk();
        $response->assertSee('Alte Meldung');
    }

    // -----------------------------------------------------------------
    // Anlegen (Teil 4)
    // -----------------------------------------------------------------

    public function test_neu_formular_ist_erreichbar(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.neu'));

        $response->assertOk();
        $response->assertSee('name="titel"', false);
    }

    public function test_gueltiger_store_legt_beitrag_an(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.store'), [
            'titel' => 'Neuer Beitrag',
            'datum' => '2026-09-20',
            'jahr' => 2026,
            'text' => 'Ein Text mit **Markdown**.',
            'link' => 'https://example.test',
            'archiviert' => '0',
        ]);

        $beitrag = Beitrag::where('typ', 'aktuelles')->where('titel', 'Neuer Beitrag')->first();
        $this->assertNotNull($beitrag);
        $response->assertRedirect(route('admin.aktuelles.bearbeiten', $beitrag));
        $response->assertSessionHas('status');
        $this->assertSame('2026-09-20', $beitrag->datum->toDateString());
        $this->assertSame(2026, $beitrag->jahr);
        $this->assertSame('Ein Text mit **Markdown**.', $beitrag->text);
        $this->assertFalse($beitrag->archiviert);
        $this->assertSame(1, $beitrag->legacy_index, 'erster Beitrag muss legacy_index 1 bekommen.');
        $this->assertNotSame('', $beitrag->slug);
    }

    public function test_titel_ist_beim_anlegen_pflicht(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.store'), ['titel' => '']);

        $response->assertSessionHasErrors('titel');
        $this->assertSame(0, Beitrag::where('typ', 'aktuelles')->count());
    }

    public function test_ungueltige_kategorie_wird_beim_anlegen_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.store'), [
            'titel' => 'Beitrag',
            'kategorie_id' => 999999,
        ]);

        $response->assertSessionHasErrors('kategorie_id');
        $this->assertSame(0, Beitrag::where('typ', 'aktuelles')->count());
    }

    public function test_gleicher_titel_erzeugt_eindeutigen_slug(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.aktuelles.store'), ['titel' => 'Jagdhornblasen']);
        $this->post(route('admin.aktuelles.store'), ['titel' => 'Jagdhornblasen']);

        $slugs = Beitrag::where('typ', 'aktuelles')->pluck('slug')->sort()->values();
        $this->assertSame(['jagdhornblasen', 'jagdhornblasen-2'], $slugs->all());
    }

    // -----------------------------------------------------------------
    // Bearbeiten laedt bestehende Werte (Teil 5 / Teil 11)
    // -----------------------------------------------------------------

    public function test_bearbeiten_seite_laedt_bestehende_werte(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Ausbildung', 'sortierung' => 0]);
        $beitrag = $this->beitrag([
            'titel' => 'Bestehender Beitrag',
            'text' => 'Bestehender Markdown-Text.',
            'kategorie_id' => $kategorie->id,
        ]);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.bearbeiten', $beitrag));

        $response->assertOk();
        $response->assertSee('value="Bestehender Beitrag"', false);
        $response->assertSee('Bestehender Markdown-Text.', false);
        $response->assertSee('/downloads/flyer.pdf', false);
        $response->assertSee('Ausbildung', false);
    }

    public function test_bearbeiten_zeigt_oeffentlichen_vorschau_link(): void
    {
        // Auftrag Teil 11: nur eine echte, bereits bestehende oeffentliche
        // Route (aktuelles.show) - kein Vorschau-Feature fuer noch nicht
        // gespeicherte Beitraege erfinden.
        $beitrag = $this->beitrag(['slug' => 'mein-beitrag']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.bearbeiten', $beitrag));

        $response->assertOk();
        $response->assertSee(route('aktuelles.show', 'mein-beitrag'), false);
    }

    public function test_neu_formular_zeigt_keinen_vorschau_link(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.neu'));

        $response->assertOk();
        $response->assertDontSee('Beitrag ansehen');
    }

    public function test_bearbeiten_fuer_service_beitrag_404(): void
    {
        $service = $this->beitrag(['typ' => 'service', 'slug' => 'service-beitrag']);
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.aktuelles.bearbeiten', $service))->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Speichern (Teil 5)
    // -----------------------------------------------------------------

    public function test_gueltiges_update_speichert(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Neuer Titel',
            'text' => 'Neuer Text.',
        ]);

        $response->assertRedirect(route('admin.aktuelles.bearbeiten', $beitrag));
        $response->assertSessionHas('status');
        $beitrag->refresh();
        $this->assertSame('Neuer Titel', $beitrag->titel);
        $this->assertSame('Neuer Text.', $beitrag->text);
    }

    public function test_ungueltiges_update_wird_abgelehnt(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.aktuelles.update', $beitrag), ['titel' => '']);

        $response->assertSessionHasErrors('titel');
        $this->assertSame('Alter Titel', $beitrag->fresh()->titel, 'Beitrag darf bei ungueltigem Payload nicht veraendert werden.');
    }

    public function test_update_fuer_service_beitrag_404(): void
    {
        $service = $this->beitrag(['typ' => 'service', 'slug' => 'service-beitrag']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.aktuelles.update', $service), ['titel' => 'Geaendert'])->assertNotFound();
    }

    public function test_oeffentliche_seite_zeigt_nach_update_die_neuen_daten(): void
    {
        $beitrag = $this->beitrag(['slug' => 'mein-beitrag', 'titel' => 'Alter Titel']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.aktuelles.update', $beitrag), ['titel' => 'Ganz neuer Titel'])->assertRedirect();

        $oeffentlich = $this->get(route('aktuelles.show', 'mein-beitrag'));
        $oeffentlich->assertOk();
        $oeffentlich->assertSee('Ganz neuer Titel');
    }

    // -----------------------------------------------------------------
    // Preservation (Teil 2 / Teil 13): Inventar ALLER von BeitragUpdater
    // verwalteten Felder - titel/datum/jahr/kategorie_id/bild/text/link/
    // galerie_titel/archiviert sowie die eingebetteten Relationen
    // downloads/galerie.
    // -----------------------------------------------------------------

    public function test_partielles_update_erhaelt_alle_vom_formular_nicht_gesendeten_skalarfelder(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Ausbildung', 'sortierung' => 0]);
        $beitrag = $this->beitrag([
            'titel' => 'Alter Titel',
            'datum' => '2026-05-01',
            'jahr' => 2026,
            'kategorie_id' => $kategorie->id,
            'bild' => '/images/beitrag.jpg',
            'text' => 'Alter Text.',
            'link' => 'https://example.test/alt',
            'galerie_titel' => 'Alte Galerie',
            'archiviert' => false,
        ]);
        $this->actingAs($this->admin(), 'web');

        // Simuliert ein Formular, das (z.B. durch eine spaetere Aenderung des
        // Blade-Templates) NUR titel + text sendet - alle anderen von
        // BeitragUpdater::applyFields() verwalteten Skalarfelder muessen
        // exakt erhalten bleiben (siehe AktuellesController::
        // currentFieldValues()). "archiviert" bewusst NICHT Teil dieser
        // Pruefung: es ist eine echte Checkbox im Formular (Auftrag Teil 8),
        // und bei einer Checkbox bedeutet "im Payload nicht vorhanden" nach
        // HTML-Formular-Semantik immer "abgewaehlt" - es gibt (anders als bei
        // den in Phase 7B ausgeblendeten Feldern) keinen Unterschied zwischen
        // "Formular zeigt das Feld nicht" und "Nutzer hat die Box
        // abgewaehlt". Das gezielte Setzen von archiviert=true wird separat
        // in test_ins_archiv_verschieben_ueber_das_formular_speichert_archiviert()
        // geprueft.
        $response = $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Neuer Titel',
            'text' => 'Neuer Text.',
        ]);

        $response->assertRedirect(route('admin.aktuelles.bearbeiten', $beitrag));
        $beitrag->refresh();

        $this->assertSame('Neuer Titel', $beitrag->titel);
        $this->assertSame('Neuer Text.', $beitrag->text);

        $this->assertSame('2026-05-01', $beitrag->datum->toDateString(), 'datum muss erhalten bleiben.');
        $this->assertSame(2026, $beitrag->jahr, 'jahr muss erhalten bleiben.');
        $this->assertSame($kategorie->id, $beitrag->kategorie_id, 'kategorie_id muss erhalten bleiben.');
        $this->assertSame('/images/beitrag.jpg', $beitrag->bild, 'bild muss erhalten bleiben.');
        $this->assertSame('https://example.test/alt', $beitrag->link, 'link muss erhalten bleiben.');
        $this->assertSame('Alte Galerie', $beitrag->galerie_titel, 'galerie_titel muss erhalten bleiben.');
    }

    public function test_partielles_update_erhaelt_downloads_und_galerie_wenn_formular_sie_unveraendert_zurueckschickt(): void
    {
        // Das Blade-Formular zeigt downloads[]/galerie[] IMMER vorbelegt mit
        // den bestehenden Werten (siehe bearbeiten.blade.php,
        // "$downloadZeilen"/"$galerieZeilen") - ein echter Browser-Submit
        // schickt sie deshalb unveraendert zurueck, wenn Frank nichts daran
        // aendert. Dieser Test bildet genau das nach (Auftrag Teil 13:
        // "Galerien/Relationen bleiben unveraendert, wenn die UI sie nicht
        // bearbeitet").
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'vorschau' => null, 'sortierung' => 0]);
        GalerieBild::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'titel' => 'Ansitz', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Neuer Titel',
            'downloads' => [['titel' => 'Flyer', 'datei' => '/downloads/flyer.pdf', 'vorschau' => '']],
            'galerie' => [['bild' => '/images/galerie/1.jpg', 'titel' => 'Ansitz']],
        ])->assertRedirect();

        $this->assertSame(1, Download::where('owner_id', $beitrag->id)->count());
        $this->assertDatabaseHas('downloads', ['owner_id' => $beitrag->id, 'pfad' => '/downloads/flyer.pdf', 'titel' => 'Flyer']);
        $this->assertSame(1, GalerieBild::where('owner_id', $beitrag->id)->count());
        $this->assertDatabaseHas('galerie_bilder', ['owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'titel' => 'Ansitz']);
    }

    public function test_update_kann_downloads_und_galerie_gezielt_aendern(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Beitrag']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Beitrag',
            'downloads' => [['titel' => 'Neues Dokument', 'datei' => '/downloads/neu.pdf', 'vorschau' => '']],
            'galerie' => [['bild' => '/images/galerie/neu.jpg', 'titel' => 'Neues Bild']],
        ])->assertRedirect();

        $this->assertDatabaseHas('downloads', ['owner_id' => $beitrag->id, 'pfad' => '/downloads/neu.pdf']);
        $this->assertDatabaseHas('galerie_bilder', ['owner_id' => $beitrag->id, 'pfad' => '/images/galerie/neu.jpg']);
    }

    // -----------------------------------------------------------------
    // Preservation-Nachbesserung (Nutzer-Feedback): "downloads"/"galerie"
    // fehlen komplett im Request -> Relationen bleiben unveraendert;
    // "downloads"/"galerie" ausdruecklich als leere Liste gesendet ->
    // Relationen duerfen bewusst geleert werden. Siehe BeitragUpdater::
    // applyFields()-Kommentar.
    // -----------------------------------------------------------------

    public function test_update_ohne_downloads_und_galerie_keys_erhaelt_beide_relationen_unveraendert(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'sortierung' => 0]);
        GalerieBild::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'titel' => 'Ansitz', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        // Bewusst OHNE "downloads"/"galerie"-Schluessel im Request (anders
        // als test_partielles_update_erhaelt_downloads_und_galerie_wenn_
        // formular_sie_unveraendert_zurueckschickt() oben, das die
        // Schluessel MIT den unveraenderten Werten mitschickt) - simuliert
        // einen Aufrufer, der diese Formularbereiche gar nicht mit sendet.
        $response = $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Neuer Titel',
            'text' => 'Neuer Text.',
        ]);

        $response->assertRedirect(route('admin.aktuelles.bearbeiten', $beitrag));
        $this->assertSame(1, Download::where('owner_id', $beitrag->id)->count(), 'Download muss ohne "downloads"-Schluessel im Request erhalten bleiben.');
        $this->assertDatabaseHas('downloads', ['owner_id' => $beitrag->id, 'pfad' => '/downloads/flyer.pdf', 'titel' => 'Flyer']);
        $this->assertSame(1, GalerieBild::where('owner_id', $beitrag->id)->count(), 'Galeriebild muss ohne "galerie"-Schluessel im Request erhalten bleiben.');
        $this->assertDatabaseHas('galerie_bilder', ['owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'titel' => 'Ansitz']);
    }

    public function test_update_mit_ausdruecklich_leeren_downloads_und_galerie_leert_beide_relationen(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Beitrag']);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'sortierung' => 0]);
        GalerieBild::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        // Schluessel ist HIER vorhanden (Frank hat im Formular alle Zeilen
        // entfernt) - anders als der Test oben, wo der Schluessel komplett
        // fehlt.
        $response = $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Beitrag',
            'downloads' => [],
            'galerie' => [],
        ]);

        $response->assertRedirect(route('admin.aktuelles.bearbeiten', $beitrag));
        $this->assertSame(0, Download::where('owner_id', $beitrag->id)->count(), 'Ausdruecklich leere "downloads"-Liste muss die Relation leeren.');
        $this->assertSame(0, GalerieBild::where('owner_id', $beitrag->id)->count(), 'Ausdruecklich leere "galerie"-Liste muss die Relation leeren.');
    }

    public function test_json_admin_api_loescht_relationen_bei_fehlendem_downloads_key_weiterhin_unveraendert(): void
    {
        // Gegenprobe: die JSON-API baut ihr $fields-Array PRO ITEM IMMER
        // mit "downloads"/"galerie"-Schluessel (Wert null, falls das Item
        // sie nicht mitschickt) - dieses bestehende Vollpayload-Verhalten
        // (Loeschen bei fehlendem Feld) darf sich durch die obige Blade-
        // Preservation-Nachbesserung NICHT aendern.
        $this->actingAs($this->admin(), 'web');
        $beitrag = $this->beitrag(['legacy_index' => 1, 'titel' => 'Beitrag mit Anhang']);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'sortierung' => 0]);

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'beitraege' => [
                    // Item OHNE eigenes "downloads"-Feld - wie bisher.
                    ['legacy_index' => 1, 'titel' => 'Beitrag mit Anhang'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame(0, Download::where('owner_id', $beitrag->id)->count(), 'JSON-API-Vollpayload-Verhalten (Loeschen bei fehlendem Feld) muss unveraendert bleiben.');
    }

    // -----------------------------------------------------------------
    // Kategorie-Verwaltung (Nachbesserung Blocker 1): Anlegen/Loeschen
    // einzelner Kategorien direkt im neuen Blade-Admin, ueber die geteilte
    // Schicht App\Support\BeitragKategorieUpdater - dieselbe "nur loeschen,
    // wenn ungenutzt"-Regel wie im Alt-Admin (admin.js'
    // aktuellesKategorieDelete()).
    // -----------------------------------------------------------------

    public function test_kategorie_anlegen_speichert_neue_kategorie(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.kategorien.anlegen'), ['name' => 'Jugendarbeit']);

        $response->assertRedirect(route('admin.aktuelles.index'));
        $response->assertSessionHas('kategorie_status');
        $this->assertDatabaseHas('beitrag_kategorien', ['typ' => 'aktuelles', 'name' => 'Jugendarbeit']);
    }

    public function test_kategorie_anlegen_mit_leerem_namen_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.kategorien.anlegen'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertSame(0, BeitragKategorie::where('typ', 'aktuelles')->count());
    }

    public function test_kategorie_anlegen_mit_bereits_vorhandenem_namen_legt_keine_zweite_zeile_an(): void
    {
        // Admin.js' eigenes Verhalten (String-Dedup, "kats.indexOf(neu)"):
        // ein bereits vorhandener Name ist kein Fehler, sondern bleibt
        // idempotent bei genau einer Zeile.
        BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Jugend', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.kategorien.anlegen'), ['name' => 'Jugend']);

        $response->assertRedirect(route('admin.aktuelles.index'));
        $this->assertSame(1, BeitragKategorie::where('typ', 'aktuelles')->where('name', 'Jugend')->count());
    }

    public function test_ungenutzte_kategorie_kann_geloescht_werden(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Ungenutzt', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.aktuelles.kategorien.loeschen', $kategorie));

        $response->assertRedirect(route('admin.aktuelles.index'));
        $response->assertSessionHas('kategorie_status');
        $this->assertDatabaseMissing('beitrag_kategorien', ['id' => $kategorie->id]);
    }

    public function test_verwendete_kategorie_kann_nicht_geloescht_werden(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Jagd', 'sortierung' => 0]);
        $this->beitrag(['titel' => 'Beitrag mit Kategorie', 'kategorie_id' => $kategorie->id]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.aktuelles.kategorien.loeschen', $kategorie));

        $response->assertRedirect(route('admin.aktuelles.index'));
        $response->assertSessionHas('kategorie_fehler');
        $this->assertDatabaseHas('beitrag_kategorien', ['id' => $kategorie->id]);
    }

    public function test_kategorie_aktionen_mit_veraltetem_expected_version_werden_abgelehnt(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Ungenutzt', 'sortierung' => 0]);
        ContentVersioning::bump('aktuelles');
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.aktuelles.kategorien.anlegen'), ['name' => 'Neu', 'expected_version' => 0]);

        $response->assertRedirect(route('admin.aktuelles.index'));
        $response->assertSessionHas('kategorie_fehler');
        $this->assertDatabaseMissing('beitrag_kategorien', ['name' => 'Neu']);

        $deleteResponse = $this->delete(route('admin.aktuelles.kategorien.loeschen', $kategorie), ['expected_version' => 0]);
        $deleteResponse->assertSessionHas('kategorie_fehler');
        $this->assertDatabaseHas('beitrag_kategorien', ['id' => $kategorie->id]);
    }

    public function test_liste_zeigt_kategorien_mit_verwendungsanzahl(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => 'Jagd', 'sortierung' => 0]);
        $this->beitrag(['titel' => 'Beitrag mit Kategorie', 'kategorie_id' => $kategorie->id]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.index'));

        $response->assertOk();
        $response->assertSee('Jagd');
        $response->assertSee('1 Beitrag', false);
    }

    // -----------------------------------------------------------------
    // Security-Fix (Nutzer-Feedback): der Kategoriename ist Nutzereingabe
    // und landet im Loeschen-Formular zusaetzlich in einem JS-String-
    // Kontext (onsubmit="return confirm('...')") - normales Blade-"{{ }}"-
    // HTML-Escaping (siehe die reine Textanzeige weiter oben auf der Seite,
    // dort korrekt und ausreichend) schuetzt DAVOR nicht, da der Browser
    // HTML-Entities im Attributwert vor der JS-Ausfuehrung wieder dekodiert
    // - ein Apostroph im Namen koennte sonst aus dem einfach gequoteten
    // confirm()-String ausbrechen. @js() (Illuminate\Support\Js) kodiert
    // stattdessen JS-sicher (Apostroph -> Unicode-Escape).
    // -----------------------------------------------------------------

    public function test_kategorienliste_mit_apostroph_im_namen_ist_js_sicher(): void
    {
        $kategorie = BeitragKategorie::create(['typ' => 'aktuelles', 'name' => "Test' - alert(1) - '", 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.aktuelles.index'));

        $response->assertOk();

        // Gezielt das onsubmit-Attribut DES Loeschen-Formulars dieser
        // Kategorie herausgreifen (nicht die ganze Seite durchsuchen - der
        // Name erscheint an anderer Stelle bewusst normal HTML-escaped als
        // reiner Anzeigetext, das ist ein anderer, unkritischer Kontext).
        preg_match('/action="[^"]*kategorien\/'.$kategorie->id.'"[^>]*onsubmit="([^"]*)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Löschen-Formular für die Testkategorie nicht im HTML gefunden.');
        $onsubmit = $matches[1];

        // "'" ist die von Illuminate\Support\Js (JSON_HEX_APOS)
        // erzeugte, JS-sichere Kodierung eines Apostrophs - der Marker
        // dafuer, dass tatsaechlich @js() statt normalem Blade-Escaping
        // verwendet wurde.
        $jsSicheresApostroph = \chr(92).'u0027';
        $this->assertStringContainsString($jsSicheresApostroph, $onsubmit, '@js() muss den Apostroph JS-sicher kodieren.');
        $this->assertStringNotContainsString("Test' - alert(1) - '", $onsubmit, 'Der rohe, ungeescapte Name darf nicht unveraendert im JS-String-Kontext landen (Ausbruch aus confirm()).');
    }

    public function test_json_admin_api_kategorie_verwaltung_regressiert_nicht(): void
    {
        // Bestehendes Vollpayload-Verhalten (Kategorienliste komplett neu
        // schreiben + Get-or-Create fuer eine Ad-hoc-Kategorie, die ein
        // Beitrag traegt, aber noch nicht in der Liste steht) muss nach der
        // Extraktion nach App\Support\BeitragKategorieUpdater unveraendert
        // funktionieren.
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'einstellungen' => ['kategorien' => ['Jagd', 'Naturschutz']],
                'beitraege' => [
                    ['titel' => 'Beitrag mit Ad-hoc-Kategorie', 'kategorie' => 'Ad-hoc-Kategorie'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('beitrag_kategorien', ['typ' => 'aktuelles', 'name' => 'Jagd']);
        $this->assertDatabaseHas('beitrag_kategorien', ['typ' => 'aktuelles', 'name' => 'Naturschutz']);
        $this->assertDatabaseHas('beitrag_kategorien', ['typ' => 'aktuelles', 'name' => 'Ad-hoc-Kategorie']);
        $beitrag = Beitrag::where('titel', 'Beitrag mit Ad-hoc-Kategorie')->firstOrFail();
        $this->assertSame('Ad-hoc-Kategorie', $beitrag->kategorie->name);
    }

    // -----------------------------------------------------------------
    // Status/Archiviert (Teil 8): kein neuer "veroeffentlicht"-Status -
    // bestehende archiviert-Regel bleibt unveraendert.
    // -----------------------------------------------------------------

    public function test_archivierter_beitrag_bleibt_ueber_direkten_link_erreichbar(): void
    {
        $this->beitrag(['slug' => 'alte-meldung', 'titel' => 'Alte Meldung', 'archiviert' => true]);

        $response = $this->get(route('aktuelles.show', 'alte-meldung'));

        $response->assertOk();
        $response->assertSee('Alte Meldung');
    }

    public function test_archivierter_beitrag_erscheint_nicht_in_oeffentlicher_hauptliste(): void
    {
        $this->beitrag(['slug' => 'alte-meldung', 'titel' => 'Alte Meldung', 'archiviert' => true, 'datum' => now()->subYear()->toDateString()]);
        $this->beitrag(['slug' => 'neue-meldung', 'legacy_index' => 2, 'titel' => 'Neue Meldung', 'archiviert' => false, 'datum' => now()->toDateString()]);

        $response = $this->get(route('aktuelles.index'));

        $response->assertOk();
        $response->assertDontSee('Alte Meldung');
        $response->assertSee('Neue Meldung');
    }

    public function test_ins_archiv_verschieben_ueber_das_formular_speichert_archiviert(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Beitrag', 'archiviert' => false]);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Beitrag',
            'archiviert' => '1',
        ])->assertRedirect();

        $this->assertTrue($beitrag->fresh()->archiviert);
    }

    // -----------------------------------------------------------------
    // Loeschen (Teil 7): nur ueber den dedizierten Loeschen-Knopf, alte
    // Admin-Oberflaeche unterstuetzte Loeschen ebenfalls.
    // -----------------------------------------------------------------

    public function test_loeschen_entfernt_beitrag_und_eingebettete_relationen(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Zu loeschender Beitrag']);
        Download::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'titel' => 'Flyer', 'pfad' => '/downloads/flyer.pdf', 'sortierung' => 0]);
        GalerieBild::create(['owner_type' => $beitrag->getMorphClass(), 'owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg', 'sortierung' => 0]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.aktuelles.loeschen', $beitrag));

        $response->assertRedirect(route('admin.aktuelles.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('beitraege', ['id' => $beitrag->id]);
        $this->assertDatabaseMissing('downloads', ['owner_id' => $beitrag->id]);
        $this->assertDatabaseMissing('galerie_bilder', ['owner_id' => $beitrag->id]);
    }

    public function test_loeschen_fuer_service_beitrag_404(): void
    {
        $service = $this->beitrag(['typ' => 'service', 'slug' => 'service-beitrag']);
        $this->actingAs($this->admin(), 'web');

        $this->delete(route('admin.aktuelles.loeschen', $service))->assertNotFound();
        $this->assertDatabaseHas('beitraege', ['id' => $service->id]);
    }

    // -----------------------------------------------------------------
    // Versionskonflikte (Teil 9): geteilter, sammlungsweiter
    // ContentVersioning-Schluessel "aktuelles" - kein neuer, feinerer
    // pro-Beitrag-Schluessel.
    // -----------------------------------------------------------------

    public function test_veralteter_expected_version_wird_bei_update_mit_fehler_abgelehnt(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        // Simuliert eine zwischenzeitliche Aenderung ueber die JSON-API
        // (admin.js) ODER einen anderen Aktuelles-Beitrag im Blade-Admin -
        // beide teilen sich denselben Schluessel "aktuelles".
        ContentVersioning::bump('aktuelles');
        $this->actingAs($this->admin(), 'web');

        $response = $this->put(route('admin.aktuelles.update', $beitrag), [
            'titel' => 'Neuer Titel (Konflikt)',
            'expected_version' => 0,
        ]);

        $response->assertSessionHasErrors('titel');
        $this->assertSame('Alter Titel', $beitrag->fresh()->titel);
    }

    public function test_veralteter_expected_version_wird_bei_loeschen_mit_fehler_abgelehnt(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Beitrag']);
        ContentVersioning::bump('aktuelles');
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.aktuelles.loeschen', $beitrag), ['expected_version' => 0]);

        $response->assertSessionHasErrors('titel');
        $this->assertDatabaseHas('beitraege', ['id' => $beitrag->id]);
    }

    // -----------------------------------------------------------------
    // Kein Regress der bestehenden JSON-Admin-API (Teil 13): bislang
    // vollstaendig ungetesteter Schreibweg - neue, eigenstaendige
    // Regressionsabdeckung fuer Anlegen/Aktualisieren/Loeschen-per-
    // Array-Weglassen.
    // -----------------------------------------------------------------

    public function test_json_admin_api_legt_neuen_beitrag_an(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'beitraege' => [
                    ['titel' => 'Per JSON-API angelegt', 'datum' => '20.09.2026', 'archiviert' => false],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $beitrag = Beitrag::where('typ', 'aktuelles')->where('titel', 'Per JSON-API angelegt')->first();
        $this->assertNotNull($beitrag);
        $this->assertSame('2026-09-20', $beitrag->datum->toDateString());
        $this->assertSame(1, $beitrag->legacy_index);
    }

    public function test_json_admin_api_aktualisiert_bestehenden_beitrag_ueber_legacy_index(): void
    {
        $beitrag = $this->beitrag(['legacy_index' => 5, 'titel' => 'Alter Titel']);
        $admin = $this->admin();
        $this->actingAs($admin, 'web');

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'beitraege' => [
                    ['legacy_index' => 5, 'titel' => 'Aktualisierter Titel'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $beitrag->refresh();
        $this->assertSame('Aktualisierter Titel', $beitrag->titel);
        $this->assertSame(1, Beitrag::where('typ', 'aktuelles')->count(), 'darf keinen zweiten Beitrag anlegen, sondern muss ueber legacy_index zuordnen.');
    }

    public function test_json_admin_api_loescht_beitrag_der_im_payload_fehlt(): void
    {
        $bleibt = $this->beitrag(['legacy_index' => 1, 'slug' => 'bleibt', 'titel' => 'Bleibt']);
        $verschwindet = $this->beitrag(['legacy_index' => 2, 'slug' => 'verschwindet', 'titel' => 'Verschwindet']);
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'beitraege' => [
                    ['legacy_index' => 1, 'titel' => 'Bleibt'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('beitraege', ['id' => $bleibt->id]);
        $this->assertDatabaseMissing('beitraege', ['id' => $verschwindet->id]);
    }

    public function test_json_admin_api_speichert_downloads_und_galerie(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/aktuelles.json', [
            'data' => [
                'beitraege' => [
                    [
                        'titel' => 'Beitrag mit Anhaengen',
                        'downloads' => [['titel' => 'Flyer', 'datei' => '/downloads/flyer.pdf']],
                        'galerie' => [['bild' => '/images/galerie/1.jpg', 'titel' => 'Ansitz']],
                    ],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $beitrag = Beitrag::where('titel', 'Beitrag mit Anhaengen')->firstOrFail();
        $this->assertDatabaseHas('downloads', ['owner_id' => $beitrag->id, 'pfad' => '/downloads/flyer.pdf']);
        $this->assertDatabaseHas('galerie_bilder', ['owner_id' => $beitrag->id, 'pfad' => '/images/galerie/1.jpg']);
    }

    // -----------------------------------------------------------------
    // CSRF (Teil 13)
    // -----------------------------------------------------------------

    public function test_update_ist_csrf_geschuetzt(): void
    {
        $beitrag = $this->beitrag(['titel' => 'Alter Titel']);
        $this->actingAs($this->admin(), 'web');
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->call('PUT', route('admin.aktuelles.update', $beitrag), ['titel' => 'Sollte nicht gespeichert werden']);
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertSame('Alter Titel', $beitrag->fresh()->titel);
    }

    // -----------------------------------------------------------------
    // Keine JSON-Runtime noetig (Teil 12/13): Liste und Formular sind
    // normale, server-gerenderte HTML-Seiten mit klassischem
    // <form method="POST">.
    // -----------------------------------------------------------------

    public function test_liste_und_formular_sind_gewoehnliches_server_gerendertes_html(): void
    {
        $beitrag = $this->beitrag();
        $this->actingAs($this->admin(), 'web');

        $liste = $this->get(route('admin.aktuelles.index'));
        $liste->assertOk();
        $this->assertStringContainsString('text/html', $liste->headers->get('Content-Type'));

        $formular = $this->get(route('admin.aktuelles.bearbeiten', $beitrag));
        $formular->assertOk();
        $formular->assertSee('<form method="POST"', false);
        $formular->assertSee('name="_method" value="PUT"', false);
    }

    // -----------------------------------------------------------------
    // Sidebar-Navigation (Teil 12)
    // -----------------------------------------------------------------

    public function test_sidebar_aktuelles_ist_jetzt_ein_echter_link(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.aktuelles.index').'"', false);
    }
}
