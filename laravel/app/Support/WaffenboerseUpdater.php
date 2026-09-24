<?php

namespace App\Support;

use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseBild;
use App\Models\WaffenboerseKaliber;
use Illuminate\Http\UploadedFile;

/**
 * KJS Bad Segeberg - Phase 7J (Admin-Modul "Waffenboerse").
 *
 * Buendelt die Feld-Zuweisung sowie die Bildergalerie-/Kaliberlisten-Pflege
 * fuer den neuen Blade-Admin (Admin\WaffenboerseController) - Gegenstueck zu
 * HundeboerseUpdater (Phase 7I), siehe dessen Klassenkommentar fuer das
 * grundsaetzliche Muster. NICHT blind von dort kopiert - Waffenboerse hat
 * strukturell andere Fachfelder (siehe Admin\WaffenboerseController-
 * Klassenkommentar fuer die vollstaendige Alt-Admin-/Datenmodell-Analyse):
 * kein Einzelhund-/Wurf-Unterschied, dafuer eine eigene Kaliber-Liste
 * (waffenboerse_kaliber - eine Zeile pro Kaliber statt eines Skalarfelds)
 * und eine echte, admin-verwaltete Kategorienliste (waffenboerse_kategorien,
 * siehe kategorieHinzufuegen()/kategorieLoeschen() im Controller) statt
 * einer nur automatisch wachsenden Vorschlagsliste wie Hundeboerse-
 * Zuchtverbaende.
 *
 * ANDERS ALS PartnerUpdater/TerminUpdater/DownloadUpdater/BeitragUpdater/
 * HundeboerseUpdater: auch diese Klasse wird NICHT zusaetzlich von
 * Api\Admin\AdminListController genutzt - der alte admin.js schreibt fuer
 * die Waffenboerse ausschliesslich in content/waffenboerse.json (komplett
 * getrennte Datenquelle, siehe Controller-Klassenkommentar "KONKURRIERENDE
 * SCHREIBWEGE"), niemals in diese MySQL-Tabellen.
 */
class WaffenboerseUpdater
{
    public const MAX_BILDER = 10;

    /**
     * Bis zu 20 Kaliber-Zeilen, je Zeile max. 100 Zeichen - exakt dieselben
     * Grenzen wie die oeffentliche Einreichung (siehe
     * WaffenboerseAnbietenRequest::rules()).
     */
    public const MAX_KALIBER = 20;

    private const MAX_KALIBER_LAENGE = 100;

    private const FELDER = [
        'status', 'titel', 'kategorie', 'hersteller', 'modell', 'zustand',
        'preis', 'preis_typ', 'erwerbsberechtigung_erforderlich', 'beschreibung',
        'plz', 'ort', 'versand_moeglich', 'versandkosten',
        'anbieter_name', 'anbieter_email', 'anbieter_telefon',
    ];

    /**
     * Einzige mediumText()->nullable()-Spalte der Migration - alle uebrigen
     * FELDER-Eintraege sind NOT NULL mit Default '' (siehe Migration
     * 2026_09_14_000110_create_waffenboerse_anzeigen_table.php).
     */
    private const NULLABLE_FELDER = ['beschreibung'];

    /**
     * Setzt alle "einfachen" Felder und speichert. $data kommt beim Aufrufer
     * bereits aus einem Preservation-Merge (siehe Admin\
     * WaffenboerseController::currentFieldValues()) - nur Schluessel, die
     * tatsaechlich im Array vorkommen, werden angefasst, damit ein
     * fehlender Schluessel niemals versehentlich NULL setzt.
     *
     * NULL-HANDLING (siehe HundeboerseUpdater::applyFields()-
     * Klassenkommentar fuer die ausfuehrliche Begruendung - identisches
     * Problem/identische Loesung): Laravels ConvertEmptyStringsToNull-
     * Middleware wandelt ein leer abgeschicktes Formularfeld vor dem
     * Controller automatisch in null um. Alle Spalten AUSSER
     * NULLABLE_FELDER werden deshalb explizit auf einen String (Default '')
     * normalisiert.
     *
     * "beschreibung" ist bereits fertiges HTML (siehe
     * resources/views/admin/inhalte/_richtext-field.blade.php-
     * Wiederverwendung im Formular, Controller-Klassenkommentar
     * "BESCHREIBUNG") - wird hier UNVERAENDERT uebernommen, NICHT durch
     * Text::freeTextToSafeParagraphs() geschickt (das wuerde bereits
     * vorhandenes HTML kaputt escapen - siehe dortiger Klassenkommentar
     * "NICHT verwenden fuer bereits vertrauenswuerdiges HTML").
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(WaffenboerseAnzeige $anzeige, array $data): void
    {
        foreach (self::FELDER as $feld) {
            if (! array_key_exists($feld, $data)) {
                continue;
            }

            $wert = $data[$feld];
            if (in_array($feld, ['erwerbsberechtigung_erforderlich', 'versand_moeglich'], true)) {
                $wert = (bool) $wert;
            } elseif (! in_array($feld, self::NULLABLE_FELDER, true)) {
                $wert = (string) ($wert ?? '');
            }

            $anzeige->{$feld} = $wert;
        }
        $anzeige->save();
    }

    /**
     * Neue, stabile Anzeigen-ID - exakt dasselbe Format wie die oeffentliche
     * Einreichung (WaffenboerseController::store()), damit Admin-angelegte
     * und oeffentlich eingereichte Anzeigen ununterscheidbare IDs haben.
     */
    public static function neueId(): string
    {
        return 'wb-'.round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
    }

