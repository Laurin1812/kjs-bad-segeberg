<?php
/**
 * Serverseitige Pruefung der ANMELDUNG fuer die Admin-Endpunkte der
 * Hundeboerse/Waffenboerse/Kontaktanfragen.
 *
 * NETLIFY IDENTITY -> LARAVEL FORTIFY (Auftrag "Admin-Authentifizierung
 * vollstaendig auf Laravel umstellen"): dieser Datei-Kommentar ersetzt den
 * bisherigen (siehe Git-Historie) - das bestehende Admin-Panel
 * (admin/admin.js) meldet Benutzer nicht mehr ueber das Netlify-Identity-
 * Widget an, sondern ueber Laravel Fortify (klassische Session-
 * Authentifizierung, "web"-Guard, siehe laravel/config/fortify.php und
 * laravel/app/Support/AdminIdentity.php). Diese Datei hier ist bewusst
 * WEITERHIN ein eigenstaendiges PHP-Skript OHNE Laravel-Bootstrap (kein
 * "require vendor/autoload.php", kein Kernel-Handle) - die Hundeboerse-/
 * Waffenboerse-/Kontakt-Endpunkte selbst wurden NICHT nach Laravel migriert
 * (ausdruecklich nicht Teil dieses Auftrags: "keine funktionale
 * Neuentwicklung der Sondermodule") und bleiben unveraendert einfache
 * PHP-Dateien auf demselben Host.
 *
 * TECHNISCH SAUBERSTE LOESUNG FUER DIESEN FALL (vor der Umsetzung wie
 * gefordert analysiert - siehe Abschlussbericht Punkt 6 fuer die
 * ausfuehrliche Begruendung, warum die Alternativen verworfen wurden):
 * diese eigenstaendigen PHP-Skripte lesen die ECHTE, von Laravel selbst
 * verwaltete Sitzung DIREKT aus der gemeinsam genutzten MySQL-Datenbank
 * (Tabelle "sessions", von Laravels SESSION_DRIVER=database ohnehin schon
 * befuellt - siehe laravel/database/migrations/
 * 0001_01_01_000000_create_users_table.php). Es gibt dadurch weiterhin nur
 * EINE einzige Quelle der Wahrheit fuer "wer ist angemeldet" (Laravels
 * eigene Session), kein zweites, selbstgebautes Token-/Auth-System:
 *
 *   1. Das Sitzungs-Cookie des Browsers (Name aus SESSION_COOKIE in
 *      laravel/.env, siehe unten) wird mit genau demselben Verfahren
 *      entschluesselt, das Laravels eigene EncryptCookies-Middleware
 *      verwendet (AES-256-CBC + HMAC-SHA256 ueber APP_KEY, siehe
 *      Illuminate\Encryption\Encrypter, sowie der zusaetzliche
 *      "CookieValuePrefix" von Illuminate\Cookie\CookieValuePrefix) - das
 *      Ergebnis ist die reine Sitzungs-ID.
 *   2. Mit dieser ID wird die Zeile in der "sessions"-Tabelle gelesen.
 *      Laravels eigener DatabaseSessionHandler schreibt dort bei JEDER
 *      angemeldeten Anfrage automatisch die Spalte "user_id" (siehe
 *      Illuminate\Session\DatabaseSessionHandler::addUserInformation()) -
 *      diese Datei liest NUR diese eine Spalte, keine eigene
 *      Deserialisierung von PHP-Objekten aus dem restlichen "payload".
 *   3. "last_activity" wird gegen SESSION_LIFETIME geprueft (Laravel raeumt
 *      abgelaufene Sitzungen nur per Zufalls-Lotterie auf, nicht sofort -
 *      siehe Store::lottery -, ohne diese Pruefung wuerde ein abgelaufenes,
 *      aber noch nicht aufgeraeumtes Sitzungs-Cookie faelschlich als
 *      gueltig durchgehen).
 *   4. Der eigentliche Benutzer (E-Mail, roles, permissions) wird per
 *      "user_id" direkt aus der ebenfalls gemeinsam genutzten "users"-
 *      Tabelle geladen (dieselben Spalten wie
 *      laravel/app/Support/AdminIdentity.php).
 *
 * Fuer die aendernden Endpunkte (POST/PATCH: speichern.php, status.php)
 * kommt zusaetzlich eine CSRF-Pruefung dazu (siehe kjs_boerse_require_csrf()
 * unten) - notwendig, weil die Authentifizierung jetzt ueber ein
 * automatisch vom Browser mitgeschicktes Cookie laeuft (anders als vorher
 * beim Bearer-Token, das ein Angreifer nicht "einfach mitschicken" konnte).
 * Sie prueft denselben Mechanismus, den Laravels eigene
 * PreventRequestForgery-Middleware fuer die Laravel-Admin-API verwendet
 * (X-XSRF-TOKEN-Header, siehe admin/admin.js getToken()).
 *
 * KONFIGURATION: diese Datei liest AUSSCHLIESSLICH aus laravel/.env (per
 * einfachem, hier selbst implementiertem Zeilen-Parser - keine neue
 * Composer-Abhaengigkeit fuer zwei Werte):
 *   - APP_KEY        (Laravels Verschluesselungs-Schluessel, "base64:...")
 *   - SESSION_COOKIE (siehe laravel/.env.example - fest auf
 *                      "kjs_admin_session" gesetzt statt aus APP_NAME
 *                      abgeleitet, damit sich der Name nicht "unter der
 *                      Hand" aendert)
 *   - SESSION_LIFETIME (Minuten, Standard 120 wie Laravels eigener
 *                        Default)
 * Fehlt APP_KEY, verweigert diese Datei (fail-closed, wie zuvor bei einem
 * fehlenden IDENTITY_JWT_SECRET) jeden Zugriff mit 401, statt ungeprueft
 * durchzulassen.
 *
 * BEKANNTE EINSCHRAENKUNG (siehe Abschlussbericht "bekannte technische
 * Grenzen"): unterstuetzt nur EIN aktives APP_KEY (keine Schluessel-
 * Rotation ueber APP_PREVIOUS_KEYS) - identisch zur bisherigen
 * Einschraenkung bei genau einem IDENTITY_JWT_SECRET ohne Rotation.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

if (!function_exists('kjs_boerse_laravel_env_file')) {
    /** Pfad zu laravel/.env, ausgehend von diesem Verzeichnis (api/lib). */
    function kjs_boerse_laravel_env_file(): string
    {
        return dirname(__DIR__, 2) . '/laravel/.env';
    }
}

