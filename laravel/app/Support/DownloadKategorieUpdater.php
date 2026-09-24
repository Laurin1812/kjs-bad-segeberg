<?php

namespace App\Support;

use App\Models\DownloadKategorie;

/**
 * KJS Bad Segeberg - Phase 7E (Admin-Modul "Downloads", zentrale
 * Download-Bibliothek).
 *
 * Gemeinsame Schicht fuer die reine Feldzuweisung einer Download-Kategorie
 * (Tabelle "download_kategorien"), analog zu App\Support\TerminUpdater
 * (Phase 7D) - genutzt von BEIDEN Schreibwegen:
 *
 *   1. Api\Admin\AdminListController::downloads() (bestehende JSON-API,
 *      unveraendertes Verhalten - siehe dortiger Kommentar).
 *   2. Http\Controllers\Admin\DownloadsController (neu, Phase 7E).
 *
 * WICHTIG (Nutzer-Entscheidung Phase-7E-Analyse, bewusst ANDERS als
 * App\Support\BeitragKategorieUpdater fuer Aktuelles/Service): eine
 * Download-Kategorie ist ein echter Container (downloads.kategorie_id ->
 * cascadeOnDelete()), kein geteiltes Tag mehrerer Datensaetze. Es gibt
 * deshalb bewusst KEIN Get-or-Create-Dedup nach Namen und KEINE
 * "nur loeschen, wenn ungenutzt"-Regel - admin.js' dlKatDelete() loescht
 * eine Kategorie samt aller enthaltenen Downloads vorbehaltlos (nur mit
 * clientseitigem confirm()), das wird hier 1:1 uebernommen. Die eigentliche
 * Lösch-Aktion gehoert deshalb NICHT in diese Klasse (reine Feldzuweisung,
 * kein Business-Regelwerk hineinziehen) - Aufrufer nutzen einfach
 * DownloadKategorie::delete().
 */
class DownloadKategorieUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(DownloadKategorie $kategorie, array $data): void
    {
        $kategorie->fill([
            'titel' => (string) ($data['titel'] ?? ''),
            'sortierung' => (int) ($data['sortierung'] ?? 0),
        ]);
        $kategorie->save();
    }
}
