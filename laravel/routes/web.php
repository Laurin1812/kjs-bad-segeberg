<?php

use App\Http\Controllers\AktuellesController;
use App\Http\Controllers\DatenschutzController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FesteSeiteController;
use App\Http\Controllers\HegeringeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HundeausbildungController;
use App\Http\Controllers\HundeboerseController;
use App\Http\Controllers\ImpressumController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\KreisjaegermeisterController;
use App\Http\Controllers\LegacyUrlController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PersonenGremiumController;
use App\Http\Controllers\RegistrySeiteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TermineController;
use App\Http\Controllers\WaffenboerseController;
use Illuminate\Support\Facades\Route;

// Phase 4 (Startseite + komplette Laravel-Navigation, Laravel-
// Vollmigration): ersetzt die bisherige Laravel-Welcome-Seite durch die
// echte KJS-Startseite - "Route -> Controller -> Eloquent/MySQL -> Blade",
// siehe HomeController-Klassenkommentar. Kein Content-JSON mehr fuer "/".
Route::get('/', [HomeController::class, 'index'])->name('home');

// Phase 2 (Oeffentliche Inhaltsseiten / einfache Seitenfamilien,
// Laravel-Vollmigration): echte Laravel-Web-Routen -> Controller ->
// Eloquent/MySQL -> Blade fuer alle in Phase 2 beauftragten Seiten. Keine
// dieser Routen liest zur Laufzeit content/*.json, Legacy-PHP, Netlify oder
// das Git-Gateway (siehe Abschlussbericht Punkt 6).
Route::get('/impressum', [ImpressumController::class, 'show'])->name('impressum');
Route::get('/datenschutz', [DatenschutzController::class, 'show'])->name('datenschutz');
Route::get('/service', [ServiceController::class, 'show'])->name('service');

// Phase 4 Abschluss-Nacharbeit ("Kontakt-Route"): die Hauptnavigation
// (settings-Gruppe "navigation") verweist seit Phase 4 bereits auf
// "/kontakt" (ebenso mehrere bereits migrierte Seiten, z.B. pages/
// show.blade.php), bisher lief das aber auf eine echte 404, da keine Route
// existierte. Siehe KontaktController-Klassenkommentar fuer die bewusste
// Abgrenzung zum weiterhin nicht migrierten Kontaktformular-Sondermodul.
Route::get('/kontakt', [KontaktController::class, 'show'])->name('kontakt');

Route::get('/aktuelles', [AktuellesController::class, 'index'])->name('aktuelles.index');
// URL-Schema bewusst modernisiert (siehe AktuellesController-Klassenkommentar
// und Abschlussbericht Punkt 2): ersetzt die alte Query-String-Adresse
// "aktuelles/beitrag.html?i=<Array-Index>" durch den echten "slug"-
// Spaltenwert als Pfadsegment.
Route::get('/aktuelles/beitrag/{slug}', [AktuellesController::class, 'show'])->name('aktuelles.show');

Route::get('/termine', [TermineController::class, 'index'])->name('termine');
Route::get('/faq', [FaqController::class, 'index'])->name('faq');
Route::get('/downloads', [DownloadsController::class, 'index'])->name('downloads');

Route::get('/partner', [PartnerController::class, 'index'])->name('partner.index');
// Ebenfalls modernisiert: "partner/detail.html?id=pn-..." -> "/partner/
// detail/pn-..." (echtes Pfadsegment statt Query-String), Lookup weiterhin
// ueber "external_id" (siehe PartnerController-Klassenkommentar).
Route::get('/partner/detail/{externalId}', [PartnerController::class, 'show'])->name('partner.show');

// Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
// ersetzt hundeboerse/index.html + detail.html + anbieten.html - siehe
// HundeboerseController-Klassenkommentar. "/hundeboerse/detail/{id}" statt
// "?id=" analog zum bereits etablierten Partner-URL-Schema oben. Die
// POST-Route traegt eine einfache Laravel-Throttle-Bremse (5 Versuche /
// 15 Minuten je IP) als Laravel-idiomatisches Aequivalent zum bisherigen
// dateibasierten kjs_rate_limit_check() aus api/lib/rate_limit.php.
Route::get('/hundeboerse', [HundeboerseController::class, 'index'])->name('hundeboerse.index');
Route::get('/hundeboerse/anbieten', [HundeboerseController::class, 'createForm'])->name('hundeboerse.anbieten');
Route::post('/hundeboerse/anbieten', [HundeboerseController::class, 'store'])
    ->middleware('throttle:5,15')
    ->name('hundeboerse.anbieten.store');
Route::get('/hundeboerse/detail/{id}', [HundeboerseController::class, 'show'])->name('hundeboerse.show');

