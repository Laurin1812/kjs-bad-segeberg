<?php

namespace Tests\Feature\Phase6A;

use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseBild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
 * deckt HundeboerseController (Uebersicht/Detail/Anbieten) sowie die
 * zugehoerigen Legacy-URL-Redirects ab. Kernprinzip ueberall: oeffentlich
 * sichtbar ist ausschliesslich status="published" - alles andere liefert
 * eine echte 404, nie einen leeren/verschleierten Zustand.
 */
class HundeboerseTest extends TestCase
{
    use RefreshDatabase;

    private function anlegen(array $overrides = []): HundeboerseAnzeige
    {
        return HundeboerseAnzeige::create(array_merge([
            'id' => 'hb-test-'.uniqid(),
            'status' => 'published',
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

    public function test_uebersicht_zeigt_nur_veroeffentlichte_anzeigen(): void
    {
        $veroeffentlicht = $this->anlegen(['title' => 'Sichtbarer Hund']);
        $this->anlegen(['id' => 'hb-pending', 'status' => 'pending', 'title' => 'Wartender Hund']);
        $this->anlegen(['id' => 'hb-rejected', 'status' => 'rejected', 'title' => 'Abgelehnter Hund']);
        $this->anlegen(['id' => 'hb-archived', 'status' => 'archived', 'title' => 'Archivierter Hund']);

        $response = $this->get('/hundeboerse');

        $response->assertOk();
        $response->assertSee('Sichtbarer Hund');
        $response->assertDontSee('Wartender Hund');
        $response->assertDontSee('Abgelehnter Hund');
        $response->assertDontSee('Archivierter Hund');
    }

    public function test_uebersicht_ohne_veroeffentlichte_anzeigen_zeigt_hinweis_statt_leere_seite(): void
    {
        $response = $this->get('/hundeboerse');

        $response->assertOk();
        $response->assertSee('Aktuell sind keine Anzeigen veröffentlicht.');
    }

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
        $anzeige = $this->anlegen(['id' => 'hb-'.$status, 'status' => $status]);

        $response = $this->get('/hundeboerse/detail/'.$anzeige->id);

        $response->assertNotFound();
    }

    public function test_veroeffentlichte_detailseite_ist_200(): void
    {
        $anzeige = $this->anlegen(['title' => 'Bruno']);

        $response = $this->get('/hundeboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('Bruno');
    }

    public function test_unbekannte_anzeigen_id_ist_404(): void
    {
        $response = $this->get('/hundeboerse/detail/hb-existiert-nicht');

        $response->assertNotFound();
    }

    public function test_einzelhund_wird_korrekt_dargestellt(): void
    {
        $anzeige = $this->anlegen([
            'id' => 'hb-einzelhund',
            'type' => 'single',
            'gender' => 'male',
            'birth_date' => '01.01.2024',
        ]);

        $response = $this->get('/hundeboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('EINZELHUND');
        $response->assertSee('Rüde', false);
    }

    public function test_wurf_wird_korrekt_dargestellt(): void
    {
        $anzeige = $this->anlegen([
            'id' => 'hb-wurf',
            'type' => 'litter',
            'gender' => '',
            'birth_date' => '',
            'litter_date' => '05.05.2026',
            'male_count' => '3',
            'female_count' => '2',
        ]);

        $response = $this->get('/hundeboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('WURF');
        $response->assertSee('3 Rüden', false);
        $response->assertSee('2 Hündinnen', false);
    }

    public function test_galerie_wird_angezeigt(): void
    {
        $anzeige = $this->anlegen(['id' => 'hb-galerie']);
        HundeboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/hundeboerse/bild1.jpg', 'sortierung' => 0]);
        HundeboerseBild::create(['anzeige_id' => $anzeige->id, 'pfad' => '/uploads/boersen/hundeboerse/bild2.jpg', 'sortierung' => 1]);

        $response = $this->get('/hundeboerse/detail/'.$anzeige->id);

        $response->assertOk();
        $response->assertSee('/uploads/boersen/hundeboerse/bild1.jpg', false);
        $response->assertSee('hb-thumbs', false);
    }

    public function test_seite_enthaelt_keine_json_runtime_fetch_aufrufe(): void
    {
        $anzeige = $this->anlegen(['id' => 'hb-kein-fetch']);

        $index = $this->get('/hundeboerse');
        $detail = $this->get('/hundeboerse/detail/'.$anzeige->id);

        // Kein Runtime-Fetch mehr auf die frueheren Datenquellen (siehe
        // HundeboerseController-Klassenkommentar) - Daten kommen
        // ausschliesslich serverseitig aus Eloquent/MySQL.
        $index->assertDontSee('api/hundeboerse/anzeigen.php', false);
        $index->assertDontSee('content/hundeboerse.json', false);
        $detail->assertDontSee('api/hundeboerse/anzeigen.php', false);
        $detail->assertDontSee('content/hundeboerse.json', false);
    }

    private function gueltigeEinreichung(array $overrides = []): array
    {
        return array_merge([
            'type' => 'single',
            'title' => 'Neuer Testhund',
            'breed' => 'Labrador',
            'birthDate' => '2024-01-15',
            'gender' => 'male',
            'priceType' => 'on_request',
            'postalCode' => '23795',
            'city' => 'Bad Segeberg',
            'description' => 'Ein sehr lieber, freundlicher Hund.',
            'providerName' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
            'confirmCorrect' => '1',
            'confirmPrivacy' => '1',
            'images' => [UploadedFile::fake()->image('hund.jpg', 800, 600)],
        ], $overrides);
    }

    public function test_oeffentliche_einreichung_erzeugt_anzeige_mit_status_pending(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung());

        $response->assertRedirect(route('hundeboerse.anbieten'));
        $response->assertSessionHas('hb_success', true);

        $this->assertDatabaseHas('hundeboerse_anzeigen', [
            'title' => 'Neuer Testhund',
            'status' => 'pending',
        ]);

        $anzeige = HundeboerseAnzeige::where('title', 'Neuer Testhund')->firstOrFail();
        $this->assertSame(1, $anzeige->bilder()->count());
        // Neu eingereichte Anzeigen sind NICHT oeffentlich sichtbar, bis sie
        // (spaeter, per Laravel-Admin) freigegeben werden.
        $this->get('/hundeboerse/detail/'.$anzeige->id)->assertNotFound();
        $this->get('/hundeboerse')->assertDontSee('Neuer Testhund');
    }

    public function test_wurf_einreichung_erzeugt_anzeige_mit_wurf_feldern(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung([
            'type' => 'litter',
            'birthDate' => null,
            'gender' => null,
            'litterDate' => '2026-05-05',
            'maleCount' => '2',
            'femaleCount' => '3',
        ]));

        $response->assertRedirect(route('hundeboerse.anbieten'));
        $this->assertDatabaseHas('hundeboerse_anzeigen', [
            'title' => 'Neuer Testhund',
            'type' => 'litter',
            'litter_date' => '05.05.2026',
            'male_count' => '2',
            'female_count' => '3',
        ]);
    }

    public function test_validierungsfehler_bei_fehlenden_pflichtfeldern(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), []);

        $response->assertSessionHasErrors(['type', 'title', 'breed', 'postalCode', 'city', 'description', 'providerName', 'email', 'images', 'confirmCorrect', 'confirmPrivacy']);
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_ungueltige_plz_wird_abgelehnt(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung(['postalCode' => '123']));

        $response->assertSessionHasErrors('postalCode');
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_mehr_als_zehn_bilder_werden_abgelehnt(): void
    {
        $bilder = [];
        for ($i = 0; $i < 11; $i++) {
            $bilder[] = UploadedFile::fake()->image("bild{$i}.jpg", 400, 300);
        }

        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung(['images' => $bilder]));

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_nicht_erlaubter_dateityp_wird_abgelehnt(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung([
            'images' => [UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_zu_grosses_bild_wird_abgelehnt(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung([
            'images' => [UploadedFile::fake()->create('riesig.jpg', 9000)->size(9000)],
        ]));

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_honeypot_gefuellt_wird_still_verworfen_ohne_zu_speichern(): void
    {
        $response = $this->post(route('hundeboerse.anbieten.store'), $this->gueltigeEinreichung(['_honey' => 'ich bin ein bot']));

        // Bewusst dieselbe "Erfolg"-Antwort wie bei einer echten Einreichung
        // (siehe HundeboerseController::store()-Kommentar) - ein Bot soll
        // keinen Unterschied zwischen "abgelehnt" und "angenommen" erkennen
        // koennen -, aber es wird NICHTS in der Datenbank gespeichert.
        $response->assertRedirect(route('hundeboerse.anbieten'));
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_formularseite_per_get_erzeugt_keine_anzeige(): void
    {
        // "/hundeboerse/anbieten" ist absichtlich unter derselben URI per
        // GET (Formular anzeigen, HundeboerseController::createForm()) UND
        // POST (echte Einreichung, HundeboerseController::store()) erreichbar
        // - ein reiner Seitenaufruf per GET darf niemals eine Anzeige
        // anlegen. CSRF-Schutz kommt aus der regulaeren "web"-Middleware-
        // Gruppe (siehe routes/web.php), genau wie bei jeder anderen
        // Formular-Route dieser Anwendung; PHPUnit-Testfahrten umgehen die
        // CSRF-Pruefung planmaessig (Laravel-Kernverhalten fuer Tests), ein
        // echter Browser-POST ohne gueltigen Token wuerde stattdessen 419
        // erhalten.
        $response = $this->get(route('hundeboerse.anbieten'));

        $response->assertOk();
        $this->assertDatabaseCount('hundeboerse_anzeigen', 0);
    }

    public function test_alte_hundeboerse_urls_werden_auf_neue_laravel_routen_umgeleitet(): void
    {
        $anzeige = $this->anlegen(['id' => 'hb-legacy-redirect']);

        $this->get('/hundeboerse/index.html')->assertRedirect('/hundeboerse');
        $this->get('/hundeboerse/anbieten.html')->assertRedirect('/hundeboerse/anbieten');
        $this->get('/hundeboerse/detail.html?id='.$anzeige->id)->assertRedirect('/hundeboerse/detail/'.$anzeige->id);
        $this->get('/hundeboerse/detail.html')->assertRedirect('/hundeboerse');
    }
}
