<?php

namespace Tests\Feature\Phase7J;

use App\Models\User;
use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseBild;
use App\Models\WaffenboerseKaliber;
use App\Models\WaffenboerseKategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 7J (Admin-Modul "Waffenboerse"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/waffenboerse" ab (Http\Controllers\Admin\
 * WaffenboerseController) - siehe dortiger Klassenkommentar fuer die
 * vollstaendige Alt-Admin-/Datenmodell-Analyse (Waffenboerse ist bereits
 * seit Phase 6B auf Laravel/MySQL migriert, hier kommt ausschliesslich die
 * Admin-Oberflaeche fuer diese bereits lebende Datenquelle dazu).
 *
 * Die Migration legt bereits 7 Standardkategorien an (insertOrIgnore, siehe
 * 2026_09_14_000113_create_waffenboerse_kategorien_table.php) - Tests
 * nutzen "Kurzwaffen" als bekannte, immer vorhandene Kategorie statt jedes
 * Mal eine eigene anzulegen.
 *
 * Echte Datei-Uploads landen unter public/uploads/boersen/waffenboerse/ -
 * jeder Test, der eine Anzeige mit Bildern anlegt, raeumt sie am Ende wieder
 * auf (siehe tearDown()), damit keine Testartefakte im Arbeitsverzeichnis
 * zurueckbleiben.
 */
class AdminWaffenboerseTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $angelegteBildPfade = [];

    protected function tearDown(): void
    {
        foreach ($this->angelegteBildPfade as $pfad) {
            $this->loescheBildDateien($pfad);
        }

        parent::tearDown();
    }

    private function loescheBildDateien(string $pfad): void
    {
        $voll = public_path(ltrim($pfad, '/'));
        if (is_file($voll)) {
            @unlink($voll);
        }
        foreach (['thumb', 'card'] as $ordner) {
            $variante = dirname($voll).'/'.$ordner.'/'.basename($voll);
            if (is_file($variante)) {
                @unlink($variante);
            }
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['roles' => ['admin'], 'permissions' => []]);
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create(['roles' => [], 'permissions' => []]);
    }

    private function anlegen(array $overrides = []): WaffenboerseAnzeige
    {
        return WaffenboerseAnzeige::create(array_merge([
            'id' => 'wb-test-'.uniqid(),
            'status' => 'pending',
            'titel' => 'Testwaffe',
            'kategorie' => 'Kurzwaffen',
            'hersteller' => 'Testhersteller',
            'zustand' => 'gebraucht',
            'preis' => '500',
            'preis_typ' => 'festpreis',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'anbieter_name' => 'Max Mustermann',
            'anbieter_email' => 'max@example.test',
        ], $overrides));
    }

    private function grundfelder(array $overrides = []): array
    {
        return array_merge([
            'status' => 'pending',
            'titel' => 'Formular-Waffe',
            'kategorie' => 'Kurzwaffen',
            'hersteller' => 'Formularhersteller',
            'zustand' => 'gebraucht',
            'preis_typ' => 'festpreis',
            'preis' => '500',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'anbieter_name' => 'Erika Musterfrau',
            'anbieter_email' => 'erika@example.test',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.waffenboerse.index'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.waffenboerse.index'))->assertForbidden();
    }

    public function test_alle_waffenboerse_admin_routen_sind_geschuetzt(): void
    {
        $anzeige = $this->anlegen();
        $kategorie = WaffenboerseKategorie::first();

        $this->get(route('admin.waffenboerse.neu'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.waffenboerse.bearbeiten', $anzeige))->assertRedirect(route('admin.login'));
        $this->post(route('admin.waffenboerse.speichern'), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.waffenboerse.freigeben', $anzeige))->assertRedirect(route('admin.login'));
        $this->put(route('admin.waffenboerse.archivieren', $anzeige))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.waffenboerse.loeschen', $anzeige))->assertRedirect(route('admin.login'));
        $this->get(route('admin.waffenboerse.kategorien.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.waffenboerse.kategorien.speichern'), [])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.waffenboerse.kategorien.loeschen', $kategorie))->assertRedirect(route('admin.login'));
    }

    // -----------------------------------------------------------------
    // Liste / Filter
    // -----------------------------------------------------------------

    public function test_liste_zeigt_alle_status(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anlegen(['id' => 'wb-p', 'status' => 'pending', 'titel' => 'Wartende Waffe']);
        $this->anlegen(['id' => 'wb-v', 'status' => 'published', 'titel' => 'Veröffentlichte Waffe']);
        $this->anlegen(['id' => 'wb-a', 'status' => 'rejected', 'titel' => 'Abgelehnte Waffe']);
        $this->anlegen(['id' => 'wb-r', 'status' => 'archived', 'titel' => 'Archivierte Waffe']);

        $response = $this->get(route('admin.waffenboerse.index'));

        $response->assertOk();
        $response->assertSee('Wartende Waffe');
        $response->assertSee('Veröffentlichte Waffe');
        $response->assertSee('Abgelehnte Waffe');
        $response->assertSee('Archivierte Waffe');
    }

    public function test_statusfilter_zeigt_nur_passende_anzeigen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anlegen(['id' => 'wb-p', 'status' => 'pending', 'titel' => 'Wartende Waffe']);
        $this->anlegen(['id' => 'wb-v', 'status' => 'published', 'titel' => 'Veröffentlichte Waffe']);

        $response = $this->get(route('admin.waffenboerse.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('Wartende Waffe');
        $response->assertDontSee('Veröffentlichte Waffe');
    }

    public function test_liste_zeigt_fachfelder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['titel' => 'Feld-Test-Waffe', 'hersteller' => 'Blaser', 'kategorie' => 'Kurzwaffen']);
        WaffenboerseKaliber::create(['anzeige_id' => $anzeige->id, 'kaliber' => '9 mm', 'sortierung' => 0]);

        $response = $this->get(route('admin.waffenboerse.index'));

        $response->assertOk();
        $response->assertSee('Feld-Test-Waffe');
        $response->assertSee('Blaser');
        $response->assertSee('9 mm');
    }

    // -----------------------------------------------------------------
    // Anlegen
    // -----------------------------------------------------------------

    public function test_neu_formular_ist_erreichbar(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.waffenboerse.neu'))->assertOk();
    }

    public function test_anzeige_kann_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder([
            'kaliber' => "9 mm\n.22 lr",
        ]));

        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $response->assertRedirect(route('admin.waffenboerse.bearbeiten', $anzeige));
        $this->assertSame('Kurzwaffen', $anzeige->kategorie);
        $this->assertStringStartsWith('wb-', $anzeige->id);
        $this->assertSame(['9 mm', '.22 lr'], $anzeige->kaliber()->orderBy('sortierung')->pluck('kaliber')->all());
    }

    public function test_admin_kann_eine_anzeige_direkt_als_veroeffentlicht_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder(['status' => 'published']));

        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $this->assertSame('published', $anzeige->status);
    }

    /**
     * Regression (analog Hundeboerse, siehe dortigen Test): ein echtes
     * Browser-Formular sendet auch leer gelassene Felder mit - Laravels
     * ConvertEmptyStringsToNull-Middleware wandelt sie in null um. Fuer
     * NOT-NULL-Spalten mit Default '' (z.B. "modell") darf das nicht zu
     * einem SQL-Fehler fuehren.
     */
    public function test_leer_abgeschickte_pflichtspalten_erzeugen_keinen_datenbankfehler(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'modell' => '',
            'anbieter_telefon' => '',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $this->assertSame('', $anzeige->modell);
        $this->assertSame('', $anzeige->anbieter_telefon);
    }

    public function test_ungueltiger_status_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder(['status' => 'unbekannt']));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('waffenboerse_anzeigen', ['titel' => 'Formular-Waffe']);
    }

    public function test_ungueltiger_zustand_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder(['zustand' => 'unbekannt']));

        $response->assertSessionHasErrors('zustand');
    }

    public function test_vorfuehrwaffe_ist_ein_gueltiger_zustand(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder(['zustand' => 'vorfuehrwaffe']));

        $response->assertSessionDoesntHaveErrors();
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $this->assertSame('vorfuehrwaffe', $anzeige->zustand);
    }

    public function test_auf_anfrage_ist_eine_gueltige_preisart(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder(['preis_typ' => 'auf_anfrage']));

        $response->assertSessionDoesntHaveErrors();
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $this->assertSame('auf_anfrage', $anzeige->preis_typ);
        $this->assertSame('Preis auf Anfrage', $anzeige->preisText());
    }

    // -----------------------------------------------------------------
    // Bearbeiten / Preservation
    // -----------------------------------------------------------------

    public function test_bearbeiten_formular_ist_erreichbar_und_zeigt_werte(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['titel' => 'Anzuzeigende Waffe']);

        $response = $this->get(route('admin.waffenboerse.bearbeiten', $anzeige));

        $response->assertOk();
        $response->assertSee('Anzuzeigende Waffe');
    }

    public function test_felder_werden_gespeichert(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $response = $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge(
            $this->grundfelder(),
            ['titel' => 'Geänderter Titel', 'aktion' => 'speichern']
        ));

        $response->assertRedirect(route('admin.waffenboerse.bearbeiten', $anzeige));
        $this->assertSame('Geänderter Titel', $anzeige->fresh()->titel);
    }

    public function test_teil_update_erhaelt_nicht_gesendete_felder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen([
            'modell' => 'Luger',
            'anbieter_telefon' => '0151-1234567',
        ]);
        WaffenboerseKaliber::create(['anzeige_id' => $anzeige->id, 'kaliber' => '9 mm', 'sortierung' => 0]);

        // Absichtlich ein minimaler Teil-Request (nur Pflicht-Enums + neuer
        // Titel) - modell/anbieter_telefon/kaliber werden NICHT mitgesendet
        // und muessen erhalten bleiben (Preservation).
        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), [
            'status' => $anzeige->status,
            'zustand' => $anzeige->zustand,
            'preis_typ' => $anzeige->preis_typ,
            'titel' => 'Nur der Titel ändert sich',
        ]);

        $anzeige->refresh();
        $this->assertSame('Nur der Titel ändert sich', $anzeige->titel);
        $this->assertSame('Luger', $anzeige->modell);
        $this->assertSame('0151-1234567', $anzeige->anbieter_telefon);
        $this->assertSame(['9 mm'], $anzeige->kaliber()->pluck('kaliber')->all());
    }

    public function test_kaliber_liste_wird_beim_speichern_komplett_ersetzt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        WaffenboerseKaliber::create(['anzeige_id' => $anzeige->id, 'kaliber' => 'alt', 'sortierung' => 0]);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'kaliber' => "7x65R\n12/70\n\n5,6x52R",
        ]));

        $this->assertSame(
            ['7x65R', '12/70', '5,6x52R'],
            $anzeige->kaliber()->orderBy('sortierung')->pluck('kaliber')->all()
        );
    }

    public function test_leeres_kaliber_feld_loescht_die_liste(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        WaffenboerseKaliber::create(['anzeige_id' => $anzeige->id, 'kaliber' => '9 mm', 'sortierung' => 0]);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'kaliber' => '',
        ]));

        $this->assertSame([], $anzeige->kaliber()->pluck('kaliber')->all());
    }

    /**
     * Regression: das wiederverwendete Rich-Text-Feld (Phase 7B) speichert
     * die "beschreibung" als rohes HTML (siehe Controller-Klassenkommentar
     * "Beschreibung") - ein erneutes Speichern desselben HTML-Werts darf
     * ihn NICHT kaputt escapen (das waere der Fall, wuerde man ihn
     * versehentlich durch Text::freeTextToSafeParagraphs() schicken).
     */
    public function test_beschreibung_bleibt_bei_erneutem_speichern_intaktes_html(): void
    {
        $this->actingAs($this->admin(), 'web');
        $html = '<p>Zu verkaufen: <strong>gepflegte</strong> Waffe.<br>Nur Abholung.</p>';
        $anzeige = $this->anlegen(['beschreibung' => $html]);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'beschreibung' => $html,
        ]));

        $this->assertSame($html, $anzeige->fresh()->beschreibung);
    }

    // -----------------------------------------------------------------
    // Status-Workflow
    // -----------------------------------------------------------------

    public function test_pending_kann_freigegeben_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending', 'titel' => 'Bleibt unverändert']);

        $response = $this->put(route('admin.waffenboerse.freigeben', $anzeige));

        $response->assertRedirect();
        $anzeige->refresh();
        $this->assertSame('published', $anzeige->status);
        $this->assertSame('Bleibt unverändert', $anzeige->titel);
    }

    public function test_speichern_und_ablehnen_setzt_status_und_speichert_felder_in_einem_schritt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending']);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'pending',
            'titel' => 'Beim Ablehnen geänderter Titel',
            'aktion' => 'ablehnen',
        ]));

        $anzeige->refresh();
        $this->assertSame('rejected', $anzeige->status);
        $this->assertSame('Beim Ablehnen geänderter Titel', $anzeige->titel);
    }

    public function test_speichern_und_freigeben_setzt_status_und_speichert_felder_in_einem_schritt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending']);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'pending',
            'titel' => 'Beim Freigeben geänderter Titel',
            'aktion' => 'freigeben',
        ]));

        $anzeige->refresh();
        $this->assertSame('published', $anzeige->status);
        $this->assertSame('Beim Freigeben geänderter Titel', $anzeige->titel);
    }

    public function test_published_oder_rejected_koennen_archiviert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $veroeffentlicht = $this->anlegen(['id' => 'wb-v', 'status' => 'published']);
        $abgelehnt = $this->anlegen(['id' => 'wb-a', 'status' => 'rejected']);

        $this->put(route('admin.waffenboerse.archivieren', $veroeffentlicht));
        $this->put(route('admin.waffenboerse.archivieren', $abgelehnt));

        $this->assertSame('archived', $veroeffentlicht->fresh()->status);
        $this->assertSame('archived', $abgelehnt->fresh()->status);
    }

    public function test_archivierte_anzeige_kann_ueber_das_bearbeiten_formular_wiederhergestellt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'archived']);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'published',
        ]));

        $this->assertSame('published', $anzeige->fresh()->status);
    }

    public function test_statuswechsel_veraendert_keine_inhaltsfelder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending', 'titel' => 'Unveränderter Titel', 'hersteller' => 'Beretta']);

        $this->put(route('admin.waffenboerse.freigeben', $anzeige));

        $anzeige->refresh();
        $this->assertSame('Unveränderter Titel', $anzeige->titel);
        $this->assertSame('Beretta', $anzeige->hersteller);
    }

    // -----------------------------------------------------------------
    // Bilder
    // -----------------------------------------------------------------

    public function test_bild_kann_beim_anlegen_hochgeladen_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('waffe.jpg', 200, 200)],
        ]));

        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $response->assertRedirect(route('admin.waffenboerse.bearbeiten', $anzeige));
        $this->assertCount(1, $anzeige->bilder);
        $pfad = $anzeige->bilder->first()->pfad;
        $this->angelegteBildPfade[] = $pfad;
        $this->assertFileExists(public_path(ltrim($pfad, '/')));
    }

    public function test_vorhandene_bilder_bleiben_ohne_neuen_upload_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        $bild = WaffenboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/waffenboerse/bestand.jpg', 'titel' => '', 'sortierung' => 0]);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), $this->grundfelder());

        $this->assertDatabaseHas('waffenboerse_bilder', ['id' => $bild->id]);
    }

    public function test_einzelnes_bild_kann_entfernt_werden_ohne_die_restliche_galerie_zu_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 100, 100),
                UploadedFile::fake()->image('b.jpg', 100, 100),
            ],
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $bilder = $anzeige->bilder()->orderBy('sortierung')->get();
        $this->assertCount(2, $bilder);
        foreach ($bilder as $b) {
            $this->angelegteBildPfade[] = $b->pfad;
        }
        $ersteDatei = public_path(ltrim($bilder[0]->pfad, '/'));
        $zweiteDatei = public_path(ltrim($bilder[1]->pfad, '/'));

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'bild_entfernen' => [$bilder[0]->id],
        ]));

        $this->assertDatabaseMissing('waffenboerse_bilder', ['id' => $bilder[0]->id]);
        $this->assertDatabaseHas('waffenboerse_bilder', ['id' => $bilder[1]->id]);
        $this->assertFileDoesNotExist($ersteDatei);
        $this->assertFileExists($zweiteDatei);
    }

    public function test_maximal_zehn_bilder_werden_akzeptiert(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("bild-{$i}.jpg", 50, 50), range(1, 11)),
        ]));

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseMissing('waffenboerse_anzeigen', ['titel' => 'Formular-Waffe']);
    }

    public function test_zusaetzliche_bilder_ueber_das_limit_beim_update_werden_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        for ($i = 0; $i < 9; $i++) {
            WaffenboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => "/uploads/boersen/waffenboerse/bestand-{$i}.jpg", 'titel' => '', 'sortierung' => $i]);
        }

        $response = $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'images' => [
                UploadedFile::fake()->image('neu1.jpg', 50, 50),
                UploadedFile::fake()->image('neu2.jpg', 50, 50),
            ],
        ]));

        $response->assertSessionHasErrors('images');
        $this->assertCount(9, $anzeige->fresh()->bilder);
    }

    public function test_ungueltiger_dateityp_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseMissing('waffenboerse_anzeigen', ['titel' => 'Formular-Waffe']);
    }

    public function test_bild_entfernen_loescht_auch_thumb_und_card_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('gross.jpg', 900, 900)],
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;

        $original = public_path(ltrim($bild->pfad, '/'));
        $thumb = dirname($original).'/thumb/'.basename($original);
        $card = dirname($original).'/card/'.basename($original);
        $this->assertFileExists($original);
        $this->assertFileExists($thumb);
        $this->assertFileExists($card);

        $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'bild_entfernen' => [$bild->id],
        ]));

        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($thumb);
        $this->assertFileDoesNotExist($card);
    }

    /**
     * TRANSAKTIONSSICHERHEIT (1:1 aus Phase 7I uebernommen, siehe
     * AdminHundeboerseTest::test_bild_entfernen_bleibt_bei_db_fehler_ohne_wirkung()
     * fuer die ausfuehrliche Begruendung, warum ein Eloquent-"saving"-Event
     * statt eines DB-Spaltenlimit-Tricks verwendet wird - "php artisan
     * test" laeuft laut phpunit.xml gegen SQLite, das VARCHAR-Laengen nicht
     * durchsetzt).
     */
    public function test_bild_entfernen_bleibt_bei_db_fehler_ohne_wirkung(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;
        $vollpfad = public_path(ltrim($bild->pfad, '/'));
        $this->assertFileExists($vollpfad);

        WaffenboerseAnzeige::saving(function (WaffenboerseAnzeige $model) use ($anzeige) {
            if ($model->is($anzeige)) {
                throw new \RuntimeException('Simulierter DB-Fehler fuer Test.');
            }
        });

        try {
            $response = $this->put(route('admin.waffenboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
                'bild_entfernen' => [$bild->id],
            ]));
            $response->assertStatus(500);
        } finally {
            WaffenboerseAnzeige::flushEventListeners();
        }

        $this->assertFileExists($vollpfad);
        $this->assertDatabaseHas('waffenboerse_bilder', ['id' => $bild->id]);
    }

    // -----------------------------------------------------------------
    // Löschen
    // -----------------------------------------------------------------

    public function test_anzeige_kann_geloescht_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $response = $this->delete(route('admin.waffenboerse.loeschen', $anzeige));

        $response->assertRedirect(route('admin.waffenboerse.index'));
        $this->assertDatabaseMissing('waffenboerse_anzeigen', ['id' => $anzeige->id]);
    }

    public function test_loeschen_entfernt_zugehoerige_bilder_kaliber_und_dateien(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
            'kaliber' => '9 mm',
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $pfad = $anzeige->bilder->first()->pfad;
        $vollpfad = public_path(ltrim($pfad, '/'));
        $this->assertFileExists($vollpfad);
        $this->assertDatabaseHas('waffenboerse_kaliber', ['anzeige_id' => $anzeige->id]);

        $this->delete(route('admin.waffenboerse.loeschen', $anzeige));

        $this->assertDatabaseMissing('waffenboerse_bilder', ['anzeige_id' => $anzeige->id]);
        $this->assertDatabaseMissing('waffenboerse_kaliber', ['anzeige_id' => $anzeige->id]);
        $this->assertFileDoesNotExist($vollpfad);
    }

    public function test_loeschen_entfernt_auch_thumb_und_card_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('gross.jpg', 900, 900)],
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;

        $original = public_path(ltrim($bild->pfad, '/'));
        $thumb = dirname($original).'/thumb/'.basename($original);
        $card = dirname($original).'/card/'.basename($original);
        $this->assertFileExists($original);
        $this->assertFileExists($thumb);
        $this->assertFileExists($card);

        $this->delete(route('admin.waffenboerse.loeschen', $anzeige));

        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($thumb);
        $this->assertFileDoesNotExist($card);
    }

    /**
     * TRANSAKTIONSSICHERHEIT bei Anzeigen-Loeschung - siehe
     * AdminHundeboerseTest::test_loeschen_bleibt_bei_fehlgeschlagener_transaktion_ohne_wirkung_auf_dateien()
     * fuer die ausfuehrliche Begruendung des Eloquent-Event-Mechanismus.
     */
    public function test_loeschen_bleibt_bei_fehlgeschlagener_transaktion_ohne_wirkung_auf_dateien(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.waffenboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]));
        $anzeige = WaffenboerseAnzeige::where('titel', 'Formular-Waffe')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;
        $vollpfad = public_path(ltrim($bild->pfad, '/'));
        $this->assertFileExists($vollpfad);

        WaffenboerseAnzeige::deleting(function (WaffenboerseAnzeige $model) use ($anzeige) {
            if ($model->is($anzeige)) {
                throw new \RuntimeException('Simulierter DB-Fehler fuer Test.');
            }
        });

        try {
            $response = $this->delete(route('admin.waffenboerse.loeschen', $anzeige));
            $response->assertStatus(500);
        } finally {
            WaffenboerseAnzeige::flushEventListeners();
        }

        $this->assertFileExists($vollpfad);
        $this->assertDatabaseHas('waffenboerse_anzeigen', ['id' => $anzeige->id]);
        $this->assertDatabaseHas('waffenboerse_bilder', ['id' => $bild->id]);
    }

    // -----------------------------------------------------------------
    // Kategorien
    // -----------------------------------------------------------------

    public function test_kategorien_seite_zeigt_bestehende_kategorien(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.waffenboerse.kategorien.index'));

        $response->assertOk();
        $response->assertSee('Kurzwaffen');
        $response->assertSee('Büchsen');
    }

    public function test_neue_kategorie_kann_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.kategorien.speichern'), ['name' => 'Ganz Neue Kategorie']);

        $response->assertRedirect(route('admin.waffenboerse.kategorien.index'));
        $this->assertDatabaseHas('waffenboerse_kategorien', ['name' => 'Ganz Neue Kategorie']);
    }

    public function test_doppelte_kategorie_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.waffenboerse.kategorien.speichern'), ['name' => 'Kurzwaffen']);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, WaffenboerseKategorie::where('name', 'Kurzwaffen')->count());
    }

    public function test_unbenutzte_kategorie_kann_geloescht_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = WaffenboerseKategorie::create(['name' => 'Löschbare Kategorie', 'sortierung' => 99]);

        $response = $this->delete(route('admin.waffenboerse.kategorien.loeschen', $kategorie));

        $response->assertRedirect(route('admin.waffenboerse.kategorien.index'));
        $this->assertDatabaseMissing('waffenboerse_kategorien', ['id' => $kategorie->id]);
    }

    public function test_verwendete_kategorie_kann_nicht_geloescht_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = WaffenboerseKategorie::where('name', 'Kurzwaffen')->firstOrFail();
        $this->anlegen(['kategorie' => 'Kurzwaffen']);

        $response = $this->delete(route('admin.waffenboerse.kategorien.loeschen', $kategorie));

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseHas('waffenboerse_kategorien', ['id' => $kategorie->id]);
    }

    // -----------------------------------------------------------------
    // Regression: öffentliche Waffenbörse & altes JSON-Admin unangetastet
    // -----------------------------------------------------------------

    public function test_oeffentliche_uebersicht_bleibt_regressionsfrei(): void
    {
        $this->anlegen(['id' => 'wb-pub', 'status' => 'published', 'titel' => 'Öffentlich sichtbare Waffe']);
        $this->anlegen(['id' => 'wb-pending', 'status' => 'pending', 'titel' => 'Nicht sichtbare Waffe']);

        $response = $this->get('/waffenboerse');

        $response->assertOk();
        $response->assertSee('Öffentlich sichtbare Waffe');
        $response->assertDontSee('Nicht sichtbare Waffe');
    }

    public function test_oeffentliche_detailseite_bleibt_regressionsfrei(): void
    {
        $anzeige = $this->anlegen(['status' => 'published', 'titel' => 'Detailseiten-Waffe']);

        $this->get('/waffenboerse/detail/'.$anzeige->id)->assertOk()->assertSee('Detailseiten-Waffe');
    }

    public function test_nicht_veroeffentlichte_anzeige_liefert_404_auf_der_oeffentlichen_detailseite(): void
    {
        $anzeige = $this->anlegen(['status' => 'pending', 'titel' => 'Noch nicht öffentliche Waffe']);

        $this->get('/waffenboerse/detail/'.$anzeige->id)->assertNotFound();
    }

    public function test_alte_json_datei_wird_von_diesem_modul_nicht_angefasst(): void
    {
        $pfad = base_path('../content/waffenboerse.json');
        $vorherigerInhalt = File::exists($pfad) ? File::get($pfad) : null;

        $this->actingAs($this->admin(), 'web');
        $this->anlegen();
        $this->post(route('admin.waffenboerse.speichern'), $this->grundfelder());

        if ($vorherigerInhalt !== null) {
            $this->assertSame($vorherigerInhalt, File::get($pfad), 'Admin-Schreibweg darf content/waffenboerse.json nicht verändern.');
        }
    }

    public function test_sidebar_link_ist_aktiv(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.waffenboerse.index'));

        $response->assertOk();
        $response->assertSee('Waffenbörse');
    }
}
