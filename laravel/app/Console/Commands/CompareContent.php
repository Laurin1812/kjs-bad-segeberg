<?php

namespace App\Console\Commands;

use App\Support\KjsPagesConfig;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * KJS Bad Segeberg - Phase 3 (Read-API).
 *
 * Vergleicht strukturell JEDE echte content/*.json-Datei mit der Antwort
 * des entsprechenden neuen Laravel-Read-API-Endpunkts (Auftrag Phase 3
 * Punkt 4/14: "automatisierter Vergleich alte JSON-Struktur vs.
 * API-Response"). Laeuft komplett IN-PROCESS (dispatcht die Routen ueber
 * den echten HTTP-Kernel, ohne "php artisan serve" zu benoetigen) - Grund:
 * dieses Sandbox-Environment kann selbst keinen laufenden Server starten
 * bzw. testen; der Nutzer soll dieses Kommando stattdessen lokal ausfuehren.
 *
 * Voraussetzung: Datenbank wurde bereits per
 *   php artisan migrate:fresh --seed=false && php artisan kjs:import-content --force
 * befuellt - dieses Kommando LIEST nur, es importiert nichts selbst.
 *
 * Vergleichsstrategie (bewusst pragmatisch, kein 1:1-Byte-Vergleich):
 * - FEHLENDER Schluessel in der API-Antwort, den das Original hat: ABWEICHUNG
 *   (potenzielle Regression) - wird gemeldet.
 * - ZUSAETZLICHER Schluessel in der API-Antwort, den das Original nicht hat:
 *   NUR als Hinweis gezaehlt, keine Abweichung (siehe PageContentController::
 *   pageToJson()-Kommentar: bewusste Superset-Strategie, harmlos fuers
 *   Frontend).
 * - Bekannte, bewusste Formatunterschiede (siehe $KNOWN_NORMALISATIONS)
 *   werden NICHT als Abweichung gezaehlt, sondern separat ausgewiesen.
 */
class CompareContent extends Command
{
    protected $signature = 'kjs:compare-content
        {--content-path= : Pfad zum content/-Verzeichnis (Default: neben laravel/)}
        {--fail-on-diff : Exit-Code 1 setzen, wenn echte Abweichungen gefunden wurden (fuer CI)}';

    protected $description = 'Vergleicht die echten content/*.json-Dateien strukturell mit den Phase-3-Read-API-Antworten.';

    private string $contentPath;

    /** @var list<array{modul: string, datei: string, api: string}> */
    private array $ergebnisse = [];

    private int $abweichungen = 0;

    private int $hinweise = 0;

