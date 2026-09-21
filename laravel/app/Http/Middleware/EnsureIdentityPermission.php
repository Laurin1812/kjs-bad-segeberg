<?php

namespace App\Http\Middleware;

use App\Support\AdminIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL): serverseitige
 * Modul-Rechtepruefung fuer die neuen Admin-Schreib-Endpunkte, 1:1 nach dem
 * Muster von identity_auth.php::kjs_boerse_require_permission() (dort fuer
 * Hundeboerse/Waffenboerse).
 *
 * Netlify Identity -> Laravel Fortify: prueft seit dieser Umstellung die
 * Laravel-Session (App\Support\AdminIdentity) statt eines Netlify-JWT -
 * siehe dortigen Klassenkommentar. 401/403-Antwortform fuer admin.js
 * unveraendert.
 *
 * $permissionKey entspricht exakt den Werten aus admin.js' PERM_BY_KEY
 * (z.B. "footer", "vorstand", "aktuelles") - siehe Verwendung in
 * routes/api.php. Der Sonderwert "__admin__" verlangt IMMER die Rolle
 * "admin" (kein einzelnes Modul-Recht kann das freischalten) - siehe
 * PERM_BY_KEY-Eintraege mit Wert "null" (z.B. 'benutzer') sowie, in dieser
 * Phase 4, AdminPageController (siehe dortiger Klassenkommentar: granulare
 * Seiten-Rechte pro Slug sind noch nicht serverseitig abgebildet, siehe
 * Abschlussbericht "offene Punkte").
 *
 * 401, wenn kein gueltiges Token vorliegt; 403, wenn das Token gueltig ist,
 * aber weder "admin" noch die geforderte Berechtigung traegt. Der
 * authentifizierte Benutzer wird als Request-Attribut "identity_user" fuer
 * die Controller verfuegbar gemacht (z.B. fuer Logging), wird aber fuer die
 * eigentliche Schreiblogik nicht vorausgesetzt.
 */
class EnsureIdentityPermission
{
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = AdminIdentity::currentUser($request);
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => 'not_authenticated',
                'message' => 'Nicht angemeldet oder Sitzung abgelaufen.',
            ], 401);
        }

        $allowed = $permissionKey === '__admin__'
            ? AdminIdentity::isAdmin($user)
            : AdminIdentity::hasPermission($user, $permissionKey);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Keine Berechtigung fuer dieses Modul.',
            ], 403);
        }

        $request->attributes->set('identity_user', $user);

        return $next($request);
    }
}
