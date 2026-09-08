<?php
/**
 * PDO-Verbindung fuer die Hundeboerse-/Waffenboerse-Endpunkte.
 *
 * Konfiguration ausschliesslich ueber Environment-Variablen
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS), mit optionalem
 * Datei-Fallback (siehe kjs_boerse_load_db_config()) fuer Hosting-Umgebungen
 * ohne Environment-Variablen-Unterstuetzung - exakt dasselbe Prinzip wie
 * bereits bei api/lib/mail_config.php fuer das Kontaktformular etabliert.
 *
 * Es werden NIRGENDS echte Zugangsdaten in Git committet - siehe
 * config/db.example.php (Vorlage) und .gitignore (config/db.local.php ist
 * ausgeschlossen).
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

if (!function_exists('kjs_boerse_load_db_config')) {
    function kjs_boerse_load_db_config(): ?array
    {
        $requiredKeys = [
            'host' => 'DB_HOST',
            'port' => 'DB_PORT',
            'name' => 'DB_NAME',
            'user' => 'DB_USER',
            'pass' => 'DB_PASS',
        ];

        $config = [];
        $complete = true;
        foreach ($requiredKeys as $configKey => $envKey) {
            $value = kjs_boerse_env($envKey);
            // DB_PASS darf technisch leer sein (z.B. lokale Testumgebung ohne
            // Passwort) - dafuer reicht getenv() direkt statt kjs_boerse_env(),
            // das eine leere Umgebungsvariable wie "nicht gesetzt" behandelt.
            if ($configKey === 'pass') {
                $raw = getenv('DB_PASS');
                if ($raw === false && isset($_ENV['DB_PASS'])) $raw = $_ENV['DB_PASS'];
                $config['pass'] = ($raw === false) ? null : $raw;
                continue;
            }
            if ($value === null) {
                $complete = false;
                break;
            }
            $config[$configKey] = $value;
        }

        if (!$complete || !array_key_exists('pass', $config)) {
            $configPath = kjs_boerse_env('DB_CONFIG_PATH');
            if ($configPath === null) {
                $configPath = dirname(__DIR__, 2) . '/config/db.local.php';
            }
            if (is_file($configPath)) {
                $fileConfig = require $configPath;
                if (is_array($fileConfig)) {
                    $config = array_merge(['host' => null, 'port' => null, 'name' => null, 'user' => null, 'pass' => null], $fileConfig);
                    $complete = ($config['host'] && $config['port'] && $config['name'] && $config['user'] && $config['pass'] !== null);
                }
            }
        }

        if (!$complete) return null;

        return [
            'host' => (string) $config['host'],
            'port' => (int) $config['port'],
            'name' => (string) $config['name'],
            'user' => (string) $config['user'],
            'pass' => (string) $config['pass'],
        ];
    }
}

if (!function_exists('kjs_boerse_db')) {
    /**
     * Liefert eine gecachte PDO-Instanz, oder null wenn die DB nicht
     * konfiguriert ist bzw. die Verbindung fehlschlaegt (Aufrufer antwortet
     * dann mit einem klaren 503/"server_not_configured", siehe die
     * einzelnen Endpunkte) - wirft absichtlich KEINE Exception nach aussen,
     * damit jeder Endpunkt selbst entscheidet, wie er das dem Client
     * meldet.
     */
    function kjs_boerse_db(): ?PDO
    {
        static $pdo = null;
        static $attempted = false;
        if ($pdo !== null) return $pdo;
        if ($attempted) return null;
        $attempted = true;

        $config = kjs_boerse_load_db_config();
        if ($config === null) return null;

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['name']
            );
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log('KJS Boersen: DB-Verbindung fehlgeschlagen - ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('kjs_boerse_require_db')) {
    function kjs_boerse_require_db(): PDO
    {
        $pdo = kjs_boerse_db();
        if ($pdo === null) {
            kjs_boerse_json_response(503, [
                'success' => false,
                'ok' => false,
                'error' => 'server_not_configured',
                'message' => 'Die Datenbank ist auf diesem Server noch nicht konfiguriert.',
            ]);
        }
        return $pdo;
    }
}
