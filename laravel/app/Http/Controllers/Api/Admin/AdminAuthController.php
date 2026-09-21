<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Netlify Identity -> Laravel Fortify (Session/"web"-Guard).
 *
 * Fortify selbst liefert fuer Login/Logout absichtlich KEINE Benutzerdaten
 * zurueck (siehe Laravel\Fortify\Http\Responses\LoginResponse: bei einer
 * JSON-Anfrage nur {"two_factor": false}, LogoutResponse nur einen leeren
 * 204) - das ist fuer die klassische Inertia-/Blade-Zielgruppe von Fortify
 * gedacht, wo der Benutzer ueber eine anschliessende Seiten-Neuladung bzw.
 * einen geteilten Inertia-Prop ankommt. Das bestehende Admin-Panel
 * (admin/admin.js) ist eine reine JS-Oberflaeche ohne Seiten-Neuladung und
 * braucht die Benutzerdaten (Name/E-Mail/Rollen/Rechte fuer Sidebar- und
 * Panel-Sichtbarkeit, siehe dortiges onLogin()) direkt als JSON - dieser
 * eine kleine, eigene Endpunkt schliesst genau diese Luecke:
 *
 *   - beim Laden von /admin/ (checkSession() in admin.js): 200 mit den
 *     Benutzerdaten, wenn bereits eine gueltige Sitzung besteht (Browser
 *     hat das Sitzungs-Cookie mitgeschickt), sonst 401 (admin.js zeigt dann
 *     #login-screen) - ersetzt netlifyIdentity.on('init', ...).
 *   - direkt NACH einem erfolgreichen POST auf .../auth/login (Fortify):
 *     admin.js ruft diesen Endpunkt zusaetzlich auf, um die Sidebar/den
 *     Benutzernamen zu befuellen - ersetzt netlifyIdentity.on('login', ...).
 *
 * Bewusst KEIN "auth"-Middleware-Zwang auf der Route (siehe routes/api.php)
 * - ein Gast bekommt hier schlicht 401 statt eines Redirects, wie es die
 * bestehende Fehlerbehandlung in admin.js (guardSavePermission()/doSave())
 * bereits fuer alle anderen Endpunkte erwartet.
 */
class AdminAuthController extends Controller
{
    public function user(Request $request): JsonResponse
    {
        $user = AdminIdentity::currentUser($request);
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => 'not_authenticated',
                'message' => 'Nicht angemeldet oder Sitzung abgelaufen.',
            ], 401);
        }

        $model = $request->user('web');

        return response()->json([
            'success' => true,
            'name' => $model?->name ?? $user['email'] ?? '',
            'email' => $user['email'],
            'roles' => $user['roles'],
            'permissions' => $user['permissions'],
        ]);
    }
}
