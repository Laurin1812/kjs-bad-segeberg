<?php

namespace App\Support;

use App\Models\Download;
use App\Models\GalerieBild;
use App\Models\Page;
use App\Models\PageLink;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7B (Admin-Modul "Inhalte / Seiten").
 *
 * Extrahiert die bislang ausschliesslich in Api\Admin\AdminPageController
 * lebende Schreiblogik fuer eine BESTEHENDE Page (Felder uebernehmen +
 * eingebettete Downloads/Galerie/Linkliste ersetzen + Versionspruefung/
 * -erhoehung), damit sie von ZWEI Aufrufern genutzt werden kann, ohne dass
 * eine der beiden eine eigene Kopie pflegt:
 *
 *   1. Api\Admin\AdminPageController (bestehende JSON-Schreib-API unter
 *      /api/admin/content/*, admin.js) - ruft ab jetzt saveWithVersionCheck()
 *      auf, statt die Logik selbst zu enthalten. Verhalten 1:1 unveraendert
 *      (reine Verschiebung, keine Anpassung der Feldliste/Reihenfolge/
 *      Fehlerbehandlung).
 *   2. Http\Controllers\Admin\InhalteController (neu, Phase 7B) - der
 *      server-gerenderte Blade-Admin unter /admin/inhalte/*.
 *
 * Phase-7B-Auftrag Punkt 4 ("Bestehende Laravel-Schreiblogik... wiederver-
 * wenden. Bitte NICHT dieselbe Validierung nochmals separat neu bauen / zwei
 * unterschiedliche Update-Regeln fuer JSON-API und Blade-Admin erzeugen.")
 * - diese Klasse IST die gemeinsame Schicht (Option B aus dem Auftrag:
 * "vorhandene serverseitige Update-Logik so strukturieren, dass API und
 * Blade dieselbe fachliche Logik nutzen").
 */
class PageUpdater
{
    /**
     * Uebertraegt alle Felder aus dem Payload auf eine BESTEHENDE Page -
     * unveraendert aus AdminPageController::applyFields() uebernommen (siehe
     * dortigen, hierher mitgewanderten Kommentar zu "registry_veroeffentlicht").
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(Page $page, array $data): void
    {
        $page->fill([
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
            'bild_flat' => self::toBool($data['bild_flat'] ?? null, false),
            'kontakt_name' => (string) ($data['kontakt_name'] ?? '') ?: null,
            'kontakt_email' => (string) ($data['kontakt_email'] ?? '') ?: null,
            'kontakt_telefon' => (string) ($data['kontakt_telefon'] ?? '') ?: null,
            'antrag_url' => (string) ($data['antrag_url'] ?? '') ?: null,
            'unterseiten_titel' => (string) ($data['unterseiten_titel'] ?? '') ?: null,
            'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
            'gruppe' => (string) ($data['gruppe'] ?? '') ?: null,
            'linkliste_titel' => (string) ($data['linkliste_titel'] ?? '') ?: null,
            'hundeboerse_cta_titel' => (string) ($data['hundeboerse_cta_titel'] ?? '') ?: null,
            'hundeboerse_cta_text' => (string) ($data['hundeboerse_cta_text'] ?? '') ?: null,
            'hundeboerse_cta_button' => (string) ($data['hundeboerse_cta_button'] ?? '') ?: null,
            'in_navigation' => self::toBool($data['in_navigation'] ?? null, true),
            'veroeffentlicht' => self::toBool($data['veroeffentlicht'] ?? null, true),
        ]);
        $page->save();

        self::replaceEmbeddedDownloads($page, $data['downloads'] ?? null);
        self::replaceEmbeddedGalerie($page, $data['galerie'] ?? null);
        self::replacePageLinks($page, $data['linkliste'] ?? null);
    }

    /**
     * Gemeinsamer Speicher-Ablauf: Version pruefen, Felder anwenden, Version
     * erhoehen - unveraendert aus AdminPageController::saveResolved()
     * uebernommen (dort nur noch das Uebersetzen in eine JsonResponse bzw.
     * Blade-Redirect, siehe jeweiliger Aufrufer).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ContentVersionConflictException
     */
    public static function saveWithVersionCheck(Page $page, string $versionSection, array $data, ?int $expectedVersion): int
    {
        return DB::transaction(function () use ($page, $versionSection, $data, $expectedVersion) {
            ContentVersioning::assertNotStale($versionSection, $expectedVersion);
            self::applyFields($page, $data);

            return ContentVersioning::bump($versionSection);
        });
    }

    /**
     * "section:slug" bzw. "sub:parentSlug:childSlug" - identische Bildung wie
     * AdminPageController::versionSection(), aber ausgehend von einer bereits
     * geladenen Page (Route-Model-Binding im neuen Blade-Admin, siehe
     * InhalteController) statt von URL-Segmenten. Fuer denselben
     * Datenbank-Datensatz liefert dies IMMER denselben Schluessel wie der
     * bestehende JSON-Schreibweg - Voraussetzung dafuer, dass die
     * Konflikterkennung (ContentVersioning) beide Oberflaechen gemeinsam
     * abdeckt, statt dass eine Seite ueber zwei getrennte Versionszaehler
     * liefe.
     *
     * Phase 7H (Admin-Modul "Hundeausbildung"): section 'hundeausbildung'
     * MUSS vor der generischen parent_id-Pruefung abgefangen werden - der
     * bestehende JSON-Schreibweg (AdminPageController::hundeausbildungHub()/
     * hundeausbildungKurs()) verwendet fuer diese Familie bewusst EIGENE,
     * von der generischen "sub:parentSlug:childSlug"-Bildung abweichende
     * Schluessel (versionSection('hundeausbildung','hub') fuer den Hub statt
     * dessen eigenem Slug, versionSection('hundeausbildung',$slug) fuer einen
     * Kurs OHNE das sonst uebliche "sub:"-Praefix/den Eltern-Slug) - siehe
     * dortige Kommentare "Hundeausbildung-Kurse nur innerhalb ihres Hub-
     * Parents". Ohne diesen Sonderfall wuerde InhalteController (seit Phase
     * 7H ebenfalls fuer diese Section zustaendig) einen ANDEREN Schluessel
     * berechnen als der bestehende JSON-Weg fuer denselben Datensatz -
     * die Konflikterkennung wuerde dann NICHT greifen, wenn dieselbe
     * Hundeausbildungs-Seite einmal ueber admin.js und einmal ueber den
     * neuen Blade-Admin gespeichert wird. Kein neues Konzept, nur dieselbe,
     * bereits im alten JSON-Weg etablierte Schluesselbildung 1:1 uebernommen.
     */
    public static function versionSectionFor(Page $page): string
    {
        if ($page->section === 'hundeausbildung') {
            return $page->parent_id === null
                ? 'page:hundeausbildung:hub'
                : 'page:hundeausbildung:'.$page->slug;
        }
        if ($page->parent_id !== null) {
            $parent = $page->relationLoaded('parent') ? $page->parent : Page::find($page->parent_id);

            return 'page:sub:'.($parent?->slug ?? 'unbekannt').':'.$page->slug;
        }
        if ($page->section === 'weitere') {
            return 'page:weitere:'.$page->slug;
        }

        return 'page:'.$page->section.':'.$page->slug;
    }

    public static function toBool(mixed $value, bool $default = false): bool
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

            return false;
        }

        return (bool) $value;
    }

    /**
     * Oeffentlich (statt private), weil AdminPageController::loescheSeite()
     * dieselben drei Methoden auch beim LOESCHEN einer Seite braucht (mit
     * $items=[] = "alles entfernen", siehe dortiger Aufruf) - nicht nur
     * applyFields() oben.
     */
    public static function replaceEmbeddedDownloads(Page $page, mixed $items): void
    {
        Download::where('owner_type', $page->getMorphClass())->where('owner_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $pfad = trim((string) ($item['datei'] ?? ''));
            $titel = trim((string) ($item['titel'] ?? ''));
            if ($pfad === '' && $titel === '') {
                continue;
            }
            Download::create([
                'owner_type' => $page->getMorphClass(),
                'owner_id' => $page->id,
                'titel' => $titel !== '' ? $titel : $pfad,
                'pfad' => $pfad,
                'vorschau' => (string) ($item['vorschau'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    public static function replaceEmbeddedGalerie(Page $page, mixed $items): void
    {
        GalerieBild::where('owner_type', $page->getMorphClass())->where('owner_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $pfad = trim((string) ($item['bild'] ?? ''));
            if ($pfad === '') {
                continue;
            }
            GalerieBild::create([
                'owner_type' => $page->getMorphClass(),
                'owner_id' => $page->id,
                'pfad' => $pfad,
                'titel' => (string) ($item['titel'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    public static function replacePageLinks(Page $page, mixed $items): void
    {
        PageLink::where('page_id', $page->id)->delete();
        if (! is_array($items)) {
            return;
        }
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['url'] ?? ''));
            $label = trim((string) ($item['titel'] ?? ''));
            if ($href === '' && $label === '') {
                continue;
            }
            PageLink::create(['page_id' => $page->id, 'label' => $label, 'href' => $href, 'sortierung' => $i]);
        }
    }
}
