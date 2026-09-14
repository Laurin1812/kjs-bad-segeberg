<?php

use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\PageContentController;
use App\Http\Controllers\Api\SettingsContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KJS Bad Segeberg - Phase 3 Read-API
|--------------------------------------------------------------------------
|
| Kompatibilitaetsschicht MySQL -> Laravel -> JSON, die inhaltlich exakt
| (bzw. so exakt wie sinnvoll moeglich) dieselben Strukturen zurueckgibt wie
| die bisherigen statischen content/*.json-Dateien - siehe Auftrag Phase 3
| Punkt 1-4. Automatisch unter "/api" registriert (siehe bootstrap/app.php,
| withRouting(api: ...)) und bekommt automatisch die "api"-Middleware-Gruppe.
|
| Alle Routen liegen unter dem Praefix "content", damit die finale URL exakt
| die urspruengliche Verzeichnisstruktur unter "/content/..." widerspiegelt
| (z.B. /api/content/aktuelles.json) - Kompatibilitaetsprinzip Punkt 3.
|
| WICHTIG (siehe Analyse in der Sitzungszusammenfassung): Laravels
| Routen-Matching ist bei ueberlappenden literalen vs. parametrisierten
| Mustern auf gleicher Pfadtiefe registrierungsreihenfolge-abhaengig.
| Deshalb MUESSEN die literalen Routen
|   aufgaben/hundeausbildung-seiten.json
|   aufgaben/hundeausbildung.json
| VOR der generischen 2-Segment-Route {section}/{slug}.json stehen - sonst
| wuerden sie faelschlich als "feste Seite 'hundeausbildung(-seiten)' der
| Section 'aufgaben'" interpretiert. Alle anderen Routen unten ueberlappen
| NICHT (unterschiedliche Pfadtiefe oder disjunkte literale Praefixe), die
| Reihenfolge ist dort nur aus Lesbarkeitsgruenden thematisch gruppiert.
|
*/

Route::prefix('content')->group(function () {

    // -- Singleton-Konfigurationsdateien (Settings-Familie, Punkt 5) -----
    Route::get('design.json', [SettingsContentController::class, 'design']);
    Route::get('einstellungen.json', [SettingsContentController::class, 'einstellungen']);
    Route::get('footer.json', [SettingsContentController::class, 'footer']);
    Route::get('impressum.json', [SettingsContentController::class, 'impressum']);
    Route::get('navigation.json', [SettingsContentController::class, 'navigation']);
    Route::get('navigation-extra.json', [SettingsContentController::class, 'navigationExtra']);
    Route::get('startseite.json', [SettingsContentController::class, 'startseite']);

    // -- Flache Content-Listen -------------------------------------------
    Route::get('aktuelles.json', [ContentController::class, 'aktuelles']);
    Route::get('termine.json', [ContentController::class, 'termine']);
    Route::get('vorstand.json', [ContentController::class, 'vorstand']);
    Route::get('obleute.json', [ContentController::class, 'obleute']);
    Route::get('hegeringe.json', [ContentController::class, 'hegeringe']);
    Route::get('partner.json', [ContentController::class, 'partner']);
    Route::get('faq.json', [ContentController::class, 'faq']);
    Route::get('downloads.json', [ContentController::class, 'downloads']);
    Route::get('kreisjjaegermeister.json', [ContentController::class, 'kreisjaegermeister']);

    // -- Registries (Punkt 6) ---------------------------------------------
    Route::get('seiten.json', [PageContentController::class, 'registryLeer']);
    Route::get('seiten-kjs.json', [PageContentController::class, 'registrySeitenKjs']);
    Route::get('seiten-aufgaben.json', [PageContentController::class, 'registrySeitenAufgaben']);
    Route::get('seiten-verbraucher.json', [PageContentController::class, 'registrySeitenVerbraucher']);
    Route::get('seiten-weitere.json', [PageContentController::class, 'registryWeitere']);
    Route::get('seiten-sub-{parentSlug}.json', [PageContentController::class, 'registrySub']);

    // -- Hundeausbildung: literale Routen MUESSEN vor der generischen
    //    {section}/{slug}.json-Route weiter unten registriert werden -----
    Route::get('aufgaben/hundeausbildung-seiten.json', [PageContentController::class, 'registryHundeausbildungSeiten']);
    Route::get('aufgaben/hundeausbildung.json', [PageContentController::class, 'hundeausbildungHub']);
    Route::get('aufgaben/hundeausbildung/{slug}.json', [PageContentController::class, 'hundeausbildungKurs']);

    // -- Registry-Zusatzseiten (eigenes Verzeichnis je Section) -----------
    Route::get('seiten-kjs/{slug}.json', [PageContentController::class, 'registrierteSeiteKjs']);
    Route::get('seiten-aufgaben/{slug}.json', [PageContentController::class, 'registrierteSeiteAufgaben']);
    Route::get('seiten-verbraucher/{slug}.json', [PageContentController::class, 'registrierteSeiteVerbraucher']);
    Route::get('seiten-weitere/{slug}.json', [PageContentController::class, 'weitereSeite']);
    Route::get('seiten-sub-{parentSlug}/{childSlug}.json', [PageContentController::class, 'subSeite']);

    // -- Feste Vorlagen-Seiten (generischer Katalog, MUSS nach den
    //    literalen "aufgaben/hundeausbildung*"-Routen oben stehen) --------
    Route::get('{section}/{slug}.json', [PageContentController::class, 'festeSeite'])
        ->where('section', 'jaeger|aufgaben|verbraucher');
});
