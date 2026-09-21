<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Netlify Identity -> Laravel Fortify (Session/"web"-Guard) - Nachfolger von
 * App\Support\NetlifyIdentity, das hier ersetzt wurde. Liefert unveraendert
 * dieselbe ['sub','email','roles'=>[],'permissions'=>[]]-Form, die
 * EnsureIdentityPermission, EnsurePagePermission und PagePermissions bereits
 * konsumieren - dadurch musste an diesen drei Verbraucher-Klassen NUR der
 * Klassenname in ihrem "use"-Import bzw. Aufruf angepasst werden, ihre
 * gesamte Ablauf-/Rechtelogik blieb unveraendert.
 *
 * VORHER (NetlifyIdentity): currentUser() verifizierte selbst ein vom
 * Browser mitgesendetes Netlify-Identity-JWT (Authorization: Bearer ...)
 * per eigener HS256-Pruefung gegen ein geteiltes Secret, roles/permissions
 * kamen aus dem JWT-Claim app_metadata.
 *
 * JETZT (AdminIdentity): currentUser() liest den bereits von Laravels
 * eigener Session-Middleware ("web"-Gruppe, siehe routes/api.php) fest-
 * gestellten, ueber Fortify per E-Mail+Passwort angemeldeten Benutzer
 * ($request->user('web') === Auth::guard('web')->user()) und uebernimmt
 * roles/permissions direkt aus dessen eigenen Datenbankspalten (siehe
 * Migration 2026_09_21_000001_add_roles_permissions_to_users_table.php) -
 * KEINE JWT-Verarbeitung, KEIN geteiltes Secret mehr noetig. "Fail-closed"
 * bleibt erhalten: kein angemeldeter Benutzer -> null, exakt wie vorher.
 */
class AdminIdentity
{
    /**
     * @return array{sub: ?string, email: ?string, roles: list<string>, permissions: list<string>}|null
     */
    public static function currentUser(Request $request): ?array
    {
        $user = $request->user('web') ?? Auth::guard('web')->user();
        if ($user === null) {
            return null;
        }

        $roles = is_array($user->roles ?? null) ? array_values(array_filter($user->roles, 'is_string')) : [];
        $permissions = is_array($user->permissions ?? null) ? array_values(array_filter($user->permissions, 'is_string')) : [];

        return [
            'sub' => (string) $user->getAuthIdentifier(),
            'email' => is_string($user->email ?? null) ? $user->email : null,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /** @param array{roles: list<string>, permissions: list<string>} $user */
    public static function isAdmin(array $user): bool
    {
        return in_array('admin', $user['roles'], true);
    }

    /** @param array{roles: list<string>, permissions: list<string>} $user */
    public static function hasPermission(array $user, string $permissionKey): bool
    {
        return self::isAdmin($user) || in_array($permissionKey, $user['permissions'], true);
    }
}
