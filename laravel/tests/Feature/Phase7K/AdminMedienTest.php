<?php

namespace Tests\Feature\Phase7K;

use App\Models\MedienArchivEintrag;
use App\Models\MedienEintrag;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 7K (Admin-Modul "Medien"): deckt den neuen, server-gerenderten
 * Bereich unter "/admin/medien" ab (Http\Controllers\Admin\MedienController) -
 * siehe dortiger Klassenkommentar und App\Support\MediaLibrary fuer die
 * vollstaendige Analyse ("Fall A": es gibt bereits eine echte zentrale
 * Medienbibliothek unter images/downloads, siehe config/kjs_media.php).
 *
 * WICHTIG (Test-Dateisicherheit): App\Support\MediaStorage zeigt in dieser
 * Umgebung auf die ECHTEN Verzeichnisse images/ und downloads/ im Repo-
 * Wurzelverzeichnis - dieselben ca. 250 echten Bestandsdateien, die auch die
 * oeffentliche Website ausliefert (kein separates Test-Storage konfiguriert,
 * siehe phpunit.xml - identisch zum bereits etablierten Vorgehen in
 * tests/Feature/Phase7J/AdminWaffenboerseTest.php fuer public/uploads/boersen/).
 * Jeder Test, der eine Datei anlegt (per Upload ODER direkt per file_put_
 * contents() fuer ein "historisches Bestandsbild ohne DB-Zeile"), verwendet
 * ausschliesslich per uniqid() erzeugte, garantiert einzigartige Test-
 * Dateinamen und raeumt sie in tearDown() wieder auf (Original + Thumb/Card-
 * Varianten) - niemals wird eine der echten Bestandsdateien angefasst,
 * gelesen zum Zaehlen abgesehen.
 */
class AdminMedienTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> Dateinamen (nicht Pfade) in images/, die in tearDown() zu entfernen sind. */
    private array $testBilder = [];

    /** @var list<string> Dateinamen (nicht Pfade) in downloads/, die in tearDown() zu entfernen sind. */
    private array $testDateien = [];

    protected function tearDown(): void
    {
        foreach ($this->testBilder as $name) {
            $this->loescheBildDateien($name);
        }
        foreach ($this->testDateien as $name) {
            @unlink(MediaStorage::downloadsPath().'/'.$name);
        }

        parent::tearDown();
    }

    private function loescheBildDateien(string $name): void
    {
        @unlink(MediaStorage::imagesPath().'/'.$name);
        @unlink(MediaStorage::imagesPath().'/'.MediaStorage::thumbDirName().'/'.$name);
        @unlink(MediaStorage::imagesPath().'/'.MediaStorage::cardDirName().'/'.$name);
    }

    private function admin(): User
    {
        return User::factory()->create(['roles' => ['admin'], 'permissions' => []]);
    }

    private function nichtAdmin(): User
    {
        return User::factory()->create(['roles' => [], 'permissions' => []]);
    }

    /** Legt ein "historisches" Bestandsbild DIREKT auf der Platte an (keine DB-Zeile) - simuliert die ca. 250 echten Altbilder, ohne eine davon anzufassen. */
    private function legacyBildAnlegen(?string $name = null): string
    {
        $name = $name ?? ('phase7ktest-'.uniqid().'.jpg');
        $this->testBilder[] = $name;
        $img = imagecreatetruecolor(4, 4);
        imagejpeg($img, MediaStorage::imagesPath().'/'.$name);
        imagedestroy($img);

        return $name;
    }

    // ────────────────────────────────────────────────────────────
    // Zugriff
    // ────────────────────────────────────────────────────────────

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.medien.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.medien.archiv'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.medien.bilder.hochladen'), [])->assertRedirect(route('admin.login'));
        $this->post(route('admin.medien.dateien.hochladen'), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.medien.archivieren', 'x.jpg'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.medien.loeschen', ['bild', 'x.jpg']))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.medien.index'))->assertForbidden();
    }

    public function test_admin_erreicht_die_uebersicht(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.medien.index'))->assertOk();
        $this->get(route('admin.medien.archiv'))->assertOk();
    }

    // ────────────────────────────────────────────────────────────
    // Listing
    // ────────────────────────────────────────────────────────────

    public function test_historisches_bestandsbild_ohne_db_zeile_wird_angezeigt(): void
    {
        $name = $this->legacyBildAnlegen();
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.medien.index'))->assertOk()->assertSee($name);
    }

    public function test_versteckte_dateien_werden_nicht_angezeigt(): void
    {
        $hidden = '.phase7ktest-hidden-'.uniqid().'.jpg';
        $this->testBilder[] = $hidden;
        $img = imagecreatetruecolor(2, 2);
        imagejpeg($img, MediaStorage::imagesPath().'/'.$hidden);
        imagedestroy($img);

        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.medien.index'))->assertOk()->assertDontSee($hidden);
    }

    public function test_bilder_und_dateien_filter_funktionieren(): void
    {
        $bild = $this->legacyBildAnlegen();
        $pdfName = 'phase7ktest-'.uniqid().'.pdf';
        $this->testDateien[] = $pdfName;
        file_put_contents(MediaStorage::downloadsPath().'/'.$pdfName, "%PDF-1.4\n%%EOF");

        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.medien.index', ['typ' => 'bild']))
            ->assertOk()->assertSee($bild)->assertDontSee($pdfName);

        $this->get(route('admin.medien.index', ['typ' => 'datei']))
            ->assertOk()->assertSee($pdfName)->assertDontSee($bild);
    }

    public function test_hundeboerse_und_waffenboerse_uploads_tauchen_nicht_in_medien_auf(): void
    {
        $dir = public_path('uploads/boersen/waffenboerse');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fremd = 'phase7ktest-fremd-'.uniqid().'.jpg';
        $img = imagecreatetruecolor(2, 2);
        imagejpeg($img, $dir.'/'.$fremd);
        imagedestroy($img);

        try {
            $this->actingAs($this->admin(), 'web');
            $this->get(route('admin.medien.index'))->assertOk()->assertDontSee($fremd);
        } finally {
            @unlink($dir.'/'.$fremd);
        }
    }

    public function test_archivierte_bilder_verschwinden_aus_der_uebersicht_und_erscheinen_im_archiv(): void
    {
        $name = $this->legacyBildAnlegen();
        MedienArchivEintrag::create(['dateiname' => $name, 'archiviert_am' => now()]);

        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.medien.index'))->assertOk()->assertDontSee($name);
        $this->get(route('admin.medien.archiv'))->assertOk()->assertSee($name);
    }

    // ────────────────────────────────────────────────────────────
    // Hochladen
    // ────────────────────────────────────────────────────────────

    public function test_bild_hochladen_erzeugt_datensatz_und_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.medien.bilder.hochladen'), [
            'datei' => UploadedFile::fake()->image('phase7ktest.jpg', 60, 60),
        ]);

        $medium = MedienEintrag::where('media_type', 'image')->where('original_name', 'phase7ktest.jpg')->first();
        $this->assertNotNull($medium, 'Es sollte eine neue "medien"-Zeile angelegt worden sein.');
        $this->testBilder[] = $medium->path;

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertFileExists(MediaStorage::imagesPath().'/'.$medium->path);
        $this->assertFileExists(MediaStorage::imagesPath().'/'.MediaStorage::thumbDirName().'/'.$medium->path);
        $this->assertFileExists(MediaStorage::imagesPath().'/'.MediaStorage::cardDirName().'/'.$medium->path);
    }

    public function test_pdf_hochladen_erzeugt_datensatz(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.medien.dateien.hochladen'), [
            'datei' => UploadedFile::fake()->createWithContent('phase7ktest.pdf', "%PDF-1.4\n%%EOF"),
        ]);

        $medium = MedienEintrag::where('media_type', 'pdf')->where('original_name', 'phase7ktest.pdf')->first();
        $this->assertNotNull($medium, 'Es sollte eine neue "medien"-Zeile fuer das PDF angelegt worden sein.');
        $this->testDateien[] = $medium->path;

        $response->assertRedirect();
        $this->assertFileExists(MediaStorage::downloadsPath().'/'.$medium->path);
    }

    public function test_ungueltiges_pdf_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->post(route('admin.medien.dateien.hochladen'), [
            'datei' => UploadedFile::fake()->create('phase7ktest-invalid.pdf', 10, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('medien');
        $this->assertNull(MedienEintrag::where('original_name', 'phase7ktest-invalid.pdf')->first());
    }

    // ────────────────────────────────────────────────────────────
    // Archivieren
    // ────────────────────────────────────────────────────────────

    public function test_archivieren_und_wiederherstellen_toggeln_ohne_die_datei_zu_veraendern(): void
    {
        $name = $this->legacyBildAnlegen();
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.medien.archivieren', $name))->assertRedirect();
        $this->assertTrue(MedienArchivEintrag::where('dateiname', $name)->exists());
        $this->assertFileExists(MediaStorage::imagesPath().'/'.$name);

        $this->put(route('admin.medien.archivieren', $name))->assertRedirect();
        $this->assertFalse(MedienArchivEintrag::where('dateiname', $name)->exists());
        $this->assertFileExists(MediaStorage::imagesPath().'/'.$name);
    }

    public function test_archivieren_einer_nicht_existierenden_datei_ergibt_404(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.medien.archivieren', 'phase7ktest-nichtvorhanden.jpg'))->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // Loeschen / Sicherheit
    // ────────────────────────────────────────────────────────────

    public function test_unreferenzierte_datei_ist_loeschbar(): void
    {
        $name = $this->legacyBildAnlegen();
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.medien.loeschen', ['bild', $name]));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertFileDoesNotExist(MediaStorage::imagesPath().'/'.$name);
    }

    public function test_referenzierte_datei_ist_nicht_loeschbar(): void
    {
        $name = $this->legacyBildAnlegen();
        Setting::create(['gruppe' => 'phase7ktest', 'key' => 'bild', 'value' => '/images/'.$name]);
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.medien.loeschen', ['bild', $name]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('medien');
        $this->assertFileExists(MediaStorage::imagesPath().'/'.$name, 'Referenzierte Datei darf nicht geloescht werden.');
    }

    public function test_loeschen_entfernt_auch_thumb_und_card_varianten(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->post(route('admin.medien.bilder.hochladen'), [
            'datei' => UploadedFile::fake()->image('phase7ktest-variant.jpg', 60, 60),
        ]);
        $medium = MedienEintrag::where('original_name', 'phase7ktest-variant.jpg')->first();
        $this->testBilder[] = $medium->path;

        $this->delete(route('admin.medien.loeschen', ['bild', $medium->path]))->assertRedirect();

        $this->assertFileDoesNotExist(MediaStorage::imagesPath().'/'.$medium->path);
        $this->assertFileDoesNotExist(MediaStorage::imagesPath().'/'.MediaStorage::thumbDirName().'/'.$medium->path);
        $this->assertFileDoesNotExist(MediaStorage::imagesPath().'/'.MediaStorage::cardDirName().'/'.$medium->path);
    }

    public function test_loeschen_raeumt_verwaisten_archiv_eintrag_mit_auf(): void
    {
        $name = $this->legacyBildAnlegen();
        MedienArchivEintrag::create(['dateiname' => $name, 'archiviert_am' => now()]);
        $this->actingAs($this->admin(), 'web');

        $this->delete(route('admin.medien.loeschen', ['bild', $name]))->assertRedirect();

        $this->assertFalse(MedienArchivEintrag::where('dateiname', $name)->exists());
    }

    public function test_pfad_traversal_beim_loeschen_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin(), 'web');

        $response = $this->delete(route('admin.medien.loeschen', ['bild', '..%2F..%2F.env']));

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_nicht_existierende_datei_ergibt_404_statt_erfolgsmeldung(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->delete(route('admin.medien.loeschen', ['bild', 'phase7ktest-nichtvorhanden.jpg']))->assertNotFound();
    }

    public function test_symlink_ausserhalb_des_medienverzeichnisses_wird_nicht_ueber_ihn_hinaus_geloescht(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() nicht verfuegbar.');
        }

        $ziel = base_path('composer.json');
        $linkName = 'phase7ktest-symlink-'.uniqid().'.jpg';
        $link = MediaStorage::imagesPath().'/'.$linkName;
        if (! @symlink($ziel, $link)) {
            $this->markTestSkipped('Symlink konnte in dieser Umgebung nicht angelegt werden.');
        }

        try {
            $this->actingAs($this->admin(), 'web');
            $response = $this->delete(route('admin.medien.loeschen', ['bild', $linkName]));

            // MediaStorage::assertWithinRoot() wirft hier eine RuntimeException
            // (aufgefangen als generischer Fehler) - die eigentliche Zieldatei
            // ausserhalb des Medienverzeichnisses bleibt in jedem Fall unberuehrt.
            $response->assertRedirect();
            $this->assertFileExists(base_path('composer.json'));
        } finally {
            @unlink($link);
        }
    }

    public function test_symlink_im_medienverzeichnis_wird_nicht_gelistet(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() nicht verfuegbar.');
        }

        $ziel = base_path('composer.json');
        $linkName = 'phase7ktest-symlink-listing-'.uniqid().'.jpg';
        $link = MediaStorage::imagesPath().'/'.$linkName;
        if (! @symlink($ziel, $link)) {
            $this->markTestSkipped('Symlink konnte in dieser Umgebung nicht angelegt werden.');
        }

        try {
            $this->actingAs($this->admin(), 'web');

            // Sicherheitsfix: MediaLibrary::scanLegacyFiles() ueberspringt
            // Symlinks generell (is_link()-Check), statt is_file() zu
            // vertrauen - der Symlink darf also gar nicht erst im Listing
            // auftauchen, unabhaengig davon, wohin er zeigt.
            $this->get(route('admin.medien.index'))->assertOk()->assertDontSee($linkName);

            $this->assertFileExists($ziel);
        } finally {
            @unlink($link);
        }
    }

    public function test_symlink_im_downloads_verzeichnis_wird_nicht_gelistet(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() nicht verfuegbar.');
        }

        $ziel = base_path('composer.json');
        $linkName = 'phase7ktest-symlink-listing-'.uniqid().'.pdf';
        $link = MediaStorage::downloadsPath().'/'.$linkName;
        if (! @symlink($ziel, $link)) {
            $this->markTestSkipped('Symlink konnte in dieser Umgebung nicht angelegt werden.');
        }

        try {
            $this->actingAs($this->admin(), 'web');

            $this->get(route('admin.medien.index'))->assertOk()->assertDontSee($linkName);

            $this->assertFileExists($ziel);
        } finally {
            @unlink($link);
        }
    }
}
