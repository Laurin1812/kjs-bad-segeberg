<?php
/**
 * Lädt die SMTP-/Mail-Konfiguration für das Kontaktformular.
 *
 * Priorität (siehe config/mail.example.php für die ausführliche Erklärung):
 *   1) Environment-Variablen SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
 *      SMTP_ENCRYPTION, SMTP_FROM, SMTP_FROM_NAME, CONTACT_RECIPIENT
 *   2) Eine Config-Datei, die ein Array im Format von
 *      config/mail.example.php zurückgibt - Pfad über die optionale
 *      Environment-Variable MAIL_CONFIG_PATH, sonst Standardpfad
 *      config/mail.local.php (relativ zum Projekt-Root). Diese Datei
 *      wird NIE mit Git committet (siehe .gitignore).
 *
 * Gibt bei vollständiger, gültiger Konfiguration ein Array mit allen
 * Schlüsseln zurück, sonst null (der Aufrufer antwortet dann mit einem
 * klaren 500-Fehler statt eine kaputte/leere Mail zu verschicken).
 */

function kjs_env(string $key): ?string
{
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    if ($value === false || $value === '') {
        return null;
    }
    return $value;
}

function kjs_load_mail_config(): ?array
{
    // smtp_encryption ist bewusst NICHT in $requiredKeys: ein leerer Wert
    // ("keine Verschlüsselung") ist gültig und darf die Konfiguration nicht
    // als "unvollständig" gelten lassen - er wird unten separat behandelt
    // und defaultet auf 'tls', falls gar nichts angegeben ist.
    $requiredKeys = [
        'smtp_host'          => 'SMTP_HOST',
        'smtp_port'          => 'SMTP_PORT',
        'smtp_user'          => 'SMTP_USER',
        'smtp_pass'          => 'SMTP_PASS',
        'smtp_from'          => 'SMTP_FROM',
        'smtp_from_name'     => 'SMTP_FROM_NAME',
        'contact_recipient'  => 'CONTACT_RECIPIENT',
    ];

    // 1) Environment-Variablen versuchen
    $config = [];
    $complete = true;
    foreach ($requiredKeys as $configKey => $envKey) {
        $value = kjs_env($envKey);
        if ($value === null) {
            $complete = false;
            break;
        }
        $config[$configKey] = $value;
    }
    if ($complete) {
        // Eigener, toleranterer Zugriff statt kjs_env(): '' ist hier ein
        // gültiger, expliziter Wert ("keine Verschlüsselung") und kein
        // "nicht gesetzt".
        $rawEncryption = getenv('SMTP_ENCRYPTION');
        if ($rawEncryption === false && isset($_ENV['SMTP_ENCRYPTION'])) {
            $rawEncryption = $_ENV['SMTP_ENCRYPTION'];
        }
        $config['smtp_encryption'] = $rawEncryption === false ? 'tls' : $rawEncryption;
    }

    // 2) Falls unvollständig: Config-Datei versuchen
    if (!$complete) {
        $configPath = kjs_env('MAIL_CONFIG_PATH');
        if ($configPath === null) {
            $configPath = dirname(__DIR__, 2) . '/config/mail.local.php';
        }
        if (is_file($configPath)) {
            $fileConfig = require $configPath;
            if (is_array($fileConfig)) {
                $config = array_merge(array_fill_keys(array_keys($requiredKeys), null), $fileConfig);
                if (!array_key_exists('smtp_encryption', $config) || $config['smtp_encryption'] === null) {
                    $config['smtp_encryption'] = 'tls';
                }
                $complete = true;
                foreach (array_keys($requiredKeys) as $configKey) {
                    if (empty($config[$configKey]) && $config[$configKey] !== '0') {
                        $complete = false;
                        break;
                    }
                }
            }
        }
    }

    if (!$complete) {
        return null;
    }

    $config['smtp_port'] = (int) $config['smtp_port'];
    $config['smtp_encryption'] = strtolower(trim((string) $config['smtp_encryption']));

    if (!filter_var($config['smtp_from'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if (!filter_var($config['contact_recipient'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $config;
}