// Phase 6B (Waffenboerse auf Laravel/MySQL): ersetzt waffenboerse/index.html
// + detail.html + anbieten.html - siehe WaffenboerseController-
// Klassenkommentar. Gleiches URL-/Throttle-Schema wie Hundeboerse oben.
Route::get('/waffenboerse', [WaffenboerseController::class, 'index'])->name('waffenboerse.index');
Route::get('/waffenboerse/anbieten', [WaffenboerseController::class, 'createForm'])->name('waffenboerse.anbieten');
Route::post('/waffenboerse/anbieten', [WaffenboerseController::class, 'store'])
    ->middleware('throttle:5,15')
    ->name('waffenboerse.anbieten.store');
Route::get('/waffenboerse/detail/{id}', [WaffenboerseController::class, 'show'])->name('waffenboerse.show');

// Bewusst unter dem alten "/jaeger/"-Pfadpraefix belassen (entspricht der
// bisherigen Verzeichnisstruktur jaeger/vorstand.html etc.) statt neue
// Top-Level-Pfade zu erfinden - im Sinne von "alte URLs bleiben erhalten",
// soweit mit sauberem Laravel-Routing vereinbar.
Route::get('/jaeger/vorstand', [PersonenGremiumController::class, 'vorstand'])->name('jaeger.vorstand');
Route::get('/jaeger/obleute', [PersonenGremiumController::class, 'obleute'])->name('jaeger.obleute');
Route::get('/jaeger/hegeringe', [HegeringeController::class, 'index'])->name('jaeger.hegeringe');

// Absichtlich mit dem echten (doppel-j) Tippfehler aus der Original-Site
// beibehalten ("kreisjjaegermeister" statt "kreisjaegermeister") - das ist
// die tatsaechliche, bereits produktiv verlinkte/indexierte URL.
Route::get('/kreisjjaegermeister', [KreisjaegermeisterController::class, 'show'])->name('kreisjaegermeister');

// ---------------------------------------------------------------------
// Phase 5 (URL-Erhalt / alte Pfade / Redirects): permanente (301)
// Weiterleitungen von den alten, vor der Laravel-Migration oeffentlich
// erreichbaren ".html"-URLs auf die entsprechende kanonische Laravel-Route
// - damit alte Google-Ergebnisse, Lesezeichen und externe Verlinkungen
// nicht unnoetig auf 404 laufen. MUESSEN vor der generischen
// "{section}/{slug}"-Route weiter unten stehen, sonst wuerde z.B.
// "/jaeger/hochwild.html" faelschlich als Registry-Seite mit dem Slug
// "hochwild.html" interpretiert (und 404en), statt hierher zu greifen.
//
// Bewusst NICHT per pauschalem Catch-all geloest, sondern als explizite
// Liste tatsaechlich vorher existierender Pfade - jedes Redirect-Ziel ist
// eine echte, bereits registrierte Route (siehe Abschlussbericht). Alte
// URLs, die zur weiterhin unveraendert erreichbaren Admin-/Login-
// Infrastruktur gehoeren, werden hier bewusst NICHT umgebogen (siehe
// Abschlussbericht) - sie bleiben als echte, im Webroot weiterhin
// vorhandene Dateien unveraendert erreichbar. Hundeboerse (Phase 6A) und
// Waffenboerse (Phase 6B) sind seitdem migriert und weiter unten mit
// eigenen Bloecken erfasst.
Route::permanentRedirect('/index.html', '/');
Route::permanentRedirect('/impressum.html', '/impressum');
Route::permanentRedirect('/datenschutz.html', '/datenschutz');
Route::permanentRedirect('/service.html', '/service');
Route::permanentRedirect('/aktuelles/index.html', '/aktuelles');
Route::permanentRedirect('/termine/index.html', '/termine');
Route::permanentRedirect('/faq/index.html', '/faq');
Route::permanentRedirect('/downloads/index.html', '/downloads');
Route::permanentRedirect('/partner/index.html', '/partner');
Route::permanentRedirect('/kontakt/index.html', '/kontakt');
Route::permanentRedirect('/kreisjjaegermeister/index.html', '/kreisjjaegermeister');

// "jaeger/index.html" ist der einzige Sonderfall unter den festen Seiten:
// ergibt NICHT das (nicht existierende) bereinigte "/jaeger", sondern die
// Jaeger-Uebersichtsseite - identische Ausnahme wie in
// HomeController::$quicklinkHref/Navigation::prettyHref()-Klassenkommentar.
Route::permanentRedirect('/jaeger/index.html', '/jaeger/uebersicht');

