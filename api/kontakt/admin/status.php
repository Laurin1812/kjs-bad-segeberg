<?php
/**
 * Admin-Endpunkt: setzt den Status einer einzelnen Kontaktanfrage
 * ("neu" <-> "bearbeitet"). Kein Loeschen vorgesehen (siehe Auftrag: "Keine
 * Loeschfunktion noetig").
 *
 * POST { id, status }  ->  { success: true, anfrage: {...} }
 *
 * "bearbeitet_am" wird bei JEDER expliziten Statusaenderung ueber diesen
 * Endpunkt auf den aktuellen Zeitpunkt gesetzt (auch beim Zuruecksetzen auf
 * "neu") - es ist damit "wann hat zuletzt ein Admin/Redakteur den Status
 * dieser Anfrage angefasst", nicht ausschliesslich ein einmaliger
 * "erledigt am"-Zeitstempel.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/identity_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kjs_boerse_json_response(405, ['success' => false, 'error' => 'method_not_allowed']);
}

kjs_boerse_require_permission('kontaktanfragen');

$pdo = kjs_boerse_require_db();

$allowedStatus = ['neu', 'bearbeitet'];

$body = kjs_boerse_read_json_body();
$id = isset($body['id']) ? (int) $body['id'] : 0;
$status = kjs_boerse_field($body, 'status', 20);

if ($id <= 0) {
    kjs_boerse_json_response(400, ['success' => false, 'error' => 'missing_id', 'message' => 'Keine Anfrage-ID angegeben.']);
}
if (!in_array($status, $allowedStatus, true)) {
    kjs_boerse_json_response(400, ['success' => false, 'error' => 'invalid_status', 'message' => 'Ungueltiger Status.']);
}

try {
    $stmt = $pdo->prepare('UPDATE kontakt_anfragen SET status = :status, bearbeitet_am = CURRENT_TIMESTAMP(3) WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    if ($stmt->rowCount() === 0) {
        // Entweder existiert die ID nicht, oder sie hatte bereits genau
        // diesen Status (dann aendert UPDATE nichts an den Werten und
        // rowCount() ist bei MySQL/PDO ebenfalls 0) - beides wird hier
        // gleich behandelt: einmal frisch nachladen und pruefen, ob die
        // Zeile ueberhaupt existiert, um einen falschen "not_found"-Fehler
        // bei einem no-op-Update zu vermeiden.
        $check = $pdo->prepare('SELECT id FROM kontakt_anfragen WHERE id = :id');
        $check->execute(['id' => $id]);
        if (!$check->fetch()) {
            kjs_boerse_json_response(404, ['success' => false, 'error' => 'not_found', 'message' => 'Anfrage nicht gefunden.']);
        }
    }

    $selectStmt = $pdo->prepare(
        'SELECT id, name, email, telefon, anliegen, bereits_jaeger, hegering,
                nachricht, status, mail_versendet, mail_fehler, erstellt_am, bearbeitet_am
         FROM kontakt_anfragen WHERE id = :id'
    );
    $selectStmt->execute(['id' => $id]);
    $row = $selectStmt->fetch();
} catch (Throwable $e) {
    error_log('KJS Kontaktanfragen Admin: Status-Update fehlgeschlagen - ' . $e->getMessage());
    kjs_boerse_json_response(500, ['success' => false, 'error' => 'server_error', 'message' => 'Der Status konnte nicht geaendert werden.']);
}

kjs_boerse_json_response(200, [
    'success' => true,
    'anfrage' => [
        'id'            => (int) $row['id'],
        'name'          => $row['name'],
        'email'         => $row['email'],
        'telefon'       => $row['telefon'],
        'anliegen'      => $row['anliegen'],
        'bereitsJaeger' => $row['bereits_jaeger'],
        'hegering'      => $row['hegering'],
        'nachricht'     => $row['nachricht'] ?? '',
        'status'        => $row['status'],
        'mailVersendet' => (bool) $row['mail_versendet'],
        'mailFehler'    => $row['mail_fehler'],
        'erstelltAm'    => kjs_kontakt_status_datetime_to_iso($row['erstellt_am']),
        'bearbeitetAm'  => kjs_kontakt_status_datetime_to_iso($row['bearbeitet_am']),
    ],
]);

function kjs_kontakt_status_datetime_to_iso(?string $mysqlDatetime): ?string
{
    if (!$mysqlDatetime) return null;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $mysqlDatetime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDatetime);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}
