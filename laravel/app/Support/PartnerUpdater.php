<?php

namespace App\Support;

use App\Models\Partner;
use App\Models\PartnerVorteil;

/**
 * KJS Bad Segeberg - Phase 7F (Admin-Modul "Partner").
 *
 * Zentralisiert die reine Feld-Zuweisung/Normalisierung eines einzelnen
 * Partner-Datensatzes (inkl. seiner partner_vorteile-Zeilen), die bisher
 * inline in Api\Admin\AdminListController::partner() lag (siehe dortiger,
 * jetzt vereinfachter Feld-Mapping-Block) - 1:1 unveraendertes Verhalten,
 * nur an einer Stelle statt potenziell zwei (Alt-Admin-JSON-API + neuer
 * Blade-Admin, siehe Http\Controllers\Admin\PartnerController) gepflegt.
 * Exakt dasselbe Prinzip wie TerminUpdater (Phase 7D) / DownloadUpdater
 * (Phase 7E).
 *
 * "beschreibung" bleibt bewusst OHNE "leer -> null"-Normalisierung (anders
 * als die uebrigen String-Felder hier) - identisch zum bisherigen Verhalten
 * in AdminListController::partner() ("$item['beschreibung'] ?? null", ohne
 * (string)-Cast/?:-Fallback). Eine Aenderung dieses Sonderfalls waere eine
 * ungefragte Verhaltensaenderung des bestehenden JSON-Schreibwegs gewesen.
 *
 * "rahmenvertrag": DB-Spalte ist bereits seit Phase 2 ein echtes Boolean
 * (siehe Migration 2026_09_14_000050 + ImportContent::importPartner()-
 * Kommentar: im realen Datenbestand bei allen 14 Partnern durchgaengig ''
 * statt Boolean) - toBool() faellt bei leerem/fehlendem Wert robust auf
 * false zurueck, exakt wie der bisherige JSON-Weg.
 *
 * "vorteile" (Nutzer-Entscheidung/bestehende Altentscheidung, siehe
 * AdminListController::partner()-Klassenkommentar "wird hier NICHT
 * angetastet"): weiterhin Freitext, eine Zeile = ein Vorteil, in
 * partner_vorteile gespeichert (statt eines eigenen strukturierten
 * Listen-Editors) - sowohl der bestehende JSON-Weg als auch der neue
 * Blade-Admin senden diesen Freitext unveraendert an replaceVorteile().
 * Akzeptiert zusaetzlich ein Array von Strings (identisch zum bisherigen
 * JSON-Weg, der beide Formen zuliess).
 *
 * PRESERVATION-NACHBESSERUNG (Phase-7F-Korrektur): applyFields() ruft
 * replaceVorteile() NUR auf, wenn der Aufrufer ueberhaupt einen "vorteile"-
 * Schluessel in $data mitgegeben hat (array_key_exists(), nicht nur
 * isset() - ein bewusst auf null/leer gesetzter Schluessel bleibt damit
 * weiterhin ein Loeschen, ein komplett FEHLENDER Schluessel fasst die
 * partner_vorteile-Zeilen dagegen gar nicht an). Der bestehende JSON-Weg
 * (AdminListController::partner()) gibt "vorteile" immer explizit mit
 * (auch als null) - sein Verhalten aendert sich dadurch nicht: ein
 * Partner-Item ohne "vorteile" im JSON-Payload loescht weiterhin alle
 * Vorteile, exakt wie vor dieser Korrektur. Nur Http\Controllers\Admin\
 * PartnerController::update() nutzt die neue Unterscheidung, um bei einem
 * Teil-Update (Formularfeld nicht mitgesendet) bestehende Vorteile zu
 * erhalten statt sie stillschweigend zu leeren.
 */
class PartnerUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyFields(Partner $partner, array $data): void
    {
        $partner->fill([
            'name' => (string) ($data['name'] ?? ''),
            'logo' => (string) ($data['logo'] ?? '') ?: null,
            'kurzbeschreibung' => (string) ($data['kurzbeschreibung'] ?? '') ?: null,
            'beschreibung' => $data['beschreibung'] ?? null,
            'ansprechpartner' => (string) ($data['ansprechpartner'] ?? '') ?: null,
            'telefon' => (string) ($data['telefon'] ?? '') ?: null,
            'email' => (string) ($data['email'] ?? '') ?: null,
            'website' => (string) ($data['website'] ?? '') ?: null,
            'rahmenvertrag' => PageUpdater::toBool($data['rahmenvertrag'] ?? null, false),
            'weitere_infos' => (string) ($data['weitere_infos'] ?? '') ?: null,
            'aktiv' => PageUpdater::toBool($data['aktiv'] ?? null, true),
            'sortierung' => (int) ($data['sortierung'] ?? 0),
        ]);
        $partner->save();

        if (array_key_exists('vorteile', $data)) {
            self::replaceVorteile($partner, $data['vorteile']);
        }
    }

    /**
     * 1:1 aus dem bisherigen AdminListController::partner() uebernommen:
     * bestehende Vorteile-Zeilen komplett ersetzen (unbedenklich, da sie
     * keine eigene, extern bekannte ID haben - dieselbe Begruendung wie bei
     * BeitragUpdater::replaceEmbeddedDownloads()/-Galerie()).
     */
    public static function replaceVorteile(Partner $partner, mixed $vorteileRoh): void
    {
        PartnerVorteil::where('partner_id', $partner->id)->delete();

        if (is_string($vorteileRoh) && trim($vorteileRoh) !== '') {
            $zeilen = preg_split('/\r\n|\r|\n/', $vorteileRoh) ?: [];
            $j = 0;
            foreach ($zeilen as $zeile) {
                $zeile = trim($zeile);
                if ($zeile === '') {
                    continue;
                }
                PartnerVorteil::create(['partner_id' => $partner->id, 'text' => $zeile, 'sortierung' => $j]);
                $j++;
            }
        } elseif (is_array($vorteileRoh)) {
            foreach (array_values($vorteileRoh) as $j => $text) {
                if (! is_string($text) || trim($text) === '') {
                    continue;
                }
                PartnerVorteil::create(['partner_id' => $partner->id, 'text' => $text, 'sortierung' => $j]);
            }
        }
    }
}
