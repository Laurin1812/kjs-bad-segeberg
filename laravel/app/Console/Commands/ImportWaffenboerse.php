<?php

namespace App\Console\Commands;

use App\Models\WaffenboerseAnzeige;
use App\Models\WaffenboerseBild;
use App\Models\WaffenboerseKaliber;
use App\Models\WaffenboerseKategorie;
use App\Support\BoerseUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * KJS Bad Segeberg - Phase 6B (Waffenboerse auf Laravel/MySQL).
 *
 * Liest content/waffenboerse.json (unveraendert, read-only) und uebernimmt
 * Bestands-Anzeigen/Bilder/Kaliber/Kategorien verlustfrei in die
 * waffenboerse_*-Tabellen (siehe WaffenboerseAnzeige-Klassenkommentar).
 * Analog zu kjs:import-hundeboerse (Phase 6A) - siehe dortigen
 * Klassenkommentar fuer die ausfuehrliche Begruendung, warum dies ein
 * eigenes, von ImportContent GETRENNTES Kommando ist (ImportContent
 * truncatet auf --force, waffenboerse_* nimmt aber ab Phase 6B echte
 * oeffentliche Einreichungen entgegen - siehe WaffenboerseController::
 * store()). Dieses Kommando ist daher bewusst NIE destruktiv (kein
 * TRUNCATE) und rein additiv/idempotent (Existenzpruefung anhand der
 * stabilen "id" aus der JSON-Datei - "keine Doppelimporte", siehe Auftrag).
 *
 * Bilder: die JSON-Pfade ("/images/<datei>") verweisen auf den ALTEN
 * Webroot (ein Verzeichnis oberhalb des Laravel-Projekts, siehe
 * $webrootPath) - werden hier ueber BoerseUploads::importLocalFile() in den
 * Laravel-eigenen Pfad public/uploads/boersen/waffenboerse/ kopiert (siehe
 * dortigen Kommentar), damit die oeffentliche Waffenboerse-Seite ab Phase
 * 6B keine Laufzeit-Abhaengigkeit mehr auf den alten Webroot hat. Fehlt
 * eine Bilddatei am erwarteten Pfad, wird das Bild uebersprungen und
 * gezaehlt (siehe $bilderFehlend) statt den ganzen Import abzubrechen.
 */
class ImportWaffenboerse extends Command
{
    protected $signature = 'kjs:import-waffenboerse
        {--content-path= : Pfad zum content/-Verzeichnis (Default: eine Ebene ueber dem Laravel-Projekt)}
        {--webroot-path= : Pfad zum alten Webroot fuer Bilder (Default: eine Ebene ueber dem Laravel-Projekt)}';

    protected $description = 'Uebernimmt Bestandsdaten aus content/waffenboerse.json in die waffenboerse_*-Tabellen (Phase 6B, rein additiv, keine Doppelimporte).';

    public function handle(): int
    {
        $configuredContentPath = $this->option('content-path');
        $contentPath = $configuredContentPath
            ? rtrim($configuredContentPath, '/')
            : rtrim(base_path('../content'), '/');
        $file = $contentPath.'/waffenboerse.json';

        $configuredWebrootPath = $this->option('webroot-path');
        $webrootPath = $configuredWebrootPath
            ? rtrim($configuredWebrootPath, '/')
            : rtrim(base_path('..'), '/');

        if (! File::exists($file)) {
            $this->error("content/waffenboerse.json nicht gefunden unter: {$file}");

            return self::FAILURE;
        }

        $raw = File::get($file);
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON-Fehler in content/waffenboerse.json: '.json_last_error_msg());

            return self::FAILURE;
        }
        $data = is_array($data) ? $data : [];

        $anzeigenQuelle = is_array($data['anzeigen'] ?? null) ? $data['anzeigen'] : [];
        $kategorienQuelle = is_array($data['kategorien'] ?? null) ? $data['kategorien'] : [];

        $anzeigenGefunden = count($anzeigenQuelle);
        $anzeigenNeu = 0;
        $anzeigenUebersprungen = 0;
        $bilderNeu = 0;
        $bilderFehlend = 0;
        $kategorienNeu = 0;

