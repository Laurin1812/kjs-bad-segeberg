<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Phase 5B.2 (Admin-Medienfunktionen auf Laravel-Media-API umstellen,
 * Auftrag Punkt 5 "Löschen – Sicherheitsverbesserung"): einfache, zentrale
 * Referenzprüfung VOR dem Löschen eines Bildes/PDFs. Sucht den öffentlichen
 * Pfad (z.B. "/images/1234-foto.jpg") per LIKE über genau die Spalten, in
 * denen admin.js/ImportContent bislang Bild-/PDF-Pfade als reine
 * Zeichenketten ablegen - sowohl dedizierte Pfad-Spalten (pages.bild,
 * personen.bild, ...) als auch Rich-Text-/Markdown-Felder, in die ein Bild
 * über den Editor eingebettet werden kann (pages.inhalt/grusswort,
 * beitraege.text, faq_fragen.antwort) - siehe Auftrag Punkt 9 "Rich-Text-
 * Bild"-Testfall.
 *
 * Sucht nach dem VOLLEN Pfad-String (nicht nur dem Dateinamen), weil genau
 * der so in den Content-Feldern gespeichert wird (siehe admin.js
 * pickImg()/addDownloadRow() - immer "/images/<name>" bzw.
 * "/downloads/<name>", nie der nackte Dateiname) - das vermeidet
 * Falschtreffer durch zufällig gleiche Dateinamen in anderen Kontexten.
 *
 * BEWUSST NICHT GEPRÜFT (dokumentierte Lücke, Auftrag: "konservativ
 * fail-safe, dokumentieren, nicht still trotzdem löschen" - siehe
 * Abschlussbericht "offene Risiken"):
 * - Hundebörse/Waffenbörse (hundeboerse_meta/-anzeigen/-bilder etc., siehe
 *   database/schema.sql) laufen über ein SEPARATES, eigenständiges PHP/
 *   MySQL-Backend (api/*.php), nicht über Laravel/Eloquent - diese Tabellen
 *   sind von hier aus nicht ohne eigene Datenbankverbindung/eigenes Schema-
 *   Wissen sicher abfragbar und werden in Phase 5B.2 explizit nicht
 *   umgebaut (Auftrag Punkt 10). Ein Hundebörse-Hero-Bild (das denselben
 *   Bild-Picker/"/images/..."-Pfad wiederverwendet, siehe Projektnotizen)
 *   wird von diesem Scanner NICHT erkannt.
 * - content/medien-archiv.json (Setting-Gruppe? Nein: eigene Tabelle
 *   "medien_archiv") zählt bewusst NICHT als Referenz - das ist nur die
 *   Sichtbarkeits-/Archiv-Liste der Medienbibliothek selbst, keine
 *   inhaltliche Verwendung.
 * - Historische Git-Gateway-Inhalte, die NICHT über Laravel/MySQL laufen
 *   (falls auf Netlify-Vorschauen noch content/*.json direkt im Repo
 *   liegt), werden hier naturgemäß nicht erfasst - diese Prüfung läuft nur
 *   serverseitig gegen die MySQL-Datenbank, die auf einem PHP-/Laravel-Host
 *   die alleinige Quelle ist.
 */
class MediaReferenceScanner
{
    /**
     * @return string[] menschlich lesbare Fundstellen - leer bedeutet
     *                   "keine Referenz in den geprüften Tabellen/Spalten
     *                   gefunden" (siehe Klassenkommentar für die Grenzen
     *                   dieser Aussage).
     */
    public static function referencingLocations(string $publicPath): array
    {
        $like = '%'.$publicPath.'%';
        $found = [];

        DB::table('pages')
            ->where('hero_bild', 'like', $like)
            ->orWhere('bild', 'like', $like)
            ->orWhere('inhalt', 'like', $like)
            ->orWhere('grusswort', 'like', $like)
            ->get(['section', 'slug', 'titel'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Seite: '.$row->section.'/'.$row->slug.($row->titel ? ' ("'.$row->titel.'")' : '');
            });

        DB::table('beitraege')
            ->where('bild', 'like', $like)
            ->orWhere('text', 'like', $like)
            ->get(['typ', 'titel'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Beitrag ('.$row->typ.'): "'.$row->titel.'"';
            });

        DB::table('galerie_bilder')
            ->where('pfad', 'like', $like)
            ->get(['owner_type', 'owner_id', 'titel'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Galerie-Bild ('.class_basename((string) $row->owner_type).' #'.$row->owner_id.')'
                    .($row->titel ? ': '.$row->titel : '');
            });

        DB::table('downloads')
            ->where('pfad', 'like', $like)
            ->get(['titel'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Download: "'.$row->titel.'"';
            });

        DB::table('partner')
            ->where('logo', 'like', $like)
            ->orWhere('beschreibung', 'like', $like)
            ->orWhere('weitere_infos', 'like', $like)
            ->get(['name'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Partner: "'.$row->name.'"';
            });

        DB::table('personen')
            ->where('bild', 'like', $like)
            ->get(['gremium', 'name'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Person ('.$row->gremium.'): "'.$row->name.'"';
            });

        DB::table('startseite_hero_slides')
            ->where('bild', 'like', $like)
            ->get(['id'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Startseite: Hero-Slide #'.$row->id;
            });

        DB::table('testimonials')
            ->where('icon', 'like', $like)
            ->get(['name'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Testimonial: "'.$row->name.'"';
            });

        DB::table('faq_fragen')
            ->where('antwort', 'like', $like)
            ->get(['frage'])
            ->each(function ($row) use (&$found) {
                $found[] = 'FAQ: "'.$row->frage.'"';
            });

        // Catch-all für die Settings-Familie (design/footer/einstellungen/
        // navigation/navigation-extra/startseite-Fließtexte) - all diese
        // Module speichern ihre Werte (teils als JSON-Blob, z.B. Footer-
        // Spalten/Navigation) in derselben "value"-Spalte, siehe
        // AdminSettingsController.
        DB::table('settings')
            ->where('value', 'like', $like)
            ->get(['gruppe', 'key'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Einstellung: '.$row->gruppe.'.'.$row->key;
            });

        DB::table('footer_links')
            ->where('href', 'like', $like)
            ->get(['label'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Footer-Link: "'.$row->label.'"';
            });

        DB::table('page_links')
            ->where('href', 'like', $like)
            ->get(['label'])
            ->each(function ($row) use (&$found) {
                $found[] = 'Seiten-Link: "'.$row->label.'"';
            });

        return $found;
    }
}
