<?php
/**
 * Serverseitige Pruefung von Netlify-Identity-Zugriffstoken (JWT) fuer die
 * Admin-Endpunkte der Hundeboerse/Waffenboerse.
 *
 * HINTERGRUND / WARUM DAS UEBERHAUPT NOETIG IST:
 * Das bestehende Admin-Panel (admin/admin.js) meldet Benutzer ueber das
 * Netlify-Identity-Widget an und schreibt Inhalte bisher ausschliesslich
 * ueber Netlifys eigene git-gateway-API. Diese prueft zwar, dass ueberhaupt
 * ein gueltiger Identity-Benutzer eingeloggt ist, aber NICHT, ob dieser
 * Benutzer die passende Berechtigung fuer ein bestimmtes Modul
 * (app_metadata.permissions, z.B. "hundeboerse"/"waffenboerse") hat - siehe
 * der Kommentar bei guardSavePermission() in admin/admin.js: "serverseitige
 * Pruefung [...] gibt es fuer Inhalte technisch [...] nicht". Das ist genau
 * die im Auftrag ausdrueckliche "keine Scheinsicherheit"-Anforderung, die
 * dieser neue PHP-Server auf einem dritten Host (kjs.mysolution-webservice.de)
 * NICHT einfach von Netlify erben kann (siehe netlify/functions/admin-users.js:
 * dort erledigt Netlifys eigene Infrastruktur die Verifikation automatisch
 * und liefert context.clientContext.user - das gibt es fuer einen
 * eigenstaendigen PHP-Server nicht).
 *
 * Diese Datei prueft das vom Browser mitgesendete Netlify-Identity-JWT
 * (Authorization: Bearer <token>) DESHALB selbststaendig nach, per HS256
 * (Netlifys Identity-Instanz signiert Zugriffstoken standardmaessig mit
 * einem einzigen, pro Site festen "JWT secret" - siehe Netlify-
 * Dashboard: Site configuration -> Identity -> Settings and usage ->
 * Abschnitt "JSON Web Tokens" -> "JWT secret" kopieren). Ohne dieses Secret
 * (Environment-Variable IDENTITY_JWT_SECRET, siehe unten) kann diese Datei
 * KEIN Token pruefen und verweigert dann - bewusst fail-closed, siehe
 * kjs_boerse_current_admin_user() - jeden Admin-Zugriff, statt ihn
 * ungeprueft durchzulassen.
 *
 * WICHTIGE EINSCHRAENKUNG (siehe Abschlussbericht Punkt "was Carsten noch
 * liefern muss" / "bekannte technische Grenzen"): dies deckt den
 * Standardfall von Netlify Identity ab (HS256, ein Shared Secret). Wurde
 * auf der Site zusaetzlich ein externer OAuth-Provider fuer Identity
 * konfiguriert, kann sich das Token-Format unterscheiden - das war anhand
 * des bestehenden Codes (netlify/functions/admin-users.js, admin/admin.js)
 * nicht zu erkennen und muesste im Zweifel nachgeprueft werden.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

if (!function_exists('kjs_boerse_jwt_b64url_decode')) {
    function kjs_boerse_jwt_b64url_decode(string $segment): string
    {
        $segment = strtr($segment, '-_', '+/');
        $pad = strlen($segment) % 4;
        if ($pad) {
            $segment .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($segment, true);
        return $decoded === false ? '' : $decoded;
    }
}

if (!function_exists('kjs_boerse_verify_identity_jwt')) {
    /**
     * Prueft Signatur + Zeitgueltigkeit eines HS256-JWT gegen das gegebene
     * Secret. Gibt bei Erfolg das dekodierte Payload-Array zurueck, sonst
     * null. Es wird bewusst NUR "HS256" akzeptiert (Netlify-Identity-
     * Standard) - jeder andere/fehlende "alg"-Header (z.B. "none", was ein
     * Angreifer versuchen koennte, um die Signaturpruefung zu umgehen) wird
     * abgelehnt.
     *
     * Security-Finalisierung, Punkt "JWT-Pruefung" ("keine Scheinsicherheit"):
     * - "exp" (Ablaufzeitpunkt) war bisher nur geprueft, WENN vorhanden -
     *   ein Token ganz ohne "exp"-Claim waere also unbegrenzt gueltig
     *   gewesen. Das ist jetzt ein Pflichtfeld: fehlt "exp" oder ist es kein
     *   gueltiger Zeitstempel, wird das Token abgelehnt.
     * - "nbf" ("not before") wurde bisher gar nicht geprueft. Netlify
     *   Identity setzt diesen Claim standardmaessig nicht, aber falls er
     *   doch vorkommt (z.B. durch eine kuenftige Netlify-Aenderung oder
     *   einen frei konfigurierten Provider), MUSS ein noch nicht gueltiges
     *   Token abgelehnt werden - alles andere waere eine bekannte,
     *   vermeidbare Luecke.
     * - "iss"/"aud" (Aussteller/Empfaenger) werden NUR geprueft, wenn Carsten
     *   ueber die Environment-Variablen IDENTITY_JWT_ISSUER bzw.
     *   IDENTITY_JWT_AUDIENCE einen erwarteten Wert konfiguriert. Es wird
     *   hier bewusst KEIN Wert geraten/hartkodiert (die genaue "iss"/"aud"-
     *   Struktur echter Netlify-Identity-Token dieser Site konnte ohne
     *   Zugriff auf die echte Produktionsumgebung nicht verifiziert werden -
     *   siehe Abschlussbericht). Ohne Konfiguration ist diese Pruefung ein
     *   reines No-Op, verhaelt sich also exakt wie vorher.
     */
    function kjs_boerse_verify_identity_jwt(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode(kjs_boerse_jwt_b64url_decode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') return null;

        $expectedSig = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);
        $actualSig = kjs_boerse_jwt_b64url_decode($sigB64);
        if ($actualSig === '' || !hash_equals($expectedSig, $actualSig)) return null;

        $payload = json_decode(kjs_boerse_jwt_b64url_decode($payloadB64), true);
        if (!is_array($payload)) return null;

        // "exp" ist jetzt Pflicht (fail-closed statt "nur wenn vorhanden").
        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }
        if (time() >= (int) $payload['exp']) {
            return null; // abgelaufen
        }

        // "nbf": nur pruefen, wenn vorhanden - Netlify Identity setzt diesen
        // Claim standardmaessig nicht, ein Token ohne "nbf" bleibt also wie
        // bisher gueltig. Ist er vorhanden, muss er bereits erreicht sein.
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && time() < (int) $payload['nbf']) {
            return null; // noch nicht gueltig
        }

        // "iss"/"aud": nur pruefen, wenn Carsten einen erwarteten Wert
        // konfiguriert hat (siehe Funktionskommentar) - sonst No-Op.
        $expectedIssuer = kjs_boerse_env('IDENTITY_JWT_ISSUER');
        if ($expectedIssuer !== null && $expectedIssuer !== '') {
            if (!isset($payload['iss']) || !is_string($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
                return null;
            }
        }
        $expectedAudience = kjs_boerse_env('IDENTITY_JWT_AUDIENCE');
        if ($expectedAudience !== null && $expectedAudience !== '') {
            $aud = $payload['aud'] ?? null;
            $audMatches = (is_string($aud) && $aud === $expectedAudience)
                || (is_array($aud) && in_array($expectedAudience, $aud, true));
            if (!$audMatches) {
                return null;
            }
        }

        return $payload;
    }
}

