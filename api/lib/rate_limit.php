<?php
/**
 * Einfacher, dateibasierter Flood-/Rate-Limit-Schutz pro IP-Adresse.
 *
 * Bewusst ohne Datenbank umgesetzt, damit der Endpunkt auf praktisch jedem
 * PHP-Hosting ohne weitere Einrichtung funktioniert (nutzt nur das ohnehin
 * beschreibbare System-Temp-Verzeichnis). Kein Ersatz für einen
 * dediziertes Schutzsystem, aber ausreichend, um simple Spam-/Flood-
 * Skripte zu bremsen. Die IP wird nur gehasht und nur für die Dauer des
 * Zeitfensters gespeichert - es werden keine Klartext-IPs abgelegt.
 *
 * Limits (bewusst konservativ, damit ein normaler Nutzer nie ausgebremst
 * wird): maximal 5 Anfragen pro 15 Minuten je IP, zusätzlich mindestens
 * 5 Sekunden Abstand zwischen zwei Anfragen derselben IP (bremst script-
 * gesteuerte Doppel-/Mehrfachsendungen ab).
 *
 * $bucket trennt die Zaehler verschiedener Formulare voneinander (z.B.
 * "contact", "hb_submit", "wb_submit") - ohne das wuerde z.B. eine
 * Kontaktanfrage direkt gefolgt von einer Hundeboerse-Einreichung von
 * derselben IP faelschlich als "zu schnell hintereinander" desselben
 * Formulars gewertet. Der Parameter ist optional (Default "default") und
 * bricht damit den bestehenden Aufruf aus api/contact.php nicht.
 */

function kjs_rate_limit_check(string $ip, string $bucket = 'default'): bool
{
    $maxRequests   = 5;
    $windowSeconds = 15 * 60;
    $minGapSeconds = 5;

    $safeBucket = preg_replace('/[^a-z0-9_-]/', '', strtolower($bucket));
    if ($safeBucket === '') $safeBucket = 'default';

    $dir = sys_get_temp_dir() . '/kjs_contact_ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        // Kein beschreibbares Temp-Verzeichnis verfügbar - lieber
        // durchlassen als das Kontaktformular komplett zu blockieren.
        return true;
    }

    $file = $dir . '/' . $safeBucket . '-' . hash('sha256', $ip) . '.json';
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }

    $allowed = true;
    if (flock($fh, LOCK_EX)) {
        $now = time();
        $raw = stream_get_contents($fh);
        $timestamps = [];
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $timestamps = $decoded;
            }
        }

        // Alte Einträge außerhalb des Zeitfensters verwerfen
        $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
            return is_numeric($t) && ($now - (int) $t) < $windowSeconds;
        }));

        $lastTimestamp = empty($timestamps) ? null : max($timestamps);

        if (count($timestamps) >= $maxRequests) {
            $allowed = false;
        } elseif ($lastTimestamp !== null && ($now - $lastTimestamp) < $minGapSeconds) {
            $allowed = false;
        }

        if ($allowed) {
            $timestamps[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($timestamps));
            fflush($fh);
        }

        flock($fh, LOCK_UN);
    }
    fclose($fh);

    return $allowed;
}

function kjs_client_ip(): string
{
    // Bewusst nur REMOTE_ADDR - Header wie X-Forwarded-For lassen sich vom
    // Client frei fälschen und dürfen nur vertraut werden, wenn bekannt ist,
    // dass ausschließlich ein vertrauenswürdiger Reverse-Proxy davorsteht.
    // Läuft der Kontaktformular-Endpunkt später hinter einem solchen Proxy,
    // muss das hier gezielt angepasst werden.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
