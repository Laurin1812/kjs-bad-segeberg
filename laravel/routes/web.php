<?php

use App\Http\Controllers\AktuellesController;
use App\Http\Controllers\DatenschutzController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HegeringeController;
use App\Http\Controllers\ImpressumController;
use App\Http\Controllers\KreisjaegermeisterController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PersonenGremiumController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TermineController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Phase 2 (Oeffentliche Inhaltsseiten / einfache Seitenfamilien,
// Laravel-Vollmigration): echte Laravel-Web-Routen -> Controller ->
// Eloquent/MySQL -> Blade fuer alle in Phase 2 beauftragten Seiten. "/"
// bleibt bewusst unveraendert (welche, siehe Route oben) - die eigentliche
// Startseite ist erst Phase 4. Keine dieser Routen liest zur Laufzeit
// content/*.json, Legacy-PHP, Netlify oder das Git-Gateway (siehe
// Abschlussbericht Punkt 6).
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
