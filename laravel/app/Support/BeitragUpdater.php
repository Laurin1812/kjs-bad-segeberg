<?php

namespace App\Support;

use App\Models\Beitrag;
use App\Models\Download;
use App\Models\GalerieBild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Phase 7C (Admin-Modul "Aktuelles").
 *
 * Extrahiert die bislang ausschliesslich in Api\Admin\AdminListController
 * lebende, fachliche Feld-Anwendungs-/Relations-Logik fuer EINEN Beitrag
 * (Felder uebernehmen + eingebettete Downloads/Galerie ersetzen), damit sie
 * von ZWEI Aufrufern genutzt werden kann, ohne dass einer davon eine eigene
 * Kopie pflegt - identisches Prinzip wie App\Support\PageUpdater in Phase 7B
 * (siehe dortiger Klassenkommentar "diese Klasse IST die gemeinsame
 * Schicht"):
 *
 *   1. Api\Admin\AdminListController::aktuelles() (bestehende JSON-Schreib-
 *      API unter /api/admin/content/aktuelles.json, admin.js) - ruft ab
 *      jetzt fuer JEDEN einzelnen Beitrag aus dem vollstaendigen Payload
 *      applyFields() auf, statt die Feld-/Relations-Logik selbst zu
 *      enthalten. Verhalten 1:1 unveraendert (reine Verschiebung).
 *   2. Http\Controllers\Admin\AktuellesController (neu, Phase 7C) - der
 *      server-gerenderte Blade-Admin unter /admin/aktuelles/*, der IMMER nur
 *      einen einzelnen Beitrag speichert (nicht die gesamte Liste).
 *
 * WICHTIG (Unterschied zu PageUpdater): admin.js' Aktuelles-Schreibweg
 * sendet bei JEDEM Speichern die VOLLSTAENDIGE Beitragsliste erneut (siehe
 * AdminListController::aktuelles()-Klassenkommentar "Aktuelles ... haben
 * bereits eigene, extern bedeutsame Schluessel" / doSave(S.section.file,
 * S.data,...) in admin.js) - Zuordnung/Anlegen/Loeschen einzelner Beitraege
 * bleibt deshalb bewusst Aufgabe von AdminListController::aktuelles()
 * selbst (Upsert-per-legacy_index, Loeschen von Zeilen, die im Payload
 * fehlen, Kategorien-Liste komplett neu schreiben). BeitragUpdater
 * uebernimmt nur den Teil, der fuer EINEN Beitrag in BEIDEN Faellen
 * identisch ist: welche Felder auf die Beitrag-Zeile geschrieben werden und
 * wie deren eingebettete Downloads/Galerie ersetzt werden. "typ"/"slug"/
 * "legacy_index"/"sortierung" sind bewusst NICHT Teil von applyFields()
 * (identitaets-/sortierrelevante Felder, die AdminListController bzw.
 * AktuellesController::store() separat setzen - siehe dortige Kommentare),
 * genau wie PageUpdater::applyFields() "section"/"parent_id"/"slug" nicht
 * anfasst.
 */
class BeitragUpdater
{
    /**
     * Uebertraegt die vom Aktuelles-Formular (Blade) bzw. dem
     * entsprechenden Item aus dem JSON-Payload (admin.js) verwalteten
     * Felder auf einen Beitrag - unveraendert aus AdminListController::
     * aktuelles()' bisherigem $fields-Aufbau uebernommen. "datum" wird
     * bereits als ISO-String (Y-m-d) oder null erwartet - die jeweilige
     * Quellformat-Umwandlung (admin.js liefert TT.MM.JJJJ, das native
     * HTML-Date-Feld im Blade-Formular liefert bereits ISO) bleibt
     * Aufgabe des jeweiligen Aufrufers (siehe AdminListController::
     * parseDatum() bzw. AktuellesController - keine Vermischung zweier
     * Datumsformate an dieser gemeinsamen Stelle).
     *
     * downloads/galerie (Nachbesserung nach Erstauslieferung, Preservation-
     * Bug): "der Schluessel FEHLT im $data-Array" und "der Schluessel ist
     * vorhanden, aber eine leere Liste" sind bewusst zwei verschiedene
     * Zustaende, siehe array_key_exists() unten statt eines einfachen
     * "?? null":
     *   - Schluessel fehlt komplett -> die jeweilige Relation wird gar
     *     nicht angefasst (bestehende Downloads/Galerie bleiben exakt
     *     erhalten) - genau das im Preservation-Sinn von Phase 7B/7C
     *     erwartete Verhalten fuer einen Aufrufer, der diesen Teil des
     *     Beitrags gar nicht mit editiert/gesendet hat.
     *   - Schluessel vorhanden, Wert eine leere Liste (oder null) -> die
     *     Relation wird bewusst GELEERT (Frank hat im Formular alle Zeilen
     *     entfernt bzw. der JSON-Payload hat das Feld ausdruecklich
     *     mitgeschickt).
     * Api\Admin\AdminListController::aktuelles() setzt 'downloads'/
     * 'galerie' in seinem $fields-Array PER ITEM IMMER (literal
     * "'downloads' => $item['downloads'] ?? null" - der Schluessel selbst
     * ist also immer vorhanden, nur der Wert kann null sein) - fuer die
     * bestehende JSON-API aendert sich dadurch NICHTS: ein Item ohne
     * eigenes "downloads"-Feld im Payload loescht die Relation weiterhin
     * unveraendert wie bisher (Vollpayload-Prinzip, siehe dortiger
     * Klassenkommentar). Nur Http\Controllers\Admin\AktuellesController
     * (Blade, einzelner Beitrag, echtes Teil-Update moeglich) baut sein
     * $data bewusst so auf, dass der Schluessel fehlt, wenn das Formular
     * ihn nicht gesendet hat (siehe dortige validateData()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(Beitrag $beitrag, array $data): void
    {
        $beitrag->fill([
            'titel' => (string) ($data['titel'] ?? ''),
            'datum' => is_string($data['datum'] ?? null) && $data['datum'] !== '' ? $data['datum'] : null,
            'jahr' => isset($data['jahr']) && $data['jahr'] !== '' && $data['jahr'] !== null ? (int) $data['jahr'] : null,
            'kategorie_id' => isset($data['kategorie_id']) && $data['kategorie_id'] !== '' ? (int) $data['kategorie_id'] : null,
            'bild' => (string) ($data['bild'] ?? '') ?: null,
            'text' => $data['text'] ?? null,
            'link' => (string) ($data['link'] ?? '') ?: null,
            'galerie_titel' => (string) ($data['galerie_titel'] ?? '') ?: null,
            'archiviert' => PageUpdater::toBool($data['archiviert'] ?? null, false),
        ]);
        $beitrag->save();

        if (array_key_exists('downloads', $data)) {
            self::replaceEmbeddedDownloads($beitrag, $data['downloads']);
        }
        if (array_key_exists('galerie', $data)) {
            self::replaceEmbeddedGalerie($beitrag, $data['galerie']);
        }
    }

    /**
     * Naechster freier "legacy_index" fuer einen NEU angelegten Beitrag
     * eines Typs - identische Formel wie AdminListController::aktuelles()'
     * bisheriger $nextLegacyIndex-Aufbau. AdminListController selbst nutzt
     * diese Methode NICHT (dort werden beim Batch-Speichern der gesamten
     * Liste ggf. mehrere neue Beitraege in einer Schleife angelegt - ein
     * lokal mitgefuehrter Zaehler bleibt dort bewusst bestehen, um nicht bei
     * jedem neuen Item erneut MAX() abzufragen). AktuellesController::
     * store() (Blade, immer nur EIN neuer Beitrag pro Aufruf) nutzt sie
     * direkt.
     */
    public static function nextLegacyIndex(string $typ): int
    {
        return ((int) Beitrag::where('typ', $typ)->max('legacy_index')) + 1;
    }

    /**
     * Eindeutigen Slug fuer einen neuen Beitrag erzeugen - Zeichen fuer
     * Zeichen identischer Algorithmus wie der bisher NUR in
     * AdminListController::aktuelles() inline vorhandene (Str::slug() +
     * Kollisions-Schleife "-2", "-3", ...), hierher verschoben, damit
     * admin.js-erzeugte und Blade-erzeugte Beitraege garantiert demselben
     * Schema folgen (Auftrag Teil 2: "keine doppelte Fachlogik").
     */
    public static function generateUniqueSlug(string $typ, string $titel, int $fallbackSeed): string
    {
        $basis = $titel !== '' ? $titel : ('beitrag-'.$fallbackSeed);
        $baseSlug = Str::slug($basis) ?: 'beitrag';
        $candidate = $baseSlug;
        $n = 2;
        while (Beitrag::where('typ', $typ)->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    /**
     * Ersetzt die eingebetteten downloads[]/galerie[] eines einzelnen
     * Owners komplett - unveraendert aus AdminListController uebernommen
     * (dort bislang zwei private Methoden, die dort fuer DREI Owner-Arten
     * aufgerufen wurden: Beitrag [aktuelles()/service()] und Page
     * [kreisjaegermeister()]). Bewusst weiterhin generisch auf
     * Illuminate\Database\Eloquent\Model gehalten (nicht auf Beitrag
     * verengt), damit AdminListController alle drei bestehenden Aufrufer
     * unveraendert auf diese EINE gemeinsame Stelle umstellen kann, statt
     * dass hier fuer Aktuelles eine vierte Kopie entsteht (App\Support\
     * PageUpdater haelt fuer die Page-Seiten-Familie aus Phase 7B bereits
     * eine eigene, strukturell identische Kopie - siehe dortiger
     * Kommentar - das bleibt unangetastet, um Phase-7B-Code nicht erneut
     * anzufassen).
     *
     * WICHTIG (warum service() NICHT applyFields() oben nutzt): applyFields()
     * ruft IMMER beide Methoden auf (Downloads UND Galerie) - korrekt fuer
     * Aktuelles (admin.js' aktuellesEdit() zeigt/sendet immer beides), aber
     * AdminListController::service() ruft bisher bewusst NUR
     * replaceEmbeddedDownloads() auf (Service kennt keine Galerie, siehe
     * dortiger Methodenkommentar "kein bild/galerie bei Service") - wuerde
     * service() applyFields() nutzen, wuerde JEDES Service-Speichern
     * zusaetzlich (neu, bisher nie passiert) alle GalerieBild-Zeilen dieses
     * Beitrags loeschen. service() ruft deshalb weiterhin nur
     * replaceEmbeddedDownloads() direkt auf (siehe dort) - keine
     * Verhaltensaenderung fuer das ausdruecklich nicht in Phase 7C
     * enthaltene Service-Modul.
     */
    public static function replaceEmbeddedDownloads(Model $owner, mixed $items): void
    {
        Download::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->delete();
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
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'titel' => $titel !== '' ? $titel : $pfad,
                'pfad' => $pfad,
                'vorschau' => (string) ($item['vorschau'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }

    public static function replaceEmbeddedGalerie(Model $owner, mixed $items): void
    {
        GalerieBild::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->delete();
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
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'pfad' => $pfad,
                'titel' => (string) ($item['titel'] ?? '') ?: null,
                'sortierung' => $i,
            ]);
        }
    }
}
