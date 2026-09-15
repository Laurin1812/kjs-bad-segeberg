<?php

namespace App\Http\Middleware;

use App\Support\NetlifyIdentity;
use App\Support\PagePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 4 (Fortsetzung): Gegenstueck zu EnsureIdentityPermission, aber fuer
 * die Seiten-Routen (AdminPageController), deren erforderliches Recht -
 * anders als bei den Settings-/Listen-Modulen - vom SLUG in der URL abhaengt,
 * nicht vom Endpunkt selbst (siehe PagePermissions-Klassenkommentar und der
 * bisherige Blocker-Kommentar in AdminPageController).
 *
 * $kind identifiziert die Routen-"Familie" (siehe routes/api.php), damit
 * diese Middleware weiss, WELCHE Routen-Parameter sie lesen und an
 * PagePermissions weiterreichen muss. 401, wenn kein gueltiges Token
 * vorliegt; 403, wenn das Token gueltig ist, aber PagePermissions::
 * userMayAccess() ablehnt - exakt dasselbe Antwortformat wie
 * EnsureIdentityPermission, damit admin.js' bestehende Fehlerbehandlung
 * (guardSavePermission()/doSave()) unveraendert funktioniert.
 */
class EnsurePagePermission
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $user = NetlifyIdentity::currentUser($request);
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => 'not_authenticated',
                'message' => 'Nicht angemeldet oder Sitzung abgelaufen.',
            ], 401);
        }

        $permissionKey = match ($kind) {
            'feste' => PagePermissions::forFesteSeite(
                (string) $request->route('section'),
                (string) $request->route('slug')
            ),
            'registrierte_jaeger' => PagePermissions::forRegistrierteSeite('jaeger'),
            'registrierte_aufgaben' => PagePermissions::forRegistrierteSeite('aufgaben'),
            'registrierte_verbraucher' => PagePermissions::forRegistrierteSeite('verbraucher'),
            'weitere' => PagePermissions::forWeitereSeite(),
            'sub' => PagePermissions::forSubSeite((string) $request->route('parentSlug')),
            'hundeausbildung_hub' => PagePermissions::forHundeausbildungHub(),
            'hundeausbildung_kurs' => PagePermissions::forHundeausbildungKurs(),
            default => null,
        };

        if (! PagePermissions::userMayAccess($user, $permissionKey)) {
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
