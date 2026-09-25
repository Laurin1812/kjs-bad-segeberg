<?php

namespace Tests\Feature\Phase7L;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7L (Admin-Modul "Einstellungen"), Teil A - deckt den neuen,
 * server-gerenderten Bereich unter "/admin/einstellungen" ab
 * (Http\Controllers\Admin\EinstellungenController) - siehe dortiger
 * Klassenkommentar und App\Support\EinstellungenUpdater fuer die
 * vollstaendige Analyse ("Fall A": es gibt bereits eine echte "settings"-
 * Tabelle, siehe App\Models\Setting).
 *
 * Die "settings"-Tabelle ist unter RefreshDatabase leer (kein Seeder fuellt
 * sie) - jeder Test legt genau die Zeilen an, die er braucht, exakt wie
 * bereits in tests/Feature/Phase3/GlobaleKontaktUndDesignTest.php etabliert.
 */
class AdminEinstellungenTest extends TestCase
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

    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('admin.einstellungen.index'))->assertRedirect(route('admin.login'));
    }

    public function test_nicht_admin_bekommt_403(): void
    {
        $this->actingAs($this->nichtAdmin(), 'web');

        $this->get(route('admin.einstellungen.index'))->assertForbidden();
    }

    public function test_admin_erreicht_die_seite_und_sieht_bekannte_werte(): void
    {
        Setting::create(['gruppe' => 'design', 'key' => 'farbe_gruen', 'value' => '#024511']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => '01556-0020001']);
        Setting::create(['gruppe' => 'footer', 'key' => 'copyright', 'value' => 'Copyright 2026 © Testverein']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'verein', 'value' => 'Testverein e.V.']);

        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.einstellungen.index'))
            ->assertOk()
            ->assertSee('#024511', false)
            ->assertSee('01556-0020001')
            ->assertSee('Copyright 2026 © Testverein')
            ->assertSee('Testverein e.V.');
    }

    public function test_darstellung_kann_gespeichert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'darstellung'), [
            'farbe_gruen' => '#111111',
            'schrift_ueberschrift' => 'Georgia',
            'schrift_text' => 'Inter',
            'schriftgroesse_h1' => '3rem',
        ])->assertRedirect(route('admin.einstellungen.index'));

        $this->assertSame('#111111', Setting::where('gruppe', 'design')->where('key', 'farbe_gruen')->value('value'));
        $this->assertSame('3rem', Setting::where('gruppe', 'design')->where('key', 'schriftgroesse_h1')->value('value'));
    }

    public function test_kontakt_kann_gespeichert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'kontakt'), [
            'telefon' => '0123-456789',
            'email' => 'neu@kjs-segeberg.de',
        ])->assertRedirect(route('admin.einstellungen.index'));

        $this->assertSame('0123-456789', Setting::where('gruppe', 'einstellungen')->where('key', 'telefon')->value('value'));
        $this->assertSame('neu@kjs-segeberg.de', Setting::where('gruppe', 'einstellungen')->where('key', 'email')->value('value'));
    }

    public function test_footer_kann_gespeichert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'footer'), [
            'copyright' => 'Copyright 2027 © Testverein',
        ])->assertRedirect(route('admin.einstellungen.index'));

        $this->assertSame('Copyright 2027 © Testverein', Setting::where('gruppe', 'footer')->where('key', 'copyright')->value('value'));
    }

    public function test_impressum_kann_gespeichert_werden(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'impressum'), [
            'verein' => 'Neuer Vereinsname e.V.',
        ])->assertRedirect(route('admin.einstellungen.index'));

        $this->assertSame('Neuer Vereinsname e.V.', Setting::where('gruppe', 'impressum')->where('key', 'verein')->value('value'));
    }

    public function test_unbekannter_key_wird_ignoriert(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'impressum'), [
            'verein' => 'Testverein e.V.',
            'boese_spalte' => 'sollte niemals gespeichert werden',
        ])->assertRedirect();

        $this->assertFalse(Setting::where('gruppe', 'impressum')->where('key', 'boese_spalte')->exists());
    }

    public function test_nicht_gesendete_werte_bleiben_erhalten(): void
    {
        Setting::create(['gruppe' => 'impressum', 'key' => 'verein', 'value' => 'Alter Vereinsname e.V.']);
        Setting::create(['gruppe' => 'impressum', 'key' => 'vertreten_durch', 'value' => 'Alter Vorsitzender']);
        $this->actingAs($this->admin(), 'web');

        // Sendet bewusst NUR "verein" - "vertreten_durch" fehlt komplett im
        // Request (kein leerer String, der Schluessel ist gar nicht Teil des
        // Payloads) und muss deshalb unveraendert bleiben.
        $this->put(route('admin.einstellungen.aktualisieren', 'impressum'), [
            'verein' => 'Neuer Vereinsname e.V.',
        ])->assertRedirect();

        $this->assertSame('Neuer Vereinsname e.V.', Setting::where('gruppe', 'impressum')->where('key', 'verein')->value('value'));
        $this->assertSame('Alter Vorsitzender', Setting::where('gruppe', 'impressum')->where('key', 'vertreten_durch')->value('value'));
    }

    /**
     * Legacy-Schluessel derselben Gruppe, die in KEINEM Formular (alt oder
     * neu) editierbar ist (siehe EinstellungenUpdater-Klassenkommentar
     * "telefon_festnetz") - darf durch ein normales Speichern nicht
     * verschwinden.
     */
    public function test_unbekannter_legacy_schluessel_derselben_gruppe_bleibt_erhalten(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon_festnetz', 'value' => '']);
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'telefon', 'value' => 'alt']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'kontakt'), [
            'telefon' => 'neu',
        ])->assertRedirect();

        $this->assertTrue(Setting::where('gruppe', 'einstellungen')->where('key', 'telefon_festnetz')->exists());
    }

    public function test_leeres_optionales_feld_wird_sauber_gespeichert(): void
    {
        Setting::create(['gruppe' => 'einstellungen', 'key' => 'google_kalender_url', 'value' => 'https://alt.example/']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'kontakt'), [
            'telefon' => 'irrelevant',
            'google_kalender_url' => '',
        ])->assertRedirect();

        $this->assertSame('', Setting::where('gruppe', 'einstellungen')->where('key', 'google_kalender_url')->value('value'));
    }

    public function test_oeffnungszeiten_werden_aus_freitext_geparst(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'kontakt'), [
            'telefon' => 'irrelevant',
            'oeffnungszeiten_text' => "Montag bis Freitag | 10:00 – 17:00 Uhr\nSamstag | 09:00 – 12:00 Uhr",
        ])->assertRedirect();

        $gespeichert = json_decode(Setting::where('gruppe', 'einstellungen')->where('key', 'oeffnungszeiten')->value('value'), true);

        $this->assertSame([
            ['tage' => 'Montag bis Freitag', 'zeiten' => '10:00 – 17:00 Uhr'],
            ['tage' => 'Samstag', 'zeiten' => '09:00 – 12:00 Uhr'],
        ], $gespeichert);
    }

    public function test_oeffnungszeile_ohne_trennzeichen_wird_tolerant_uebernommen(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'kontakt'), [
            'telefon' => 'irrelevant',
            'oeffnungszeiten_text' => 'Nach Vereinbarung',
        ])->assertRedirect();

        $gespeichert = json_decode(Setting::where('gruppe', 'einstellungen')->where('key', 'oeffnungszeiten')->value('value'), true);

        $this->assertSame([['tage' => 'Nach Vereinbarung', 'zeiten' => '']], $gespeichert);
    }

    public function test_oeffentliche_verwendung_bleibt_regressionsfrei(): void
    {
        Setting::create(['gruppe' => 'impressum', 'key' => 'verein', 'value' => 'Altverein e.V.']);
        $this->actingAs($this->admin(), 'web');

        $this->put(route('admin.einstellungen.aktualisieren', 'impressum'), [
            'verein' => 'Regressionstest-Verein e.V.',
        ])->assertRedirect();

        $this->get(route('impressum'))->assertOk()->assertSee('Regressionstest-Verein e.V.');
    }

    public function test_sidebar_link_ist_ein_echter_link(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.einstellungen.index'), false);
    }
}
