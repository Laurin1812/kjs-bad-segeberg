<?php

namespace App\Support;

use App\Models\Download;

/**
 * KJS Bad Segeberg - Phase 7E (Admin-Modul "Downloads", zentrale
 * Download-Bibliothek).
 *
 * Gemeinsame Schicht fuer die reine Feldzuweisung eines einzelnen
 * Bibliotheks-Downloads (Tabelle "downloads", nur Zeilen mit gesetzter
 * "kategorie_id" UND owner_type/owner_id = NULL - siehe Analysebericht
 * Abschnitt 6/Punkt D), analog zu App\Support\TerminUpdater (Phase 7D) -
 * genutzt von BEIDEN Schreibwegen:
 *
 *   1. Api\Admin\AdminListController::downloads() (bestehende JSON-API,
 *      unveraendertes Verhalten).
 *   2. Http\Controllers\Admin\DownloadsController (neu, Phase 7E).
 *
 * VERWALTETE FELDER (Nutzer-Entscheidung Phase-7E-Analyse): titel,
 * beschreibung, typ, pfad, sortierung. AUSDRUECKLICH NICHT verwaltet:
 * vorschau (gehoert ausschliesslich dem eingebetteten "Dokumente &
 * Downloads"-Mechanismus, siehe PageUpdater/BeitragUpdater), dateigroesse
 * (wird von keinem der beiden Schreibwege befuellt), owner_type/owner_id
 * (gehoeren ausschliesslich den seiteneigenen Downloads). applyFields()
 * fasst diese vier Spalten deshalb bewusst nirgends an - ein bestehender
 * Wert (z.B. ein historisch gesetztes "vorschau" auf einer Bibliothekszeile)
 * bleibt beim Speichern unangetastet, ganz gleich ueber welchen der beiden
 * Wege gespeichert wird.
 *
 * NORMALISIERUNG (1:1 aus admin.js' collectDownloads()/AdminListController::
 * downloads() uebernommen): "titel" faellt auf "pfad" zurueck, wenn leer
 * (ein Download ohne eigene Beschriftung zeigt seinen Pfad/URL als Titel);
 * "pfad" ist im neuen Blade-Admin ein Pflichtfeld (siehe DownloadsController-
 * Validierung), bleibt hier aber als reiner String behandelt - keine neue
 * URL-/Pfad-Policy (weder Formatpruefung noch Einschraenkung auf lokale
 * Pfade); "beschreibung"/"typ" werden bei leerem String zu null normalisiert
 * (identisch zum bestehenden JSON-Weg).
 */
class DownloadUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(Download $download, array $data): void
    {
        $pfad = (string) ($data['pfad'] ?? '');
        $titel = trim((string) ($data['titel'] ?? ''));

        $download->fill([
            'titel' => $titel !== '' ? $titel : $pfad,
            'beschreibung' => (string) ($data['beschreibung'] ?? '') ?: null,
            'typ' => (string) ($data['typ'] ?? '') ?: null,
            'pfad' => $pfad,
            'sortierung' => (int) ($data['sortierung'] ?? 0),
        ]);
        $download->save();
    }
}
