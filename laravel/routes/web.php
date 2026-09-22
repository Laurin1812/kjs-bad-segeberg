<?php

use App\Http\Controllers\AktuellesController;
use App\Http\Controllers\DatenschutzController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FesteSeiteController;
use App\Http\Controllers\HegeringeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HundeausbildungController;
use App\Http\Controllers\ImpressumController;
use App\Http\Controllers\KreisjaegermeisterController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PersonenGremiumController;
use App\Http\Controllers\RegistrySeiteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TermineController;
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
