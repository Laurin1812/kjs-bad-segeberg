<?php

namespace App\Support;

use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseBild;
use App\Models\HundeboerseZuchtverband;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * KJS Bad Segeberg - Phase 7I (Admin-Modul "Hundeboerse").
 *
 * Buendelt die Feld-Zuweisung sowie die Bildergalerie-Pflege fuer den neuen
 * Blade-Admin (Admin\HundeboerseController).
 *
 * ANDERS ALS PartnerUpdater/TerminUpdater/DownloadUpdater/BeitragUpdater
 * (siehe deren Klassenkommentare): diese Klasse wird NICHT zusaetzlich von
 * Api\Admin\AdminListController genutzt, weil es dafuer keinen zweiten,
 * konkurrierenden Laravel-Schreibweg gibt (siehe Admin\HundeboerseController-
 * Klassenkommentar, Abschnitt "KONKURRIERENDE SCHREIBWEGE" fuer die
 * vollstaendige Analyse) - der alte admin.js schreibt fuer die Hundeboerse
 * ausschliesslich in content/hundeboerse.json (komplett getrennte
 * Datenquelle), niemals in diese MySQL-Tabellen. Diese Klasse existiert
 * trotzdem als eigene Einheit statt direkt im Controller, weil store()/
 * update() sonst denselben, recht umfangreichen Feld-Block doppelt haetten -
 * reine Wiederverwendung innerhalb dieses einen Moduls, keine geteilte
 * Fachlogik mit einer zweiten Oberflaeche/API.
 */
class HundeboerseUpdater
{
    public const MAX_BILDER = 10;

    private const FELDER = [
        'status', 'type', 'title', 'breed', 'color', 'coat',
        'price_type', 'price', 'postal_code', 'city', 'description',
        'father', 'father_tests', 'mother', 'mother_tests',
        'hunting_tests', 'training_level',
        'provider_name', 'contact_person', 'email', 'phone', 'contact_notes',
        'dog_name', 'birth_date', 'gender',
        'litter_date', 'male_count', 'female_count',
        'gallery_title', 'has_zuchtverband', 'zuchtverband',
    ];

    /**
     * Einzige mediumText()->nullable()-Spalten der Migration - alle uebrigen
     * FELDER-Eintraege sind NOT NULL mit Default '' (siehe Migration
     * 2026_09_14_000100_create_hundeboerse_anzeigen_table.php).
     */
    private const NULLABLE_FELDER = ['description', 'training_level', 'contact_notes'];

    /**
     * Setzt alle "einfachen" Felder und speichert. $data kommt beim Aufrufer
     * bereits aus einem Preservation-Merge (siehe Admin\HundeboerseController::
     * currentFieldValues()) - nur Schluessel, die tatsaechlich im Array
     * vorkommen, werden angefasst (analog PartnerUpdater::applyFields()),
     * damit ein fehlender Schluessel niemals versehentlich NULL setzt.
     *
     * NULL-HANDLING (per Browser-QA gefunden, siehe Klassenkommentar-
     * Aenderungshistorie): Laravels ConvertEmptyStringsToNull-Middleware
     * wandelt ein leer abgeschicktes Formularfeld VOR dem Controller
     * automatisch in null um. Fuer die meisten FELDER-Spalten ist das eine
     * echte NOT-NULL-Spalte mit Default '' (siehe Migration) - ein
     * durchgereichtes null wuerde dort eine SQL-Integrity-Constraint-
     * Verletzung ausloesen. Alle Spalten AUSSER NULLABLE_FELDER werden
     * deshalb wie beim oeffentlichen Schreibweg (HundeboerseController::
     * store()) und beim Bestandsimport (ImportHundeboerse) explizit auf
     * einen String (Default '') normalisiert.
     *
     * Waechst die Zuchtverband-Vorschlagsliste automatisch, sobald ein noch
     * unbekannter Name gespeichert wird - 1:1 dieselbe Regel wie die
     * oeffentliche Einreichung (HundeboerseController::store()) und der alte
     * Admin (admin.js' hbZuchtverbandMerken()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(HundeboerseAnzeige $anzeige, array $data): void
    {
        foreach (self::FELDER as $feld) {
            if (! array_key_exists($feld, $data)) {
                continue;
            }

            $wert = $data[$feld];
            if ($feld === 'has_zuchtverband') {
                $wert = (bool) $wert;
            } elseif (! in_array($feld, self::NULLABLE_FELDER, true)) {
                $wert = (string) ($wert ?? '');
            }

            $anzeige->{$feld} = $wert;
        }
        $anzeige->save();

        if (! empty($data['zuchtverband'] ?? null)) {
            self::zuchtverbandMerken((string) $data['zuchtverband']);
        }
    }

    /**
     * Neue, stabile Anzeigen-ID - exakt dasselbe Format wie die oeffentliche
     * Einreichung (HundeboerseController::store()), damit Admin-angelegte und
     * oeffentlich eingereichte Anzeigen ununterscheidbare IDs haben.
     */
    public static function neueId(): string
    {
        return 'hb-'.round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
    }

