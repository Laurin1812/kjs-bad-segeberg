<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Phase 4 (Admin-Schreibweg Git/JSON -> Laravel/MySQL): serverseitige
 * Pruefung von Netlify-Identity-Zugriffstoken (JWT), 1:1 nach demselben
 * Verfahren wie api/lib/identity_auth.php (dort fuer die Hundeboerse/
 * Waffenboerse-PHP-Endpunkte) - bewusst NICHT per Composer-Paket (z.B.
 * firebase/php-jwt), sondern als eigenstaendige Portierung, damit Laravel
 * exakt dieselbe Pruefung (Algorithmus, Pflichtfelder, Fail-Closed-
 * Verhalten) verwendet wie der bestehende PHP-Layer und beide Layer
 * dasselbe Secret aus derselben Quelle lesen - siehe unten.
 *
 * WARUM UEBERHAUPT NOETIG: das bestehende Admin-Panel (admin/admin.js)
 * meldet Benutzer ueber das Netlify-Identity-Widget an. Bisher ging JEDE
 * Speicherung ueber Netlifys eigene git-gateway-API, die zwar prueft, dass
 * ueberhaupt ein gueltiger Identity-Benutzer eingeloggt ist, aber NICHT, ob
 * dieser Benutzer die passende Modul-Berechtigung hat (siehe
 * guardSavePermission()-Kommentar in admin.js: "serverseitige Pruefung [...]
 * gibt es fuer Inhalte technisch [...] nicht"). Die neuen Laravel-
 * Schreib-Endpunkte koennen das nicht einfach von Netlify erben und pruefen
 * das JWT deshalb selbst nach.
 *
 * KONFIGURATION (identisch zu api/lib/identity_auth.php, siehe dortiger
 * Datei-Kommentar und config/identity.example.php):
 *   1) Environment-Variable IDENTITY_JWT_SECRET (empfohlen, funktioniert
 *      sowohl in Laravels .env als auch klassisch per getenv() fuer den
 *      PHP-Layer - EINE Variable fuer beide).
 *   2) Falls nicht gesetzt: dieselbe Datei config/identity.local.php im
 *      Repository-Wurzelverzeichnis (NICHT laravel/config/ - siehe
 *      IDENTITY_CONFIG_PATH-Berechnung unten), die der PHP-Layer bereits
 *      verwendet. Damit reicht EINE einmalige Konfiguration fuer beide
 *      Layer, ohne das Secret zweimal pflegen zu muessen.
 * Ohne Secret lehnt kjs_boerse_current_admin_user()/currentUser() JEDEN
 * Zugriff ab (fail-closed) - identisches Verhalten zum PHP-Layer.
 */
class NetlifyIdentity
{
    private static function envOrNull(string $key): ?string
    {
        $value = env($key);
        if ($value === null) {
            return null;
        }
        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    private static function secret(): ?string
    {
        $secret = self::envOrNull('IDENTITY_JWT_SECRET');
        if ($secret !== null) {
            return $secret;
        }

        // Identisch zu identity_auth.php::kjs_boerse_current_admin_user():
        // dirname(__DIR__, 2) dort ist von api/lib/ aus das Repo-Root; von
        // laravel/app/Support/ aus sind das drei Ebenen nach oben, dasselbe
        // Ziel (Repo-Root)/config/identity.local.php.
        $configPath = self::envOrNull('IDENTITY_CONFIG_PATH')
            ?? dirname(__DIR__, 3).'/config/identity.local.php';

        if (! is_file($configPath)) {
            return null;
        }
        $fileConfig = require $configPath;
        if (is_array($fileConfig) && ! empty($fileConfig['jwt_secret'])) {
            return (string) $fileConfig['jwt_secret'];
        }

        return null;
    }

    private static function b64UrlDecode(string $segment): string
    {
        $segment = strtr($segment, '-_', '+/');
        $pad = strlen($segment) % 4;
        if ($pad) {
            $segment .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($segment, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * Prueft Signatur + Zeitgueltigkeit eines HS256-JWT - siehe
     * identity_auth.php::kjs_boerse_verify_identity_jwt() fuer die
     * ausfuehrliche Begruendung jeder einzelnen Pruefung (Pflichtfeld "exp",
     * optionales "nbf", optionale "iss"/"aud"). Bewusst identisch gehalten,
     * damit ein Token, das der PHP-Layer akzeptiert/ablehnt, von Laravel
     * genauso akzeptiert/abgelehnt wird.
     *
     * @return array<string, mixed>|null
     */
    private static function verifyJwt(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode(self::b64UrlDecode($headerB64), true);
        if (! is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $secret, true);
        $actualSig = self::b64UrlDecode($sigB64);
        if ($actualSig === '' || ! hash_equals($expectedSig, $actualSig)) {
            return null;
        }

        $payload = json_decode(self::b64UrlDecode($payloadB64), true);
        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['exp']) || ! is_numeric($payload['exp'])) {
            return null;
        }
        if (time() >= (int) $payload['exp']) {
            return null;
        }

        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && time() < (int) $payload['nbf']) {
            return null;
        }

        $expectedIssuer = self::envOrNull('IDENTITY_JWT_ISSUER');
        if ($expectedIssuer !== null) {
            if (! isset($payload['iss']) || ! is_string($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
                return null;
            }
        }
        $expectedAudience = self::envOrNull('IDENTITY_JWT_AUDIENCE');
        if ($expectedAudience !== null) {
            $aud = $payload['aud'] ?? null;
            $audMatches = (is_string($aud) && $aud === $expectedAudience)
                || (is_array($aud) && in_array($expectedAudience, $aud, true));
            if (! $audMatches) {
                return null;
            }
        }

        return $payload;
    }

    private static function bearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if ($header === '' || ! preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * Liefert ['sub','email','roles'=>[],'permissions'=>[]] fuer den
     * aufrufenden, per gueltigem Netlify-Identity-JWT authentifizierten
     * Benutzer, oder null (nicht angemeldet/Token ungueltig oder abgelaufen/
     * Secret nicht konfiguriert - fail-closed).
     *
     * @return array{sub: ?string, email: ?string, roles: list<string>, permissions: list<string>}|null
     */
    public static function currentUser(Request $request): ?array
    {
        $secret = self::secret();
        if ($secret === null) {
            return null;
        }

        $token = self::bearerToken($request);
        if ($token === null) {
            return null;
        }

        $payload = self::verifyJwt($token, $secret);
        if ($payload === null) {
            return null;
        }

        $appMeta = is_array($payload['app_metadata'] ?? null) ? $payload['app_metadata'] : [];
        $roles = is_array($appMeta['roles'] ?? null) ? array_values(array_filter($appMeta['roles'], 'is_string')) : [];
        $permissions = is_array($appMeta['permissions'] ?? null) ? array_values(array_filter($appMeta['permissions'], 'is_string')) : [];

        return [
            'sub' => is_string($payload['sub'] ?? null) ? $payload['sub'] : null,
            'email' => is_string($payload['email'] ?? null) ? $payload['email'] : null,
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