Route::permanentRedirect('/jaeger/vorstand.html', '/jaeger/vorstand');
Route::permanentRedirect('/jaeger/obleute.html', '/jaeger/obleute');
Route::permanentRedirect('/jaeger/hegeringe.html', '/jaeger/hegeringe');
Route::permanentRedirect('/jaeger/hochwild.html', '/jaeger/hochwild');
Route::permanentRedirect('/jaeger/niederwild.html', '/jaeger/niederwild');
Route::permanentRedirect('/jaeger/ueber-uns.html', '/jaeger/ueber-uns');
Route::permanentRedirect('/jaeger/satzung.html', '/jaeger/satzung');
Route::permanentRedirect('/jaeger/landesjagdverband.html', '/jaeger/landesjagdverband');
Route::permanentRedirect('/jaeger/schiessobleute.html', '/jaeger/schiessobleute');
Route::permanentRedirect('/jaeger/jaeger-werden.html', '/jaeger/jaeger-werden');
Route::permanentRedirect('/jaeger/mitglied-werden.html', '/jaeger/mitglied-werden');
Route::permanentRedirect('/jaeger/infomobil.html', '/jaeger/infomobil');

Route::permanentRedirect('/aufgaben/hundeausbildung.html', '/aufgaben/hundeausbildung');
Route::permanentRedirect('/aufgaben/jagdhundeschule.html', '/aufgaben/jagdhundeschule');
Route::permanentRedirect('/aufgaben/jagdhorn.html', '/aufgaben/jagdhorn');
Route::permanentRedirect('/aufgaben/jugend.html', '/aufgaben/jugend');
Route::permanentRedirect('/aufgaben/jungwildrettung.html', '/aufgaben/jungwildrettung');
Route::permanentRedirect('/aufgaben/naturschutz.html', '/aufgaben/naturschutz');
Route::permanentRedirect('/aufgaben/schiessen.html', '/aufgaben/schiessen');
Route::permanentRedirect('/aufgaben/schweisshunde.html', '/aufgaben/schweisshunde');

Route::permanentRedirect('/verbraucher/gruenes-klassenzimmer.html', '/verbraucher/gruenes-klassenzimmer');
Route::permanentRedirect('/verbraucher/lernort-natur.html', '/verbraucher/lernort-natur');
Route::permanentRedirect('/verbraucher/waidmannssprache.html', '/verbraucher/waidmannssprache');
Route::permanentRedirect('/verbraucher/wildfleisch.html', '/verbraucher/wildfleisch');

// "aktuelles/beitrag.html?i=<Array-Index>": der Index war eine reine
// Fetch-Zeit-Position innerhalb von content/aktuelles.json, keine stabile
// ID - verschiebt sich, sobald neue Aktuelles-Beitraege dazukommen (siehe
// Abschlussbericht Punkt "form_id"/Aktuelles fuer die ausfuehrliche
// Begruendung). Ohne den exakten JSON-Stand von damals laesst sich "i"
// nicht mehr verlustfrei auf einen bestimmten Beitrags-Slug zurueckrechnen
// - eine falsche Rate-Weiterleitung waere schlimmer als eine 404. Die
// Aktuelles-Uebersicht ist dagegen fachlich eindeutig die richtige
// naechsthoehere Seite fuer JEDEN alten Beitrags-Link, deshalb (und nur
// dafuer) ein bewusster, eng auf genau dieses eine alte Pfadmuster
// begrenzter Fallback - kein pauschaler Catch-all.
Route::permanentRedirect('/aktuelles/beitrag.html', '/aktuelles');

// "seiten/index.html?s=<slug>" und "partner/detail.html?id=<id>" haengen
// vom jeweiligen Query-Parameter ab - keine feste Ziel-URL moeglich, daher
// eigene Controller-Methoden statt Route::permanentRedirect() (siehe
// LegacyUrlController-Klassenkommentar).
Route::get('/seiten/index.html', [LegacyUrlController::class, 'seiten']);
Route::get('/partner/detail.html', [LegacyUrlController::class, 'partnerDetail']);

// Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
// alte Hundeboerse-Pfade (bislang echte Dateien im Webroot) auf die neuen
// Laravel-Routen - "detail.html?id=" haengt vom Query-Parameter ab, siehe
// LegacyUrlController::hundeboerseDetail(). MUESSEN ebenfalls vor der
// generischen "{section}/{slug}"-Route weiter unten stehen (siehe Kommentar
// dort) - unproblematisch, da "hundeboerse" nicht Teil von deren
// section-Whitelist (jaeger|aufgaben|verbraucher) ist, aber zur
// Konsistenz mit dem uebrigen Phase-5-Block hier zusammen registriert.
Route::permanentRedirect('/hundeboerse/index.html', '/hundeboerse');
Route::permanentRedirect('/hundeboerse/anbieten.html', '/hundeboerse/anbieten');
Route::get('/hundeboerse/detail.html', [LegacyUrlController::class, 'hundeboerseDetail']);

