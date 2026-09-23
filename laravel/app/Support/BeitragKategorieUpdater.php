<?php

namespace App\Support;

use App\Models\Beitrag;
use App\Models\BeitragKategorie;

/**
 * Phase 7C (Nachbesserung: Kategorie-Verwaltung im neuen Blade-Admin).
 *
 * Alt-Admin-Analyse (admin/admin.js, window.aktuellesKategorieAdd/-Delete):
 * Kategorien einer Rubrik ("aktuelles"/"service") sind eine dauerhafte
 * Namensliste (frueher content/aktuelles.json -> einstellungen.kategorien,
 * jetzt die Tabelle beitrag_kategorien). "+ Neu" fragt einen Namen ab und
 * haengt ihn - falls noch nicht vorhanden (String-Dedup) - dauerhaft an;
 * "Loeschen" ist NUR erlaubt, wenn kein einziger Beitrag diese Kategorie
 * gerade traegt (admin.js prueft das VOR dem Loeschen und zeigt sonst eine
 * Meldung mit der Liste der betroffenen Beitraege - siehe
 * aktuellesKategorieDelete()).
 *
 * Diese Klasse ist die gemeinsame Schicht fuer BEIDE Schreibwege, analog zu
 * App\Support\BeitragUpdater fuer die Beitragsfelder selbst:
 *
 *   1. Api\Admin\AdminListController::aktuelles() (bestehende JSON-API) -
 *      deren "$ensureKategorie"-Closure (Get-or-Create einer Kategorie
 *      anhand des Namens, fuer einen einzelnen Beitrag, der eine noch nicht
 *      in der gerade gespeicherten Liste enthaltene Kategorie traegt) nutzt
 *      seitdem ensure() unten, statt eine eigene Kopie der Get-or-Create-
 *      Logik zu pflegen. WICHTIG: der davor liegende "gesamte Kategorie-
 *      Liste loeschen und aus dem Payload neu anlegen"-Schritt in
 *      aktuelles() bleibt UNVERAENDERT (siehe dortiger Kommentar) - admin.js
 *      sendet ohnehin bei jedem Speichern die vollstaendige, bereits durch
 *      den Client selbst gegen die "nur wenn ungenutzt"-Regel gepruefte
 *      Namensliste, ein serverseitiger Verwendungs-Check waere dort
 *      wirkungslos (jede im Payload fehlende, aber noch verwendete
 *      Kategorie wuerde durch ensure() ohnehin sofort wieder angelegt, siehe
 *      dortiger Kommentar).
 *   2. Http\Controllers\Admin\AktuellesController (neu: kategorieAnlegen()/
 *      kategorieLoeschen()) - der server-gerenderte Blade-Admin, der
 *      Kategorien jetzt EINZELN (nicht als Gesamt-Payload) anlegen/loeschen
 *      kann, siehe dortiger Klassenkommentar "Kategorien".
 */
class BeitragKategorieUpdater
{
    /**
     * Get-or-Create anhand des (getrimmten) Namens - identisch zu admin.js'
     * String-Dedup ("kats.indexOf(neu) === -1"): ein bereits vorhandener
     * Name legt KEINE zweite Zeile an, sondern liefert die bestehende
     * zurueck (idempotent, kein Fehler - admin.js zeigt bei einem bereits
     * vorhandenen Namen ebenfalls einfach denselben Erfolgs-Toast, keine
     * Fehlermeldung). Leerer/nur-Leerzeichen-Name liefert null (Aufrufer
     * entscheidet, ob das ein Validierungsfehler ist).
     */
    public static function ensure(string $typ, string $name): ?BeitragKategorie
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = BeitragKategorie::where('typ', $typ)->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        return BeitragKategorie::create([
            'typ' => $typ,
            'name' => $name,
            'sortierung' => BeitragKategorie::where('typ', $typ)->count(),
        ]);
    }

    /**
     * Anzahl der Beitraege, die diese Kategorie gerade tragen - Grundlage
     * der "nur loeschen, wenn ungenutzt"-Regel sowie der deutschen
     * Fehlermeldung, falls das Loeschen deshalb verweigert wird.
     */
    public static function anzahlVerwendungen(BeitragKategorie $kategorie): int
    {
        return Beitrag::where('kategorie_id', $kategorie->id)->count();
    }

    /**
     * Loescht die Kategorie NUR, wenn sie aktuell von keinem Beitrag
     * verwendet wird (1:1 die admin.js-Regel aus aktuellesKategorieDelete(),
     * dort clientseitig geprueft - hier serverseitig, da der neue
     * Blade-Admin kein weiteres, abweichendes clientseitiges JS dafuer
     * bekommt, siehe Auftrag "kein NICHT JETZT-Feature erfinden").
     *
     * @return bool true, wenn geloescht; false, wenn durch Verwendung
     *              blockiert (Aufrufer zeigt dann anzahlVerwendungen() in
     *              einer verstaendlichen deutschen Meldung an).
     */
    public static function loeschenWennUngenutzt(BeitragKategorie $kategorie): bool
    {
        if (self::anzahlVerwendungen($kategorie) > 0) {
            return false;
        }

        $kategorie->delete();

        return true;
    }
}