    /**
     * Haengt neu hochgeladene Bilder ans Ende der bestehenden Galerie an
     * (Preservation: vorhandene Bilder/Reihenfolge bleiben unangetastet).
     * Die Obergrenze (MAX_BILDER) wird bereits vom Aufrufer geprueft (siehe
     * Admin\HundeboerseController::update()) - hier keine zweite Pruefung,
     * um nicht zwei Stellen synchron halten zu muessen.
     *
     * @param  list<UploadedFile>  $files
     */
    public static function addBilder(HundeboerseAnzeige $anzeige, array $files): void
    {
        if (! $files) {
            return;
        }

        $vorhandene = $anzeige->bilder()->count();
        $gespeichert = BoerseUploads::store($files, 'hundeboerse');
        foreach (array_values($gespeichert) as $i => $bild) {
            HundeboerseBild::create([
                'anzeige_id' => $anzeige->id,
                'pfad' => $bild['pfad'],
                'titel' => $bild['titel'],
                'sortierung' => $vorhandene + $i,
            ]);
        }
    }

    /**
     * Entfernt gezielt einzelne DB-Zeilen (per ID) - alle UEBRIGEN Bilder der
     * Galerie bleiben unangetastet (siehe Auftrag "Löschen eines Bildes
     * löscht nicht die restliche Galerie").
     *
     * TRANSAKTIONSSICHERHEIT (Korrektur nach Auslieferung, vom Auftraggeber
     * gefunden): loescht bewusst NUR die DB-Zeile(n), NICHT mehr die
     * physische(n) Datei(en). Eine DB-Transaktion kann eine bereits erfolgte
     * Dateisystem-Loeschung nicht zurückrollen - schlaegt eine spaetere
     * Operation in derselben Transaktion fehl (z.B. applyFields()/
     * addBilder() im Aufrufer), waere die physische Datei sonst trotz
     * DB-Rollback unwiederbringlich weg (echtes Datenverlust-Risiko bei
     * Kundendateien). Der Aufrufer (Admin\HundeboerseController::update()/
     * destroy()) sammelt daher hier nur die Pfade ein und loescht die
     * physischen Dateien selbst erst NACH einem erfolgreichen
     * DB::transaction()-Commit.
     *
     * @param  list<int>  $bildIds
     * @return list<string> Pfade der geloeschten Bilder (zur physischen
     *                      Loeschung durch den Aufrufer nach Commit).
     */
    public static function removeBilder(HundeboerseAnzeige $anzeige, array $bildIds): array
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

    /**
     * 1:1 Port von kjs_boerse_iso_date_to_de() / admin.js' isoToDatum() -
     * wandelt ein Datum aus einem <input type="date"> (ISO "YYYY-MM-DD") in
     * das im bestehenden Datenmodell verwendete Format "DD.MM.YYYY" um.
     * Bereits identisch in HundeboerseController (oeffentlich) vorhanden -
     * hier bewusst dieselbe Logik fuer den Admin-Weg, damit beide Schreibwege
     * exakt dasselbe Datumsformat in der DB ablegen.
     */
    public static function normalizeDatum(?string $iso): string
    {
        if (! $iso) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $iso)->format('d.m.Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function zuchtverbandMerken(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        HundeboerseZuchtverband::firstOrCreate(['name' => $name]);
    }
}
