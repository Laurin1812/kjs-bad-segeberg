<?php
/**
 * Kleine, gemeinsame Antwort-/Hilfsfunktionen fuer alle Boersen-Endpunkte
 * (Hundeboerse + Waffenboerse, oeffentlich + Admin). Bewusst als eigene
 * Datei, damit api/contact.php (nutzt sein eigenes, unabhaengiges
 * kjs_json_response()) unangetastet bleibt.
 */

declare(strict_types=1);

if (!function_exists('kjs_boerse_json_response')) {
    function kjs_boerse_json_response(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('kjs_boerse_env')) {
    function kjs_boerse_env(string $key): ?string
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
}

if (!function_exists('kjs_boerse_field')) {
    function kjs_boerse_field(array $input, string $key, int $maxLength, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        if (!is_string($value)) {
            if (is_scalar($value)) {
                $value = (string) $value;
            } else {
                return $default;
            }
        }
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }
        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('kjs_boerse_bool')) {
    function kjs_boerse_bool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) !== 0;
        if (is_string($value)) return in_array(strtolower(trim($value)), ['1', 'true', 'ja', 'yes', 'on'], true);
        return false;
    }
}

if (!function_exists('kjs_boerse_has_line_breaks')) {
    function kjs_boerse_has_line_breaks(string $value): bool
    {
        return (bool) preg_match('/[\r\n]/', $value);
    }
}

if (!function_exists('kjs_boerse_read_json_body')) {
    /** Liest den Request-Body als JSON-Array ein, oder [] wenn kein gueltiges JSON. */
    function kjs_boerse_read_json_body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('kjs_boerse_iso_date_to_de')) {
    /**
     * Wandelt ein Datum aus einem <input type="date"> (ISO "YYYY-MM-DD", wie
     * es die oeffentlichen Formulare liefern) in das im bestehenden
     * Admin-Datenmodell verwendete Format "DD.MM.YYYY" um (siehe
     * content/hundeboerse.json Beispieldaten und admin.js isoToDatum()).
     * Ist der Wert bereits "DD.MM.YYYY" oder leer/ungueltig, wird er
     * unveraendert (bzw. leer) zurueckgegeben - es wird nie geraten.
     */
    function kjs_boerse_iso_date_to_de(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return $m[3] . '.' . $m[2] . '.' . $m[1];
        }
        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $value)) {
            return $value;
        }
        return '';
    }
}
