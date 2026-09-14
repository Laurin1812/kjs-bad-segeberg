<?php

namespace App\Console\Commands;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\FaqFrage;
use App\Models\FaqKategorie;
use App\Models\FooterLink;
use App\Models\GalerieBild;
use App\Models\Hegering;
use App\Models\MedienArchivEintrag;
use App\Models\Page;
use App\Models\PageLink;
use App\Models\Partner;
use App\Models\PartnerVorteil;
use App\Models\Person;
use App\Models\Setting;
use App\Models\StartseiteHeroSlide;
use App\Models\Termin;
use App\Models\Testimonial;
use App\Models\WunschlisteEintrag;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * KJS Bad Segeberg - Laravel-Migration Phase 2 ("JSON-Importer +
 * vollstaendige Datenmigration").
 *
 * Liest die bestehenden content/*.json-Dateien (unveraendert, read-only)
 * und schreibt sie normalisiert in die lokale lokale Dev-Datenbank, die in
 * Phase 1 angelegt wurde. Betrifft AUSSCHLIESSLICH JSON -> lokale MySQL-
 * Datenbank - kein Frontend-/Admin-Wechsel, kein Antasten der
 * Hundeboerse-/Waffenboerse-/Kontakt-Tabellen, keine Produktivdatenbank.
 *
 * Siehe Analysebericht (Phase 0) und Auftrag Phase 2 fuer die vollstaendige
 * Herleitung aller Entscheidungen, die in diesem Kommando nur noch
 * umgesetzt werden.
 */
class ImportContent extends Command
{
    protected $signature = 'kjs:import-content
        {--force : Vorhandene Zieldaten vorher leeren (TRUNCATE) und neu importieren}
        {--report= : Pfad einer Datei, in die der Abgleichsbericht zusaetzlich geschrieben wird}
        {--content-path= : Pfad zum content/-Verzeichnis (Default: eine Ebene ueber dem Laravel-Projekt)}';

    protected $description = 'Importiert die bestehenden content/*.json-Dateien in die lokale MySQL-Datenbank (Phase 2, nur lokale Dev-DB).';

    /** @var array<string, array{source:int,target:int,skipped:int,errors:int,note:?string}> */
    private array $stats = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $sampleLines = [];

    private string $contentPath;

    /**
     * Tabellen, die dieses Kommando befuellt - AUSDRUECKLICH NICHT enthalten:
     * hundeboerse_*, waffenboerse_*, kontakt_anfragen (fachlich eigene
     * MySQL-Strukturen, siehe Auftrag Phase 2 Punkt 6), sowie users/cache/
     * jobs (Laravel-Grundinstallation).
     *
     * @var list<string>
     */
    private array $managedTables = [
        'galerie_bilder',
        'downloads',
        'download_kategorien',
        'page_links',
        'pages',
        'faq_fragen',
        'faq_kategorien',
        'partner_vorteile',
        'partner',
        'personen',
        'hegeringe',
        'termine',
        'beitraege',
        'beitrag_kategorien',
        'wunschliste_eintraege',
        'medien_archiv',
        'testimonials',
        'startseite_hero_slides',
        'footer_links',
        'settings',
    ];

    public function handle(): int
    {
        $configuredPath = $this->option('content-path');
        $this->contentPath = $configuredPath
            ? rtrim($configuredPath, '/')
            : rtrim(base_path('../content'), '/');

        if (! File::isDirectory($this->contentPath)) {
            $this->error("content/-Verzeichnis nicht gefunden unter: {$this->contentPath}");
            $this->error('Bitte --content-path=... angeben, falls die Ordnerstruktur abweicht.');

            return self::FAILURE;
        }

        $this->info("KJS Content-Import - Quelle: {$this->contentPath}");

        if (! $this->ensureCleanTarget()) {
            return self::FAILURE;
        }

        try {
            DB::transaction(function () {
                $this->importSettingsGroups();
                $this->importMedienArchiv();
                $this->importWunschliste();
                $this->importBeitraege();
                $this->importServicePlatzhalter();
                $this->importTermine();
                $this->importPersonen();
                $this->importHegeringe();
                $this->importPartner();
                $this->importFaq();
                $this->importDownloadBibliothek();
                $this->importKreisjaegermeister();
                $this->importSeitenLeeresRegistry();
                $this->importPagesFamily('jaeger', 'jaeger', [
                    'uebersicht', 'hochwild', 'infomobil', 'jaeger-werden',
                    'landesjagdverband', 'mitglied-werden', 'niederwild',
                    'satzung', 'schiessobleute', 'ueber-uns',
                ], 'seiten-kjs.json', null);
                $this->importPagesFamily('aufgaben', 'aufgaben', [
                    'jagdhorn', 'jugend', 'jungwildrettung', 'naturschutz',
                    'schiessen', 'schweisshunde',
                ], 'seiten-aufgaben.json', 'seiten-aufgaben');
                $this->importPagesFamily('verbraucher', 'verbraucher', [
                    'gruenes-klassenzimmer', 'lernort-natur', 'waidmannssprache',
                    'wildfleisch',
                ], 'seiten-verbraucher.json', null);
                $this->importWeitere();
                $this->importHundeausbildung();
            });
        } catch (Throwable $e) {
            $this->error('Import abgebrochen, Transaktion zurueckgerollt: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->printReport();
        $this->printSampleComparison();

        if ($reportPath = $this->option('report')) {
            $this->writeReportFile($reportPath);
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Vorbereitung / Idempotenz
    // ------------------------------------------------------------------

    private function ensureCleanTarget(): bool
    {
        $existing = [];
        foreach ($this->managedTables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $existing[$table] = $count;
            }
        }

        if (empty($existing)) {
            return true;
        }

        if (! $this->option('force')) {
            $this->error('Zieltabellen enthalten bereits Daten - Import abgebrochen (Standard: kein Ueberschreiben).');
            foreach ($existing as $table => $count) {
                $this->line("  - {$table}: {$count} Zeile(n)");
            }
            $this->error('Mit --force erneut aufrufen, um diese Tabellen vorher zu leeren (TRUNCATE) und neu zu importieren.');

            return false;
        }

        $this->warn('--force gesetzt: leere die Zieltabellen vor dem Import (TRUNCATE).');

        // TRUNCATE fuehrt in MySQL/MariaDB einen impliziten COMMIT durch und
        // kann nicht in einer Transaktion zurueckgerollt werden - deshalb
        // bewusst VOR dem DB::transaction()-Block in handle(). Self-
        // referenzierende FK (pages.parent_id) und die Reihenfolge der
        // uebrigen FKs erfordern kurzzeitig deaktivierte FK-Pruefung.
        Schema::disableForeignKeyConstraints();
        foreach ($this->managedTables as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        return true;
    }

    // ------------------------------------------------------------------
    // Hilfsfunktionen
    // ------------------------------------------------------------------

    private function loadJson(string $relativePath): mixed
    {
        $path = $this->contentPath.'/'.ltrim($relativePath, '/');
        if (! File::exists($path)) {
            $this->warnings[] = "Datei nicht gefunden, uebersprungen: content/{$relativePath}";

            return null;
        }

        $raw = File::get($path);
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warnings[] = "JSON-Fehler in content/{$relativePath}: ".json_last_error_msg();

            return null;
        }

        return $data;
    }

    private function addStat(string $module, int $source, int $target, int $skipped = 0, int $errors = 0, ?string $note = null): void
    {
        $this->stats[$module] = [
            'source' => $source,
            'target' => $target,
            'skipped' => $skipped,
            'errors' => $errors,
            'note' => $note,
        ];
    }

    /**
     * Robuste Boolean-Normalisierung (Auftrag Phase 2 Punkt 3A):
     * akzeptiert true/false, "true"/"false", "True"/"False", 1/0 - keine
     * stille Fehlinterpretation eines unerwarteten Werts.
     */
    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', '1'], true)) {
                return true;
            }
            if (in_array($v, ['false', '0', ''], true)) {
                return false;
            }
            $this->warnings[] = "Unerwarteter Boolean-Wert \"{$value}\" wurde defensiv als \"false\" interpretiert.";

            return false;
        }

        return (bool) $value;
    }

    /**
     * Deutsches Datumsformat "TT.MM.JJJJ" (auch ohne fuehrende Null, z.B.
     * "1.04.2025") -> ISO-Datum fuer die Datenbank. Liefert NULL und
     * dokumentiert eine Warnung, statt zu raten, wenn das Format abweicht.
     */
    private function parseDatum(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d.m.Y', trim($value))->format('Y-m-d');
        } catch (Throwable $e) {
            $this->warnings[] = "Datum \"{$value}\" konnte nicht als TT.MM.JJJJ geparst werden - als NULL importiert.";

            return null;
        }
    }

    /**
     * Erzeugt einen stabilen, eindeutigen Slug innerhalb eines Scopes
     * (z.B. je "typ" bei Beitraegen, je section+parent_id bei Seiten).
     */
    private function uniqueSlug(string $base, callable $existsCheck): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'eintrag';
        }
        $candidate = $slug;
        $i = 2;
        while ($existsCheck($candidate)) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Importiert ein "downloads": [...]-Array, das direkt an einer Seite
     * oder einem Beitrag haengt (Feldschema {titel, datei, vorschau} - NICHT
     * zu verwechseln mit der zentralen Bibliothek aus downloads.json, siehe
     * importDownloadBibliothek()).
     */
    private function importEmbeddedDownloads(Page|Beitrag $owner, mixed $items): int
    {
        if (! is_array($items)) {
            return 0;
        }
        $n = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $titel = trim((string) ($item['titel'] ?? ''));
            $pfad = trim((string) ($item['datei'] ?? ''));
            if ($pfad === '') {
                continue;
            }
            Download::create([
                'kategorie_id' => null,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'titel' => $titel !== '' ? $titel : $pfad,
                'pfad' => $pfad,
                'vorschau' => (string) ($item['vorschau'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Importiert ein "galerie": [...]-Array ({bild, titel}) fuer eine Seite
     * oder einen Beitrag in die gemeinsame galerie_bilder-Tabelle.
     */
    private function importEmbeddedGalerie(Page|Beitrag $owner, mixed $items): int
    {
        if (! is_array($items)) {
            return 0;
        }
        $n = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $pfad = trim((string) ($item['bild'] ?? ''));
            if ($pfad === '') {
                continue;
            }
            GalerieBild::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'pfad' => $pfad,
                'titel' => (string) ($item['titel'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Importiert eine "linkliste": [...] ({titel, url} in den echten Daten -
     * WEICHT von den Spaltennamen der page_links-Tabelle ab, siehe
     * Analysebericht/Auftrag Phase 2: titel -> label, url -> href).
     */
    private function importPageLinks(Page $page, mixed $items): int
    {
        if (! is_array($items)) {
            return 0;
        }
        $n = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['url'] ?? $item['href'] ?? ''));
            if ($href === '') {
                continue;
            }
            PageLink::create([
                'page_id' => $page->id,
                'label' => (string) ($item['titel'] ?? $item['label'] ?? $href),
                'href' => $href,
                'sortierung' => $i,
            ]);
            $n++;
        }

        return $n;
    }

    // ------------------------------------------------------------------
    // Settings-Familie (design/einstellungen/footer/impressum/navigation/
    // navigation-extra/startseite) - siehe Phase-1-Settings-Konzept.
    // ------------------------------------------------------------------

    private function importSettingsGroups(): void
    {
        $this->importScalarSettings('design.json', 'design', []);
        $this->importEinstellungen();
        $this->importFooter();
        $this->importScalarSettings('impressum.json', 'impressum', []);
        $this->importNavigationBlob('navigation.json', 'navigation');
        $this->importNavigationBlob('navigation-extra.json', 'navigation_extra');
        $this->importStartseite();
    }

    /**
     * Generischer Import einer flachen Singleton-Konfigurationsdatei in die
     * settings-Tabelle (gruppe = Dateiname ohne .json, key = JSON-Schluessel).
     * $excludeKeys sind Schluessel, die anderswo (eigene Tabellen) landen.
     */
    private function importScalarSettings(string $file, string $gruppe, array $excludeKeys): void
    {
        $data = $this->loadJson($file);
        if (! is_array($data)) {
            $this->addStat($gruppe, 0, 0, 0, 0, 'Datei fehlt oder leer.');

            return;
        }

        $source = 0;
        $target = 0;
        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }
            $source++;
            if (is_array($value)) {
                // Sollte durch $excludeKeys bereits aussortiert sein - zur
                // Sicherheit dennoch keine stille Datenverlust-Gefahr:
                // als JSON dokumentieren statt zu verwerfen.
                $this->warnings[] = "settings/{$gruppe}: unerwartetes Array-Feld \"{$key}\" wurde als JSON-Text gespeichert.";
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif ($value === null) {
                $value = null;
            } else {
                $value = (string) $value;
            }

            Setting::create([
                'gruppe' => $gruppe,
                'key' => (string) $key,
                'value' => $value,
            ]);
            $target++;
        }

        $this->addStat($gruppe, $source, $target);
    }

    /**
     * einstellungen.json: alle Felder sind flache Skalare bis auf
     * "oeffnungszeiten" (kleine Liste von {tage, zeiten}) - wird als
     * JSON-Text in einer eigenen settings-Zeile gespeichert (kein eigenes
     * Tabellenmodell fuer eine derart kleine, nicht weiter relationale
     * Struktur noetig).
     */
    private function importEinstellungen(): void
    {
        $data = $this->loadJson('einstellungen.json');
        if (! is_array($data)) {
            $this->addStat('einstellungen', 0, 0, 0, 0, 'Datei fehlt oder leer.');

            return;
        }

        $this->importScalarSettings('einstellungen.json', 'einstellungen', ['oeffnungszeiten']);

        if (isset($data['oeffnungszeiten']) && is_array($data['oeffnungszeiten'])) {
            Setting::create([
                'gruppe' => 'einstellungen',
                'key' => 'oeffnungszeiten',
                'value' => json_encode($data['oeffnungszeiten'], JSON_UNESCAPED_UNICODE),
            ]);
            // Zaehlt als ein zusaetzliches Ziel-Element gegenueber dem, was
            // importScalarSettings bereits gezaehlt hat.
            $this->stats['einstellungen']['source']++;
            $this->stats['einstellungen']['target']++;
        }
    }

    /**
     * footer.json: Fliesstexte (ueber_text/facebook_url/instagram_url/
     * copyright) -> settings (Gruppe "footer"); die drei echten Listen
     * (spalte_ueber_kjs/spalte_uebersicht/spalte_informationen) -> eigene
     * footer_links-Tabelle (spalte-Enum entspricht dem jeweiligen Feldnamen).
     */
    private function importFooter(): void
    {
        $listKeys = ['spalte_ueber_kjs' => 'ueber_kjs', 'spalte_uebersicht' => 'uebersicht', 'spalte_informationen' => 'informationen'];
        $this->importScalarSettings('footer.json', 'footer', array_keys($listKeys));

        $data = $this->loadJson('footer.json');
        if (! is_array($data)) {
            $this->addStat('footer_links', 0, 0);

            return;
        }

        $source = 0;
        $target = 0;
        foreach ($listKeys as $jsonKey => $spalte) {
            $items = $data[$jsonKey] ?? [];
            if (! is_array($items)) {
                continue;
            }
            foreach (array_values($items) as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $source++;
                $href = trim((string) ($item['href'] ?? ''));
                if ($href === '') {
                    continue;
                }
                FooterLink::create([
                    'spalte' => $spalte,
                    'label' => (string) ($item['label'] ?? $href),
                    'href' => $href,
                    'sortierung' => $i,
                ]);
                $target++;
            }
        }
        $this->addStat('footer_links', $source, $target);
    }

    /**
     * navigation.json/navigation-extra.json: tief verschachtelt und nicht
     * gleichfoermig genug fuer eigene Tabellen (siehe Analysebericht) - wird
     * unveraendert als JSON-Text in EINER settings-Zeile gespeichert. Eine
     * spaetere Phase kann daraus bei Bedarf noch relationale Tabellen
     * ableiten, sobald eine konkrete Read-/Write-API dafuer ansteht.
     */
    private function importNavigationBlob(string $file, string $gruppe): void
    {
        $data = $this->loadJson($file);
        if ($data === null) {
            $this->addStat($gruppe, 0, 0, 0, 0, 'Datei fehlt.');

            return;
        }

        Setting::create([
            'gruppe' => $gruppe,
            'key' => 'data',
            'value' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        $this->addStat($gruppe, 1, 1, 0, 0, 'Als ein JSON-Blob gespeichert (settings.'.$gruppe.'.data).');
    }

    /**
     * startseite.json: viele flache Fliesstext-/Statistik-/Quicklink-Felder
     * -> settings (Gruppe "startseite"); "hero_slides" -> eigene Tabelle;
     * "testimonials" + "testimonials_sichtbar" -> eigene Tabelle;
     * "downloads"/"galerie" sind laut Analyse in den echten Daten immer
     * leer - werden dennoch defensiv behandelt, falls sich das aendert.
     *
     * Phase-3-Korrektur: "galerie_titel" stand bisher (wie "galerie"
     * selbst) auf der Ausschlussliste, obwohl es ein ganz normales
     * Fliesstext-Feld ist ("Bildergalerie") - beim Aufbau der Phase-3-
     * Read-API aufgefallen. Da js/index.html "galerie"/"galerie_titel" auf
     * der Startseite nachweislich nirgends ausliest (beide Felder sind
     * schon im Frontend tot), aendert diese Korrektur am sichtbaren
     * Verhalten nichts - sie verhindert nur, dass die Read-API dieses an
     * sich vorhandene Feld faelschlich als "nicht vorhanden" ausgeben
     * wuerde. Keine Migration noetig, landet ganz normal in "settings".
     */
    private function importStartseite(): void
    {
        $exclude = ['hero_slides', 'testimonials', 'testimonials_sichtbar', 'downloads', 'galerie'];
        $this->importScalarSettings('startseite.json', 'startseite', $exclude);

        $data = $this->loadJson('startseite.json');
        if (! is_array($data)) {
            $this->addStat('startseite_hero_slides', 0, 0);
            $this->addStat('testimonials', 0, 0);

            return;
        }

        $slides = is_array($data['hero_slides'] ?? null) ? $data['hero_slides'] : [];
        $sSource = 0;
        $sTarget = 0;
        foreach (array_values($slides) as $i => $slide) {
            if (! is_array($slide)) {
                continue;
            }
            $sSource++;
            $bild = trim((string) ($slide['bild'] ?? ''));
            if ($bild === '') {
                continue;
            }
            StartseiteHeroSlide::create([
                'bild' => $bild,
                'dauer' => isset($slide['dauer']) ? (string) $slide['dauer'] : null,
                'sortierung' => $i,
            ]);
            $sTarget++;
        }
        $this->addStat('startseite_hero_slides', $sSource, $sTarget);

        $sichtbarStandard = $this->toBool($data['testimonials_sichtbar'] ?? null, true);
        $testimonials = is_array($data['testimonials'] ?? null) ? $data['testimonials'] : [];
        $tSource = 0;
        $tTarget = 0;
        foreach (array_values($testimonials) as $i => $t) {
            if (! is_array($t)) {
                continue;
            }
            $tSource++;
            $text = trim((string) ($t['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            Testimonial::create([
                'text' => $text,
                'name' => (string) ($t['name'] ?? ''),
                'rolle' => (string) ($t['rolle'] ?? '') ?: null,
                'icon' => (string) ($t['icon'] ?? '') ?: null,
                'sichtbar' => $sichtbarStandard,
                'sortierung' => $i,
            ]);
            $tTarget++;
        }
        $this->addStat('testimonials', $tSource, $tTarget, 0, 0, 'testimonials_sichtbar galt als gemeinsames Sichtbarkeits-Flag fuer alle Eintraege.');

        // downloads/galerie an der Startseite sind laut Analyse immer leer -
        // dennoch pruefen und warnen statt stillschweigend zu verwerfen.
        if (! empty($data['downloads']) || ! empty($data['galerie'])) {
            $this->warnings[] = 'startseite.json enthaelt entgegen der Analyse nicht-leere downloads/galerie-Felder - wurden NICHT importiert (kein vorgesehenes Ziel in Phase 2).';
        }
    }

    // ------------------------------------------------------------------
    // Medienarchiv / Wunschliste
    // ------------------------------------------------------------------

    private function importMedienArchiv(): void
    {
        $data = $this->loadJson('medien-archiv.json');
        $items = is_array($data['archiviert'] ?? null) ? $data['archiviert'] : [];
        $source = count($items);
        $target = 0;
        foreach ($items as $dateiname) {
            if (! is_string($dateiname) || trim($dateiname) === '') {
                continue;
            }
            MedienArchivEintrag::create([
                'dateiname' => $dateiname,
                'archiviert_am' => null,
            ]);
            $target++;
        }
        $this->addStat('medien_archiv', $source, $target);
    }

    private function importWunschliste(): void
    {
        $data = $this->loadJson('wunschliste.json');
        $items = is_array($data['aufgaben'] ?? null) ? $data['aufgaben'] : [];
        $source = count($items);
        $target = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            WunschlisteEintrag::create([
                'titel' => (string) ($item['titel'] ?? ''),
                'beschreibung' => (string) ($item['beschreibung'] ?? '') ?: null,
                'bild' => (string) ($item['bild'] ?? '') ?: null,
                'status' => (string) ($item['status'] ?? 'offen'),
            ]);
            $target++;
        }
        $this->addStat('wunschliste', $source, $target);
    }

    // ------------------------------------------------------------------
    // Aktuelles (Beitraege) + service.json (bewusst NICHT importiert)
    // ------------------------------------------------------------------

    private function importBeitraege(): void
    {
        $data = $this->loadJson('aktuelles.json');
        if (! is_array($data)) {
            $this->addStat('aktuelles', 0, 0, 0, 0, 'Datei fehlt oder leer.');

            return;
        }

        // Phase-3-Nachtrag: "hauptseite_anzahl" (aktiv genutzt von
        // js/content.js, KJSContent.Aktuelles.standardAuswahl() als "letzte
        // N"-Override fuer die Startseiten-/Uebersichts-Auswahl) und
        // "hauptseite_modus" (im echten Datenbestand vorhanden, aber
        // aktuell von keinem Frontend-Code ausgelesen - dennoch der
        // Vollstaendigkeit halber mitgespeichert) wurden bisher NICHT
        // importiert. Beim Aufbau der Phase-3-Read-API aufgefallen, da
        // aktuelles.json.einstellungen mehr Felder enthaelt als nur
        // "kategorien". Landet generisch in settings (Gruppe "aktuelles"),
        // keine Migration noetig.
        if (is_array($data['einstellungen'] ?? null)) {
            foreach (['hauptseite_anzahl', 'hauptseite_modus'] as $key) {
                if (array_key_exists($key, $data['einstellungen']) && ! is_array($data['einstellungen'][$key])) {
                    Setting::create([
                        'gruppe' => 'aktuelles',
                        'key' => $key,
                        'value' => (string) $data['einstellungen'][$key],
                    ]);
                }
            }
        }

        // einstellungen.kategorien -> beitrag_kategorien (typ=aktuelles).
        $kategorieNamen = is_array($data['einstellungen']['kategorien'] ?? null)
            ? $data['einstellungen']['kategorien']
            : [];
        $kategorieMap = [];
        foreach (array_values($kategorieNamen) as $i => $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $kat = BeitragKategorie::create([
                'typ' => 'aktuelles',
                'name' => $name,
                'sortierung' => $i,
            ]);
            $kategorieMap[$name] = $kat->id;
        }

        $items = is_array($data['beitraege'] ?? null) ? $data['beitraege'] : [];
        $source = count($items);
        $target = 0;
        $errors = 0;
        foreach (array_values($items) as $legacyIndex => $item) {
            if (! is_array($item)) {
                $errors++;
                continue;
            }
            try {
                $titel = (string) ($item['titel'] ?? '');
                $kategorieName = trim((string) ($item['kategorie'] ?? ''));
                if ($kategorieName !== '' && ! isset($kategorieMap[$kategorieName])) {
                    // Kategorie im Beitrag referenziert, aber nicht in
                    // einstellungen.kategorien gelistet - defensiv anlegen
                    // statt den Beitrag zu verwerfen, mit Warnung.
                    $this->warnings[] = "aktuelles: Kategorie \"{$kategorieName}\" war nicht in einstellungen.kategorien gelistet - automatisch nachgetragen.";
                    $kat = BeitragKategorie::create([
                        'typ' => 'aktuelles',
                        'name' => $kategorieName,
                        'sortierung' => count($kategorieMap),
                    ]);
                    $kategorieMap[$kategorieName] = $kat->id;
                }

                $slug = $this->uniqueSlug($titel !== '' ? $titel : ('beitrag-'.$legacyIndex), fn ($s) => Beitrag::where('typ', 'aktuelles')->where('slug', $s)->exists()
                );

                $beitrag = Beitrag::create([
                    'typ' => 'aktuelles',
                    'slug' => $slug,
                    'legacy_index' => $legacyIndex,
                    'titel' => $titel,
                    'datum' => $this->parseDatum($item['datum'] ?? null),
                    'jahr' => isset($item['jahr']) && $item['jahr'] !== '' ? (int) $item['jahr'] : null,
                    'kategorie_id' => $kategorieName !== '' ? ($kategorieMap[$kategorieName] ?? null) : null,
                    'bild' => (string) ($item['bild'] ?? '') ?: null,
                    // Rich Text unveraendert uebernehmen (Auftrag Phase 2
                    // Punkt 3F) - kein HTML-Escaping, keine Markdown-
                    // Konvertierung.
                    'text' => $item['text'] ?? null,
                    'link' => (string) ($item['link'] ?? '') ?: null,
                    'galerie_titel' => (string) ($item['galerie_titel'] ?? '') ?: null,
                    'archiviert' => $this->toBool($item['archiviert'] ?? null, false),
                    'sortierung' => $legacyIndex,
                ]);

                $this->importEmbeddedDownloads($beitrag, $item['downloads'] ?? null);
                $this->importEmbeddedGalerie($beitrag, $item['galerie'] ?? null);

                $target++;
            } catch (Throwable $e) {
                $errors++;
                $this->warnings[] = "aktuelles: Beitrag Index {$legacyIndex} (\"".($item['titel'] ?? '?')."\") konnte nicht importiert werden: ".$e->getMessage();
            }
        }

        $this->addStat('aktuelles', $source, $target, 0, $errors);
    }

    /**
     * service.json enthaelt laut Analysebericht ausschliesslich Platzhalter-
     * /Testdaten (kontakt_name/kontakt_email = "Test", beitraege: []) - wird
     * daher AUSDRUECKLICH NICHT als produktiver Inhalt importiert (Auftrag
     * Phase 2 Punkt 3B). Es werden keine Service-Inhalte erfunden.
     */
    private function importServicePlatzhalter(): void
    {
        $data = $this->loadJson('service.json');
        $count = is_array($data['beitraege'] ?? null) ? count($data['beitraege']) : 0;
        $this->addStat(
            'service',
            $count,
            0,
            $count,
            0,
            'NICHT importiert: service.json enthaelt laut Analyse nur Platzhalter-/Testdaten (kontakt_name/kontakt_email = "Test", beitraege leer). Kein produktiver Inhalt erfunden.'
        );
    }

    // ------------------------------------------------------------------
    // Termine / Personen (Vorstand+Obleute) / Hegeringe / Partner / FAQ
    // ------------------------------------------------------------------

    private function importTermine(): void
    {
        $data = $this->loadJson('termine.json');
        if (is_array($data['einstellungen'] ?? null)) {
            foreach ($data['einstellungen'] as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                Setting::create(['gruppe' => 'termine', 'key' => (string) $key, 'value' => (string) $value]);
            }
        }

        $items = is_array($data['termine'] ?? null) ? $data['termine'] : [];
        $source = count($items);
        $target = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            Termin::create([
                'datum' => $this->parseDatum($item['datum'] ?? null) ?? now()->toDateString(),
                'uhrzeit' => (string) ($item['uhrzeit'] ?? '') ?: null,
                'veranstaltung' => (string) ($item['veranstaltung'] ?? ''),
                'strasse' => (string) ($item['strasse'] ?? '') ?: null,
                // Bugfix (kjs:compare-content, Werte-Vergleich): trim() hier
                // entfernte bei mind. einem echten Termin ein tatsaechlich
                // vorhandenes Leerzeichen am Ende ("24568 "/"Kattendorf ")
                // und verletzte damit das 1:1-Kompatibilitaetsprinzip -
                // bewusst NICHT mehr trimmen, das Original ist Referenz.
                'plz' => (string) ($item['plz'] ?? '') ?: null,
                'ort' => (string) ($item['ort'] ?? '') ?: null,
                'revier' => (string) ($item['revier'] ?? '') ?: null,
                'kategorie' => (string) ($item['kategorie'] ?? '') ?: null,
                'archiviert' => $this->toBool($item['archiviert'] ?? null, false),
            ]);
            $target++;
        }
        $this->addStat('termine', $source, $target);
    }

    private function importPersonen(): void
    {
        $this->importPersonenGremium('vorstand.json', 'mitglieder', 'vorstand');
        $this->importPersonenGremium('obleute.json', 'obleute', 'obmann');
    }

    private function importPersonenGremium(string $file, string $jsonKey, string $gremium): void
    {
        $data = $this->loadJson($file);
        $items = is_array($data[$jsonKey] ?? null) ? $data[$jsonKey] : [];
        $source = count($items);
        $target = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            Person::create([
                'gremium' => $gremium,
                'rolle' => (string) ($item['rolle'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'email' => (string) ($item['email'] ?? '') ?: null,
                'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                'bild' => (string) ($item['bild'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
            $target++;
        }
        $this->addStat($gremium === 'vorstand' ? 'vorstand' : 'obleute', $source, $target);
    }

    private function importHegeringe(): void
    {
        $data = $this->loadJson('hegeringe.json');
        $items = is_array($data['hegeringe'] ?? null) ? $data['hegeringe'] : [];
        $source = count($items);
        $target = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            Hegering::create([
                'nummer' => (string) ($item['nummer'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'obmann' => (string) ($item['obmann'] ?? '') ?: null,
                'gemeinden' => (string) ($item['gemeinden'] ?? '') ?: null,
                'email' => (string) ($item['email'] ?? '') ?: null,
                'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                'geschlecht' => (string) ($item['geschlecht'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
            $target++;
        }
        $this->addStat('hegeringe', $source, $target);
    }

    /**
     * partner.json: "rahmenvertrag" und "vorteile" sind in den echten Daten
     * durchgaengig leere Strings ('') statt Boolean/Array, wie das Phase-1-
     * Schema es urspruenglich vorsah (siehe Analysebericht-Korrektur) -
     * werden defensiv behandelt: leerer String -> false bzw. keine
     * partner_vorteile-Zeilen, ohne Fehler.
     */
    private function importPartner(): void
    {
        $data = $this->loadJson('partner.json');
        $items = is_array($data['partner'] ?? null) ? $data['partner'] : [];
        $source = count($items);
        $target = 0;
        $vorteileTarget = 0;
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $partner = Partner::create([
                // Phase-3-Fix: "id" aus content/partner.json 1:1 uebernehmen
                // (siehe Migration 2026_09_16_000002) - wird fuer die
                // Partner-Detailseiten-Verlinkung im Frontend benoetigt.
                'external_id' => isset($item['id']) ? (string) $item['id'] : null,
                'name' => (string) ($item['name'] ?? ''),
                'logo' => (string) ($item['logo'] ?? '') ?: null,
                'kurzbeschreibung' => (string) ($item['kurzbeschreibung'] ?? '') ?: null,
                'beschreibung' => $item['beschreibung'] ?? null,
                'ansprechpartner' => (string) ($item['ansprechpartner'] ?? '') ?: null,
                'telefon' => (string) ($item['telefon'] ?? '') ?: null,
                'email' => (string) ($item['email'] ?? '') ?: null,
                'website' => (string) ($item['website'] ?? '') ?: null,
                // rahmenvertrag ist in den echten Daten immer '' - toBool()
                // faellt in diesem Fall robust auf false zurueck.
                'rahmenvertrag' => $this->toBool($item['rahmenvertrag'] ?? null, false),
                'weitere_infos' => (string) ($item['weitere_infos'] ?? '') ?: null,
                'aktiv' => $this->toBool($item['aktiv'] ?? null, true),
                'sortierung' => $i,
            ]);
            $target++;

            $vorteile = $item['vorteile'] ?? null;
            if (is_array($vorteile)) {
                foreach (array_values($vorteile) as $j => $text) {
                    if (! is_string($text) || trim($text) === '') {
                        continue;
                    }
                    PartnerVorteil::create([
                        'partner_id' => $partner->id,
                        'text' => $text,
                        'sortierung' => $j,
                    ]);
                    $vorteileTarget++;
                }
            }
            // vorteile === '' (der in den echten Daten durchgaengige Fall):
            // bewusst keine Zeile, kein Fehler - siehe Klassenkommentar.
        }
        $this->addStat('partner', $source, $target);
        $this->addStat('partner_vorteile', 0, $vorteileTarget, 0, 0, 'partner[].vorteile war in allen 14 echten Datensaetzen ein leerer String (nicht Array) - daher aktuell 0 Quelleintraege.');
    }

    private function importFaq(): void
    {
        $data = $this->loadJson('faq.json');
        $kategorien = is_array($data['kategorien'] ?? null) ? $data['kategorien'] : [];
        $kSource = count($kategorien);
        $kTarget = 0;
        $fSource = 0;
        $fTarget = 0;
        foreach (array_values($kategorien) as $i => $kat) {
            if (! is_array($kat)) {
                continue;
            }
            $kategorie = FaqKategorie::create([
                'titel' => (string) ($kat['titel'] ?? ''),
                'sortierung' => $i,
            ]);
            $kTarget++;

            $fragen = is_array($kat['fragen'] ?? null) ? $kat['fragen'] : [];
            foreach (array_values($fragen) as $j => $f) {
                if (! is_array($f)) {
                    continue;
                }
                $fSource++;
                $frage = trim((string) ($f['frage'] ?? ''));
                if ($frage === '') {
                    continue;
                }
                FaqFrage::create([
                    'faq_kategorie_id' => $kategorie->id,
                    'frage' => $frage,
                    'antwort' => $f['antwort'] ?? null,
                    'sortierung' => $j,
                ]);
                $fTarget++;
            }
        }
        $this->addStat('faq_kategorien', $kSource, $kTarget);
        $this->addStat('faq_fragen', $fSource, $fTarget);
    }

    /**
     * downloads.json: zentrale Download-Bibliothek mit Feldschema
     * {name, beschreibung, url, typ} - abweichend von den seiteneigenen
     * downloads-Arrays ({titel, datei, vorschau}), siehe Klassenkommentar
     * der downloads-Migration.
     */
    private function importDownloadBibliothek(): void
    {
        $data = $this->loadJson('downloads.json');
        if (is_string($data['titel'] ?? null) || is_string($data['intro'] ?? null)) {
            foreach (['titel', 'intro'] as $key) {
                if (isset($data[$key]) && ! is_array($data[$key])) {
                    Setting::create(['gruppe' => 'downloads', 'key' => $key, 'value' => (string) $data[$key]]);
                }
            }
        }

        $kategorien = is_array($data['kategorien'] ?? null) ? $data['kategorien'] : [];
        $kSource = count($kategorien);
        $kTarget = 0;
        $dSource = 0;
        $dTarget = 0;
        foreach (array_values($kategorien) as $i => $kat) {
            if (! is_array($kat)) {
                continue;
            }
            $kategorie = DownloadKategorie::create([
                'titel' => (string) ($kat['titel'] ?? ''),
                'sortierung' => $i,
            ]);
            $kTarget++;

            $downloads = is_array($kat['downloads'] ?? null) ? $kat['downloads'] : [];
            foreach (array_values($downloads) as $j => $d) {
                if (! is_array($d)) {
                    continue;
                }
                $dSource++;
                $url = trim((string) ($d['url'] ?? ''));
                $name = trim((string) ($d['name'] ?? ''));
                if ($url === '' && $name === '') {
                    continue;
                }
                Download::create([
                    'kategorie_id' => $kategorie->id,
                    'owner_type' => null,
                    'owner_id' => null,
                    'titel' => $name !== '' ? $name : $url,
                    'beschreibung' => (string) ($d['beschreibung'] ?? '') ?: null,
                    'typ' => (string) ($d['typ'] ?? '') ?: null,
                    'pfad' => $url,
                    'sortierung' => $j,
                ]);
                $dTarget++;
            }
        }
        $this->addStat('download_kategorien', $kSource, $kTarget);
        $this->addStat('downloads_bibliothek', $dSource, $dTarget, 0, 0, 'url war in mehreren echten Eintraegen leer (noch kein hochgeladenes Dokument) - wurde dennoch mit leerem pfad importiert, kein Datenverlust.');
    }

    // ------------------------------------------------------------------
    // Kreisjaegermeister (Singleton-Page)
    // ------------------------------------------------------------------

    private function importKreisjaegermeister(): void
    {
        $data = $this->loadJson('kreisjjaegermeister.json');
        if (! is_array($data)) {
            $this->addStat('kreisjaegermeister', 0, 0, 0, 0, 'Datei fehlt oder leer.');

            return;
        }

        $aufgaben = (string) ($data['aufgaben'] ?? '');
        $gruszwort = (string) ($data['grußwort'] ?? $data['gruszwort'] ?? '');
        // Phase 3 Korrektur (siehe Migration 2026_09_16_000001): das
        // Frontend (kreisjjaegermeister/index.html) rendert "aufgaben" und
        // "grußwort" in zwei getrennten, optisch unterschiedlichen
        // DOM-Bloecken - eine Verkettung in EIN Feld (wie in Phase 2 aus
        // reinem Datenerhaltungs-Interesse zunaechst gemacht) wuerde die
        // Read-API in Phase 3 daran hindern, diese Trennung wiederherzu-
        // stellen. Ab Phase 3 werden beide Felder daher UNVERKETTET
        // gespeichert: aufgaben -> inhalt, grußwort -> grusswort.
        $page = Page::create([
            'section' => 'kreisjaegermeister',
            'parent_id' => null,
            'slug' => 'kreisjaegermeister',
            'titel' => 'Kreisjägermeister',
            'kontakt_name' => (string) ($data['name'] ?? '') ?: null,
            'kontakt_email' => (string) ($data['email'] ?? '') ?: null,
            'kontakt_telefon' => (string) ($data['telefon'] ?? '') ?: null,
            'bild' => (string) ($data['bild'] ?? '') ?: null,
            'inhalt' => $aufgaben !== '' ? $aufgaben : null,
            'grusswort' => $gruszwort !== '' ? $gruszwort : null,
            // Bugfix (kjs:compare-content, Werte-Vergleich): fehlte hier
            // komplett, obwohl content/kreisjjaegermeister.json real
            // "galerie_titel": "Bildergalerie" fuehrt - dieser Import nutzt
            // sein eigenes Page::create() statt createPageFromFields() und
            // hatte den Phase-3-Fix fuer galerie_titel deshalb nicht
            // automatisch mitbekommen.
            'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
            'in_navigation' => true,
            'veroeffentlicht' => true,
            'sortierung' => 0,
        ]);

        $this->addStat('kreisjaegermeister', 1, 1, 0, 0, "Seite #{$page->id}, aufgaben -> inhalt und grußwort -> grusswort getrennt gespeichert (Phase-3-Korrektur).");
    }

    // ------------------------------------------------------------------
    // content/seiten.json - genuinely leere Registry (Auftrag Punkt 3C)
    // ------------------------------------------------------------------

    private function importSeitenLeeresRegistry(): void
    {
        $data = $this->loadJson('seiten.json');
        $items = is_array($data['seiten'] ?? null) ? $data['seiten'] : [];
        $this->addStat(
            'seiten_registry',
            count($items),
            0,
            0,
            0,
            'content/seiten.json ist im echten Datenbestand leer ({"seiten": []}), wird aber vom Frontend (js/main.js, __navReady) aktiv geladen. Es werden absichtlich KEINE Datensaetze erzeugt - der Import darf und wird durch diesen leeren Zustand nicht fehlschlagen.'
        );
        if (! empty($items)) {
            $this->warnings[] = 'content/seiten.json enthaelt entgegen der Analyse Eintraege - diese wurden NICHT importiert, da fuer diese Registry noch keine Zielstruktur (section) definiert wurde. Bitte vor Phase 3 klaeren.';
        }
    }

    // ------------------------------------------------------------------
    // Feste Seiten-Familien (jaeger/aufgaben/verbraucher) inkl. Unterseiten
    // und registry-basierter Zusatzseiten.
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $fixedSlugs  Dateinamen (ohne .json) der festen Seiten in content/{$dir}/
     */
    private function importPagesFamily(string $section, string $dir, array $fixedSlugs, string $extraRegistryFile, ?string $extraDir): void
    {
        $sourceTotal = 0;
        $targetTotal = 0;
        $errors = 0;

        foreach ($fixedSlugs as $slug) {
            $sourceTotal++;
            $data = $this->loadJson("{$dir}/{$slug}.json");
            if (! is_array($data)) {
                $errors++;

                continue;
            }
            try {
                $page = $this->createPageFromFields($section, null, $slug, $data);
                $targetTotal++;
                $sourceTotal += $this->importPageSubfamily($section, $page, $slug);
                $targetTotal += 0; // importPageSubfamily zaehlt selbst mit, siehe Rueckgabe unten.
            } catch (Throwable $e) {
                $errors++;
                $this->warnings[] = "pages/{$section}: feste Seite \"{$slug}\" konnte nicht importiert werden: ".$e->getMessage();
            }
        }

        // Unterseiten-Registrierungen laufen ueber importPageSubfamily() mit
        // eigener Zaehlung - hier wird nur deren Summe eingesammelt, siehe
        // pageSubfamilyStats.
        [$subSource, $subTarget] = $this->drainSubfamilyStats();
        $sourceTotal += $subSource;
        $targetTotal += $subTarget;

        // Registry-basierte Zusatzseiten (seiten-aufgaben.json /
        // seiten-kjs.json / seiten-verbraucher.json), aktuell teils leer.
        $registry = $this->loadJson($extraRegistryFile);
        $extraItems = is_array($registry['seiten'] ?? null) ? $registry['seiten'] : [];
        foreach (array_values($extraItems) as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $sourceTotal++;
            $entrySlug = (string) ($entry['slug'] ?? '');
            if ($entrySlug === '' || $extraDir === null) {
                $errors++;
                $this->warnings[] = "pages/{$section}: Registry-Eintrag ohne Slug oder ohne bekanntes Verzeichnis in {$extraRegistryFile} uebersprungen.";

                continue;
            }
            $fileData = $this->loadJson("{$extraDir}/{$entrySlug}.json") ?? [];
            $merged = array_merge($fileData, [
                'in_navigation' => $entry['in_navigation'] ?? $fileData['in_navigation'] ?? true,
                'veroeffentlicht' => $entry['veroeffentlicht'] ?? $fileData['veroeffentlicht'] ?? true,
                'nav_label' => $fileData['nav_label'] ?? $entry['nav_label'] ?? null,
            ]);
            try {
                $this->createPageFromFields($section, null, $entrySlug, $merged, $i);
                $targetTotal++;
            } catch (Throwable $e) {
                $errors++;
                $this->warnings[] = "pages/{$section}: Registry-Seite \"{$entrySlug}\" konnte nicht importiert werden: ".$e->getMessage();
            }
        }

        $this->addStat("pages_{$section}", $sourceTotal, $targetTotal, 0, $errors);
    }

    /** @var array{0:int,1:int} */
    private array $pageSubfamilyStats = [0, 0];

    private function drainSubfamilyStats(): array
    {
        $stats = $this->pageSubfamilyStats;
        $this->pageSubfamilyStats = [0, 0];

        return $stats;
    }

    /**
     * Unterseiten einer festen Seite ueber "seiten-sub-<slug>.json"
     * (+ Verzeichnis "seiten-sub-<slug>/") - z.B. content/seiten-sub-
     * wildfleisch.json + content/seiten-sub-wildfleisch/lagerung-
     * wildfleisch.json. In den meisten Familien-Mitgliedern ist diese
     * Registry leer ({"seiten": []}) - das ist der Normalfall, kein Fehler.
     */
    private function importPageSubfamily(string $section, Page $parent, string $parentSlug): int
    {
        $registry = $this->loadJson("seiten-sub-{$parentSlug}.json");
        $items = is_array($registry['seiten'] ?? null) ? $registry['seiten'] : [];
        $target = 0;
        foreach (array_values($items) as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $childSlug = (string) ($entry['slug'] ?? '');
            if ($childSlug === '') {
                $this->warnings[] = "pages/{$section}: Unterseiten-Registry seiten-sub-{$parentSlug}.json enthaelt einen Eintrag ohne Slug - uebersprungen.";

                continue;
            }
            $fileData = $this->loadJson("seiten-sub-{$parentSlug}/{$childSlug}.json") ?? [];
            $merged = array_merge($fileData, [
                'in_navigation' => $fileData['in_navigation'] ?? $entry['in_navigation'] ?? true,
                'veroeffentlicht' => $fileData['veroeffentlicht'] ?? $entry['veroeffentlicht'] ?? true,
                'nav_label' => $fileData['nav_label'] ?? $entry['nav_label'] ?? null,
            ]);
            try {
                $this->createPageFromFields($section, $parent->id, $childSlug, $merged, $i);
                $target++;
            } catch (Throwable $e) {
                $this->warnings[] = "pages/{$section}: Unterseite \"{$childSlug}\" von \"{$parentSlug}\" konnte nicht importiert werden: ".$e->getMessage();
            }
        }
        $this->pageSubfamilyStats[0] += count($items);
        $this->pageSubfamilyStats[1] += $target;

        return 0;
    }

    /**
     * content/seiten-weitere.json + content/seiten-weitere/*.json - eigene,
     * flache Sektion "weitere" (kein "fixed_dir", nur Registry+Verzeichnis).
     * Laeuft heute ueber einen abweichenden Rendering-/Navigationspfad
     * (js/main.js bindet seiten-weitere.json NICHT in dasselbe
     * __navReady-Promise.all ein wie die anderen drei Registries) - der
     * Import bildet die Daten dennoch korrekt in "pages" ab, ohne das
     * Frontend zu aendern (Auftrag Phase 2 Punkt 3D).
     *
     * Bekannter, dokumentierter Konflikt: die Registry markiert
     * "jagdhornblasen" als veroeffentlicht=false, die Einzeldatei selbst
     * aber veroeffentlicht=true. Da die Einzeldatei der eigentliche
     * Seiteninhalt ist, gewinnt IHR Wert - der Widerspruch wird als
     * Warnung dokumentiert, nicht stillschweigend aufgeloest.
     */
    private function importWeitere(): void
    {
        $registry = $this->loadJson('seiten-weitere.json');
        $items = is_array($registry['seiten'] ?? null) ? $registry['seiten'] : [];
        $source = count($items);
        $target = 0;
        $errors = 0;
        foreach (array_values($items) as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') {
                $errors++;

                continue;
            }
            $fileData = $this->loadJson("seiten-weitere/{$slug}.json") ?? [];

            $registryVeroeffentlicht = array_key_exists('veroeffentlicht', $entry) ? $this->toBool($entry['veroeffentlicht']) : null;
            $fileVeroeffentlicht = array_key_exists('veroeffentlicht', $fileData) ? $this->toBool($fileData['veroeffentlicht']) : null;
            if ($registryVeroeffentlicht !== null && $fileVeroeffentlicht !== null && $registryVeroeffentlicht !== $fileVeroeffentlicht) {
                $this->warnings[] = "pages/weitere: Widerspruch bei \"{$slug}\" - Registry seiten-weitere.json sagt veroeffentlicht=".($registryVeroeffentlicht ? 'true' : 'false').", die Seite selbst sagt ".($fileVeroeffentlicht ? 'true' : 'false').'. Es wurde der Wert der Seite selbst uebernommen (siehe Abschlussbericht).';
            }

            $merged = array_merge($fileData, [
                'in_navigation' => $fileData['in_navigation'] ?? $entry['in_navigation'] ?? true,
                'veroeffentlicht' => $fileVeroeffentlicht ?? $registryVeroeffentlicht ?? true,
                'nav_label' => $fileData['nav_label'] ?? $entry['nav_label'] ?? null,
            ]);

            try {
                $page = $this->createPageFromFields('weitere', null, $slug, $merged, $i);
                // Bugfix (kjs:compare-content, Werte-Vergleich): Registry
                // und Seite selbst koennen bei "veroeffentlicht"
                // widerspruechlich sein (siehe Warnung oben) -
                // "veroeffentlicht" speichert bewusst den Seiten-eigenen
                // Wert (fuer /api/content/seiten-weitere/{slug}.json),
                // "registry_veroeffentlicht" zusaetzlich den
                // Registry-eigenen Wert (fuer
                // /api/content/seiten-weitere.json) - siehe Migration
                // 2026_09_16_000004 und PageContentController::
                // registryEntry().
                $page->update(['registry_veroeffentlicht' => $registryVeroeffentlicht]);
                $target++;
            } catch (Throwable $e) {
                $errors++;
                $this->warnings[] = "pages/weitere: \"{$slug}\" konnte nicht importiert werden: ".$e->getMessage();
            }
        }
        $this->addStat('pages_weitere', $source, $target, 0, $errors);
    }

    /**
     * Hundeausbildung-Familie: Hub (content/aufgaben/hundeausbildung.json)
     * + 19 Kurse (Registry aufgaben/hundeausbildung-seiten.json +
     * Verzeichnis aufgaben/hundeausbildung/) - bewusst eigene Section
     * "hundeausbildung" statt Teil der allgemeinen "aufgaben"-Seiten, da
     * die Kurse zusaetzliche Felder (gruppe/vorschaubild/kurzbeschreibung)
     * fuehren, die sonst nirgends vorkommen (siehe Migration
     * 2026_09_15_000001_add_phase2_columns).
     */
    private function importHundeausbildung(): void
    {
        $hubData = $this->loadJson('aufgaben/hundeausbildung.json');
        if (! is_array($hubData)) {
            $this->addStat('pages_hundeausbildung', 0, 0, 0, 1, 'Hub-Datei aufgaben/hundeausbildung.json fehlt.');

            return;
        }

        $hub = $this->createPageFromFields('hundeausbildung', null, 'hundeausbildung', $hubData);

        $registry = $this->loadJson('aufgaben/hundeausbildung-seiten.json');
        $items = is_array($registry['seiten'] ?? null) ? $registry['seiten'] : [];
        $source = 1 + count($items);
        $target = 1;
        $errors = 0;
        foreach (array_values($items) as $i => $entry) {
            if (! is_array($entry)) {
                $errors++;

                continue;
            }
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') {
                $errors++;

                continue;
            }
            $fileData = $this->loadJson("aufgaben/hundeausbildung/{$slug}.json") ?? [];
            $merged = array_merge($fileData, [
                'nav_label' => $entry['nav_label'] ?? $fileData['nav_label'] ?? null,
                'veroeffentlicht' => array_key_exists('veroeffentlicht', $entry)
                    ? $this->toBool($entry['veroeffentlicht'], true)
                    : ($fileData['veroeffentlicht'] ?? true),
                'vorschaubild' => $entry['vorschaubild'] ?? $fileData['vorschaubild'] ?? null,
                'kurzbeschreibung' => $entry['kurzbeschreibung'] ?? $fileData['kurzbeschreibung'] ?? null,
                'gruppe' => $entry['gruppe'] ?? $fileData['gruppe'] ?? null,
                'in_navigation' => true,
            ]);
            try {
                $this->createPageFromFields('hundeausbildung', $hub->id, $slug, $merged, $i);
                $target++;
            } catch (Throwable $e) {
                $errors++;
                $this->warnings[] = "pages/hundeausbildung: Kurs \"{$slug}\" konnte nicht importiert werden: ".$e->getMessage();
            }
        }

        $this->addStat('pages_hundeausbildung', $source, $target, 0, $errors, '1 Hub + bis zu 19 Kurse (Registry-/Verzeichnis-Abgleich vorab bestaetigt: exakt 19/19 ohne Waisen).');
    }

    /**
     * Zentrale Factory fuer eine "pages"-Zeile aus einem beliebigen
     * Quell-Datensatz (feste Seite, Unterseite, Registry-Zusatzseite,
     * Hundeausbildungs-Kurs, ...) - inkl. eingebetteter downloads/galerie/
     * linkliste.
     */
    private function createPageFromFields(string $section, ?int $parentId, string $slug, array $data, int $sortierung = 0): Page
    {
        $page = Page::create([
            'section' => $section,
            'parent_id' => $parentId,
            'slug' => $slug,
            'titel' => (string) ($data['titel'] ?? '') ?: null,
            'untertitel' => is_string($data['untertitel'] ?? null) ? $data['untertitel'] : null,
            'nav_label' => (string) ($data['nav_label'] ?? '') ?: null,
            'intro' => $data['intro'] ?? null,
            'inhalt' => $data['inhalt'] ?? null,
            'hero_bild' => (string) ($data['hero_bild'] ?? '') ?: null,
            'bild' => (string) ($data['bild'] ?? '') ?: null,
            'bild_alt' => (string) ($data['bild_alt'] ?? '') ?: null,
            'vorschaubild' => (string) ($data['vorschaubild'] ?? '') ?: null,
            'kurzbeschreibung' => $data['kurzbeschreibung'] ?? null,
            'bild_groesse' => (string) ($data['bild_groesse'] ?? '') ?: null,
            // Phase 3 Nachtrag (siehe Migration 2026_09_16_000001): nur bei
            // genau 2 Hundeausbildungs-Kursseiten real vorhanden ("bild_flat":
            // true) - steuert Rahmen/Schatten des Bildes. Default false ist
            // fuer alle anderen Seiten identisch zum bisherigen (impliziten)
            // Frontend-Verhalten bei fehlendem Feld.
            'bild_flat' => $this->toBool($data['bild_flat'] ?? null, false),
            'kontakt_name' => (string) ($data['kontakt_name'] ?? '') ?: null,
            'kontakt_email' => (string) ($data['kontakt_email'] ?? '') ?: null,
            'kontakt_telefon' => (string) ($data['kontakt_telefon'] ?? '') ?: null,
            // Phase-3-Fix (siehe Migration 2026_09_16_000003): bislang nur
            // bei content/jaeger/mitglied-werden.json real vorhanden
            // (Button-Link zum externen Online-Mitgliedsantrag).
            'antrag_url' => (string) ($data['antrag_url'] ?? '') ?: null,
            'unterseiten_titel' => (string) ($data['unterseiten_titel'] ?? '') ?: null,
            // Phase-3-Fix (siehe Migration 2026_09_16_000003): war schon in
            // PageContentController::pageToJson() vorgesehen, hatte aber nie
            // eine Spalte - bislang stiller Datenverlust bei 31 Seiten.
            'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
            'gruppe' => (string) ($data['gruppe'] ?? '') ?: null,
            'linkliste_titel' => (string) ($data['linkliste_titel'] ?? '') ?: null,
            // Phase 3 Nachtrag (siehe Migration 2026_09_16_000001): nur bei
            // content/seiten-aufgaben/hundevermittlung.json real vorhanden
            // (Sidebar-CTA-Box zur Hundeboerse) - fuer alle anderen Seiten
            // ohne diese Schluessel bleibt das Feld einfach NULL (no-op).
            'hundeboerse_cta_titel' => (string) ($data['hundeboerse_cta_titel'] ?? '') ?: null,
            'hundeboerse_cta_text' => (string) ($data['hundeboerse_cta_text'] ?? '') ?: null,
            'hundeboerse_cta_button' => (string) ($data['hundeboerse_cta_button'] ?? '') ?: null,
            'in_navigation' => $this->toBool($data['in_navigation'] ?? null, true),
            'veroeffentlicht' => $this->toBool($data['veroeffentlicht'] ?? null, true),
            'sortierung' => $sortierung,
        ]);

        $this->importEmbeddedDownloads($page, $data['downloads'] ?? null);
        $this->importEmbeddedGalerie($page, $data['galerie'] ?? null);
        $this->importPageLinks($page, $data['linkliste'] ?? null);

        return $page;
    }

    // ------------------------------------------------------------------
    // Bericht (Auftrag Phase 2 Punkt 7/8)
    // ------------------------------------------------------------------

    private function printReport(): void
    {
        $this->newLine();
        $this->info('=== Import-Abgleichsbericht ===');
        $rows = [];
        foreach ($this->stats as $module => $s) {
            $rows[] = [$module, $s['source'], $s['target'], $s['skipped'], $s['errors'], $s['note'] ?? ''];
        }
        $this->table(['Modul', 'Quelle (JSON)', 'Ziel (DB)', 'Übersprungen', 'Fehler', 'Hinweis'], $rows);

        $pagesTotalSource = 0;
        $pagesTotalTarget = 0;
        foreach ($this->stats as $module => $s) {
            if (str_starts_with($module, 'pages_')) {
                $pagesTotalSource += $s['source'];
                $pagesTotalTarget += $s['target'];
            }
        }
        $this->line("Seiten gesamt (alle Sections): {$pagesTotalSource} JSON-Quelleintraege -> {$pagesTotalTarget} DB-Zeilen in \"pages\".");

        $this->line('Eltern-Kind-Stichproben:');
        $samples = [
            ['weitere', 'jagdhornblasen'],
            ['verbraucher', 'wildfleisch'],
            ['hundeausbildung', null],
        ];
        foreach ($samples as [$section, $slug]) {
            if ($slug === null) {
                $hub = Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first();
                $childCount = $hub ? Page::where('parent_id', $hub->id)->count() : 0;
                $this->line("  - hundeausbildung: Hub #{$hub?->id} hat {$childCount} Kind-Seite(n).");

                continue;
            }
            $parent = Page::where('section', $section)->whereNull('parent_id')->where('slug', $slug)->first();
            $childCount = $parent ? Page::where('parent_id', $parent->id)->count() : 0;
            $this->line("  - {$section}/{$slug}: ".($parent ? "Seite #{$parent->id} gefunden, {$childCount} Kind-Seite(n)." : 'NICHT gefunden.'));
        }

        if (! empty($this->warnings)) {
            $this->newLine();
            $this->warn('Warnungen/Abweichungen:');
            foreach (array_unique($this->warnings) as $w) {
                $this->warn('  - '.$w);
            }
        }
    }

    private function printSampleComparison(): void
    {
        $this->newLine();
        $this->info('=== Stichprobenvergleich (Auftrag Phase 2 Punkt 8) ===');

        $this->sampleLine('Aktuelles-Beitrag', Beitrag::where('typ', 'aktuelles')->where('titel', 'like', 'Kreisjägertages%')->first(), fn ($m) => [
            'titel' => $m->titel,
            'text_laenge' => mb_strlen((string) $m->text),
            'html_enthalten' => str_contains((string) $m->text, '**') ? 'Markdown-Reste!' : 'kein HTML/Markdown-Bruch erkennbar',
            'galerie_bilder' => $m->galerieBilder()->count(),
            'downloads' => $m->downloads()->count(),
            'sortierung/legacy_index' => $m->legacy_index,
            'status' => $m->archiviert ? 'archiviert' : 'aktiv',
        ]);

        $this->sampleLine('Termin', Termin::orderBy('id')->first(), fn ($m) => [
            'veranstaltung' => $m->veranstaltung,
            'datum' => $m->datum?->format('Y-m-d'),
            'ort' => $m->ort,
            'status' => $m->archiviert ? 'archiviert' : 'aktiv',
        ]);

        $this->sampleLine('Vorstandsmitglied', Person::where('gremium', 'vorstand')->orderBy('sortierung')->first(), fn ($m) => [
            'name' => $m->name, 'rolle' => $m->rolle, 'telefon' => $m->telefon, 'bild' => $m->bild,
        ]);

        $this->sampleLine('Obmann', Person::where('gremium', 'obmann')->orderBy('sortierung')->first(), fn ($m) => [
            'name' => $m->name, 'rolle' => $m->rolle, 'telefon' => $m->telefon,
        ]);

        $this->sampleLine('Hegering', Hegering::orderBy('id')->first(), fn ($m) => [
            'name' => $m->name, 'obmann' => $m->obmann, 'gemeinden' => $m->gemeinden,
        ]);

        $this->sampleLine('Partner', Partner::orderBy('sortierung')->first(), fn ($m) => [
            'name' => $m->name, 'website' => $m->website, 'aktiv' => $m->aktiv ? 'ja' : 'nein', 'vorteile' => $m->vorteile()->count(),
        ]);

        $this->sampleLine('FAQ', FaqFrage::orderBy('id')->first(), fn ($m) => [
            'frage' => $m->frage, 'antwort_laenge' => mb_strlen((string) $m->antwort),
        ]);

        $this->sampleLine('Jäger-Seite (hochwild)', Page::where('section', 'jaeger')->where('slug', 'hochwild')->first(), fn ($m) => [
            'titel' => $m->titel,
            'inhalt_enthaelt_table' => str_contains((string) $m->inhalt, '<table') ? 'ja (HTML-Tabelle erhalten)' : 'nein',
            'veroeffentlicht' => $m->veroeffentlicht ? 'ja' : 'nein',
        ]);

        $this->sampleLine('Hundeausbildung-Uebersicht (Hub)', Page::where('section', 'hundeausbildung')->whereNull('parent_id')->first(), fn ($m) => [
            'titel' => $m->titel, 'kontakt_name' => $m->kontakt_name,
        ]);

        $this->sampleLine('Hundeausbildung-Kurs', Page::where('section', 'hundeausbildung')->whereNotNull('parent_id')->orderBy('id')->first(), fn ($m) => [
            'titel' => $m->titel, 'gruppe' => $m->gruppe, 'vorschaubild' => $m->vorschaubild, 'veroeffentlicht' => $m->veroeffentlicht ? 'ja' : 'nein',
        ]);

        $this->sampleLine('Seite unter "weitere"', Page::where('section', 'weitere')->where('slug', 'jagdhornblasen')->first(), fn ($m) => [
            'titel' => $m->titel, 'veroeffentlicht' => $m->veroeffentlicht ? 'ja' : 'nein', 'in_navigation' => $m->in_navigation ? 'ja' : 'nein',
        ]);
    }

    private function sampleLine(string $label, mixed $model, callable $fields): void
    {
        if ($model === null) {
            $line = "{$label}: KEIN Datensatz gefunden.";
            $this->warn('  '.$line);
            $this->sampleLines[] = $line;

            return;
        }
        $data = $fields($model);
        $parts = [];
        foreach ($data as $k => $v) {
            $parts[] = "{$k}=".(is_null($v) ? 'NULL' : (is_string($v) && mb_strlen($v) > 60 ? mb_substr($v, 0, 60).'…' : $v));
        }
        $line = "{$label}: ".implode(', ', $parts);
        $this->line('  '.$line);
        $this->sampleLines[] = $line;
    }

    private function writeReportFile(string $path): void
    {
        $lines = [];
        $lines[] = '# KJS Content-Import - Abgleichsbericht';
        $lines[] = '';
        $lines[] = 'Erzeugt: '.now()->toDateTimeString();
        $lines[] = '';
        $lines[] = '| Modul | Quelle (JSON) | Ziel (DB) | Übersprungen | Fehler | Hinweis |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($this->stats as $module => $s) {
            $note = str_replace('|', '\\|', $s['note'] ?? '');
            $lines[] = "| {$module} | {$s['source']} | {$s['target']} | {$s['skipped']} | {$s['errors']} | {$note} |";
        }
        $lines[] = '';
        $lines[] = '## Stichprobenvergleich';
        $lines[] = '';
        foreach ($this->sampleLines as $sample) {
            $lines[] = '- '.$sample;
        }
        $lines[] = '';
        $lines[] = '## Warnungen/Abweichungen';
        $lines[] = '';
        foreach (array_unique($this->warnings) as $w) {
            $lines[] = '- '.$w;
        }

        File::put($path, implode("\n", $lines)."\n");
        $this->info("Bericht zusaetzlich geschrieben nach: {$path}");
    }
}
