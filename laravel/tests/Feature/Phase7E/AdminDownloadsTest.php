<?php

namespace Tests\Feature\Phase7E;

use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\Page;
use App\Models\User;
use App\Support\ContentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7E (Admin-Modul "Downloads", zentrale Download-Bibliothek): deckt
 * den neuen, server-gerenderten Bereich unter "/admin/downloads" ab
 * (Http\Controllers\Admin\DownloadsController) - Liste (inkl. leerer
 * Kategorien), Kategorie anlegen/umbenennen/löschen, Download anlegen/
 * bearbeiten/löschen, Titel/Einleitungstext. Die BESTEHENDE JSON-Schreib-API
 * unter "/api/admin/content/downloads.json" (admin.js, Api\Admin\
 * AdminListController::downloads()) bleibt unveraendert nutzbar - siehe
 * test_json_admin_api_*() unten. Besonderer Schwerpunkt (Analysebericht
 * Punkt D "Scope-Schutz wegen geteilter downloads-Tabelle"): seiteneigene,
 * eingebettete Downloads (owner_type/owner_id gesetzt) duerfen durch dieses
 * Modul unter keinen Umstaenden sichtbar, bearbeitbar oder loeschbar sein.
 */
class AdminDownloadsTest extends TestCase
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

    private function kategorie(array $overrides = []): DownloadKategorie
    {
        return DownloadKategorie::create(array_merge([
            'titel' => 'Satzung & Ordnungen',
            'sortierung' => 0,
        ], $overrides));
    }

    private function download(DownloadKategorie $kategorie, array $overrides = []): Download
    {
        return Download::create(array_merge([
            'kategorie_id' => $kategorie->id,
            'owner_type' => null,
            'owner_id' => null,
            'titel' => 'Satzung 2026',
            'beschreibung' => 'Die aktuelle Vereinssatzung.',
            'typ' => 'PDF',
            'pfad' => '/downloads/satzung-2026.pdf',
            'sortierung' => 0,
        ], $overrides));
    }

    /**
     * Seiteneigener, eingebetteter Download (owner_type/owner_id gesetzt,
     * kategorie_id NULL) - 1:1 dasselbe Fixture-Muster wie Phase3/
     * SeitenfamilienTest.php, hier zur Absicherung des Scope-Schutzes.
     */
    private function embeddedDownload(): Download
    {
        $page = Page::create(['section' => 'weitere', 'slug' => 'test-seite', 'titel' => 'Testseite', 'inhalt' => '<p>Test</p>']);

        return $page->downloads()->create(['titel' => 'Seiteneigenes Dokument', 'pfad' => '/downloads/seite.pdf', 'sortierung' => 0]);
    }

    // -----------------------------------------------------------------
    // Zugriffsschutz
    // -----------------------------------------------------------------

    public function test_gast_wird_von_allen_admin_downloads_routen_zum_login_umgeleitet(): void
    {
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);

        $this->get(route('admin.downloads.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.downloads.kategorien.anlegen'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.downloads.kategorien.umbenennen', $kategorie))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.downloads.kategorien.loeschen', $kategorie))->assertRedirect(route('admin.login'));
        $this->post(route('admin.downloads.eintraege.anlegen', $kategorie))->assertRedirect(route('admin.login'));
        $this->put(route('admin.downloads.eintraege.aktualisieren', $download))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.downloads.eintraege.loeschen', $download))->assertRedirect(route('admin.login'));
        $this->post(route('admin.downloads.einstellungen'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403_auf_allen_admin_downloads_routen(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);

        $this->get(route('admin.downloads.index'))->assertForbidden();
        $this->post(route('admin.downloads.kategorien.anlegen'))->assertForbidden();
        $this->put(route('admin.downloads.kategorien.umbenennen', $kategorie))->assertForbidden();
        $this->delete(route('admin.downloads.kategorien.loeschen', $kategorie))->assertForbidden();
        $this->post(route('admin.downloads.eintraege.anlegen', $kategorie))->assertForbidden();
        $this->put(route('admin.downloads.eintraege.aktualisieren', $download))->assertForbidden();
        $this->delete(route('admin.downloads.eintraege.loeschen', $download))->assertForbidden();
        $this->post(route('admin.downloads.einstellungen'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Liste
    // -----------------------------------------------------------------

    public function test_liste_zeigt_kategorien_mit_downloads(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie(['titel' => 'Formulare']);
        $this->download($kategorie, ['titel' => 'Aufnahmeantrag']);

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        $response->assertSee('Formulare');
        $response->assertSee('Aufnahmeantrag');
    }

    public function test_liste_zeigt_auch_leere_kategorien(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->kategorie(['titel' => 'Noch leere Kategorie']);

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        $response->assertSee('Noch leere Kategorie');
    }

    public function test_liste_zeigt_kategorien_und_downloads_in_richtiger_sortierung(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kat1 = $this->kategorie(['titel' => 'Zweite Kategorie', 'sortierung' => 1]);
        $kat0 = $this->kategorie(['titel' => 'Erste Kategorie', 'sortierung' => 0]);
        $this->download($kat0, ['titel' => 'B-Download', 'sortierung' => 1]);
        $this->download($kat0, ['titel' => 'A-Download', 'sortierung' => 0]);

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Zweite Kategorie'), strpos($html, 'Erste Kategorie'));
        $this->assertLessThan(strpos($html, 'B-Download'), strpos($html, 'A-Download'));
    }

    public function test_liste_zeigt_eingebettete_downloads_nicht(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->embeddedDownload();

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        $response->assertDontSee('Seiteneigenes Dokument');
    }

    // -----------------------------------------------------------------
    // Kategorie
    // -----------------------------------------------------------------

    public function test_kategorie_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $versionVorher = ContentVersioning::current('downloads');

        $response = $this->post(route('admin.downloads.kategorien.anlegen'), [
            'expected_version' => $versionVorher,
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertDatabaseHas('download_kategorien', ['titel' => 'Neue Kategorie']);
        $this->assertSame($versionVorher + 1, ContentVersioning::current('downloads'));
    }

    public function test_kategorie_umbenennen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie(['titel' => 'Alter Name']);

        $response = $this->put(route('admin.downloads.kategorien.umbenennen', $kategorie), [
            'titel' => 'Neuer Name',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertSame('Neuer Name', $kategorie->fresh()->titel);
    }

    public function test_kategorie_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();

        $response = $this->delete(route('admin.downloads.kategorien.loeschen', $kategorie), [
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertNull(DownloadKategorie::find($kategorie->id));
    }

    public function test_kategorie_loeschen_loescht_enthaltene_downloads_per_cascade(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);

        $response = $this->delete(route('admin.downloads.kategorien.loeschen', $kategorie), [
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertNull(Download::find($download->id));
    }

    public function test_kategorie_loeschen_laesst_eingebettete_downloads_unangetastet(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $this->download($kategorie);
        $embedded = $this->embeddedDownload();

        $this->delete(route('admin.downloads.kategorien.loeschen', $kategorie), [
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $this->assertNotNull(Download::find($embedded->id));
    }

    public function test_kategorie_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie(['titel' => 'Unveraendert']);

        $response = $this->put(route('admin.downloads.kategorien.umbenennen', $kategorie), [
            'titel' => 'Sollte nicht gespeichert werden',
            'expected_version' => ContentVersioning::current('downloads') + 1,
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $response->assertSessionHas('downloads_fehler');
        $this->assertSame('Unveraendert', $kategorie->fresh()->titel);
    }

    // -----------------------------------------------------------------
    // Download
    // -----------------------------------------------------------------

    public function test_download_anlegen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();

        $response = $this->post(route('admin.downloads.eintraege.anlegen', $kategorie), [
            'titel' => 'Neues Dokument',
            'beschreibung' => 'Eine Beschreibung.',
            'pfad' => '/downloads/neu.pdf',
            'typ' => 'Word',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $download = Download::where('titel', 'Neues Dokument')->firstOrFail();
        $this->assertSame($kategorie->id, $download->kategorie_id);
        $this->assertNull($download->owner_type);
        $this->assertSame('Word', $download->typ);
    }

    public function test_download_anlegen_ohne_titel_faellt_auf_pfad_zurueck(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();

        $response = $this->post(route('admin.downloads.eintraege.anlegen', $kategorie), [
            'pfad' => '/downloads/ohne-titel.pdf',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $download = Download::where('pfad', '/downloads/ohne-titel.pdf')->firstOrFail();
        $this->assertSame('/downloads/ohne-titel.pdf', $download->titel);
    }

    public function test_download_anlegen_ohne_pfad_schlaegt_fehl(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();

        $response = $this->post(route('admin.downloads.eintraege.anlegen', $kategorie), [
            'titel' => 'Ohne Pfad',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertSessionHasErrors('pfad');
        $this->assertNull(Download::where('titel', 'Ohne Pfad')->first());
    }

    public function test_download_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);

        $response = $this->put(route('admin.downloads.eintraege.aktualisieren', $download), [
            'titel' => 'Geänderter Titel',
            'beschreibung' => $download->beschreibung,
            'pfad' => $download->pfad,
            'typ' => $download->typ,
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertSame('Geänderter Titel', $download->fresh()->titel);
    }

    /**
     * Preservation (Analysebericht Punkt C): ein Teil-Request, der ein
     * Feld gar nicht mitschickt, darf dessen bestehenden Wert nicht auf
     * Default/leer zuruecksetzen - insbesondere darf "pfad" nicht verloren
     * gehen, nur weil nur "beschreibung" geaendert wird.
     */
    public function test_preservation_nicht_gesendeter_felder_beim_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie, [
            'pfad' => '/downloads/bleibt-erhalten.pdf',
            'typ' => 'Excel',
        ]);

        $response = $this->put(route('admin.downloads.eintraege.aktualisieren', $download), [
            // "pfad"/"typ" absichtlich NICHT mitgeschickt.
            'beschreibung' => 'Nur die Beschreibung geändert',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $download->refresh();
        $this->assertSame('Nur die Beschreibung geändert', $download->beschreibung);
        $this->assertSame('/downloads/bleibt-erhalten.pdf', $download->pfad);
        $this->assertSame('Excel', $download->typ);
    }

    public function test_vorschau_bleibt_beim_bearbeiten_unveraendert(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);
        // Historischer Wert, den Phase 7E nicht anfassen darf (siehe
        // DownloadUpdater-Klassenkommentar) - direkt per DB gesetzt, da
        // weder admin.js' renderDownloads() noch der neue Blade-Admin dieses
        // Feld fuer Bibliotheks-Downloads jemals selbst schreiben.
        $download->forceFill(['vorschau' => '/images/historisch.jpg'])->save();

        $this->put(route('admin.downloads.eintraege.aktualisieren', $download), [
            'titel' => 'Neuer Titel',
            'pfad' => $download->pfad,
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $this->assertSame('/images/historisch.jpg', $download->fresh()->vorschau);
    }

    public function test_download_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie);

        $response = $this->delete(route('admin.downloads.eintraege.loeschen', $download), [
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertNull(Download::find($download->id));
    }

    public function test_download_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $download = $this->download($kategorie, ['titel' => 'Unveraendert']);

        $response = $this->put(route('admin.downloads.eintraege.aktualisieren', $download), [
            'titel' => 'Sollte nicht gespeichert werden',
            'pfad' => $download->pfad,
            'expected_version' => ContentVersioning::current('downloads') + 1,
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $response->assertSessionHas('downloads_fehler');
        $this->assertSame('Unveraendert', $download->fresh()->titel);
    }

    public function test_manipulierte_route_darf_eingebetteten_download_nicht_bearbeiten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $embedded = $this->embeddedDownload();

        $response = $this->put(route('admin.downloads.eintraege.aktualisieren', $embedded), [
            'titel' => 'Manipulationsversuch',
            'pfad' => '/downloads/hack.pdf',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertNotFound();
        $this->assertSame('Seiteneigenes Dokument', $embedded->fresh()->titel);
    }

    public function test_manipulierte_route_darf_eingebetteten_download_nicht_loeschen(): void
    {
        $this->actingAs($this->admin(), 'web');
        $embedded = $this->embeddedDownload();

        $response = $this->delete(route('admin.downloads.eintraege.loeschen', $embedded), [
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertNotFound();
        $this->assertNotNull(Download::find($embedded->id));
    }

    // -----------------------------------------------------------------
    // Typ-Dropdown
    // -----------------------------------------------------------------

    public function test_typ_dropdown_zeigt_die_fuenf_standardwerte(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $this->download($kategorie);

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        foreach (['PDF', 'Word', 'Excel', 'ZIP', 'Sonstiges'] as $typ) {
            $response->assertSee('>'.$typ.'<', false);
        }
    }

    public function test_exotischer_vorhandener_typ_bleibt_im_dropdown_auswaehlbar(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie();
        $this->download($kategorie, ['typ' => 'Ganz Exotischer Typ']);

        $response = $this->get(route('admin.downloads.index'));

        $response->assertOk();
        $response->assertSee('Ganz Exotischer Typ');
    }

    // -----------------------------------------------------------------
    // Titel & Einleitungstext
    // -----------------------------------------------------------------

    public function test_titel_und_intro_speichern(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.downloads.einstellungen'), [
            'titel' => 'Neue Downloads-Überschrift',
            'intro' => 'Neuer Einleitungstext.',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $this->assertDatabaseHas('settings', ['gruppe' => 'downloads', 'key' => 'titel', 'value' => 'Neue Downloads-Überschrift']);
        $this->assertDatabaseHas('settings', ['gruppe' => 'downloads', 'key' => 'intro', 'value' => 'Neuer Einleitungstext.']);
    }

    public function test_einstellungen_versionskonflikt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.downloads.einstellungen'), [
            'titel' => 'Sollte nicht gespeichert werden',
            'expected_version' => ContentVersioning::current('downloads') + 1,
        ]);

        $response->assertRedirect(route('admin.downloads.index'));
        $response->assertSessionHas('downloads_fehler');
        $this->assertDatabaseMissing('settings', ['gruppe' => 'downloads', 'key' => 'titel', 'value' => 'Sollte nicht gespeichert werden']);
    }

    // -----------------------------------------------------------------
    // JSON-Admin-API-Regression (Api\Admin\AdminListController::downloads(),
    // seit Phase 7E auf App\Support\DownloadKategorieUpdater/DownloadUpdater
    // umgestellt)
    // -----------------------------------------------------------------

    public function test_json_admin_api_legt_kategorie_und_download_an(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => [
                'kategorien' => [
                    ['titel' => 'Neue JSON-Kategorie', 'downloads' => [
                        ['name' => 'JSON-Dokument', 'url' => '/downloads/json.pdf', 'typ' => 'PDF'],
                    ]],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $kategorie = DownloadKategorie::where('titel', 'Neue JSON-Kategorie')->firstOrFail();
        $download = Download::where('kategorie_id', $kategorie->id)->firstOrFail();
        $this->assertSame('JSON-Dokument', $download->titel);
        $this->assertSame('/downloads/json.pdf', $download->pfad);
    }

    public function test_json_admin_api_aktualisiert_kategorie_und_download_ueber_id(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie(['titel' => 'Alter Kategorie-Name']);
        $download = $this->download($kategorie, ['titel' => 'Alter Download-Name']);

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => [
                'kategorien' => [
                    ['_id' => $kategorie->id, 'titel' => 'Neuer Kategorie-Name', 'downloads' => [
                        ['_id' => $download->id, 'name' => 'Neuer Download-Name', 'url' => $download->pfad],
                    ]],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame('Neuer Kategorie-Name', $kategorie->fresh()->titel);
        $this->assertSame('Neuer Download-Name', $download->fresh()->titel);
    }

    public function test_json_admin_api_loescht_nicht_mehr_im_payload_enthaltene_eintraege(): void
    {
        $this->actingAs($this->admin(), 'web');
        $bleibtKat = $this->kategorie(['titel' => 'Bleibt']);
        $verschwindetKat = $this->kategorie(['titel' => 'Verschwindet']);
        $bleibtDl = $this->download($bleibtKat, ['titel' => 'Bleibt']);
        $verschwindetDl = $this->download($bleibtKat, ['titel' => 'Verschwindet']);

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => [
                'kategorien' => [
                    ['_id' => $bleibtKat->id, 'titel' => 'Bleibt', 'downloads' => [
                        ['_id' => $bleibtDl->id, 'name' => 'Bleibt', 'url' => $bleibtDl->pfad],
                    ]],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertNotNull(DownloadKategorie::find($bleibtKat->id));
        $this->assertNull(DownloadKategorie::find($verschwindetKat->id));
        $this->assertNotNull(Download::find($bleibtDl->id));
        $this->assertNull(Download::find($verschwindetDl->id));
    }

    public function test_json_admin_api_leere_beschreibung_und_typ_werden_null(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => [
                'kategorien' => [
                    ['titel' => 'Kategorie', 'downloads' => [
                        ['name' => 'Ohne Beschreibung/Typ', 'url' => '/downloads/x.pdf', 'beschreibung' => '', 'typ' => ''],
                    ]],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $download = Download::where('titel', 'Ohne Beschreibung/Typ')->firstOrFail();
        $this->assertNull($download->beschreibung);
        $this->assertNull($download->typ);
    }

    public function test_json_admin_api_titel_faellt_auf_url_zurueck(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => [
                'kategorien' => [
                    ['titel' => 'Kategorie', 'downloads' => [
                        ['name' => '', 'url' => '/downloads/ohne-name.pdf'],
                    ]],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $download = Download::where('pfad', '/downloads/ohne-name.pdf')->firstOrFail();
        $this->assertSame('/downloads/ohne-name.pdf', $download->titel);
    }

    public function test_json_admin_api_versionskonflikt_unveraendert(): void
    {
        $this->actingAs($this->admin(), 'web');
        ContentVersioning::bump('downloads');

        $response = $this->putJson('/api/admin/content/downloads.json', [
            'data' => ['kategorien' => []],
            'expected_version' => 0,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(409);
    }

    // -----------------------------------------------------------------
    // Öffentliche Ausgabe darf keine Regression bekommen
    // -----------------------------------------------------------------

    public function test_oeffentliche_downloads_seite_zeigt_ueber_blade_admin_angelegten_download(): void
    {
        $this->actingAs($this->admin(), 'web');
        $kategorie = $this->kategorie(['titel' => 'Über Blade-Admin']);
        $this->post(route('admin.downloads.eintraege.anlegen', $kategorie), [
            'titel' => 'Über Blade-Admin angelegtes Dokument',
            'pfad' => '/downloads/blade.pdf',
            'expected_version' => ContentVersioning::current('downloads'),
        ]);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Über Blade-Admin angelegtes Dokument');
    }

    public function test_leere_kategorie_bleibt_auf_oeffentlicher_seite_ausgeblendet(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->kategorie(['titel' => 'Leere Kategorie Öffentlich']);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertDontSee('Leere Kategorie Öffentlich');
    }

    public function test_oeffentliche_downloads_seite_bleibt_regressionsfrei(): void
    {
        $kategorie = $this->kategorie(['titel' => 'Satzung & Ordnungen']);
        $this->download($kategorie);
        $this->embeddedDownload();

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Satzung & Ordnungen');
        $response->assertSee('Satzung 2026');
        $response->assertDontSee('Seiteneigenes Dokument');
    }
}
