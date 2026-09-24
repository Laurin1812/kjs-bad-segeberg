<?php

namespace Tests\Feature\Phase7I;

use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseBild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 7I (Admin-Modul "Hundeboerse"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/hundeboerse" ab (Http\Controllers\Admin\
 * HundeboerseController) - siehe dortiger Klassenkommentar fuer die
 * vollstaendige Alt-Admin-/Datenmodell-Analyse (Hundeboerse ist bereits seit
 * Phase 6A auf Laravel/MySQL migriert, hier kommt ausschliesslich die
 * Admin-Oberflaeche fuer diese bereits lebende Datenquelle dazu).
 *
 * Echte Datei-Uploads landen unter public/uploads/boersen/hundeboerse/ -
 * jeder Test, der eine Anzeige mit Bildern anlegt, raeumt sie am Ende wieder
 * auf (siehe tearDown()), damit keine Testartefakte im Arbeitsverzeichnis
 * zurueckbleiben (Auftrag "Testartefakte strikt von echten Dateien
 * unterscheiden").
 */
class AdminHundeboerseTest extends TestCase
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

    private function anlegen(array $overrides = []): HundeboerseAnzeige
    {
        return HundeboerseAnzeige::create(array_merge([
            'id' => 'hb-test-'.uniqid(),
            'status' => 'pending',
            'type' => 'single',
            'title' => 'Testhund',
            'breed' => 'Deutsch Kurzhaar',
            'postal_code' => '23795',
            'city' => 'Bad Segeberg',
            'description' => 'Ein freundlicher Testhund.',
            'provider_name' => 'Max Mustermann',
            'email' => 'max@example.test',
            'birth_date' => '12.03.2024',
            'gender' => 'male',
            'price_type' => 'on_request',
        ], $overrides));
    }

    private function grundfelder(array $overrides = []): array
    {
        return array_merge([
            'status' => 'pending',
            'type' => 'single',
            'title' => 'Formular-Hund',
            'breed' => 'Labrador',
            'price_type' => 'on_request',
            'postal_code' => '23795',
            'city' => 'Bad Segeberg',
            'provider_name' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.hundeboerse.index'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.hundeboerse.index'))->assertForbidden();
    }

    public function test_alle_hundeboerse_admin_routen_sind_geschuetzt(): void
    {
        $anzeige = $this->anlegen();

        $this->get(route('admin.hundeboerse.neu'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.hundeboerse.bearbeiten', $anzeige))->assertRedirect(route('admin.login'));
        $this->post(route('admin.hundeboerse.speichern'), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.hundeboerse.freigeben', $anzeige))->assertRedirect(route('admin.login'));
        $this->put(route('admin.hundeboerse.archivieren', $anzeige))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.hundeboerse.loeschen', $anzeige))->assertRedirect(route('admin.login'));
    }

    // -----------------------------------------------------------------
    // Liste / Filter
    // -----------------------------------------------------------------

    public function test_liste_zeigt_alle_status(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anlegen(['id' => 'hb-p', 'status' => 'pending', 'title' => 'Wartender Hund']);
        $this->anlegen(['id' => 'hb-v', 'status' => 'published', 'title' => 'Veröffentlichter Hund']);
        $this->anlegen(['id' => 'hb-a', 'status' => 'rejected', 'title' => 'Abgelehnter Hund']);
        $this->anlegen(['id' => 'hb-r', 'status' => 'archived', 'title' => 'Archivierter Hund']);

        $response = $this->get(route('admin.hundeboerse.index'));

        $response->assertOk();
        $response->assertSee('Wartender Hund');
        $response->assertSee('Veröffentlichter Hund');
        $response->assertSee('Abgelehnter Hund');
        $response->assertSee('Archivierter Hund');
    }

    public function test_statusfilter_zeigt_nur_passende_anzeigen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anlegen(['id' => 'hb-p', 'status' => 'pending', 'title' => 'Wartender Hund']);
        $this->anlegen(['id' => 'hb-v', 'status' => 'published', 'title' => 'Veröffentlichter Hund']);

        $response = $this->get(route('admin.hundeboerse.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('Wartender Hund');
        $response->assertDontSee('Veröffentlichter Hund');
    }

    public function test_einzelhund_und_wurf_werden_in_der_liste_unterschieden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->anlegen(['id' => 'hb-single', 'type' => 'single', 'title' => 'Einzelhund-Anzeige']);
        $this->anlegen(['id' => 'hb-litter', 'type' => 'litter', 'title' => 'Wurf-Anzeige', 'litter_date' => '01.05.2026']);

        $response = $this->get(route('admin.hundeboerse.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Einzelhund-Anzeige', 'EINZELHUND']);
        $response->assertSeeInOrder(['Wurf-Anzeige', 'WURF']);
    }

    // -----------------------------------------------------------------
    // Anlegen
    // -----------------------------------------------------------------

    public function test_neu_formular_ist_erreichbar(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.hundeboerse.neu'))->assertOk();
    }

    public function test_einzelhund_kann_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder([
            'type' => 'single',
            'dog_name' => 'Bello',
            'gender' => 'male',
            'birth_date' => '2024-03-12',
        ]));

        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $response->assertRedirect(route('admin.hundeboerse.bearbeiten', $anzeige));
        $this->assertSame('single', $anzeige->type);
        $this->assertSame('Bello', $anzeige->dog_name);
        $this->assertSame('12.03.2024', $anzeige->birth_date);
        $this->assertStringStartsWith('hb-', $anzeige->id);
    }

    public function test_wurf_kann_angelegt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder([
            'type' => 'litter',
            'litter_date' => '2026-05-01',
            'male_count' => '3',
            'female_count' => '2',
        ]));

        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $response->assertRedirect(route('admin.hundeboerse.bearbeiten', $anzeige));
        $this->assertSame('litter', $anzeige->type);
        $this->assertSame('01.05.2026', $anzeige->litter_date);
        $this->assertSame('3', $anzeige->male_count);
        $this->assertSame('2', $anzeige->female_count);
    }

    /**
     * Siehe HundeboerseController-Klassenkommentar "Defaultstatus nach
     * Admin-Anlage": 1:1 wie admin.js' hundeboerseNeu() ist "pending" der
     * Formular-Default, der Status ist aber ganz normal ueber das Dropdown
     * bereits beim Anlegen frei waehlbar (kein erzwungenes "immer pending"
     * wie bei der oeffentlichen Einreichung).
     */
    public function test_admin_kann_eine_anzeige_direkt_als_veroeffentlicht_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder(['status' => 'published']));

        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $this->assertSame('published', $anzeige->status);
    }

    /**
     * Regression (per Browser-QA gefunden): ein echtes Browser-Formular
     * sendet JEDES gerenderte Textfeld mit, auch leer gelassene (anders als
     * ein PHPUnit-Request mit nur ausgewaehlten Schluesseln) - Laravels
     * ConvertEmptyStringsToNull-Middleware wandelt das leere Feld dann in
     * null um. Fuer NOT-NULL-Spalten mit Default '' (z.B. "color") darf das
     * nicht zu einem SQL-Fehler fuehren (siehe HundeboerseUpdater::
     * applyFields()-Klassenkommentar "NULL-HANDLING").
     */
    public function test_leer_abgeschickte_pflichtspalten_erzeugen_keinen_datenbankfehler(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'color' => '',
            'coat' => '',
            'father' => '',
            'gender' => '',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $this->assertSame('', $anzeige->color);
        $this->assertSame('', $anzeige->coat);
    }

    public function test_neue_anzeige_ohne_bilder_ist_gueltig(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder());

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('hundeboerse_anzeigen', ['title' => 'Formular-Hund']);
    }

    public function test_ungueltiger_status_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder(['status' => 'unbekannt']));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('hundeboerse_anzeigen', ['title' => 'Formular-Hund']);
    }

    // -----------------------------------------------------------------
    // Bearbeiten / Preservation
    // -----------------------------------------------------------------

    public function test_bearbeiten_formular_ist_erreichbar_und_zeigt_werte(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['title' => 'Anzuzeigender Hund']);

        $response = $this->get(route('admin.hundeboerse.bearbeiten', $anzeige));

        $response->assertOk();
        $response->assertSee('Anzuzeigender Hund');
    }

    public function test_felder_werden_gespeichert(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $response = $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge(
            $this->grundfelder(),
            ['title' => 'Geänderter Titel', 'aktion' => 'speichern']
        ));

        $response->assertRedirect(route('admin.hundeboerse.bearbeiten', $anzeige));
        $this->assertSame('Geänderter Titel', $anzeige->fresh()->title);
    }

    public function test_teil_update_erhaelt_nicht_gesendete_felder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen([
            'father' => 'Rex vom Wald',
            'contact_person' => 'Frau Beispiel',
            'gallery_title' => 'Unsere Bilder',
        ]);

        // Absichtlich ein minimaler Teil-Request (nur Pflicht-Enums + neuer
        // Titel) - father/contact_person/gallery_title werden NICHT
        // mitgesendet und muessen erhalten bleiben (Preservation).
        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), [
            'status' => $anzeige->status,
            'type' => $anzeige->type,
            'price_type' => $anzeige->price_type,
            'title' => 'Nur der Titel ändert sich',
        ]);

        $anzeige->refresh();
        $this->assertSame('Nur der Titel ändert sich', $anzeige->title);
        $this->assertSame('Rex vom Wald', $anzeige->father);
        $this->assertSame('Frau Beispiel', $anzeige->contact_person);
        $this->assertSame('Unsere Bilder', $anzeige->gallery_title);
    }

    public function test_zuchtverband_bleibt_ohne_checkbox_im_request_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['has_zuchtverband' => true, 'zuchtverband' => 'JGHV-Testverband']);

        // has_zuchtverband fehlt hier bewusst komplett (wie bei einem
        // Teil-Request/aelteren Tab) - Preservation muss greifen.
        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), [
            'status' => $anzeige->status,
            'type' => $anzeige->type,
            'price_type' => $anzeige->price_type,
            'title' => $anzeige->title,
        ]);

        $anzeige->refresh();
        $this->assertTrue($anzeige->has_zuchtverband);
        $this->assertSame('JGHV-Testverband', $anzeige->zuchtverband);
    }

    public function test_neuer_zuchtverband_wird_der_vorschlagsliste_hinzugefuegt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'has_zuchtverband' => '1',
            'zuchtverband' => 'Ganz Neuer Verband e.V.',
        ]));

        $this->assertDatabaseHas('hundeboerse_zuchtverbaende', ['name' => 'Ganz Neuer Verband e.V.']);
    }

    // -----------------------------------------------------------------
    // Status-Workflow
    // -----------------------------------------------------------------

    public function test_pending_kann_freigegeben_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending', 'title' => 'Bleibt unverändert']);

        $response = $this->put(route('admin.hundeboerse.freigeben', $anzeige));

        $response->assertRedirect();
        $anzeige->refresh();
        $this->assertSame('published', $anzeige->status);
        $this->assertSame('Bleibt unverändert', $anzeige->title);
    }

    public function test_speichern_und_ablehnen_setzt_status_und_speichert_felder_in_einem_schritt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending']);

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'pending', // Dropdown-Wert wird von "aktion" ueberschrieben
            'title' => 'Beim Ablehnen geänderter Titel',
            'aktion' => 'ablehnen',
        ]));

        $anzeige->refresh();
        $this->assertSame('rejected', $anzeige->status);
        $this->assertSame('Beim Ablehnen geänderter Titel', $anzeige->title);
    }

    public function test_speichern_und_freigeben_setzt_status_und_speichert_felder_in_einem_schritt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending']);

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'pending',
            'title' => 'Beim Freigeben geänderter Titel',
            'aktion' => 'freigeben',
        ]));

        $anzeige->refresh();
        $this->assertSame('published', $anzeige->status);
        $this->assertSame('Beim Freigeben geänderter Titel', $anzeige->title);
    }

    public function test_published_oder_rejected_koennen_archiviert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $veroeffentlicht = $this->anlegen(['id' => 'hb-v', 'status' => 'published']);
        $abgelehnt = $this->anlegen(['id' => 'hb-a', 'status' => 'rejected']);

        $this->put(route('admin.hundeboerse.archivieren', $veroeffentlicht));
        $this->put(route('admin.hundeboerse.archivieren', $abgelehnt));

        $this->assertSame('archived', $veroeffentlicht->fresh()->status);
        $this->assertSame('archived', $abgelehnt->fresh()->status);
    }

    /**
     * Siehe HundeboerseController-Klassenkommentar "Wiederherstellen": keine
     * eigene Route - eine archivierte Anzeige wird ganz normal ueber das
     * Bearbeiten-Formular samt Status-Dropdown zurueckgestuft.
     */
    public function test_archivierte_anzeige_kann_ueber_das_bearbeiten_formular_wiederhergestellt_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'archived']);

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'status' => 'published',
        ]));

        $this->assertSame('published', $anzeige->fresh()->status);
    }

    public function test_statuswechsel_veraendert_keine_inhaltsfelder(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen(['status' => 'pending', 'title' => 'Unveränderter Titel', 'breed' => 'Beagle']);

        $this->put(route('admin.hundeboerse.freigeben', $anzeige));

        $anzeige->refresh();
        $this->assertSame('Unveränderter Titel', $anzeige->title);
        $this->assertSame('Beagle', $anzeige->breed);
    }

    // -----------------------------------------------------------------
    // Bilder
    // -----------------------------------------------------------------

    public function test_bild_kann_beim_anlegen_hochgeladen_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('hund.jpg', 200, 200)],
        ]));

        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $response->assertRedirect(route('admin.hundeboerse.bearbeiten', $anzeige));
        $this->assertCount(1, $anzeige->bilder);
        $pfad = $anzeige->bilder->first()->pfad;
        $this->angelegteBildPfade[] = $pfad;
        $this->assertFileExists(public_path(ltrim($pfad, '/')));
    }

    public function test_vorhandene_bilder_bleiben_ohne_neuen_upload_erhalten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        $bild = HundeboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/hundeboerse/bestand.jpg', 'titel' => '', 'sortierung' => 0]);

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), $this->grundfelder());

        $this->assertDatabaseHas('hundeboerse_bilder', ['id' => $bild->id]);
    }

    public function test_einzelnes_bild_kann_entfernt_werden_ohne_die_restliche_galerie_zu_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $upload = $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 100, 100),
                UploadedFile::fake()->image('b.jpg', 100, 100),
            ],
        ]));
        $neueAnzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $bilder = $neueAnzeige->bilder()->orderBy('sortierung')->get();
        $this->assertCount(2, $bilder);
        foreach ($bilder as $b) {
            $this->angelegteBildPfade[] = $b->pfad;
        }
        $ersteDatei = public_path(ltrim($bilder[0]->pfad, '/'));
        $zweiteDatei = public_path(ltrim($bilder[1]->pfad, '/'));

        $this->put(route('admin.hundeboerse.aktualisieren', $neueAnzeige), array_merge($this->grundfelder(), [
            'bild_entfernen' => [$bilder[0]->id],
        ]));

        $this->assertDatabaseMissing('hundeboerse_bilder', ['id' => $bilder[0]->id]);
        $this->assertDatabaseHas('hundeboerse_bilder', ['id' => $bilder[1]->id]);
        $this->assertFileDoesNotExist($ersteDatei);
        $this->assertFileExists($zweiteDatei);
    }

    public function test_maximal_zehn_bilder_werden_akzeptiert(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("bild-{$i}.jpg", 50, 50), range(1, 11)),
        ]));

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseMissing('hundeboerse_anzeigen', ['title' => 'Formular-Hund']);
    }

    public function test_zusaetzliche_bilder_ueber_das_limit_beim_update_werden_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();
        for ($i = 0; $i < 9; $i++) {
            HundeboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => "/uploads/boersen/hundeboerse/bestand-{$i}.jpg", 'titel' => '', 'sortierung' => $i]);
        }

        $response = $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
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

        $response = $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseMissing('hundeboerse_anzeigen', ['title' => 'Formular-Hund']);
    }

    // -----------------------------------------------------------------
    // Transaktionssicherheit (Korrektur nach Auslieferung, Auftraggeber-Fund)
    //
    // HundeboerseUpdater::removeBilder() und HundeboerseController::destroy()
    // loeschten physische Dateien urspruenglich INNERHALB der jeweiligen
    // DB::transaction()-Closure - eine Dateisystem-Loeschung kann ein
    // DB-Rollback aber nicht rueckgaengig machen. Schlug eine spaetere
    // Operation in derselben Transaktion fehl, waere die Datei trotz
    // zurueckgerolltem DB-Stand unwiederbringlich weg gewesen (echtes
    // Datenverlust-Risiko bei Kundendateien). Seit der Korrektur werden
    // Pfade vor der Transaktion gesammelt/aus ihr zurueckgegeben und
    // physische Dateien erst NACH einem erfolgreichen Commit geloescht.
    // -----------------------------------------------------------------

    /**
     * Simuliert einen DB-Fehler NACH der "Vorbereitung" (removeBilder() hat
     * die Bild-DB-Zeile zu diesem Zeitpunkt in derselben Transaktion bereits
     * geloescht) per Eloquent-"saving"-Event (Standard-Laravel-Mechanismus,
     * kein Mocking-Framework/keine neue Architektur) - applyFields() ruft
     * direkt danach $anzeige->save() auf, wo der Handler eine Exception
     * wirft, gleichwertig zu einem echten DB-Fehler an dieser Stelle.
     *
     * Bewusst NICHT per ueberlangem "gallery_title" (> 190 Zeichen, DB-Spalte
     * string('gallery_title', 190)) simuliert: das wuerde unter echtem MySQL
     * mit striktem SQL-Modus (config/database.php: 'strict' => true) zwar
     * zuverlaessig eine QueryException ausloesen, `php artisan test` laeuft
     * laut phpunit.xml aber gegen SQLite (":memory:"), das deklarierte
     * VARCHAR-Laengen nicht durchsetzt - der Trick waere dort wirkungslos.
     * Das Eloquent-Event ist DB-Treiber-unabhaengig und damit robuster.
     */
    public function test_bild_entfernen_bleibt_bei_db_fehler_ohne_wirkung(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]));
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;
        $vollpfad = public_path(ltrim($bild->pfad, '/'));
        $this->assertFileExists($vollpfad);

        HundeboerseAnzeige::saving(function (HundeboerseAnzeige $model) use ($anzeige) {
            if ($model->is($anzeige)) {
                throw new \RuntimeException('Simulierter DB-Fehler fuer Test.');
            }
        });

        try {
            $response = $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
                'bild_entfernen' => [$bild->id],
            ]));
            $response->assertStatus(500);
        } finally {
            HundeboerseAnzeige::flushEventListeners();
        }

        $this->assertFileExists($vollpfad);
        $this->assertDatabaseHas('hundeboerse_bilder', ['id' => $bild->id]);
    }

    /**
     * Simuliert einen DB-Fehler waehrend destroy()'s Transaktion per
     * Eloquent-"deleting"-Event (Standard-Laravel-Mechanismus, kein
     * Mocking-Framework/keine neue Architektur) - der Handler wirft
     * innerhalb von $hundeboerseAnzeige->delete(), also mitten in der
     * echten DB::transaction()-Closure aus destroy(), eine Exception,
     * gleichwertig zu einem echten DB-Fehler an dieser Stelle. Die Bildpfade
     * wurden zu diesem Zeitpunkt bereits (vor der Transaktion) eingesammelt -
     * es darf trotzdem keine Datei geloescht worden sein.
     */
    public function test_loeschen_bleibt_bei_fehlgeschlagener_transaktion_ohne_wirkung_auf_dateien(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]));
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;
        $vollpfad = public_path(ltrim($bild->pfad, '/'));
        $this->assertFileExists($vollpfad);

        HundeboerseAnzeige::deleting(function (HundeboerseAnzeige $model) use ($anzeige) {
            if ($model->is($anzeige)) {
                throw new \RuntimeException('Simulierter DB-Fehler fuer Test.');
            }
        });

        try {
            $response = $this->delete(route('admin.hundeboerse.loeschen', $anzeige));
            $response->assertStatus(500);
        } finally {
            HundeboerseAnzeige::flushEventListeners();
        }

        $this->assertFileExists($vollpfad);
        $this->assertDatabaseHas('hundeboerse_anzeigen', ['id' => $anzeige->id]);
        $this->assertDatabaseHas('hundeboerse_bilder', ['id' => $bild->id]);
    }

    /**
     * Erfolgsfall (Auftrag Punkt 3 "Erfolgsfall weiterhin: DB-Zeile weg,
     * physische Originaldatei weg, thumb/card weg"): die uebrigen Bilder-
     * Tests nutzen bewusst kleine 100x100/200x200-Bilder, bei denen
     * BoerseUploads::generateVariants() mangels Ueberschreitung der
     * Schwellenwerte (480/800px, siehe dortige VARIANTS-Konstante) gar keine
     * thumb-/card-Datei erzeugt - "thumb/card weg" war dadurch bislang nie
     * wirklich getestet. Nutzt hier bewusst ein 900x900-Bild, das beide
     * Schwellenwerte ueberschreitet.
     */
    public function test_bild_entfernen_loescht_auch_thumb_und_card_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('gross.jpg', 900, 900)],
        ]));
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;

        $original = public_path(ltrim($bild->pfad, '/'));
        $thumb = dirname($original).'/thumb/'.basename($original);
        $card = dirname($original).'/card/'.basename($original);
        $this->assertFileExists($original);
        $this->assertFileExists($thumb);
        $this->assertFileExists($card);

        $this->put(route('admin.hundeboerse.aktualisieren', $anzeige), array_merge($this->grundfelder(), [
            'bild_entfernen' => [$bild->id],
        ]));

        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($thumb);
        $this->assertFileDoesNotExist($card);
        $this->assertDatabaseMissing('hundeboerse_bilder', ['id' => $bild->id]);
    }

    /**
     * Siehe Klassenkommentar der vorigen Methode - dasselbe fuer destroy().
     */
    public function test_loeschen_entfernt_auch_thumb_und_card_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('gross.jpg', 900, 900)],
        ]));
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $bild = $anzeige->bilder->first();
        $this->angelegteBildPfade[] = $bild->pfad;

        $original = public_path(ltrim($bild->pfad, '/'));
        $thumb = dirname($original).'/thumb/'.basename($original);
        $card = dirname($original).'/card/'.basename($original);
        $this->assertFileExists($original);
        $this->assertFileExists($thumb);
        $this->assertFileExists($card);

        $this->delete(route('admin.hundeboerse.loeschen', $anzeige));

        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($thumb);
        $this->assertFileDoesNotExist($card);
    }

    // -----------------------------------------------------------------
    // Löschen
    // -----------------------------------------------------------------

    public function test_anzeige_kann_geloescht_werden(): void
    {
        $this->actingAs($this->admin(), 'web');
        $anzeige = $this->anlegen();

        $response = $this->delete(route('admin.hundeboerse.loeschen', $anzeige));

        $response->assertRedirect(route('admin.hundeboerse.index'));
        $this->assertDatabaseMissing('hundeboerse_anzeigen', ['id' => $anzeige->id]);
    }

    public function test_loeschen_entfernt_zugehoerige_bilder_und_dateien(): void
    {
        $this->actingAs($this->admin(), 'web');

        $upload = $this->actingAs($this->admin(), 'web')->post(route('admin.hundeboerse.speichern'), array_merge($this->grundfelder(), [
            'images' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]));
        $anzeige = HundeboerseAnzeige::where('title', 'Formular-Hund')->firstOrFail();
        $pfad = $anzeige->bilder->first()->pfad;
        $vollpfad = public_path(ltrim($pfad, '/'));
        $this->assertFileExists($vollpfad);

        $this->delete(route('admin.hundeboerse.loeschen', $anzeige));

        $this->assertDatabaseMissing('hundeboerse_bilder', ['anzeige_id' => $anzeige->id]);
        $this->assertFileDoesNotExist($vollpfad);
    }

    // -----------------------------------------------------------------
    // Regression: öffentliche Hundebörse & altes JSON-Admin unangetastet
    // -----------------------------------------------------------------

    public function test_oeffentliche_uebersicht_bleibt_regressionsfrei(): void
    {
        $this->anlegen(['id' => 'hb-pub', 'status' => 'published', 'title' => 'Öffentlich sichtbarer Hund']);
        $this->anlegen(['id' => 'hb-pending', 'status' => 'pending', 'title' => 'Nicht sichtbarer Hund']);

        $response = $this->get('/hundeboerse');

        $response->assertOk();
        $response->assertSee('Öffentlich sichtbarer Hund');
        $response->assertDontSee('Nicht sichtbarer Hund');
    }

    public function test_oeffentliche_detailseite_bleibt_regressionsfrei(): void
    {
        $anzeige = $this->anlegen(['status' => 'published', 'title' => 'Detailseiten-Hund']);

        $this->get('/hundeboerse/detail/'.$anzeige->id)->assertOk()->assertSee('Detailseiten-Hund');
    }

    public function test_oeffentliche_einreichung_landet_weiterhin_im_status_pending(): void
    {
        $response = $this->post('/hundeboerse/anbieten', [
            'type' => 'single',
            'title' => 'Frisch eingereichter Hund',
            'breed' => 'Mischling',
            'dogName' => 'Fiffi',
            'birthDate' => '2024-01-01',
            'gender' => 'male',
            'priceType' => 'on_request',
            'postalCode' => '23795',
            'city' => 'Bad Segeberg',
            'description' => 'Ein toller Hund.',
            'providerName' => 'Testperson',
            'email' => 'test@example.test',
            'images' => [UploadedFile::fake()->image('einreichung.jpg', 100, 100)],
            'confirmCorrect' => '1',
            'confirmPrivacy' => '1',
        ]);

        $response->assertRedirect(route('hundeboerse.anbieten'));
        $anzeige = HundeboerseAnzeige::where('title', 'Frisch eingereichter Hund')->firstOrFail();
        $this->assertSame('pending', $anzeige->status);
        foreach ($anzeige->bilder as $bild) {
            $this->angelegteBildPfade[] = $bild->pfad;
        }
    }

    public function test_alte_json_datei_wird_von_diesem_modul_nicht_angefasst(): void
    {
        $pfad = base_path('../content/hundeboerse.json');
        $vorherigerInhalt = File::exists($pfad) ? File::get($pfad) : null;

        $this->actingAs($this->admin(), 'web');
        $this->anlegen();
        $this->post(route('admin.hundeboerse.speichern'), $this->grundfelder());

        if ($vorherigerInhalt !== null) {
            $this->assertSame($vorherigerInhalt, File::get($pfad), 'Admin-Schreibweg darf content/hundeboerse.json nicht verändern.');
        }
    }

    public function test_sidebar_link_ist_aktiv(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->get(route('admin.hundeboerse.index'));

        $response->assertOk();
        $response->assertSee('Hundebörse');
    }
}
