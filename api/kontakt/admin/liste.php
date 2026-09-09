<?php
/**
 * Admin-Endpunkt: liefert ALLE Kontaktanfragen (jeder Status).
 *
 * Teil von "Kontaktformular ausfallsicher machen" (09.09.2026) - Gegenstueck
 * zu api/hundeboerse/admin/liste.php bzw. api/waffenboerse/admin/liste.php,
 * gleiches Muster (siehe dort fuer die ausfuehrliche Begruendung von Auth/
 * Fehlerbehandlung). Neue, eigenstaendige Berechtigung "kontaktanfragen"
 * (siehe admin/admin.js PERMISSIONS + netlify/functions/admin-users.js
 * PERMISSIONS_BEKANNT) - bewusst NICHT an die bestehende Berechtigung
 * "kontakt" ("Kontakt & Stammdaten") gekoppelt, weil das dortige Recht die
 * OEFFENTLICHE Kontaktseite (Adresse/Oeffnungszeiten) steuert, waehrend
 * "kontaktanfragen" Zugriff auf die tatsaechlich eingegangenen, teils
 * personenbezogenen Nachrichten von Besuchern gibt - zwei fachlich klar
 * getrennte Bereiche, die ein Redakteur unabhaengig voneinander erhalten
 * koennen soll.
 *
 * Antwort enthaelt bewusst die volle Nachricht direkt in der Liste (wie bei
 * den Boersen-Modulen) - admin/admin.js blendet Details nur clientseitig
 * ein/aus, es gibt keinen zweiten Netzwerk-Roundtrip fuer die Detailansicht.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/identity_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed']);
}

kjs_boerse_require_permission('kontaktanfragen');

$pdo = kjs_boerse_require_db();

// Security ("Informationslecks"): auch auf diesem bereits authentifizierten
// Admin-Endpunkt duerfen unerwartete DB-Fehler nie als PHP-Stacktrace/SQL-
// Text im Browser landen - gleiches Muster wie bei den Boersen-Endpunkten.
try {
    $rows = $pdo->query(
        "SELECT id, name, email, telefon, anliegen, bereits_jaeger, hegering,
                nachricht, status, mail_versendet, mail_fehler,
                erstellt_am, bearbeitet_am
         FROM kontakt_anfragen
         ORDER BY erstellt_am DESC"
    )->fetchAll();

    $anfragen = [];
    foreach ($rows as $row) {
        $anfragen[] = [
            'id'            => (int) $row['id'],
            'name'          => $row['name'],
            'email'         => $row['email'],
            'telefon'       => $row['telefon'],
            'anliegen'      => $row['anliegen'],
            'bereitsJaeger' => $row['bereits_jaeger'],
            'hegering'      => $row['hegering'],
            // Wird im Admin ausschliesslich als Text dargestellt (siehe
            // admin/admin.js renderKontaktanfragen() - escHtml(), niemals
            // innerHTML mit rohem Wert) - hier bewusst unveraendert, damit
            // die Originalnachricht des Besuchers erhalten bleibt.
            'nachricht'     => $row['nachricht'] ?? '',
            'status'        => $row['status'],
            'mailVersendet' => (bool) $row['mail_versendet'],
            'mailFehler'    => $row['mail_fehler'],
            'erstelltAm'    => kjs_kontakt_admin_datetime_to_iso($row['erstellt_am']),
            'bearbeitetAm'  => kjs_kontakt_admin_datetime_to_iso($row['bearbeitet_am']),
        ];
    }
} catch (Throwable $e) {
    error_log('KJS Kontaktanfragen Admin: Laden der Liste fehlgeschlagen - ' . $e->getMessage());
    kjs_boerse_json_response(500, ['success' => false, 'error' => 'server_error', 'message' => 'Die Anfragen konnten nicht geladen werden. Bitte versuchen Sie es spaeter erneut.']);
}

kjs_boerse_json_response(200, [
    'success' => true,
    'data' => [
        'anfragen' => $anfragen,
    ],
]);

function kjs_kontakt_admin_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}
