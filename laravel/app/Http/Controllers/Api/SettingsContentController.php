<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FooterLink;
use App\Models\Setting;
use App\Models\StartseiteHeroSlide;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

/**
 * KJS Bad Segeberg - Phase 3 Read-API.
 *
 * Rekonstruiert die ehemaligen Singleton-Konfigurationsdateien (design.json,
 * einstellungen.json, footer.json, impressum.json, navigation.json,
 * navigation-extra.json, startseite.json) aus der "settings"-Tabelle (+
 * footer_links/startseite_hero_slides/testimonials) - siehe
 * ImportContent::importSettingsGroups() fuer die (hier exakt umgekehrte)
 * Importrichtung.
 *
 * Kompatibilitaetsprinzip (Auftrag Phase 3 Punkt 2/4): "settings.value" ist
 * eine nullable text-Spalte - im echten Datenbestand ist dort aber nie NULL
 * gespeichert (importScalarSettings() speichert bei $value===null bewusst
 * NULL, das kommt in den echten Quelldateien schlicht nicht vor); zur
 * Sicherheit wird dennoch defensiv auf '' normalisiert, damit ein
 * NULL-Wert niemals als JSON-"null" statt als leerer String beim Frontend
 * ankommt (die Original-Dateien kennen fuer unbefuellte Textfelder
 * durchgaengig '', nicht null).
 */
class SettingsContentController extends Controller
{
    /** @return array<string, string> */
    private function settingsGroup(string $gruppe): array
    {
        return Setting::where('gruppe', $gruppe)
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => (string) ($s->value ?? '')])
            ->all();
    }

    public function design(): JsonResponse
    {
        return response()->json($this->settingsGroup('design'));
    }

    public function einstellungen(): JsonResponse
    {
        $data = $this->settingsGroup('einstellungen');

        // "oeffnungszeiten" wurde beim Import unveraendert als JSON-Text in
        // einer eigenen settings-Zeile abgelegt (siehe
        // ImportContent::importEinstellungen()) - hier wieder zu einem
        // echten Array dekodiert, statt als String stehenzubleiben.
        $roh = $data['oeffnungszeiten'] ?? null;
        unset($data['oeffnungszeiten']);
        $data['oeffnungszeiten'] = is_string($roh) && $roh !== ''
            ? (json_decode($roh, true) ?? [])
            : [];

        return response()->json($data);
    }

    public function footer(): JsonResponse
    {
        $data = $this->settingsGroup('footer');

        $listen = [
            'spalte_ueber_kjs' => 'ueber_kjs',
            'spalte_uebersicht' => 'uebersicht',
            'spalte_informationen' => 'informationen',
        ];
        foreach ($listen as $jsonKey => $spalte) {
            $data[$jsonKey] = FooterLink::where('spalte', $spalte)
                ->orderBy('sortierung')
                ->get()
                ->map(fn (FooterLink $l) => ['label' => $l->label, 'href' => $l->href])
                ->values()
                ->all();
        }

        return response()->json($data);
    }

    public function impressum(): JsonResponse
    {
        return response()->json($this->settingsGroup('impressum'));
    }

    /**
     * navigation.json/navigation-extra.json: wurden beim Import unveraendert
     * als EIN JSON-Blob gespeichert (siehe
     * ImportContent::importNavigationBlob()) - hier 1:1 zurueckdekodiert,
     * keine Rekonstruktion aus Einzelfeldern noetig/moeglich.
     */
    public function navigation(): JsonResponse
    {
        return $this->navigationBlob('navigation');
    }

    public function navigationExtra(): JsonResponse
    {
        return $this->navigationBlob('navigation_extra');
    }

    private function navigationBlob(string $gruppe): JsonResponse
    {
        $roh = Setting::where('gruppe', $gruppe)->where('key', 'data')->value('value');
        $data = is_string($roh) && $roh !== '' ? json_decode($roh, true) : null;

        return response()->json(is_array($data) ? $data : []);
    }

    public function startseite(): JsonResponse
    {
        $data = $this->settingsGroup('startseite');

        $data['hero_slides'] = StartseiteHeroSlide::orderBy('sortierung')
            ->get()
            ->map(fn (StartseiteHeroSlide $s) => ['bild' => $s->bild, 'dauer' => $s->dauer])
            ->values()
            ->all();

        $testimonials = Testimonial::orderBy('sortierung')->get();
        $data['testimonials'] = $testimonials
            ->map(fn (Testimonial $t) => [
                'text' => $t->text,
                'name' => $t->name,
                'rolle' => $t->rolle ?? '',
                'icon' => $t->icon ?? '',
            ])
            ->values()
            ->all();
        // testimonials_sichtbar war beim Import ein gemeinsames Flag fuer
        // ALLE Eintraege (siehe ImportContent::importStartseite()) - daher
        // reicht der Wert des ersten Eintrags; ohne Testimonials greift der
        // Standard "true" der Quelldatei.
        $data['testimonials_sichtbar'] = $testimonials->first()?->sichtbar ?? true;

        // downloads/galerie sind im echten Datenbestand durchgaengig leer
        // (siehe ImportContent::importStartseite()) und wurden deshalb nie
        // in eine eigene Tabelle importiert - kompatibel als leere Arrays
        // ausgegeben statt das Feld ganz wegzulassen.
        $data['downloads'] = [];
        $data['galerie'] = [];

        return response()->json($data);
    }
}