        DB::transaction(function () use (
            $anzeigenQuelle, $kategorienQuelle, $webrootPath,
            &$anzeigenNeu, &$anzeigenUebersprungen, &$bilderNeu, &$bilderFehlend, &$kategorienNeu
        ) {
            foreach ($kategorienQuelle as $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }
                $kat = WaffenboerseKategorie::firstOrCreate(['name' => trim($name)]);
                if ($kat->wasRecentlyCreated) {
                    $kategorienNeu++;
                }
            }

            foreach ($anzeigenQuelle as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }

                // Idempotenz: existiert die ID bereits (z.B. aus einer
                // frueheren Kommando-Ausfuehrung oder weil zwischenzeitlich
                // dieselbe Anzeige oeffentlich ueber WaffenboerseController::
                // store() eingereicht wurde), wird NICHTS ueberschrieben -
                // "keine Doppelimporte" (Auftrag).
                if (WaffenboerseAnzeige::whereKey((string) $item['id'])->exists()) {
                    $anzeigenUebersprungen++;

                    continue;
                }

                $attribute = [
                    'id' => (string) $item['id'],
                    'status' => (string) ($item['status'] ?? 'pending'),
                    'titel' => (string) ($item['titel'] ?? ''),
                    'kategorie' => (string) ($item['kategorie'] ?? ''),
                    'hersteller' => (string) ($item['hersteller'] ?? ''),
                    'modell' => (string) ($item['modell'] ?? ''),
                    'zustand' => (string) ($item['zustand'] ?? ''),
                    'preis' => (string) ($item['preis'] ?? ''),
                    'preis_typ' => (string) ($item['preis_typ'] ?? ''),
                    'erwerbsberechtigung_erforderlich' => (bool) ($item['erwerbsberechtigung_erforderlich'] ?? false),
                    // "beschreibung" ist im Bestand bereits fertiges,
                    // sanitisiertes HTML aus dem TipTap-Editor des alten
                    // Admin-Bereichs (siehe Klassenkommentar/Migrations-
                    // Kommentar) - wird UNVERAENDERT uebernommen, NICHT
                    // nochmal durch Text::freeTextToSafeParagraphs()
                    // geschickt (das ist ausschliesslich fuer neue,
                    // oeffentliche Freitext-Einreichungen, siehe
                    // WaffenboerseController::store()).
                    'beschreibung' => $item['beschreibung'] ?? null,
                    'plz' => (string) ($item['plz'] ?? ''),
                    'ort' => (string) ($item['ort'] ?? ''),
                    'versand_moeglich' => (bool) ($item['versand_moeglich'] ?? false),
                    'versandkosten' => (string) ($item['versandkosten'] ?? ''),
                    'anbieter_name' => (string) ($item['anbieter_name'] ?? ''),
                    'anbieter_email' => (string) ($item['anbieter_email'] ?? ''),
                    'anbieter_telefon' => (string) ($item['anbieter_telefon'] ?? ''),
                ];

                // "erstellt_am"/"aktualisiert_am" (NOT-NULL-Spalten mit
                // useCurrent()-Default, siehe Migration) nur setzen, wenn
                // die JSON-Datei einen gueltigen Wert liefert - sonst den
                // Schluessel ganz weglassen, damit Eloquents normale
                // CREATED_AT/UPDATED_AT-Logik "jetzt" eintraegt statt
                // versehentlich NULL in eine NOT-NULL-Spalte zu schreiben.
                $erstelltAm = $this->parseIsoDatum($item['erstellt_am'] ?? null);
                if ($erstelltAm !== null) {
                    $attribute['erstellt_am'] = $erstelltAm;
                }
                $aktualisiertAm = $this->parseIsoDatum($item['aktualisiert_am'] ?? null);
                if ($aktualisiertAm !== null) {
                    $attribute['aktualisiert_am'] = $aktualisiertAm;
                }

                $anzeige = WaffenboerseAnzeige::create($attribute);
                $anzeigenNeu++;

                $galerie = is_array($item['bilder'] ?? null) ? $item['bilder'] : [];
                foreach (array_values($galerie) as $i => $bild) {
                    if (! is_array($bild) || empty($bild['bild'])) {
                        continue;
                    }
                    $relativerPfad = ltrim((string) $bild['bild'], '/');
                    $quellpfad = $webrootPath.'/'.$relativerPfad;
                    $dateiname = basename($relativerPfad);

                    $importiert = BoerseUploads::importLocalFile($quellpfad, 'waffenboerse', $dateiname);
                    if ($importiert === null) {
                        $bilderFehlend++;

                        continue;
                    }

                    WaffenboerseBild::create([
                        'anzeige_id' => $anzeige->id,
                        'pfad' => $importiert['pfad'],
                        'titel' => (string) ($bild['titel'] ?? ''),
                        'sortierung' => $i,
                    ]);
                    $bilderNeu++;
                }

                $kaliberListe = is_array($item['kaliber'] ?? null) ? $item['kaliber'] : [];
                foreach (array_values($kaliberListe) as $i => $kaliber) {
                    if (! is_string($kaliber) || trim($kaliber) === '') {
                        continue;
                    }
                    WaffenboerseKaliber::create([
                        'anzeige_id' => $anzeige->id,
                        'kaliber' => trim($kaliber),
                        'sortierung' => $i,
                    ]);
                }
            }
        });

        $this->info("Waffenboerse-Import abgeschlossen: {$anzeigenGefunden} Anzeige(n) in JSON gefunden, {$anzeigenNeu} neu importiert, {$anzeigenUebersprungen} bereits vorhanden (uebersprungen), {$bilderNeu} Bild(er) importiert, {$kategorienNeu} neue Kategorie(n).");
        if ($bilderFehlend > 0) {
            $this->warn("{$bilderFehlend} Bild(er) konnten nicht gefunden/gelesen werden (siehe --webroot-path) und wurden uebersprungen.");
        }

        return self::SUCCESS;
    }

    /**
     * Wandelt ein ISO-8601-Datum aus content/waffenboerse.json (z.B.
     * "2026-08-31T14:40:00.000Z") in das von den Migrationen erwartete
     * MySQL-Datetime-Format mit Millisekunden-Praezision um. Fehlt/ist
     * ungueltig, wird null zurueckgegeben - Eloquent setzt dann ueber die
     * normale CREATED_AT/UPDATED_AT-Logik "jetzt" (kein Datenverlust bei
     * fehlendem Quellwert, aber auch keine erfundene Zeitangabe).
     */
    private function parseIsoDatum(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('Y-m-d H:i:s.u');
        } catch (\Throwable) {
            return null;
        }
    }
}