    public function handle(): int
    {
        $configuredPath = $this->option('content-path');
        $this->contentPath = $configuredPath
            ? rtrim($configuredPath, '/')
            : rtrim(base_path('../content'), '/');

        if (! File::isDirectory($this->contentPath)) {
            $this->error("content/-Verzeichnis nicht gefunden unter: {$this->contentPath}");

            return self::FAILURE;
        }

        $this->info("KJS Read-API-Vergleich - Quelle: {$this->contentPath}");
        $this->newLine();

        // -- Settings-Familie --------------------------------------------
        $this->vergleiche('design', 'design.json', '/api/content/design.json');
        $this->vergleiche('einstellungen', 'einstellungen.json', '/api/content/einstellungen.json');
        $this->vergleiche('footer', 'footer.json', '/api/content/footer.json');
        $this->vergleiche('impressum', 'impressum.json', '/api/content/impressum.json');
        $this->vergleiche('navigation', 'navigation.json', '/api/content/navigation.json');
        $this->vergleiche('navigation-extra', 'navigation-extra.json', '/api/content/navigation-extra.json');
        $this->vergleiche('startseite', 'startseite.json', '/api/content/startseite.json');

        // -- Flache Content-Listen -----------------------------------------
        $this->vergleiche('aktuelles', 'aktuelles.json', '/api/content/aktuelles.json');
        $this->vergleiche('termine', 'termine.json', '/api/content/termine.json');
        $this->vergleiche('vorstand', 'vorstand.json', '/api/content/vorstand.json');
        $this->vergleiche('obleute', 'obleute.json', '/api/content/obleute.json');
        $this->vergleiche('hegeringe', 'hegeringe.json', '/api/content/hegeringe.json');
        $this->vergleiche('partner', 'partner.json', '/api/content/partner.json');
        $this->vergleiche('faq', 'faq.json', '/api/content/faq.json');
        $this->vergleiche('downloads', 'downloads.json', '/api/content/downloads.json');
        $this->vergleiche('kreisjjaegermeister', 'kreisjjaegermeister.json', '/api/content/kreisjjaegermeister.json');

        // -- Registries ------------------------------------------------------
        $this->vergleiche('seiten', 'seiten.json', '/api/content/seiten.json');
        $this->vergleiche('seiten-kjs', 'seiten-kjs.json', '/api/content/seiten-kjs.json');
        $this->vergleiche('seiten-aufgaben', 'seiten-aufgaben.json', '/api/content/seiten-aufgaben.json');
        $this->vergleiche('seiten-verbraucher', 'seiten-verbraucher.json', '/api/content/seiten-verbraucher.json');
        $this->vergleiche('seiten-weitere', 'seiten-weitere.json', '/api/content/seiten-weitere.json');
        $this->vergleiche('hundeausbildung-seiten', 'aufgaben/hundeausbildung-seiten.json', '/api/content/aufgaben/hundeausbildung-seiten.json');
        $this->vergleiche('hundeausbildung-hub', 'aufgaben/hundeausbildung.json', '/api/content/aufgaben/hundeausbildung.json');

        // -- seiten-sub-*.json Registries (alle 18, unabhaengig davon ob leer) --
        foreach (KjsPagesConfig::familySections() as $section) {
            foreach (KjsPagesConfig::fixedSlugs($section) as $slug) {
                $datei = "seiten-sub-{$slug}.json";
                if (File::exists($this->contentPath.'/'.$datei)) {
                    $this->vergleiche("seiten-sub-{$slug}", $datei, "/api/content/seiten-sub-{$slug}.json");
                }
            }
        }

        // -- Feste Vorlagen-Seiten (jaeger/aufgaben/verbraucher) ------------
        foreach (KjsPagesConfig::familySections() as $section) {
            $dir = KjsPagesConfig::contentDir($section);
            foreach (KjsPagesConfig::fixedSlugs($section) as $slug) {
                $datei = "{$dir}/{$slug}.json";
                if (File::exists($this->contentPath.'/'.$datei)) {
                    $this->vergleiche("{$section}/{$slug}", $datei, "/api/content/{$section}/{$slug}.json");
                }
            }
        }

        // -- Registry-Zusatzseiten (real vorhanden: nur aufgaben/hundevermittlung) --
        foreach (KjsPagesConfig::familySections() as $section) {
            $extraDir = KjsPagesConfig::extraRegistryDir($section);
            if ($extraDir === null || ! File::isDirectory($this->contentPath.'/'.$extraDir)) {
                continue;
            }
            foreach (File::files($this->contentPath.'/'.$extraDir) as $file) {
                $slug = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->vergleiche("{$extraDir}/{$slug}", "{$extraDir}/{$slug}.json", "/api/content/{$extraDir}/{$slug}.json");
            }
        }

        // -- seiten-weitere Einzelseiten ------------------------------------
        if (File::isDirectory($this->contentPath.'/seiten-weitere')) {
            foreach (File::files($this->contentPath.'/seiten-weitere') as $file) {
                $slug = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->vergleiche("seiten-weitere/{$slug}", "seiten-weitere/{$slug}.json", "/api/content/seiten-weitere/{$slug}.json");
            }
        }

        // -- seiten-sub-*/<slug>.json Unterseiten ---------------------------
        foreach (KjsPagesConfig::familySections() as $section) {
            foreach (KjsPagesConfig::fixedSlugs($section) as $parentSlug) {
                $subDir = "seiten-sub-{$parentSlug}";
                if (! File::isDirectory($this->contentPath.'/'.$subDir)) {
                    continue;
                }
                foreach (File::files($this->contentPath.'/'.$subDir) as $file) {
                    $childSlug = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $this->vergleiche(
                        "{$subDir}/{$childSlug}",
                        "{$subDir}/{$childSlug}.json",
                        "/api/content/{$subDir}/{$childSlug}.json"
                    );
                }
            }
        }

        // -- Hundeausbildungs-Kurse ------------------------------------------
        if (File::isDirectory($this->contentPath.'/aufgaben/hundeausbildung')) {
            foreach (File::files($this->contentPath.'/aufgaben/hundeausbildung') as $file) {
                $slug = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->vergleiche(
                    "hundeausbildung/{$slug}",
                    "aufgaben/hundeausbildung/{$slug}.json",
                    "/api/content/aufgaben/hundeausbildung/{$slug}.json"
                );
            }
        }

        $this->newLine();
        $this->table(['Modul', 'Datei', 'API-Pfad'], array_map(
            fn ($r) => [$r['modul'], $r['datei'], $r['api']],
            $this->ergebnisse
        ));
        $this->info(count($this->ergebnisse).' Module verglichen, '.$this->hinweise.' harmlose Zusatz-Hinweise, '.$this->abweichungen.' echte Abweichung(en).');

        if ($this->abweichungen > 0 && $this->option('fail-on-diff')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function vergleiche(string $modul, string $relDatei, string $apiPfad): void
    {
        $this->ergebnisse[] = ['modul' => $modul, 'datei' => $relDatei, 'api' => $apiPfad];

        $dateiPfad = $this->contentPath.'/'.$relDatei;
        $original = File::exists($dateiPfad) ? json_decode(File::get($dateiPfad), true) : null;

        $response = $this->dispatch($apiPfad);
        $status = $response->getStatusCode();
        $api = json_decode($response->getContent(), true);

        if ($original === null) {
            $this->line("  [{$modul}] Original-Datei fehlt/leer - übersprungen.");

            return;
        }

        if ($status !== 200) {
            $this->abweichungen++;
            $this->error("  [{$modul}] API antwortet mit HTTP {$status} statt 200 fuer {$apiPfad}.");

            return;
        }

        $diffs = [];
        $extras = [];
        $this->diffRekursiv($original, $api, $modul, $diffs, $extras);

        foreach ($diffs as $d) {
            $this->abweichungen++;
            $this->error("  [ABWEICHUNG] {$d}");
        }
        foreach ($extras as $e) {
            $this->hinweise++;
            $this->line("  [Hinweis, harmlos] {$e}");
        }
        if (empty($diffs) && empty($extras)) {
            $this->line("  [{$modul}] OK - keine Abweichung.");
        }
    }

    private function dispatch(string $uri): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = $this->laravel->make(Kernel::class);
        $request = Request::create($uri, 'GET');

        return $kernel->handle($request);
    }

    /**
     * Rekursiver, pragmatischer Struktur-Vergleich (keine Byte-Gleichheit).
     * - fehlender Schluessel in $api -> $diffs (echte Abweichung)
     * - zusaetzlicher Schluessel in $api -> $extras (harmloser Hinweis)
     * - Typ-Mismatch bei einem in beiden vorhandenen Skalarwert -> $diffs,
     *   AUSSER es handelt sich um eine bekannte, bewusste Normalisierung
     *   (siehe istBekannteNormalisierung()).
     */
    private function diffRekursiv(mixed $original, mixed $api, string $pfad, array &$diffs, array &$extras): void
    {
        if (is_array($original) && array_is_list($original)) {
            if (! is_array($api) || ! array_is_list($api)) {
                $diffs[] = "{$pfad}: Original ist eine Liste, API-Antwort nicht.";

                return;
            }
            if (count($original) !== count($api)) {
                $diffs[] = "{$pfad}: unterschiedliche Anzahl Elemente (Original ".count($original).', API '.count($api).').';
            }
            $n = min(count($original), count($api));
            for ($i = 0; $i < $n; $i++) {
                $this->diffRekursiv($original[$i], $api[$i], "{$pfad}[{$i}]", $diffs, $extras);
            }

            return;
        }

        if (is_array($original)) {
            if (! is_array($api)) {
                $diffs[] = "{$pfad}: Original ist ein Objekt, API-Antwort nicht.";

                return;
            }
            foreach ($original as $key => $value) {
                if (! array_key_exists($key, $api)) {
                    $diffs[] = "{$pfad}.{$key}: fehlt in der API-Antwort (Original hatte: ".json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR).').';

                    continue;
                }
                $this->diffRekursiv($value, $api[$key], "{$pfad}.{$key}", $diffs, $extras);
            }
            foreach ($api as $key => $value) {
                if (! array_key_exists($key, $original)) {
                    $extras[] = "{$pfad}.{$key}: zusaetzliches Feld in der API-Antwort (Superset-Strategie, siehe PageContentController::pageToJson()).";
                }
            }

            return;
        }

        // Skalarer Wert.
        if (gettype($original) !== gettype($api) && ! $this->istBekannteNormalisierung($pfad, $original, $api)) {
            $diffs[] = "{$pfad}: Typ-Unterschied (Original ".gettype($original).' "'.json_encode($original, JSON_UNESCAPED_UNICODE).'" vs. API '.gettype($api).' "'.json_encode($api, JSON_UNESCAPED_UNICODE).'").';
        }
    }

    /**
     * Bekannte, im Abschlussbericht dokumentierte Formatunterschiede, die
     * NICHT als Abweichung gezaehlt werden sollen (Auftrag Phase 3 Punkt 14:
     * "bewusst dokumentierte Unterschiede duerfen explizit normalisiert
     * werden"):
     * - "datum"-Felder: Original TT.MM.JJJJ (String) vs. API-Antwort
     *   ISO-String Y-m-d - js/content.js akzeptiert beide Formate bereits
     *   heute (siehe ContentController::aktuelles()-Kommentar).
     */
    private function istBekannteNormalisierung(string $pfad, mixed $original, mixed $api): bool
    {
        if (str_ends_with($pfad, '.datum') && is_string($original) && is_string($api)) {
            return true;
        }

        return false;
    }
}
