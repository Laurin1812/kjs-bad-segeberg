<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
