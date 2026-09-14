<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\ContentVersioning;
use Illuminate\Http\JsonResponse;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL): liefert die aktuelle
 * Versionsnummer eines admin-editierbaren Moduls - der Ersatz fuer das
 * "frische SHA holen"-Verhalten aus admin.js' fetchFreshSha() (siehe
 * AdminSettingsController/AdminListController/AdminPageController fuer die
 * "section"-Namen). Bewusst ein eigener, leichter Endpunkt statt die
 * Versionsnummer in jede GET-Antwort der oeffentlichen Read-API einzubauen -
 * die Read-API bleibt dadurch unveraendert 1:1 kompatibel (Auftrag Phase 3).
 */
class AdminVersionController extends Controller
{
    public function show(string $section): JsonResponse
    {
        return response()->json([
            'success' => true,
            'section' => $section,
            'version' => ContentVersioning::current($section),
        ]);
    }
}
