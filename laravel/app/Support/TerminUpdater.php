<?php

namespace App\Support;

use App\Models\Termin;

/**
 * KJS Bad Segeberg - Phase 7D (Admin-Modul "Termine").
 *
 * Zentralisiert die reine Feld-Zuweisung/Normalisierung eines einzelnen
 * Termin-Datensatzes, die bisher inline in Api\Admin\AdminListController::
 * termine() lag (siehe dortiger, jetzt geloeschter Feld-Mapping-Block) -
 * 1:1 unveraendertes Verhalten, nur an einer Stelle statt potenziell zwei
 * (Alt-Admin-JSON-API + neuer Blade-Admin, siehe Http\Controllers\Admin\
 * TermineController) gepflegt. Exakt dasselbe Prinzip wie BeitragUpdater
 * fuer "Aktuelles" (Phase 7C) - hier aber deutlich kleiner, da Termine
 * weder eingebettete Relationen (downloads/galerie) noch ein eigenes
 * Datumsformat-Parsing teilen muessen:
 *
 * "datum" kommt bei BEIDEN Aufrufern bereits als fertiges ISO-Datum
 * (YYYY-MM-DD) oder null an - Alt-Admin: durch AdminListController::
 * parseDatum() aus dem alten "TT.MM.JJJJ"-Format bereits aufgeloest (dieser
 * Format-Parsing-Schritt bleibt bewusst dort, da er NUR fuer die alte
 * JSON-Struktur gilt); neuer Blade-Admin: direkt aus einem <input
 * type="date">, siehe TermineController::validateData() ("datum" ist dort
 * "required", die Spalte selbst ist NOT NULL). applyFields() tut hier nur
 * noch dieselbe defensive "kein nicht-leerer String -> null"-Absicherung
 * wie BeitragUpdater::applyFields(), unabhaengig vom Aufrufer.
 *
 * "veranstaltung" bleibt bewusst IMMER ein String (niemals null): die
 * DB-Spalte ist NOT NULL, erlaubt aber einen leeren String - genau dieses
 * bisherige, bewusst NICHT verschaerfte Verhalten (kein Pflichtfeld, siehe
 * Auftrag Phase 7D Punkt 3) wird hier unveraendert uebernommen.
 */
class TerminUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(Termin $termin, array $data): void
    {
        $termin->fill([
            'datum' => is_string($data['datum'] ?? null) && $data['datum'] !== '' ? $data['datum'] : null,
            'uhrzeit' => (string) ($data['uhrzeit'] ?? '') ?: null,
            'veranstaltung' => (string) ($data['veranstaltung'] ?? ''),
            'strasse' => (string) ($data['strasse'] ?? '') ?: null,
            'plz' => (string) ($data['plz'] ?? '') ?: null,
            'ort' => (string) ($data['ort'] ?? '') ?: null,
            'revier' => (string) ($data['revier'] ?? '') ?: null,
            'kategorie' => (string) ($data['kategorie'] ?? '') ?: null,
            'archiviert' => PageUpdater::toBool($data['archiviert'] ?? null, false),
        ]);
        $termin->save();
    }
}
