<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Phase 7L (Admin-Modul "Einstellungen") - Schreiblogik fuer die vier
 * "einfachen" (rein skalaren) Settings-Gruppen aus der bestehenden
 * "settings"-Tabelle (Fall A: siehe App\Models\Setting-Klassenkommentar,
 * dieselbe Tabelle, die schon seit Phase 3/4 von den View-Composern
 * (DesignComposer/TopbarComposer/FooterComposer) gelesen und von der
 * bestehenden JSON-API (Api\Admin\AdminSettingsController) geschrieben
 * wird) - "design", "einstellungen" (Kontakt & Stammdaten), "footer" und
 * "impressum". Dieselben "gruppe"-Werte wie in AdminSettingsController
 * (keine neue zweite Konfigurationsarchitektur, Auftrag "bestehende
 * Datenquelle verwenden").
 *
 * BEWUSST NICHT verwendet: "navigation"/"navigation_extra" (Blob-Struktur,
 * Drag&Drop-Reihenfolge im Alt-Admin, siehe admin.js renderNavReihenfolge())
 * - das ist eine grundlegend andere, deutlich komplexere UI-Aufgabe
 * (sortierbare Listen, verschachtelte Baumstruktur) als ein einfaches
 * Einstellungen-Formular und bleibt bewusst ausserhalb dieser Phase (siehe
 * Abschlussbericht "was noch fehlt").
 *
 * VERSIONIERUNG: nutzt fuer jede Gruppe denselben ContentVersioning-
 * Schluessel wie AdminSettingsController (Section-Name == Gruppen-Name,
 * z.B. Section "design" fuer Gruppe "design") - ein Konflikt bedeutet daher
 * "diese Einstellungen wurden zwischenzeitlich per JSON-API ODER per neuem
 * Blade-Admin anderswo gespeichert", exakt dasselbe Prinzip wie bei
 * PartnerController/AdminListController::SECTION_PARTNER.
 *
 * PRESERVATION (Auftrag Phase 7L, Teil A Punkt 4 - bewusste Abweichung von
 * AdminSettingsController::replaceScalarSettings()): die alte JSON-API
 * ersetzt beim Speichern IMMER die komplette Gruppe (loescht zuerst alle
 * Zeilen, legt dann die im Payload gelieferten neu an - 1:1 das alte
 * Verhalten "ganze Datei ueberschreiben"). Diese neue Klasse schreibt
 * stattdessen NUR die einzelnen, hier explizit bekannten Schluessel
 * (ALLOWLIST je Gruppe) per updateOrCreate() - ein evtl. vorhandener
 * Fremdschluessel derselben Gruppe (z.B. das ungenutzte Altfeld
 * "telefon_festnetz" in "einstellungen", das in keinem Formular - alt oder
 * neu - jemals bearbeitet wird) bleibt dadurch unangetastet, statt beim
 * naechsten Speichern stillschweigend zu verschwinden. Das entspricht
 * genau der in Punkt 4 verlangten Prinzip "nicht gesendete Settings bleiben
 * erhalten" bzw. "serverseitige Allowlist" - und aendert nichts am
 * Verhalten der bestehenden JSON-API selbst (die bleibt unangetastet).
 *
 * "oeffnungszeiten" (Auftrag Punkt "dynamische Zeilen mit Add/Delete" im
 * Alt-Admin, admin.js' renderKontaktStammdaten()/kontaktOzAdd()/
 * kontaktOzDelete()): bleibt in der Datenbank exakt dieselbe JSON-Struktur
 * ([{"tage":...,"zeiten":...}, ...], siehe TopbarComposer/Kontaktbox-
 * Anzeige - keine Aenderung am oeffentlichen Lesepfad), wird in der neuen
 * Blade-Maske aber - genau wie PartnerController/PartnerUpdater es bereits
 * fuer "vorteile" vormacht ("bewusst Freitext, eine Zeile je Eintrag" statt
 * eines neuen JS-Listeneditors) - als eine Zeile-je-Sprechzeit-Freitext
 * ("Tage | Uhrzeit") erfasst statt eines neuen dynamischen Add/Delete-
 * Zeilen-Widgets (das haette neues JS im bewusst framework-freien Blade-
 * Admin noetig gemacht). Kein neues JS, keine Verhaltensaenderung fuer die
 * oeffentliche Anzeige.
 */
class EinstellungenUpdater
{
    /** @var array<string, list<string>> Bekannte, editierbare Skalar-Schluessel je Gruppe (ohne "oeffnungszeiten", siehe unten). */
    private const ALLOWED_KEYS = [
        'design' => [
            'farbe_gruen', 'farbe_dunkelgruen', 'farbe_akzent',
            'schrift_ueberschrift', 'schrift_text',
            'schriftgroesse_h1', 'schriftgroesse_h2', 'schriftgroesse_h3', 'schriftgroesse_text',
        ],
        'einstellungen' => [
            'telefon_header', 'telefon', 'email', 'adresse', 'postadresse',
            'postadresse_telefon', 'postadresse_email',
            'kontakt_ueberschrift', 'kontakt_text',
            'google_kalender_url', 'google_kalender_titel',
            'infomobil_timetree_url',
        ],
        'footer' => ['ueber_text', 'copyright', 'facebook_url', 'instagram_url'],
        'impressum' => ['verein', 'vertreten_durch', 'registergericht', 'registernummer', 'verantwortlich'],
    ];

    /** @return list<string> */
    public static function allowedKeys(string $gruppe): array
    {
        return self::ALLOWED_KEYS[$gruppe] ?? [];
    }

    public static function isKnownGruppe(string $gruppe): bool
    {
        return array_key_exists($gruppe, self::ALLOWED_KEYS);
    }

    /**
     * Aktuelle Werte aller bekannten Skalar-Schluessel dieser Gruppe (fehlt
     * eine Zeile in der DB noch, liefert sie '' statt null - fuer das
     * Formular-Prefill wie fuer die Preservation-Zusammenfuehrung in
     * apply() gleichermassen unschaedlich).
     *
     * @return array<string, string>
     */
    public static function currentValues(string $gruppe): array
    {
        $vorhanden = Setting::where('gruppe', $gruppe)
            ->whereIn('key', self::allowedKeys($gruppe))
            ->pluck('value', 'key');

        $werte = [];
        foreach (self::allowedKeys($gruppe) as $key) {
            $werte[$key] = (string) ($vorhanden[$key] ?? '');
        }

        return $werte;
    }

    /** Aktuelle "oeffnungszeiten" als Freitext ("Tage | Uhrzeit", eine Zeile je Eintrag) fuer das Formular-Prefill. */
    public static function currentOeffnungszeitenText(): string
    {
        $roh = Setting::where('gruppe', 'einstellungen')->where('key', 'oeffnungszeiten')->value('value');
        $liste = is_string($roh) ? json_decode($roh, true) : null;
        if (! is_array($liste)) {
            return '';
        }

        $zeilen = [];
        foreach ($liste as $eintrag) {
            if (! is_array($eintrag)) {
                continue;
            }
            $tage = trim((string) ($eintrag['tage'] ?? ''));
            $zeiten = trim((string) ($eintrag['zeiten'] ?? ''));
            $zeilen[] = $zeiten === '' ? $tage : $tage.' | '.$zeiten;
        }

        return implode("\n", $zeilen);
    }

    /**
     * Schreibt genau die bekannten Schluessel dieser Gruppe (siehe
     * ALLOWED_KEYS) - ein im Payload fehlender Schluessel bleibt unbekannt
     * und wird deshalb erst gar nicht angefasst (Preservation liegt bereits
     * beim Aufrufer: siehe EinstellungenController::update(), das fehlende
     * Formularwerte vorab mit currentValues() auffuellt, bevor apply()
     * gerufen wird - identisch zum etablierten Partner-/Termine-Muster).
     *
     * @param  array<string, string>  $data
     */
    public static function apply(string $gruppe, array $data): void
    {
        foreach (self::allowedKeys($gruppe) as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            Setting::updateOrCreate(
                ['gruppe' => $gruppe, 'key' => $key],
                ['value' => (string) $data[$key]]
            );
        }
    }

    /**
     * Parst die Freitext-"oeffnungszeiten" ("Tage | Uhrzeit" je Zeile,
     * siehe Klassenkommentar) und speichert sie 1:1 in derselben JSON-Form,
     * die die oeffentliche Anzeige (TopbarComposer/Kontaktbox) bereits
     * erwartet. Eine Zeile ganz ohne "|" wird tolerant als reine
     * "tage"-Angabe ohne Uhrzeit uebernommen (kein Datenverlust bei
     * versehentlich vergessenem Trennzeichen). Komplett leere Zeilen werden
     * uebersprungen.
     */
    public static function applyOeffnungszeiten(string $text): void
    {
        $eintraege = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '') {
                continue;
            }
            if (str_contains($zeile, '|')) {
                [$tage, $zeiten] = array_map('trim', explode('|', $zeile, 2));
            } else {
                $tage = $zeile;
                $zeiten = '';
            }
            $eintraege[] = ['tage' => $tage, 'zeiten' => $zeiten];
        }

        Setting::updateOrCreate(
            ['gruppe' => 'einstellungen', 'key' => 'oeffnungszeiten'],
            ['value' => json_encode($eintraege, JSON_UNESCAPED_UNICODE)]
        );
    }
}