// Phase 6B (Waffenboerse auf Laravel/MySQL): alte Waffenboerse-Pfade
// (bislang echte Dateien im Webroot) auf die neuen Laravel-Routen -
// "detail.html?id=" haengt vom Query-Parameter ab, siehe
// LegacyUrlController::waffenboerseDetail(). Gleiches Schema wie
// Hundeboerse oben.
Route::permanentRedirect('/waffenboerse/index.html', '/waffenboerse');
Route::permanentRedirect('/waffenboerse/anbieten.html', '/waffenboerse/anbieten');
Route::get('/waffenboerse/detail.html', [LegacyUrlController::class, 'waffenboerseDetail']);

// ---------------------------------------------------------------------
// Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
// Vollmigration): jaeger/aufgaben/verbraucher (feste Vorlagen-Seiten UND
// per Registry hinzugefuegte Zusatzseiten UND dynamisch angelegte
// Unterseiten), "weitere"-Seiten, Hundeausbildung. Keine dieser Routen
// liest zur Laufzeit content/*.json, /api/content/*.json, Legacy-PHP,
// Netlify oder das Git-Gateway.
//
// WICHTIG (Routing-Reihenfolge, siehe Auftrag): Hundeausbildung nutzt
// LITERALE Routen unter "aufgaben/..." (aufgaben/hundeausbildung,
// aufgaben/jagdhundeschule[/…]) - diese MUESSEN vor der generischen
// "{section}/{slug}"-Route stehen, sonst wuerde z.B. "/aufgaben/
// hundeausbildung" faelschlich als (nicht existierende) Registry-Seite der
// Section "aufgaben" interpretiert (identische Falle wie zuvor in
// routes/api.php, siehe dortiger Kommentar). Aus demselben Grund steht die
// generische 2-Segment-Route ("{section}/{slug}") vor der generischen
// 3-Segment-Unterseiten-Route ("{section}/{parentSlug}/{childSlug}") -
// unproblematisch, da unterschiedliche Segment-Anzahl, aber zur
// Konsistenz in derselben Reihenfolge wie in der JSON-Read-API gehalten.
// Ein unbekannter Slug fuehrt in allen Faellen ueber firstOrFail() zu
// einer echten Laravel-404 (siehe FesteSeiteController/
// RegistrySeiteController/HundeausbildungController).
// ---------------------------------------------------------------------

Route::get('/aufgaben/hundeausbildung', [HundeausbildungController::class, 'hub'])->name('hundeausbildung.hub');
Route::get('/aufgaben/jagdhundeschule', [HundeausbildungController::class, 'index'])->name('hundeausbildung.index');
Route::get('/aufgaben/jagdhundeschule/{slug}', [HundeausbildungController::class, 'show'])->name('hundeausbildung.show');

// "weitere"-Seiten (z.B. /weitere/jagdhornblasen) - kein fixedSlugs-Konzept,
// siehe RegistrySeiteController::weitere()/KjsPagesConfig-Klassenkommentar.
Route::get('/weitere/{slug}', [RegistrySeiteController::class, 'weitere'])->name('weitere.show');

// Feste Vorlagen-Seiten UND per Registry hinzugefuegte Zusatzseiten von
// jaeger/aufgaben/verbraucher - EINE Route fuer beide Faelle (siehe
// FesteSeiteController-Klassenkommentar "ROUTING"), da die Unterscheidung
// fuer Besucher keine sichtbare URL-Struktur ist.
Route::get('/{section}/{slug}', [FesteSeiteController::class, 'show'])
    ->where('section', 'jaeger|aufgaben|verbraucher');

// Dynamisch angelegte Unterseiten (Kind-Seiten via parent_id) einer festen
// oder per Registry hinzugefuegten Eltern-Seite.
Route::get('/{section}/{parentSlug}/{childSlug}', [RegistrySeiteController::class, 'sub'])
    ->where('section', 'jaeger|aufgaben|verbraucher|weitere');

// Phase 1 (Blade-Fundament, Laravel-Vollmigration): interne Vorschau-Route
// zum visuellen Vergleich des neuen Blade-Grundlayouts (Header/Nav/Footer/
// Breadcrumb/Page-Hero) mit der bestehenden statischen Seite. Bewusst per
// Umgebungs-Guard NICHT in Production registriert - existiert also auf der
// Live-Seite gar nicht und muss dafuer in Phase 12 auch nicht extra entfernt
// werden. Ersetzt keine bestehende Route und aendert nichts an "/" (welcome
// bleibt fuer Phase 1 unveraendert, die eigentliche Startseite folgt in
// Phase 2).
if (! app()->environment('production')) {
    Route::get('/_intern/vorschau/blade-fundament', function () {
        return view('preview.blade-fundament');
    })->name('preview.blade-fundament');
}
