<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Models\Download;
use App\Models\DownloadKategorie;
use App\Models\FaqKategorie;
use App\Models\GalerieBild;
use App\Models\Hegering;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Termin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * KJS Bad Segeberg - Phase 3 Read-API.
 *
 * Rekonstruiert die "flachen Listen"-Content-Dateien (aktuelles.json,
 * termine.json, vorstand.json, obleute.json, hegeringe.json, partner.json,
 * faq.json, downloads.json, kreisjjaegermeister.json) aus den jeweiligen
 * Phase-1/2-Tabellen.
 */
class ContentController extends Controller
{
    /**
     * Gemeinsame Rekonstruktion eines "downloads": [...]-Arrays
     * ({titel, datei, vorschau}) fuer einen Seiten-/Beitrags-Owner - siehe
     * ImportContent::importEmbeddedDownloads() fuer die Gegenrichtung.
     *
     * @return list<array{titel: string, datei: string, vorschau: string}>
     */
    public static function embeddedDownloads(Model $owner): array
    {
        return Download::where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Download $d) => [
                'titel' => $d->titel,
                'datei' => $d->pfad,
                'vorschau' => $d->vorschau ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Gemeinsame Rekonstruktion eines "galerie": [...]-Arrays ({bild, titel}).
     * Siehe ImportContent::importEmbeddedGalerie() fuer die Gegenrichtung.
     *
     * @return list<array{bild: string, titel: string}>
     */
    public static function embeddedGalerie(Model $owner): array
    {
        return GalerieBild::where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->orderBy('sortierung')
            ->get()
            ->map(fn (GalerieBild $g) => [
                'bild' => $g->pfad,
                'titel' => $g->titel ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * content/aktuelles.json.
     *
     * KRITISCH (Auftrag Phase 3 Punkt 7): die Reihenfolge des "beitraege"-
     * Arrays MUSS dem urspruenglichen Array-Index entsprechen, da
     * aktuelles/beitrag.html?i=42 und js/content.js (KJSContent.Aktuelles)
     * genau diesen Index als eindeutigen Schluessel verwenden. Phase 2 hat
     * dafuer "legacy_index" gespeichert (siehe ImportContent::
     * importBeitraege()) - hier wird strikt danach sortiert, nicht nach
     * "sortierung"/id/datum.
     */
    public function aktuelles(): JsonResponse
    {
        $settings = Setting::where('gruppe', 'aktuelles')->pluck('value', 'key');

        $kategorien = BeitragKategorie::where('typ', 'aktuelles')
            ->orderBy('sortierung')
            ->pluck('name')
            ->values()
            ->all();

        $beitraege = Beitrag::where('typ', 'aktuelles')
            ->orderBy('legacy_index')
            ->get()
            ->map(fn (Beitrag $b) => [
                // Phase 4 (Admin-Schreibweg): zusaetzliches, im Original nie
                // vorhandenes Feld (harmlose "Superset"-Erweiterung, siehe
                // PageContentController::pageToJson()-Kommentar) - admin.js
                // reicht diesen Wert beim Speichern unveraendert durch
                // (siehe AdminListController::aktuelles()), damit ein
                // bearbeiteter/verschobener Beitrag serverseitig ZWEIFELSFREI
                // seiner bestehenden Zeile (und damit seinem unveraenderlichen
                // "legacy_index" fuer beitrag.html?i=<n>) zugeordnet werden
                // kann, statt (falsch) anhand der neuen Array-Position.
                'legacy_index' => $b->legacy_index,
                'titel' => $b->titel,
                // Datum bewusst als ISO-String (Y-m-d) statt zurueck ins
                // urspruengliche TT.MM.JJJJ konvertiert: js/content.js'
                // parseDatum()/sortKey() akzeptieren BEIDE Formate bereits
                // heute (Regex prueft explizit auch "^(\d{4})-(\d{1,2})-
                // (\d{1,2})") - siehe Auftrag Phase 3 Punkt 4/8, dokumentierte
                // bewusste Abweichung ohne sichtbare Auswirkung.
                'datum' => $b->datum?->toDateString() ?? '',
                'jahr' => $b->jahr !== null ? (string) $b->jahr : '',
                'kategorie' => $b->kategorie?->name ?? '',
                'bild' => $b->bild ?? '',
                // Rich Text unveraendert (Auftrag Phase 3 Punkt 8) - keine
                // Markdown-Konvertierung server-seitig, das erledigt bereits
                // heute clientseitig marked.js.
                'text' => $b->text ?? '',
                'link' => $b->link ?? '',
                'galerie_titel' => $b->galerie_titel ?? '',
                'archiviert' => (bool) $b->archiviert,
                'downloads' => self::embeddedDownloads($b),
                'galerie' => self::embeddedGalerie($b),
            ])
            ->values()
            ->all();

        return response()->json([
            'einstellungen' => [
                'hauptseite_anzahl' => isset($settings['hauptseite_anzahl']) ? (int) $settings['hauptseite_anzahl'] : 0,
                'hauptseite_modus' => (string) ($settings['hauptseite_modus'] ?? ''),
                'kategorien' => $kategorien,
            ],
            'beitraege' => $beitraege,
        ]);
    }

    /**
     * content/service.json (Phase 8C - letzter migrierter CMS-Rest).
     *
     * Analog zu aktuelles() oben (gemeinsames Beitrag-/BeitragKategorie-
     * Schema, "typ" unterscheidet beide Bereiche, siehe Migrations-
     * Kommentare) - mit drei bewussten Abweichungen, die den tatsaechlichen
     * Service-Datenformen entsprechen (siehe admin.js' renderService()/
     * serviceEdit()/NO_GALERIE_FORMS):
     * - kein "bild" pro Beitrag (Service hat nur das eine Hero-Bild auf
     *   Seitenebene, siehe unten) und keine "galerie" (NO_GALERIE_FORMS
     *   schliesst 'service' ausdruecklich aus - "rein dokumentenorientiert").
     * - "video" (YouTube-Link) ersetzt "link" im JSON - wiederverwendet die
     *   bereits vorhandene generische "link"-Spalte von "beitraege" statt
     *   einer eigenen Spalte nur fuer diesen einen Anwendungsfall (Auftrag
     *   Phase 8C Punkt 2: keine unnoetige Generalisierung).
     * - zusaetzliche Seiteneinstellungen (titel/hero_bild/kontakt_name/
     *   kontakt_email/veroeffentlicht) auf oberster Ebene statt nur unter
     *   "einstellungen" - Service ist (anders als Aktuelles) ein Hybrid aus
     *   Settings-Modul und Beitragsliste, siehe Analysebericht Phase 8C
     *   Punkt 1. Landen generisch in settings (Gruppe "service"), keine
     *   eigene Tabelle noetig.
     */
    public function service(): JsonResponse
    {
        $settings = Setting::where('gruppe', 'service')->pluck('value', 'key');

        $kategorien = BeitragKategorie::where('typ', 'service')
            ->orderBy('sortierung')
            ->pluck('name')
            ->values()
            ->all();

        $beitraege = Beitrag::where('typ', 'service')
            ->orderBy('legacy_index')
            ->get()
            ->map(fn (Beitrag $b) => [
                // Phase 8C (Admin-Schreibweg): siehe Kommentar bei
                // aktuelles()/legacy_index - identische Zuordnungslogik,
                // damit AdminListController::service() bestehende Zeilen
                // beim Speichern zweifelsfrei wiederfindet.
                'legacy_index' => $b->legacy_index,
                'titel' => $b->titel,
                'datum' => $b->datum?->toDateString() ?? '',
                'jahr' => $b->jahr !== null ? (string) $b->jahr : '',
                'kategorie' => $b->kategorie?->name ?? '',
                'text' => $b->text ?? '',
                'video' => $b->link ?? '',
                'archiviert' => (bool) $b->archiviert,
                'downloads' => self::embeddedDownloads($b),
            ])
            ->values()
            ->all();

        return response()->json([
            'titel' => (string) ($settings['titel'] ?? ''),
            'hero_bild' => (string) ($settings['hero_bild'] ?? ''),
            'kontakt_name' => (string) ($settings['kontakt_name'] ?? ''),
            'kontakt_email' => (string) ($settings['kontakt_email'] ?? ''),
            // Gespeichert als '1'/'0' (siehe ImportContent::importScalarSettings()
            // bool-Normalisierung) - fehlt der Schluessel ganz (z.B. vor dem
            // ersten Import), wird bewusst "veroeffentlicht" angenommen statt
            // die Seite grundlos zu verstecken.
            'veroeffentlicht' => ($settings['veroeffentlicht'] ?? '1') === '1',
            'einstellungen' => [
                'kategorien' => $kategorien,
            ],
            'beitraege' => $beitraege,
        ]);
    }

    /**
     * content/termine.json. Reihenfolge: "id asc" (= urspruengliche
     * Einfuege-/Array-Reihenfolge, da "termine" keine eigene
     * sortierung-Spalte besitzt - js/content.js sortiert Termine ohnehin
     * clientseitig selbst nach Datum, siehe KJSContent.Termine.sortiere()).
     */
    public function termine(): JsonResponse
    {
        $settings = Setting::where('gruppe', 'termine')->pluck('value', 'key');

        $termine = Termin::orderBy('id')
            ->get()
            ->map(fn (Termin $t) => [
                // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                // aktuelles()/legacy_index - "termine" hat keinen eigenen
                // externen Schluessel, daher hier die DB-eigene ID als
                // harmlose Zusatz-Erweiterung, damit AdminListController::
                // termine() bestehende Zeilen beim Speichern zweifelsfrei
                // wiederfindet statt sie blind zu loeschen/neu anzulegen.
                '_id' => $t->id,
                'datum' => $t->datum?->toDateString() ?? '',
                'uhrzeit' => $t->uhrzeit ?? '',
                'veranstaltung' => $t->veranstaltung,
                'strasse' => $t->strasse ?? '',
                'plz' => $t->plz ?? '',
                'ort' => $t->ort ?? '',
                'revier' => $t->revier ?? '',
                'kategorie' => $t->kategorie ?? '',
                'archiviert' => (bool) $t->archiviert,
            ])
            ->values()
            ->all();

        return response()->json([
            'einstellungen' => [
                'ueberschrift' => (string) ($settings['ueberschrift'] ?? ''),
                'einleitung' => (string) ($settings['einleitung'] ?? ''),
            ],
            'termine' => $termine,
        ]);
    }

    private function personenGremium(string $gremium): array
    {
        return Person::where('gremium', $gremium)
            ->orderBy('sortierung')
            ->get()
            ->map(fn (Person $p) => [
                // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                // termine()/'_id'.
                '_id' => $p->id,
                'rolle' => $p->rolle,
                'name' => $p->name,
                'email' => $p->email ?? '',
                'telefon' => $p->telefon ?? '',
                'bild' => $p->bild ?? '',
            ])
            ->values()
            ->all();
    }

    public function vorstand(): JsonResponse
    {
        return response()->json(['mitglieder' => $this->personenGremium('vorstand')]);
    }

    public function obleute(): JsonResponse
    {
        return response()->json(['obleute' => $this->personenGremium('obmann')]);
    }

    public function hegeringe(): JsonResponse
    {
        $items = Hegering::orderBy('sortierung')
            ->get()
            ->map(fn (Hegering $h) => [
                // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                // termine()/'_id'.
                '_id' => $h->id,
                'nummer' => $h->nummer,
                'name' => $h->name,
                'obmann' => $h->obmann ?? '',
                'gemeinden' => $h->gemeinden ?? '',
                'email' => $h->email ?? '',
                'telefon' => $h->telefon ?? '',
                'geschlecht' => $h->geschlecht ?? '',
            ])
            ->values()
            ->all();

        return response()->json(['hegeringe' => $items]);
    }

    /**
     * content/partner.json.
     *
     * "rahmenvertrag"/"vorteile" sind im REALEN Datenbestand bei allen 14
     * Partnern durchgaengig '' (leerer String) statt Boolean/Array - das
     * Phase-1-Schema hat dies bewusst korrigiert (echtes boolean bzw. echte
     * partner_vorteile-Tabelle, siehe ImportContent::importPartner()-
     * Klassenkommentar). Wuerde die Read-API dafuer stumpf "false"/"[]"
     * ausgeben, entstuende eine ECHTE optische Regression: partner/
     * detail.html rendert jedes Feld ungeprueft ueber zeile(label, wert)
     * mit der Bedingung "wert === ''" - ein Boolean false/leeres Array
     * erfuellt diese Bedingung NICHT (strikter Vergleich) und wuerde daher
     * sichtbar "Rahmenvertrag: false" bzw. eine leere "Vorteile /
     * Leistungen"-Zeile rendern, wo heute gar nichts angezeigt wird. Daher
     * hier bewusst zurueck auf '' normalisiert, solange kein echter Wert
     * vorliegt (siehe Abschlussbericht Punkt 5).
     */
    public function partner(): JsonResponse
    {
        $items = Partner::with('vorteile')
            ->orderBy('sortierung')
            ->get()
            ->map(function (Partner $p) {
                $vorteile = $p->vorteile->pluck('text')->values()->all();

                return [
                    // Phase-3-Fix: urspruengliches "id"-Feld (siehe Migration
                    // 2026_09_16_000002 + ImportContent::importPartner()) -
                    // wird von partner/index.html und partner/detail.html
                    // fuer die Detailseiten-Verlinkung benoetigt.
                    'id' => $p->external_id ?? '',
                    // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                    // termine()/'_id' - unabhaengig von "id"/external_id
                    // (das bei einem neu im Admin angelegten Partner anfangs
                    // fehlt), damit AdminListController::partner() jeden
                    // Partner-Datensatz zweifelsfrei wiederfindet.
                    '_id' => $p->id,
                    'name' => $p->name,
                    'logo' => $p->logo ?? '',
                    'kurzbeschreibung' => $p->kurzbeschreibung ?? '',
                    'beschreibung' => $p->beschreibung ?? '',
                    'ansprechpartner' => $p->ansprechpartner ?? '',
                    'telefon' => $p->telefon ?? '',
                    'email' => $p->email ?? '',
                    'website' => $p->website ?? '',
                    'rahmenvertrag' => $p->rahmenvertrag ? true : '',
                    'vorteile' => count($vorteile) > 0 ? $vorteile : '',
                    'weitere_infos' => $p->weitere_infos ?? '',
                    'aktiv' => (bool) $p->aktiv,
                ];
            })
            ->values()
            ->all();

        return response()->json(['partner' => $items]);
    }

    public function faq(): JsonResponse
    {
        $kategorien = FaqKategorie::with('fragen')
            ->orderBy('sortierung')
            ->get()
            ->map(fn (FaqKategorie $k) => [
                // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                // termine()/'_id'.
                '_id' => $k->id,
                'titel' => $k->titel,
                'fragen' => $k->fragen
                    ->sortBy('sortierung')
                    ->map(fn ($f) => ['_id' => $f->id, 'frage' => $f->frage, 'antwort' => $f->antwort ?? ''])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return response()->json(['kategorien' => $kategorien]);
    }

    /**
     * content/downloads.json - NUR die zentrale Download-Bibliothek
     * (kategorie_id gesetzt, owner_type NULL) - nicht die seiten-/
     * beitragseigenen Downloads (siehe Download-Migrations-Kommentar).
     */
    public function downloads(): JsonResponse
    {
        $settings = Setting::where('gruppe', 'downloads')->pluck('value', 'key');

        $kategorien = DownloadKategorie::with(['downloads' => function ($q) {
            $q->whereNull('owner_type')->orderBy('sortierung');
        }])
            ->orderBy('sortierung')
            ->get()
            ->map(fn (DownloadKategorie $k) => [
                // Phase 4 (Admin-Schreibweg): siehe Kommentar bei
                // termine()/'_id'.
                '_id' => $k->id,
                'titel' => $k->titel,
                'downloads' => $k->downloads
                    ->map(fn (Download $d) => [
                        '_id' => $d->id,
                        'name' => $d->titel,
                        'beschreibung' => $d->beschreibung ?? '',
                        'url' => $d->pfad,
                        'typ' => $d->typ ?? '',
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return response()->json([
            'titel' => (string) ($settings['titel'] ?? ''),
            'intro' => (string) ($settings['intro'] ?? ''),
            'kategorien' => $kategorien,
        ]);
    }

    /**
     * content/kreisjjaegermeister.json - Singleton-Seite, section=
     * kreisjaegermeister in "pages". "aufgaben"/"grußwort" wurden ab Phase 3
     * bewusst UNVERKETTET gespeichert (inhalt/grusswort - siehe Migration
     * 2026_09_16_000001 und ImportContent::importKreisjaegermeister()), da
     * kreisjjaegermeister/index.html beide in zwei getrennten DOM-Bloecken
     * rendert. "downloads"/"galerie"/"galerie_titel" sind im echten
     * Datenbestand leer UND werden von kreisjjaegermeister/index.html gar
     * nicht ausgelesen - kompatibel als leere Werte ausgegeben.
     */
    public function kreisjaegermeister(): JsonResponse
    {
        $page = Page::where('section', 'kreisjaegermeister')->first();

        if (! $page) {
            return response()->json([
                'name' => '', 'bild' => '', 'email' => '', 'telefon' => '',
                'aufgaben' => '', 'grußwort' => '', 'downloads' => [],
                'galerie' => [], 'galerie_titel' => '',
            ]);
        }

        return response()->json([
            'name' => $page->kontakt_name ?? '',
            'bild' => $page->bild ?? '',
            'email' => $page->kontakt_email ?? '',
            'telefon' => $page->kontakt_telefon ?? '',
            'aufgaben' => $page->inhalt ?? '',
            'grußwort' => $page->grusswort ?? '',
            'downloads' => self::embeddedDownloads($page),
            'galerie' => self::embeddedGalerie($page),
            // Bugfix (kjs:compare-content, Werte-Vergleich): war hier fest
            // auf "" verdrahtet statt $page->galerie_titel auszulesen -
            // hat den Wert unabhaengig vom Import immer verschluckt.
            'galerie_titel' => $page->galerie_titel ?? '',
        ]);
    }
}