    /**
     * Zerlegt den Kaliber-Textarea-Wert (eine Zeile pro Kaliber - siehe
     * Formular-Klassenkommentar "KALIBER" fuer die Begruendung, warum
     * bewusst KEIN Komma-Trennfeld wie im allerersten Waffenboerse-
     * Prototyp verwendet wird) in eine bereinigte Liste: getrimmt, leere
     * Zeilen verworfen, auf MAX_KALIBER Zeilen und je Zeile
     * MAX_KALIBER_LAENGE Zeichen begrenzt (identische Grenzen wie
     * WaffenboerseAnbietenRequest::rules(), grosszuegig statt hart
     * validiert - der Admin ist ein vertrauenswuerdiger Nutzer, siehe
     * Controller-Klassenkommentar "VALIDIERUNG").
     *
     * @return list<string>
     */
    public static function parseKaliberEingabe(string $eingabe): array
    {
        $zeilen = preg_split('/\r\n|\r|\n/', $eingabe) ?: [];
        $bereinigt = [];
        foreach ($zeilen as $zeile) {
            $zeile = mb_substr(trim($zeile), 0, self::MAX_KALIBER_LAENGE);
            if ($zeile !== '') {
                $bereinigt[] = $zeile;
            }
            if (count($bereinigt) >= self::MAX_KALIBER) {
                break;
            }
        }

        return $bereinigt;
    }

    /**
     * Ersetzt die komplette Kaliberliste einer Anzeige - exakt dasselbe
     * Verhalten wie admin.js' waffenboerseKaliberCollect() (baut bei jedem
     * Speichern die gesamte Liste aus den sichtbaren Zeilen neu auf, keine
     * gezielte Einzelzeilen-Aktualisierung). Reine DB-Zeilen ohne
     * Dateibezug - anders als removeBilder() unten ist hier KEINE
     * Nach-Commit-Sonderbehandlung noetig (siehe Auftrag 7I-Referenz "Datei-
     * sicherheit" - betrifft ausschliesslich physische Dateien).
     *
     * @param  list<string>  $kaliberListe
     */
    public static function replaceKaliber(WaffenboerseAnzeige $anzeige, array $kaliberListe): void
    {
        $anzeige->kaliber()->delete();
        foreach (array_values($kaliberListe) as $i => $kaliber) {
            WaffenboerseKaliber::create([
                'anzeige_id' => $anzeige->id,
                'kaliber' => $kaliber,
                'sortierung' => $i,
            ]);
        }
    }

    /**
     * Haengt neu hochgeladene Bilder ans Ende der bestehenden Galerie an
     * (Preservation: vorhandene Bilder/Reihenfolge bleiben unangetastet).
     * Die Obergrenze (MAX_BILDER) wird bereits vom Aufrufer geprueft (siehe
     * Admin\WaffenboerseController::update()) - hier keine zweite Pruefung,
     * um nicht zwei Stellen synchron halten zu muessen.
     *
     * @param  list<UploadedFile>  $files
     */
    public static function addBilder(WaffenboerseAnzeige $anzeige, array $files): void
    {
        if (! $files) {
            return;
        }

        $vorhandene = $anzeige->bilder()->count();
        $gespeichert = BoerseUploads::store($files, 'waffenboerse');
        foreach (array_values($gespeichert) as $i => $bild) {
            WaffenboerseBild::create([
                'anzeige_id' => $anzeige->id,
                'pfad' => $bild['pfad'],
                'titel' => $bild['titel'],
                'sortierung' => $vorhandene + $i,
            ]);
        }
    }

    /**
     * Entfernt gezielt einzelne DB-Zeilen (per ID) - alle UEBRIGEN Bilder
     * der Galerie bleiben unangetastet.
     *
     * TRANSAKTIONSSICHERHEIT (1:1 aus HundeboerseUpdater::removeBilder()
     * uebernommen, siehe dortiger Klassenkommentar fuer die vollstaendige
     * Begruendung - Phase 7I korrigierte dort einen echten Datenverlust-
     * Bug: eine DB-Transaktion kann eine bereits erfolgte Dateisystem-
     * Loeschung nicht zurueckrollen): loescht bewusst NUR die DB-Zeile(n),
     * NICHT die physische(n) Datei(en). Der Aufrufer (Admin\
     * WaffenboerseController::update()) sammelt die zurueckgegebenen Pfade
     * und loescht die physischen Dateien selbst erst NACH einem
     * erfolgreichen DB::transaction()-Commit.
     *
     * @param  list<int>  $bildIds
     * @return list<string> Pfade der geloeschten Bilder (zur physischen
     *                      Loeschung durch den Aufrufer nach Commit).
     */
    public static function removeBilder(WaffenboerseAnzeige $anzeige, array $bildIds): array
    {
        if (! $bildIds) {
            return [];
        }

        $bilder = $anzeige->bilder()->whereIn('id', $bildIds)->get();
        $pfade = [];
        foreach ($bilder as $bild) {
            $pfade[] = $bild->pfad;
            $bild->delete();
        }

        return $pfade;
    }
}
