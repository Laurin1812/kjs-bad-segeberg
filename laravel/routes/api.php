<?php

use App\Http\Controllers\Api\Admin\AdminListController;
use App\Http\Controllers\Api\Admin\AdminMediaController;
use App\Http\Controllers\Api\Admin\AdminPageController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminVersionController;
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
    // Phase 8C: letzter migrierter CMS-Rest (vorher NICHT_MIGRIERTE_DATEIEN_
    // PHP_HOST in admin.js, siehe dortiger Kommentar-Verweis).
    Route::get('service.json', [ContentController::class, 'service']);
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

/*
|--------------------------------------------------------------------------
| KJS Bad Segeberg - Phase 4 Admin-Schreib-API
|--------------------------------------------------------------------------
|
| Schreib-Gegenstueck zur Read-API oben (Admin-Schreibweg Git/JSON ->
| Laravel/MySQL, siehe Auftrag Phase 4). Jede Route ist per
| "identity.permission:<key>" serverseitig gegen das jeweilige Netlify-
| Identity-Zugriffstoken abgesichert (siehe App\Http\Middleware\
| EnsureIdentityPermission) - <key> entspricht 1:1 den Werten aus admin.js'
| PERM_BY_KEY. "__admin__" verlangt immer die Rolle "admin" (siehe dortiger
| Sonderwert-Kommentar in EnsureIdentityPermission).
|
| Unter "admin/content/*" registriert (statt unter "content/*" wie die
| Read-API oben), damit dieselben Datei-"Namen" (design.json, footer.json,
| ...) nicht mit den GET-Routen kollidieren - admin.js spricht GET weiterhin
| die oeffentliche Read-API oben an (siehe laravelModulFuerDatei()/
| apiGetLaravel() in admin.js) und nur PUT/POST hier.
|
*/
Route::prefix('admin')->group(function () {
    Route::get('version/{section}', [AdminVersionController::class, 'show'])
        ->where('section', '.*');

    // -- Settings-Familie --------------------------------------------------
    Route::put('content/design.json', [AdminSettingsController::class, 'design'])
        ->middleware('identity.permission:design');
    Route::put('content/einstellungen.json', [AdminSettingsController::class, 'einstellungen'])
        ->middleware('identity.permission:kontakt');
    Route::put('content/footer.json', [AdminSettingsController::class, 'footer'])
        ->middleware('identity.permission:footer');
    Route::put('content/impressum.json', [AdminSettingsController::class, 'impressum'])
        ->middleware('identity.permission:impressum');
    Route::put('content/navigation.json', [AdminSettingsController::class, 'navigation'])
        ->middleware('identity.permission:navigation_reihenfolge');
    Route::put('content/navigation-extra.json', [AdminSettingsController::class, 'navigationExtra'])
        ->middleware('identity.permission:navigation');
    Route::put('content/startseite.json', [AdminSettingsController::class, 'startseite'])
        ->middleware('identity.permission:startseite');

    // -- Flache Content-Listen ----------------------------------------------
    Route::put('content/aktuelles.json', [AdminListController::class, 'aktuelles'])
        ->middleware('identity.permission:aktuelles');
    // Phase 8C: letzter migrierter CMS-Rest - "service" ist ein bereits
    // bestehendes, eigenstaendiges Recht (siehe admin.js' PERM_BY_KEY).
    Route::put('content/service.json', [AdminListController::class, 'service'])
        ->middleware('identity.permission:service');
    Route::put('content/termine.json', [AdminListController::class, 'termine'])
        ->middleware('identity.permission:termine');
    Route::put('content/vorstand.json', [AdminListController::class, 'vorstand'])
        ->middleware('identity.permission:vorstand');
    Route::put('content/obleute.json', [AdminListController::class, 'obleute'])
        ->middleware('identity.permission:obleute');
    Route::put('content/hegeringe.json', [AdminListController::class, 'hegeringe'])
        ->middleware('identity.permission:hegeringe');
    Route::put('content/partner.json', [AdminListController::class, 'partner'])
        ->middleware('identity.permission:partner');
    Route::put('content/faq.json', [AdminListController::class, 'faq'])
        ->middleware('identity.permission:faq');
    Route::put('content/downloads.json', [AdminListController::class, 'downloads'])
        ->middleware('identity.permission:downloads');
    Route::put('content/kreisjjaegermeister.json', [AdminListController::class, 'kreisjaegermeister'])
        ->middleware('identity.permission:kjm');

    // -- Pages (Bearbeiten bestehender Seiten) -----------------------------
    // Phase 4 (Fortsetzung, Auftrag "normale Inhaltsseiten/Unterseiten/
    // Hundeausbildung"): granulare Pro-Seite-Rechtepruefung jetzt ueber
    // "identity.page_permission:<kind>" (siehe EnsurePagePermission/
    // PagePermissions) statt des bisherigen pauschalen "__admin__" - admin
    // bleibt weiterhin ueber PagePermissions::userMayAccess() (isAdmin()
    // zuerst geprueft) uneingeschraenkt zugriffsberechtigt, Redakteure
    // bekommen jetzt genau die Rechte, die admin.js' PERM_BY_KEY/PERM_BY_DIR
    // ihnen client-seitig ohnehin schon zubilligt.
    Route::put('content/aufgaben/hundeausbildung.json', [AdminPageController::class, 'hundeausbildungHub'])
        ->middleware('identity.page_permission:hundeausbildung_hub');
    Route::put('content/aufgaben/hundeausbildung/{slug}.json', [AdminPageController::class, 'hundeausbildungKurs'])
        ->middleware('identity.page_permission:hundeausbildung_kurs');
    Route::put('content/seiten-kjs/{slug}.json', [AdminPageController::class, 'registrierteSeiteKjs'])
        ->middleware('identity.page_permission:registrierte_jaeger');
    Route::put('content/seiten-aufgaben/{slug}.json', [AdminPageController::class, 'registrierteSeiteAufgaben'])
        ->middleware('identity.page_permission:registrierte_aufgaben');
    Route::put('content/seiten-verbraucher/{slug}.json', [AdminPageController::class, 'registrierteSeiteVerbraucher'])
        ->middleware('identity.page_permission:registrierte_verbraucher');
    Route::put('content/seiten-weitere/{slug}.json', [AdminPageController::class, 'weitereSeite'])
        ->middleware('identity.page_permission:weitere');
    Route::put('content/seiten-sub-{parentSlug}/{childSlug}.json', [AdminPageController::class, 'subSeite'])
        ->middleware('identity.page_permission:sub');
    Route::put('content/{section}/{slug}.json', [AdminPageController::class, 'festeSeite'])
        ->where('section', 'jaeger|aufgaben|verbraucher')
        ->middleware('identity.page_permission:feste');

    // -- Pages (Phase 6: NEUE Seiten/Unterseiten anlegen/löschen) -----------
    // Dieselben "<kind>"-Werte wie beim jeweiligen PUT-Pendant direkt
    // darüber (identisches Recht fürs Anlegen/Löschen wie fürs Bearbeiten,
    // siehe AdminPageController-Klassenkommentar/PagePermissions) - bewusst
    // KEINE Route fuer "festeSeite" (Systemvorlagen-Seiten sind weder neu
    // anlegbar noch löschbar, siehe Auftrag "keine festen Systemseiten neu
    // anlegbar machen").
    Route::post('content/aufgaben/hundeausbildung-seiten.json', [AdminPageController::class, 'storeHundeausbildungKurs'])
        ->middleware('identity.page_permission:hundeausbildung_kurs');
    Route::delete('content/aufgaben/hundeausbildung/{slug}.json', [AdminPageController::class, 'destroyHundeausbildungKurs'])
        ->middleware('identity.page_permission:hundeausbildung_kurs');
    Route::post('content/seiten-kjs.json', [AdminPageController::class, 'storeRegistrierteSeiteKjs'])
        ->middleware('identity.page_permission:registrierte_jaeger');
    Route::delete('content/seiten-kjs/{slug}.json', [AdminPageController::class, 'destroyRegistrierteSeiteKjs'])
        ->middleware('identity.page_permission:registrierte_jaeger');
    Route::post('content/seiten-aufgaben.json', [AdminPageController::class, 'storeRegistrierteSeiteAufgaben'])
        ->middleware('identity.page_permission:registrierte_aufgaben');
    Route::delete('content/seiten-aufgaben/{slug}.json', [AdminPageController::class, 'destroyRegistrierteSeiteAufgaben'])
        ->middleware('identity.page_permission:registrierte_aufgaben');
    Route::post('content/seiten-verbraucher.json', [AdminPageController::class, 'storeRegistrierteSeiteVerbraucher'])
        ->middleware('identity.page_permission:registrierte_verbraucher');
    Route::delete('content/seiten-verbraucher/{slug}.json', [AdminPageController::class, 'destroyRegistrierteSeiteVerbraucher'])
        ->middleware('identity.page_permission:registrierte_verbraucher');
    Route::post('content/seiten-weitere.json', [AdminPageController::class, 'storeWeitereSeite'])
        ->middleware('identity.page_permission:weitere');
    Route::delete('content/seiten-weitere/{slug}.json', [AdminPageController::class, 'destroyWeitereSeite'])
        ->middleware('identity.page_permission:weitere');
    Route::post('content/seiten-sub-{parentSlug}.json', [AdminPageController::class, 'storeSubSeite'])
        ->middleware('identity.page_permission:sub');
    Route::delete('content/seiten-sub-{parentSlug}/{childSlug}.json', [AdminPageController::class, 'destroySubSeite'])
        ->middleware('identity.page_permission:sub');

    // -- Pages (Phase 6B: Drag-&-Drop-Sortierung dynamischer Seiten) --------
    // PATCH auf denselben URLs wie die jeweiligen POST-Routen oben (Punkt 2)
    // mit identischen "<kind>"-Rechten (Punkt 3: "dieselbe PagePermissions-
    // Logik wie beim Bearbeiten/Anlegen"). Body: {"order": [...Slugs in
    // gewuenschter Reihenfolge...]}. Keine Route fuer "festeSeite" - feste
    // Systemseiten werden nie ueber eine Registry-Liste mitsortiert (siehe
    // reordne()-Kommentar in AdminPageController).
    Route::patch('content/aufgaben/hundeausbildung-seiten.json', [AdminPageController::class, 'reorderHundeausbildungKurse'])
        ->middleware('identity.page_permission:hundeausbildung_kurs');
    Route::patch('content/seiten-kjs.json', [AdminPageController::class, 'reorderRegistrierteSeiteKjs'])
        ->middleware('identity.page_permission:registrierte_jaeger');
    Route::patch('content/seiten-aufgaben.json', [AdminPageController::class, 'reorderRegistrierteSeiteAufgaben'])
        ->middleware('identity.page_permission:registrierte_aufgaben');
    Route::patch('content/seiten-verbraucher.json', [AdminPageController::class, 'reorderRegistrierteSeiteVerbraucher'])
        ->middleware('identity.page_permission:registrierte_verbraucher');
    Route::patch('content/seiten-weitere.json', [AdminPageController::class, 'reorderWeitereSeiten'])
        ->middleware('identity.page_permission:weitere');
    Route::patch('content/seiten-sub-{parentSlug}.json', [AdminPageController::class, 'reorderSubSeiten'])
        ->middleware('identity.page_permission:sub');

    // -- Medienbibliothek (Phase 5B.1, Frontend-Anbindung Phase 5B.2) -------
    // Eigenstaendige, generische Medien-API (siehe AdminMediaController-
    // Klassenkommentar) - "medien" ist ein eigener Berechtigungsschluessel
    // aus admin.js' PERM_BY_KEY, kein "__admin__". Bewusst NICHT unter
    // "content/*" registriert (kein content/*.json-Aequivalent), sondern
    // als eigene Ressource "admin/media".
    Route::get('media', [AdminMediaController::class, 'index'])
        ->middleware('identity.permission:medien');
    Route::post('media/images', [AdminMediaController::class, 'storeImage'])
        ->middleware('identity.permission:medien');
    Route::post('media/pdfs', [AdminMediaController::class, 'storePdf'])
        ->middleware('identity.permission:medien');
    // Phase 5B.2: kein {id}-Routenparameter mehr (siehe AdminMediaController
    // ::destroy()-Kommentar) - media_type/filename kommen jetzt im JSON-Body,
    // weil die Medienbibliothek jetzt auch historische Dateien ohne
    // numerische DB-ID anzeigt.
    Route::delete('media', [AdminMediaController::class, 'destroy'])
        ->middleware('identity.permission:medien');
});
