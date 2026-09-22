<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 Abschluss-Nacharbeit ("Kontakt-Route").
 *
 * Ersetzt kontakt/index.html. Die Navigation (settings-Gruppe
 * "navigation", siehe App\Support\Navigation) verweist bereits seit Phase 4
 * auf "/kontakt" (und mehrere bereits migrierte Seiten verlinken ebenfalls
 * dorthin, z.B. pages/show.blade.php "Kontakt aufnehmen"-Buttons) - bislang
 * existierte dafuer aber keine Route, weshalb der Navigationspunkt auf eine
 * echte Laravel-404 lief.
 *
 * WICHTIG (bewusste Abgrenzung): kontakt/index.html im alten Webroot ist
 * ein Sondermodul mit einem echten Kontaktformular (POST an api/contact.php,
 * Honeypot, Hegering-Auswahl per fetch aus api/content/hegeringe.json,
 * Google-Maps-Embed) UND einer daneben liegenden, rein informativen
 * Kontaktspalte (Adresse/Telefon/E-Mail/Postadresse/Sprechzeiten aus
 * content/einstellungen.json). Nur die zweite Haelfte ist Teil dieses
 * Auftrags ("Kontakt-Sondermodul-Migration" ist laut Auftrag weiterhin
 * NICHT im Umfang, siehe auch app/Models/KontaktAnfrage.php-Kommentar zur
 * separaten, eigenstaendigen Kontaktformular-Datenbank/-PHP-Sondermodule
 * api/kontakt/*.php - diese Route ersetzt/beruehrt diese Sondermodule
 * nicht). Diese Seite zeigt deshalb ausschliesslich die informativen
 * Kontaktdaten als echte Laravel-Inhaltsseite (Route -> Controller ->
 * Eloquent/MySQL -> Blade), dieselbe Settings-Gruppe "einstellungen" wie
 * Topbar/Kontaktbox/Impressum/Datenschutz (keine neue Datenquelle, keine
 * erfundenen Kontaktdaten). Fehlt ein Feld in der DB, wird es in der View
 * schlicht weggelassen statt eines erfundenen Platzhalters.
 */
class KontaktController extends Controller
{
    public function show(): View
    {
        $einstellungen = Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');

        $oeffnungszeitenRoh = $einstellungen['oeffnungszeiten'] ?? null;
        $oeffnungszeiten = is_string($oeffnungszeitenRoh) && $oeffnungszeitenRoh !== ''
            ? (json_decode($oeffnungszeitenRoh, true) ?: [])
            : [];

        return view('kontakt', [
            'ueberschrift' => (string) ($einstellungen['kontakt_ueberschrift'] ?? ''),
            'text' => (string) ($einstellungen['kontakt_text'] ?? ''),
            'adresse' => (string) ($einstellungen['adresse'] ?? ''),
            'telefon' => (string) ($einstellungen['telefon'] ?? ''),
            'email' => (string) ($einstellungen['email'] ?? ''),
            'postadresse' => (string) ($einstellungen['postadresse'] ?? ''),
            'postadresseTelefon' => (string) ($einstellungen['postadresse_telefon'] ?? ''),
            'postadresseEmail' => (string) ($einstellungen['postadresse_email'] ?? ''),
            'oeffnungszeiten' => is_array($oeffnungszeiten) ? $oeffnungszeiten : [],
        ]);
    }
}
