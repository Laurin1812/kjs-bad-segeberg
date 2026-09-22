<?php

namespace App\Console\Commands;

use App\Models\HundeboerseAnzeige;
use App\Models\HundeboerseBild;
use App\Models\HundeboerseMeta;
use App\Models\HundeboerseZuchtverband;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * KJS Bad Segeberg - Phase 6A (Sondermodule inventarisieren + Hundeboerse
 * auf Laravel/MySQL).
 *
 * Liest content/hundeboerse.json (unveraendert, read-only) und uebernimmt
 * Bestands-Anzeigen/Zuchtverbaende/Hero-Bild verlustfrei in die
 * hundeboerse_*-Tabellen (siehe HundeboerseAnzeige-Klassenkommentar).
 *
 * BEWUSST EIN EIGENES, VON ImportContent GETRENNTES KOMMANDO (statt
 * ImportContent::handle() um einen "importHundeboerse()"-Aufruf zu
 * erweitern): ImportContent ist als TRUNCATE-und-neu-Importieren-Werkzeug
 * gebaut (--force leert vorher $managedTables, siehe dortiger
 * Klassenkommentar "AUSDRUECKLICH NICHT enthalten: hundeboerse_*") - das
 * passt zum Anwendungsfall von ImportContent (rein redaktionelle Inhalte
 * aus content/*.json, jederzeit gefahrlos neu einlesbar), aber NICHT zur
 * Hundeboerse: dort landen ab Phase 6A echte oeffentliche Einreichungen
 * direkt in derselben Tabelle (siehe HundeboerseController::store()) - ein
 * kuenftiges "--force" auf ImportContent duerfte diese echten Anzeigen
 * niemals loeschen. Dieses Kommando ist daher bewusst NIE destruktiv
 * (kein TRUNCATE) und rein additiv/idempotent (firstOrCreate anhand der
 * stabilen "id" aus der JSON-Datei - "keine Doppelimporte", siehe Auftrag).
 *
 * content/hundeboerse.json enthielt zum Zeitpunkt der Migration (Phase 6A)
 * keine einzige echte Anzeige ("anzeigen": []) - die eigentliche
 * Produktivdatenquelle war die separate PHP+MySQL-Datenbank hinter
 * api/hundeboerse/anzeigen.php, die von dieser Sandbox aus nicht erreichbar
 * ist (siehe Abschlussbericht). Dieses Kommando importiert trotzdem korrekt
 * 0 Anzeigen und bleibt fuer den Fall vorbereitet, dass content/
 * hundeboerse.json spaeter doch befuellt wird (z.B. durch einen manuellen
 * Export aus der Produktivdatenbank).
 */
class ImportHundeboerse extends Command
{
    protected $signature = 'kjs:import-hundeboerse
        {--content-path= : Pfad zum content/-Verzeichnis (Default: eine Ebene ueber dem Laravel-Projekt)}';

    protected $description = 'Uebernimmt Bestandsdaten aus content/hundeboerse.json in die hundeboerse_*-Tabellen (Phase 6A, rein additiv, keine Doppelimporte).';

    public function handle(): int
    {
        $configuredPath = $this->option('content-path');
        $contentPath = $configuredPath
            ? rtrim($configuredPath, '/')
            : rtrim(base_path('../content'), '/');
        $file = $contentPath.'/hundeboerse.json';

        if (! File::exists($file)) {
            $this->error("content/hundeboerse.json nicht gefunden unter: {$file}");

            return self::FAILURE;
        }

        $raw = File::get($file);
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON-Fehler in content/hundeboerse.json: '.json_last_error_msg());

            return self::FAILURE;
        }
        $data = is_array($data) ? $data : [];

        $anzeigenQuelle = is_array($data['anzeigen'] ?? null) ? $data['anzeigen'] : [];
        $zuchtverbaendeQuelle = is_array($data['zuchtverbaende'] ?? null) ? $data['zuchtverbaende'] : [];
        $heroBild = isset($data['hero_bild']) && is_string($data['hero_bild']) && $data['hero_bild'] !== '' ? $data['hero_bild'] : null;

        $anzeigenNeu = 0;
        $anzeigenUebersprungen = 0;
        $bilderNeu = 0;
        $zuchtverbaendeNeu = 0;

        DB::transaction(function () use ($anzeigenQuelle, $zuchtverbaendeQuelle, $heroBild, &$anzeigenNeu, &$anzeigenUebersprungen, &$bilderNeu, &$zuchtverbaendeNeu) {
            foreach ($anzeigenQuelle as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }

                // Idempotenz: existiert die ID bereits (z.B. aus einer
                // frueheren Kommando-Ausfuehrung oder weil zwischenzeitlich
                // dieselbe Anzeige oeffentlich ueber HundeboerseController::
                // store() eingereicht wurde), wird NICHTS ueberschrieben -
                // "keine Doppelimporte" (Auftrag).
                if (HundeboerseAnzeige::whereKey((string) $item['id'])->exists()) {
                    $anzeigenUebersprungen++;

                    continue;
                }

                $anzeige = HundeboerseAnzeige::create([
                    'id' => (string) $item['id'],
                    'status' => (string) ($item['status'] ?? 'pending'),
                    'type' => (string) ($item['type'] ?? 'single'),
                    'title' => (string) ($item['title'] ?? ''),
                    'breed' => (string) ($item['breed'] ?? ''),
                    'color' => (string) ($item['color'] ?? ''),
                    'coat' => (string) ($item['coat'] ?? ''),
                    'price_type' => (string) ($item['priceType'] ?? 'on_request'),
                    'price' => (string) ($item['price'] ?? ''),
                    'postal_code' => (string) ($item['postalCode'] ?? ''),
                    'city' => (string) ($item['city'] ?? ''),
                    'description' => $item['description'] ?? null,
                    'father' => (string) ($item['father'] ?? ''),
                    'father_tests' => (string) ($item['fatherTests'] ?? ''),
                    'mother' => (string) ($item['mother'] ?? ''),
                    'mother_tests' => (string) ($item['motherTests'] ?? ''),
                    'hunting_tests' => (string) ($item['huntingTests'] ?? ''),
                    'training_level' => $item['trainingLevel'] ?? null,
                    'provider_name' => (string) ($item['providerName'] ?? ''),
                    'contact_person' => (string) ($item['contactPerson'] ?? ''),
                    'email' => (string) ($item['email'] ?? ''),
                    'phone' => (string) ($item['phone'] ?? ''),
                    'contact_notes' => $item['contactNotes'] ?? null,
                    'dog_name' => (string) ($item['dogName'] ?? ''),
                    'birth_date' => (string) ($item['birthDate'] ?? ''),
                    'gender' => (string) ($item['gender'] ?? ''),
                    'litter_date' => (string) ($item['litterDate'] ?? ''),
                    'male_count' => (string) ($item['maleCount'] ?? ''),
                    'female_count' => (string) ($item['femaleCount'] ?? ''),
                    'gallery_title' => (string) ($item['galerie_titel'] ?? 'Bilder'),
                    'has_zuchtverband' => (bool) ($item['hasZuchtverband'] ?? false),
                    'zuchtverband' => (string) ($item['zuchtverband'] ?? ''),
                    'lat' => is_numeric($item['lat'] ?? null) ? $item['lat'] : null,
                    'lng' => is_numeric($item['lng'] ?? null) ? $item['lng'] : null,
                ]);
                $anzeigenNeu++;

                $galerie = is_array($item['galerie'] ?? null) ? $item['galerie'] : [];
                foreach (array_values($galerie) as $i => $bild) {
                    if (! is_array($bild) || empty($bild['bild'])) {
                        continue;
                    }
                    HundeboerseBild::create([
                        'anzeige_id' => $anzeige->id,
                        'pfad' => (string) $bild['bild'],
                        'titel' => (string) ($bild['titel'] ?? ''),
                        'sortierung' => $i,
                    ]);
                    $bilderNeu++;
                }
            }

            foreach ($zuchtverbaendeQuelle as $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }
                $zv = HundeboerseZuchtverband::firstOrCreate(['name' => trim($name)]);
                if ($zv->wasRecentlyCreated) {
                    $zuchtverbaendeNeu++;
                }
            }

            if ($heroBild !== null) {
                HundeboerseMeta::updateOrCreate(['id' => 1], ['hero_bild' => $heroBild]);
            }
        });

        $this->info("Hundeboerse-Import abgeschlossen: {$anzeigenNeu} neue Anzeige(n), {$anzeigenUebersprungen} bereits vorhanden (uebersprungen), {$bilderNeu} Bild(er), {$zuchtverbaendeNeu} neue(r) Zuchtverband/Zuchtverbaende.");
        if ($anzeigenNeu === 0 && $anzeigenUebersprungen === 0) {
            $this->info('content/hundeboerse.json enthielt keine Anzeigen ("anzeigen": []) - kein Datenverlust, es gab schlicht nichts zu importieren (siehe Abschlussbericht).');
        }

        return self::SUCCESS;
    }
}