if (!function_exists('kjs_boerse_laravel_env')) {
    /**
     * Minimaler, bewusst simpler .env-Zeilen-Parser: liest NUR die eine
     * angefragte Variable aus laravel/.env (KEY=value, optionale
     * doppelte/einfache Anfuehrungszeichen um den Wert, "#"-Kommentarzeilen
     * und Leerzeilen werden uebersprungen). Kein Caching zwischen Requests
     * noetig - jeder PHP-Prozess dieser Endpunkte ist ohnehin kurzlebig
     * (klassisches PHP-FPM-Request-Modell, kein Dauerprozess).
     */
    function kjs_boerse_laravel_env(string $key): ?string
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $path = kjs_boerse_laravel_env_file();
        if (!is_file($path) || !is_readable($path)) {
            return $cache[$key] = null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $cache[$key] = null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $lineKey = trim(substr($line, 0, $eq));
            if ($lineKey !== $key) continue;

            $value = trim(substr($line, $eq + 1));
            $len = strlen($value);
            if ($len >= 2 && (
                ($value[0] === '"' && $value[$len - 1] === '"') ||
                ($value[0] === "'" && $value[$len - 1] === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            return $cache[$key] = ($value === '' ? null : $value);
        }

        return $cache[$key] = null;
    }
}

if (!function_exists('kjs_boerse_laravel_app_key_bytes')) {
    /** Rohe Schluesselbytes aus APP_KEY ("base64:..." oder Klartext). */
    function kjs_boerse_laravel_app_key_bytes(): ?string
    {
        $appKey = kjs_boerse_laravel_env('APP_KEY');
        if ($appKey === null || $appKey === '') return null;

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            return $decoded === false ? null : $decoded;
        }

        return $appKey;
    }
}

if (!function_exists('kjs_boerse_laravel_decrypt')) {
    /**
     * Entschluesselt einen von Laravel per Illuminate\Encryption\Encrypter
     * erzeugten Wert (Cookie-Inhalt) - 1:1 nachgebaut nach
     * Encrypter::decrypt() fuer den Standard-Cipher "aes-256-cbc"
     * (Laravels Standardeinstellung, unveraendert in dieser Anwendung):
     * base64(JSON{iv,value,mac,tag}), MAC = hash_hmac('sha256', iv.value,
     * key), AES-256-CBC-Entschluesselung erst NACH erfolgreicher MAC-
     * Pruefung (verhindert Padding-Oracle-artige Angriffe). Gibt den reinen
     * String zurueck (Laravel-Cookies werden ohne PHP-serialize()
     * verschluesselt, siehe Illuminate\Cookie\Middleware\EncryptCookies::
     * $serialize = false), oder null bei jedem Fehler (fail-closed).
     */
    function kjs_boerse_laravel_decrypt(string $payload, string $key): ?string
    {
        $json = json_decode(base64_decode($payload, true) ?: '', true);
        if (!is_array($json) || !isset($json['iv'], $json['value'], $json['mac'])) return null;

        $iv = base64_decode((string) $json['iv'], true);
        if ($iv === false || strlen($iv) !== openssl_cipher_iv_length('aes-256-cbc')) return null;

        $calculatedMac = hash_hmac('sha256', $json['iv'] . $json['value'], $key);
        if (!hash_equals($calculatedMac, (string) $json['mac'])) return null;

        $decrypted = openssl_decrypt((string) $json['value'], 'aes-256-cbc', $key, 0, $iv);

        return $decrypted === false ? null : $decrypted;
    }
}

if (!function_exists('kjs_boerse_strip_cookie_value_prefix')) {
    /**
     * Entfernt das von Laravel (seit Illuminate\Cookie\CookieValuePrefix)
     * jedem verschluesselten Cookie-Wert vorangestellte 41-Zeichen-Praefix
     * (40 Hex-Zeichen HMAC-SHA1 aus Cookie-Name+"v2"+Schluessel, gefolgt von
     * "|") - ein Schutz dagegen, dass ein fuer einen anderen Cookie-Namen
     * verschluesselter Wert hier wiederverwendet werden koennte. Gibt null
     * zurueck, wenn das Praefix nicht zum erwarteten Cookie-Namen passt.
     */
    function kjs_boerse_strip_cookie_value_prefix(string $decrypted, string $cookieName, string $key): ?string
    {
        $expectedPrefix = hash_hmac('sha1', $cookieName . 'v2', $key) . '|';
        if (!str_starts_with($decrypted, $expectedPrefix)) return null;

        return substr($decrypted, strlen($expectedPrefix));
    }
}

if (!function_exists('kjs_boerse_session_cookie_name')) {
    function kjs_boerse_session_cookie_name(): string
    {
        return kjs_boerse_laravel_env('SESSION_COOKIE') ?? 'kjs_admin_session';
    }
}

if (!function_exists('kjs_boerse_current_admin_user')) {
    /**
     * Liefert ['sub','email','roles'=>[],'permissions'=>[]] fuer den
     * aufrufenden, per gueltiger Laravel-Sitzung (Fortify-Login,
     * "web"-Guard) angemeldeten Benutzer, oder null (nicht angemeldet/
     * Sitzung ungueltig oder abgelaufen/APP_KEY nicht lesbar - fail-closed,
     * exakt wie zuvor bei einem fehlenden Netlify-Identity-Secret).
     *
     * @return array{sub: ?string, email: ?string, roles: list<string>, permissions: list<string>}|null
     */
    function kjs_boerse_current_admin_user(): ?array
    {
        $key = kjs_boerse_laravel_app_key_bytes();
        if ($key === null) return null;

        $cookieName = kjs_boerse_session_cookie_name();
        $rawCookie = $_COOKIE[$cookieName] ?? null;
        if (!is_string($rawCookie) || $rawCookie === '') return null;

        $decrypted = kjs_boerse_laravel_decrypt($rawCookie, $key);
        if ($decrypted === null) return null;

        $sessionId = kjs_boerse_strip_cookie_value_prefix($decrypted, $cookieName, $key);
        if ($sessionId === null || $sessionId === '') return null;

        $pdo = kjs_boerse_require_db();

        $lifetimeMinutes = (int) (kjs_boerse_laravel_env('SESSION_LIFETIME') ?? '120');
        if ($lifetimeMinutes <= 0) $lifetimeMinutes = 120;

        $stmt = $pdo->prepare('SELECT user_id, last_activity FROM sessions WHERE id = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if ($session === false) return null;

        $lastActivity = (int) ($session['last_activity'] ?? 0);
        if ($lastActivity <= 0 || $lastActivity < (time() - $lifetimeMinutes * 60)) {
            return null; // abgelaufen, wie Illuminate\Session\DatabaseSessionHandler::expired()
        }

        $userId = $session['user_id'] ?? null;
        if ($userId === null) return null; // Sitzung existiert, aber (noch) nicht angemeldet (Gast)

        $userStmt = $pdo->prepare('SELECT id, email, roles, permissions FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([(int) $userId]);
        $user = $userStmt->fetch();
        if ($user === false) return null;

        $roles = json_decode((string) ($user['roles'] ?? 'null'), true);
        $permissions = json_decode((string) ($user['permissions'] ?? 'null'), true);

        return [
            'sub' => (string) $user['id'],
            'email' => is_string($user['email'] ?? null) ? $user['email'] : null,
            'roles' => is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [],
            'permissions' => is_array($permissions) ? array_values(array_filter($permissions, 'is_string')) : [],
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
     * ("hundeboerse"/"waffenboerse"/"kontaktanfragen").
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

if (!function_exists('kjs_boerse_require_csrf')) {
    /**
     * CSRF-Schutz fuer die aendernden Sondermodul-Endpunkte (POST/PATCH:
     * speichern.php/status.php) - NUR fuer diese noetig geworden, weil die
     * Authentifizierung jetzt (Fortify-Session) ueber ein vom Browser
     * automatisch mitgeschicktes Cookie laeuft statt ueber einen Bearer-
     * Token, den ein Angreifer nicht "einfach mitschicken" kann. Prueft
     * denselben Header, den admin.js fuer die Laravel-Admin-API sendet
     * (X-XSRF-TOKEN, siehe dortige getToken()-Funktion) gegen den in der
     * Sitzung gespeicherten CSRF-Token ("_token" im Session-Payload,
     * genauso wie Laravels eigene PreventRequestForgery-Middleware das
     * fuer die Laravel-API pruefen wuerde) - MUSS nach
     * kjs_boerse_require_permission() aufgerufen werden (braucht eine
     * bereits validierte Sitzung).
     *
     * Beendet die Anfrage mit 419 (derselbe Statuscode, den Laravel selbst
     * fuer ein CSRF-Token-Mismatch verwendet), wenn die Pruefung fehlschlaegt.
     */
    function kjs_boerse_require_csrf(): void
    {
        $key = kjs_boerse_laravel_app_key_bytes();
        $cookieName = kjs_boerse_session_cookie_name();
        $rawCookie = $_COOKIE[$cookieName] ?? null;

        $header = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
        if ($key === null || !is_string($rawCookie) || $rawCookie === '' || $header === '') {
            kjs_boerse_json_response(419, ['success' => false, 'error' => 'csrf_mismatch', 'message' => 'Sitzung abgelaufen - bitte Seite neu laden.']);
        }

        $sessionDecrypted = kjs_boerse_laravel_decrypt($rawCookie, $key);
        $sessionId = $sessionDecrypted !== null
            ? kjs_boerse_strip_cookie_value_prefix($sessionDecrypted, $cookieName, $key)
            : null;

        $headerDecrypted = kjs_boerse_laravel_decrypt($header, $key);
        $headerToken = $headerDecrypted !== null
            ? kjs_boerse_strip_cookie_value_prefix($headerDecrypted, 'XSRF-TOKEN', $key)
            : null;

        if ($sessionId === null || $sessionId === '' || $headerToken === null || $headerToken === '') {
            kjs_boerse_json_response(419, ['success' => false, 'error' => 'csrf_mismatch', 'message' => 'Sitzung abgelaufen - bitte Seite neu laden.']);
        }

        $pdo = kjs_boerse_require_db();
        $stmt = $pdo->prepare('SELECT payload FROM sessions WHERE id = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        $payload = $row !== false ? json_decode((string) ($row['payload'] ?? ''), true) : null;
        $sessionToken = is_array($payload) && is_string($payload['_token'] ?? null) ? $payload['_token'] : null;

        if ($sessionToken === null || !hash_equals($sessionToken, $headerToken)) {
            kjs_boerse_json_response(419, ['success' => false, 'error' => 'csrf_mismatch', 'message' => 'Sitzung abgelaufen - bitte Seite neu laden.']);
        }
    }
}