if (!function_exists('kjs_boerse_bearer_token')) {
    function kjs_boerse_bearer_token(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        }
        $header = trim((string) $header);
        if ($header === '' || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }
        return trim($m[1]);
    }
}

if (!function_exists('kjs_boerse_current_admin_user')) {
    /**
     * Liefert ['sub','email','roles'=>[],'permissions'=>[]] fuer den
     * aufrufenden, per gueltigem Netlify-Identity-JWT authentifizierten
     * Benutzer, oder null wenn nicht angemeldet/Token ungueltig/abgelaufen
     * ODER IDENTITY_JWT_SECRET serverseitig gar nicht konfiguriert ist
     * (fail-closed - siehe Datei-Kommentar oben).
     */
    function kjs_boerse_current_admin_user(): ?array
    {
        $secret = kjs_boerse_env('IDENTITY_JWT_SECRET');
        if ($secret === null) {
            $configPath = kjs_boerse_env('IDENTITY_CONFIG_PATH');
            if ($configPath === null) {
                $configPath = dirname(__DIR__, 2) . '/config/identity.local.php';
            }
            if (is_file($configPath)) {
                $fileConfig = require $configPath;
                if (is_array($fileConfig) && !empty($fileConfig['jwt_secret'])) {
                    $secret = (string) $fileConfig['jwt_secret'];
                }
            }
        }
        if ($secret === null || $secret === '') return null;

        $token = kjs_boerse_bearer_token();
        if ($token === null) return null;

        $payload = kjs_boerse_verify_identity_jwt($token, $secret);
        if ($payload === null) return null;

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
}

if (!function_exists('kjs_boerse_is_admin')) {
    function kjs_boerse_is_admin(array $user): bool
    {
        return in_array('admin', $user['roles'], true);
    }
}

if (!function_exists('kjs_boerse_has_permission')) {
    function kjs_boerse_has_permission(array $user, string $permissionKey): bool
    {
        return kjs_boerse_is_admin($user) || in_array($permissionKey, $user['permissions'], true);
    }
}

if (!function_exists('kjs_boerse_require_permission')) {
    /**
     * Beendet die Anfrage mit 401/403, wenn kein gueltig authentifizierter
     * Benutzer mit der gegebenen Modul-Berechtigung vorliegt - sonst
     * Rueckgabe des Benutzer-Arrays. $permissionKey entspricht exakt den
     * Schluesseln aus PERMISSIONS/PERM_BY_KEY in admin/admin.js
     * ("hundeboerse"/"waffenboerse").
     */
    function kjs_boerse_require_permission(string $permissionKey): array
    {
        $user = kjs_boerse_current_admin_user();
        if ($user === null) {
            kjs_boerse_json_response(401, [
                'success' => false,
                'error' => 'not_authenticated',
                'message' => 'Nicht angemeldet oder Sitzung abgelaufen.',
            ]);
        }
        if (!kjs_boerse_has_permission($user, $permissionKey)) {
            kjs_boerse_json_response(403, [
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Keine Berechtigung fuer dieses Modul.',
            ]);
        }
        return $user;
    }
}
