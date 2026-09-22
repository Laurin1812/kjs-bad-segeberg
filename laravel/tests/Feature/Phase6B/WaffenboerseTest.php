<?php

namespace Tests\Feature\Phase6B;

use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseBild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 6B (Waffenboerse auf Laravel/MySQL): deckt WaffenboerseController
 * (Uebersicht/Detail/Anbieten), den Importer (kjs:import-waffenboerse) sowie
 * die zugehoerigen Legacy-URL-Redirects ab. Kernprinzip ueberall (analog
 * Phase 6A/HundeboerseTest): oeffentlich sichtbar ist ausschliesslich
 * status="published" - alles andere liefert eine echte 404, nie einen
 * leeren/verschleierten Zustand.
 */
class WaffenboerseTest extends TestCase
{
    use RefreshDatabase;

    private function anlegen(array $overrides = []): WaffenboerseAnzeige
    {
        return WaffenboerseAnzeige::create(array_merge([
            'id' => 'wb-test-'.uniqid(),
            'status' => 'published',
            'titel' => 'Testwaffe',
            'kategorie' => 'Kurzwaffen',
            'hersteller' => 'Testhersteller',
            'modell' => 'TM-1',
            'zustand' => 'gebraucht',
            'preis' => '100,00',
            'preis_typ' => 'festpreis',
            'erwerbsberechtigung_erforderlich' => true,
            'beschreibung' => '<p>Eine Testwaffe.</p>',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'versand_moeglich' => false,
            'versandkosten' => '',
            'anbieter_name' => 'Max Mustermann',
            'anbieter_email' => 'max@example.test',
            'anbieter_telefon' => '',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Uebersicht
    // ---------------------------------------------------------------

    public function test_uebersicht_zeigt_nur_veroeffentlichte_anzeigen(): void
    {
        $this->anlegen(['id' => 'wb-sichtbar', 'titel' => 'Sichtbare Waffe']);
        $this->anlegen(['id' => 'wb-pending', 'status' => 'pending', 'titel' => 'Wartende Waffe']);
        $this->anlegen(['id' => 'wb-rejected', 'status' => 'rejected', 'titel' => 'Abgelehnte Waffe']);
        $this->anlegen(['id' => 'wb-archived', 'status' => 'archived', 'titel' => 'Archivierte Waffe']);

        $response = $this->get('/waffenboerse');

        $response->assertOk();
        $response->assertSee('Sichtbare Waffe');
        $response->assertDontSee('Wartende Waffe');
        $response->assertDontSee('Abgelehnte Waffe');
        $response->assertDontSee('Archivierte Waffe');
    }

    public function test_uebersicht_ohne_veroeffentlichte_anzeigen_zeigt_hinweis_statt_leere_seite(): void
    {
        $response = $this->get('/waffenboerse');

        $response->assertOk();
        $response->assertSee('Aktuell sind keine Anzeigen veröffentlicht.');
    }

    // ---------------------------------------------------------------
    // Detailseite / Status
    // ---------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function nichtOeffentlicheStatus(): array
    {
        return [
            'wartet' => ['pending'],
            'abgelehnt' => ['rejected'],
            'archiviert' => ['archived'],
        ];
    }

    #[DataProvider('nichtOeffentlicheStatus')]
    public function test_nicht_veroeffentlichte_anzeige_ist_oeffentlich_404(string $status): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-'.$status, 'status' => $status]);

        $response = $this->get('/waffenboerse/detail/'.$anzeige->id);

        $response->assertNotFound();
    }

    public function test_veroeffentlichte_detailseite_ist_200(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-detail-ok', 'titel' => 'Mauser 90 DA']);

        $response = $this->get('/waffenboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('Mauser 90 DA');
    }

    public function test_unbekannte_anzeigen_id_ist_404(): void
    {
        $response = $this->get('/waffenboerse/detail/wb-existiert-nicht');

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Daten-/Preisformatierung, Kaliber, Galerie
    // ---------------------------------------------------------------

    public function test_preis_festpreis_wird_korrekt_formatiert(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-festpreis', 'preis_typ' => 'festpreis', 'preis' => '2.500,00']);

        $this->get('/waffenboerse/detail/'.$anzeige->id)
            ->assertOk()
            ->assertSee('2.500,00 €', false);
    }

    public function test_preis_vb_wird_korrekt_formatiert(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-vb', 'preis_typ' => 'vb', 'preis' => '380,00']);

        $this->get('/waffenboerse/detail/'.$anzeige->id)
            ->assertOk()
            ->assertSee('380,00 € VB', false);
    }

    public function test_kaliber_werden_verbunden_dargestellt(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-kaliber']);
        $anzeige->kaliber()->create(['kaliber' => '7x65R', 'sortierung' => 0]);
        $anzeige->kaliber()->create(['kaliber' => '6x52R', 'sortierung' => 1]);

        $this->get('/waffenboerse/detail/'.$anzeige->id)
            ->assertOk()
            ->assertSee('7x65R · 6x52R', false);
    }

    public function test_galerie_wird_angezeigt(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-galerie']);
        WaffenboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/waffenboerse/bild1.jpg', 'sortierung' => 0]);
        WaffenboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/waffenboerse/bild2.jpg', 'sortierung' => 1]);

        $response = $this->get('/waffenboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('/uploads/boersen/waffenboerse/bild1.jpg', false);
        $response->assertSee('wb-thumbs', false);
    }

    public function test_seite_enthaelt_keine_json_runtime_fetch_aufrufe(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-kein-fetch']);

        $index = $this->get('/waffenboerse');
        $detail = $this->get('/waffenboerse/detail/'.$anzeige->id);

        // Kein Runtime-Fetch mehr auf die frueheren Datenquellen (siehe
        // WaffenboerseController-Klassenkommentar) - Daten kommen
        // ausschliesslich serverseitig aus Eloquent/MySQL.
        $index->assertDontSee('api/waffenboerse/anzeigen.php', false);
        $index->assertDontSee('content/waffenboerse.json', false);
        $detail->assertDontSee('api/waffenboerse/anzeigen.php', false);
        $detail->assertDontSee('content/waffenboerse.json', false);
    }

    // ---------------------------------------------------------------
    // Importer (kjs:import-waffenboerse) - additiv, idempotent, keine
    // Doppelimporte, kein Ueberschreiben bestehender Eintraege.
    // ---------------------------------------------------------------

    public function test_importer_uebernimmt_die_beiden_echten_bestandsanzeigen(): void
    {
        Artisan::call('kjs:import-waffenboerse');
        $output = Artisan::output();

        $this->assertStringContainsString('2 Anzeige(n) in JSON gefunden', $output);
        $this->assertStringContainsString('2 neu importiert', $output);
        $this->assertStringContainsString('0 bereits vorhanden', $output);

        $this->assertDatabaseCount('waffenboerse_anzeigen', 2);
        $this->assertDatabaseHas('waffenboerse_anzeigen', [
            'id' => 'wb-1788440000002',
            'titel' => 'Mauser 90 DA - 9mm Luger',
            'status' => 'published',
            'kategorie' => 'Kurzwaffen',
        ]);
        $this->assertDatabaseHas('waffenboerse_anzeigen', [
            'id' => 'wb-1788440000003',
            'titel' => 'Blaser Bockbüchsflinte BBF 95 mit hochwertigem Zeiss / Linksschütze',
            'status' => 'published',
            'kategorie' => 'Kombinierte Waffen',
        ]);

        // Legacy-Datenartefakt (ein Kaliber-Wert mit eingebettetem Komma,
        // vermutlich ein alter Erfassungsfehler) wird bewusst UNVERAENDERT
        // uebernommen, nicht "korrigiert" - siehe ImportWaffenboerse-
        // Kommentar / "keine erfundenen Inhalte".
        $blaser = WaffenboerseAnzeige::findOrFail('wb-1788440000003');
        $this->assertSame(
            ['7x65R  / 12/70  / 5', '6x52R'],
            $blaser->kaliber()->orderBy('sortierung')->pluck('kaliber')->all()
        );

        // 4 Bilder (Mauser) + 6 Bilder (Blaser) = 10 Bilder insgesamt.
        $this->assertDatabaseCount('waffenboerse_bilder', 10);
    }

    public function test_importer_ist_idempotent_und_ueberschreibt_nichts(): void
    {
        Artisan::call('kjs:import-waffenboerse');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 2);
        $this->assertDatabaseCount('waffenboerse_bilder', 10);

        // Zweiter Lauf: keine Doppelimporte, keine Aenderung an bereits
        // vorhandenen Datensaetzen.
        Artisan::call('kjs:import-waffenboerse');
        $output = Artisan::output();

        $this->assertStringContainsString('0 neu importiert', $output);
        $this->assertStringContainsString('2 bereits vorhanden', $output);
        $this->assertDatabaseCount('waffenboerse_anzeigen', 2);
        $this->assertDatabaseCount('waffenboerse_bilder', 10);
    }

    public function test_importer_ueberschreibt_eine_bereits_veraenderte_bestehende_anzeige_nicht(): void
    {
        // Simuliert eine Anzeige, die bereits (z.B. durch Admin-Bearbeitung
        // in einer spaeteren Phase) von den JSON-Bestandsdaten abweicht -
        // der Importer darf diese lokale Aenderung unter keinen Umstaenden
        // zuruecksetzen ("keine bestehenden Laravel-Eintraege
        // ueberschreiben", siehe Auftrag).
        $this->anlegen(['id' => 'wb-1788440000002', 'titel' => 'Bereits manuell geaendert', 'status' => 'archived']);

        Artisan::call('kjs:import-waffenboerse');
        $output = Artisan::output();

        $this->assertStringContainsString('1 neu importiert', $output);
        $this->assertStringContainsString('1 bereits vorhanden', $output);

        $this->assertDatabaseHas('waffenboerse_anzeigen', [
            'id' => 'wb-1788440000002',
            'titel' => 'Bereits manuell geaendert',
            'status' => 'archived',
        ]);
    }

    // ---------------------------------------------------------------
    // Legacy-Redirects
    // ---------------------------------------------------------------

    public function test_alte_waffenboerse_urls_werden_auf_neue_laravel_routen_umgeleitet(): void
    {
        $anzeige = $this->anlegen(['id' => 'wb-legacy-redirect']);

        $this->get('/waffenboerse/index.html')->assertRedirect('/waffenboerse');
        $this->get('/waffenboerse/anbieten.html')->assertRedirect('/waffenboerse/anbieten');
        $this->get('/waffenboerse/detail.html?id='.$anzeige->id)->assertRedirect('/waffenboerse/detail/'.$anzeige->id);
        $this->get('/waffenboerse/detail.html')->assertRedirect('/waffenboerse');
    }

    public function test_unbekannte_id_ueber_legacy_redirect_ist_ebenfalls_404(): void
    {
        // Der Redirect selbst ist immer ein 301 auf eine syntaktisch gueltige
        // Ziel-URL - ob die Anzeige tatsaechlich existiert, entscheidet erst
        // WaffenboerseController::show() (keine erfundenen Ziele).
        $this->get('/waffenboerse/detail.html?id=wb-existiert-nicht')
            ->assertRedirect('/waffenboerse/detail/wb-existiert-nicht');

        $this->get('/waffenboerse/detail/wb-existiert-nicht')->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Oeffentliche Einreichung ("Waffe anbieten")
    // ---------------------------------------------------------------

    private function gueltigeEinreichung(array $overrides = []): array
    {
        return array_merge([
            'titel' => 'Neue Testwaffe',
            'kategorie' => 'Kurzwaffen',
            'hersteller' => 'Testhersteller',
            'modell' => 'TM-2',
            'kaliber' => ['9 mm'],
            'zustand' => 'gebraucht',
            'preis_typ' => 'festpreis',
            'preis' => '250,00',
            'versand_moeglich' => '1',
            'versandkosten' => '15,80',
            'plz' => '23795',
            'ort' => 'Bad Segeberg',
            'erwerbsberechtigung_erforderlich' => '1',
            'beschreibung' => 'Eine ausführliche Beschreibung der angebotenen Waffe.',
            'anbieter_name' => 'Erika Musterfrau',
            'anbieter_email' => 'erika@example.test',
            'anbieter_telefon' => '0172 1234567',
            'confirmCorrect' => '1',
            'confirmContact' => '1',
            'images' => [UploadedFile::fake()->image('waffe.jpg', 800, 600)],
        ], $overrides);
    }

    public function test_oeffentliche_einreichung_erzeugt_anzeige_mit_status_pending(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung());

        $response->assertRedirect(route('waffenboerse.anbieten'));
        $response->assertSessionHas('wb_success', true);

        $this->assertDatabaseHas('waffenboerse_anzeigen', [
            'titel' => 'Neue Testwaffe',
            'status' => 'pending',
        ]);

        $anzeige = WaffenboerseAnzeige::where('titel', 'Neue Testwaffe')->firstOrFail();
        $this->assertSame(1, $anzeige->bilder()->count());
        $this->assertSame(['9 mm'], $anzeige->kaliber()->pluck('kaliber')->all());

        // Neu eingereichte Anzeigen sind NICHT oeffentlich sichtbar, bis sie
        // (spaeter, per Laravel-Admin) freigegeben werden.
        $this->get('/waffenboerse/detail/'.$anzeige->id)->assertNotFound();
        $this->get('/waffenboerse')->assertDontSee('Neue Testwaffe');
    }

    public function test_freitext_beschreibung_wird_in_sicheres_absatz_html_umgewandelt(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung([
            'beschreibung' => "Erster Absatz mit <script>alert(1)</script>.\n\nZweiter Absatz.",
        ]));

        $response->assertRedirect(route('waffenboerse.anbieten'));

        $anzeige = WaffenboerseAnzeige::where('titel', 'Neue Testwaffe')->firstOrFail();
        $this->assertStringNotContainsString('<script>', $anzeige->beschreibung);
        $this->assertStringContainsString('&lt;script&gt;', $anzeige->beschreibung);
        $this->assertStringContainsString('<p>', $anzeige->beschreibung);
    }

    public function test_validierungsfehler_bei_fehlenden_pflichtfeldern(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), []);

        $response->assertSessionHasErrors([
            'titel', 'kategorie', 'hersteller', 'zustand', 'preis_typ', 'preis',
            'plz', 'ort', 'beschreibung', 'anbieter_name', 'anbieter_email',
            'images', 'confirmCorrect', 'confirmContact',
        ]);
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_unbekannte_kategorie_wird_abgelehnt(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung(['kategorie' => 'Erfundene Kategorie']));

        $response->assertSessionHasErrors('kategorie');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_ungueltige_plz_wird_abgelehnt(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung(['plz' => '123']));

        $response->assertSessionHasErrors('plz');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_mehr_als_zehn_bilder_werden_abgelehnt(): void
    {
        $bilder = [];
        for ($i = 0; $i < 11; $i++) {
            $bilder[] = UploadedFile::fake()->image("bild{$i}.jpg", 400, 300);
        }

        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung(['images' => $bilder]));

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_nicht_erlaubter_dateityp_wird_abgelehnt(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung([
            'images' => [UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_zu_grosses_bild_wird_abgelehnt(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung([
            'images' => [UploadedFile::fake()->create('riesig.jpg', 9000)->size(9000)],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_honeypot_gefuellt_wird_still_verworfen_ohne_zu_speichern(): void
    {
        $response = $this->post(route('waffenboerse.anbieten.store'), $this->gueltigeEinreichung(['_honey' => 'ich bin ein bot']));

        // Bewusst dieselbe "Erfolg"-Antwort wie bei einer echten Einreichung
        // (siehe WaffenboerseController::store()-Kommentar) - ein Bot soll
        // keinen Unterschied zwischen "abgelehnt" und "angenommen" erkennen
        // koennen -, aber es wird NICHTS in der Datenbank gespeichert.
        $response->assertRedirect(route('waffenboerse.anbieten'));
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_formularseite_per_get_erzeugt_keine_anzeige(): void
    {
        // "/waffenboerse/anbieten" ist absichtlich unter derselben URI per
        // GET (Formular anzeigen, WaffenboerseController::createForm()) UND
        // POST (echte Einreichung, WaffenboerseController::store())
        // erreichbar - ein reiner Seitenaufruf per GET darf niemals eine
        // Anzeige anlegen. CSRF-Schutz kommt aus der regulaeren "web"-
        // Middleware-Gruppe (siehe routes/web.php), genau wie bei jeder
        // anderen Formular-Route dieser Anwendung; PHPUnit-Testfahrten
        // umgehen die CSRF-Pruefung planmaessig (Laravel-Kernverhalten fuer
        // Tests), ein echter Browser-POST ohne gueltigen Token wuerde
        // stattdessen 419 erhalten.
        $response = $this->get(route('waffenboerse.anbieten'));

        $response->assertOk();
        $this->assertDatabaseCount('waffenboerse_anzeigen', 0);
    }

    public function test_versand_und_erwerb_werden_ohne_angabe_als_false_gespeichert(): void
    {
        // Alt-System-Parität (siehe WaffenboerseAnbietenRequest-
        // Klassenkommentar): "versand_moeglich"/"erwerbsberechtigung_
        // erforderlich" sind im PHP-Original trotz Stern im Formular NICHT
        // hart serverseitig erzwungen - fehlen sie, wird bewusst "false"
        // angenommen statt eines Validierungsfehlers.
        $overrides = $this->gueltigeEinreichung();
        unset($overrides['versand_moeglich'], $overrides['erwerbsberechtigung_erforderlich']);

        $response = $this->post(route('waffenboerse.anbieten.store'), $overrides);

        $response->assertRedirect(route('waffenboerse.anbieten'));
        $this->assertDatabaseHas('waffenboerse_anzeigen', [
            'titel' => 'Neue Testwaffe',
            'versand_moeglich' => 0,
            'erwerbsberechtigung_erforderlich' => 0,
        ]);
    }
}
