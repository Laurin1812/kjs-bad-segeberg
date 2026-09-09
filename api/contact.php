<?php
/**
 * KJS Kontaktformular - serverseitiger Speicher- und Versand-Endpunkt.
 *
 * Ersetzt den bisherigen Weg über formsubmit.co: Das öffentliche Formular
 * (kontakt/index.html, Logik in js/main.js) sendet seine Daten jetzt per
 * POST/JSON an diesen Endpunkt. Dieser validiert serverseitig, wendet
 * Honeypot- und Rate-Limit-Schutz an.
 *
 * "Kontaktformular ausfallsicher machen" (09.09.2026): bisher hing der
 * Erfolg der Anfrage komplett vom SMTP-Versand ab - ohne (oder mit
 * ausfallendem) SMTP ging eine Anfrage komplett verloren, ohne dass
 * irgendwo eine Spur davon blieb. Die Reihenfolge ist jetzt bewusst
 * umgedreht:
 *
 *   Kontaktformular -> IMMER zuerst dauerhaft in MySQL speichern
 *                    -> Erfolg an den Besucher
 *                    -> DANACH zusätzlich per PHPMailer/SMTP versuchen
 *
 * Die Speicherung (Tabelle kontakt_anfragen, siehe database/schema.sql) ist
 * die primäre Absicherung; der Mailversand ist nur noch eine zusätzliche,
 * vom Speichererfolg unabhängige Benachrichtigung. Schlägt er fehl oder ist
 * SMTP gar nicht konfiguriert, wird das an der bereits gespeicherten Zeile
 * vermerkt (mail_versendet/mail_fehler) - der Besucher bekommt trotzdem
 * "erfolgreich übermittelt", weil das stimmt: die Anfrage liegt sicher im
 * neuen Admin-Bereich "Kontaktanfragen" (admin/admin.js) vor. Nur wenn die
 * Datenbank selbst nicht erreichbar/konfiguriert ist (also NICHTS sicher
 * gespeichert werden kann), wird ehrlich ein Fehler gemeldet statt ein
 * falscher Erfolg vorgetäuscht - siehe kjs_contact_require_db() unten.
 *
 * WICHTIG - Deploy-Ziel: Dieser Endpunkt braucht eine PHP-Laufzeitumgebung
 * und funktioniert NICHT auf der Netlify-Staging-Umgebung (Netlify führt
 * kein PHP aus - siehe _redirects/netlify.toml, dort wird /api/* bewusst
 * blockiert). Der echte Einsatz- und Speicher-/Versandtest erfolgt auf
 * https://kjs.mysolution-webservice.de - siehe Abschlussbericht für die
 * Details, was dafür noch von Carsten geliefert werden muss (MySQL- und
 * SMTP-Zugangsdaten in config/db.local.php bzw. config/mail.local.php oder
 * als Environment-Variablen).
 *
 * Sicherheitsprinzipien in diesem Endpunkt:
 *  - Nur POST wird akzeptiert (GET/PUT/etc. -> 405).
 *  - Empfänger kommt ausschließlich aus der Server-Konfiguration, nie aus
 *    der Anfrage.
 *  - Alle Freitextfelder werden auf Länge begrenzt und auf Zeilenumbrüche
 *    geprüft (Schutz vor Header-Injection in From/Reply-To/Subject).
 *  - "Anliegen" wird gegen eine feste, aus dem echten Formular
 *    abgeleitete Liste geprüft - keine beliebigen Werte.
 *  - Versteckte Honeypot-Feld "_honey": nicht-leer => stiller Erfolg,
 *    ohne dass tatsächlich etwas gespeichert oder verschickt wird.
 *  - Einfacher dateibasierter Rate-Limit-Schutz pro IP (siehe
 *    api/lib/rate_limit.php).
 *  - Speicherung ausschließlich über PDO Prepared Statements (siehe
 *    api/lib/db.php) - keine zusammengebauten SQL-Strings.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function kjs_json_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Nur POST erlauben ----------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kjs_json_response(405, [
        'success' => false,
        'error'   => 'method_not_allowed',
        'message' => 'Nur POST-Anfragen sind erlaubt.',
    ]);
}

// --- Anfrage einlesen (JSON-Body bevorzugt, klassisches Formular als Fallback) ---
$raw = file_get_contents('php://input');
$input = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

function kjs_field(array $input, string $key, int $maxLength): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}

function kjs_has_line_breaks(string $value): bool
{
    return (bool) preg_match('/[\r\n]/', $value);
}

// --- Honeypot --------------------------------------------------------------
// Verstecktes Feld "_honey" (im Formular per CSS unsichtbar, für Menschen
// nicht ausfüllbar). Ist es trotzdem befüllt, war es vermutlich ein Bot -
// wir tun so, als wäre alles gutgegangen, verschicken aber nichts.
$honey = kjs_field($input, '_honey', 200);
if ($honey !== '') {
    kjs_json_response(200, ['success' => true]);
}

// --- Rate-Limit pro IP -------------------------------------------------
require __DIR__ . '/lib/rate_limit.php';
if (!kjs_rate_limit_check(kjs_client_ip())) {
    kjs_json_response(429, [
        'success' => false,
        'error'   => 'rate_limited',
        'message' => 'Zu viele Anfragen. Bitte versuchen Sie es in ein paar Minuten erneut.',
    ]);
}

// --- Felder einlesen (mit Längenbegrenzung) ---------------------------
$maxShort = 190;
$maxLong  = 5000;

$vorname       = kjs_field($input, 'vorname', $maxShort);
$nachname      = kjs_field($input, 'nachname', $maxShort);
$email         = kjs_field($input, 'email', $maxShort);
$telefon       = kjs_field($input, 'telefon', $maxShort);
$bereitsJaeger = kjs_field($input, 'bereits_jaeger', 20);
$hegering      = kjs_field($input, 'hegering', $maxShort);
$betreff       = kjs_field($input, 'betreff', $maxShort);
$nachricht     = kjs_field($input, 'nachricht', $maxLong);

// --- Validierung -----------------------------------------------------------

// Gültige Anliegen 1:1 aus dem <select id="betreff"> in kontakt/index.html
// übernommen - "Infomobil" ist enthalten (siehe dortige <option>-Liste).
$allowedBetreff = [
    'Allgemeine Anfrage',
    'Mitgliedschaft',
    'Jägerausbildung (Jagdschein)',
    'Schießwesen',
    'Hundeausbildung',
    'Jagdhornblasen',
    'Naturschutz',
    'Jungwildrettung',
    'Infomobil',
    'Pressenanfrage',
    'Sonstiges',
];

$errors = [];

if ($vorname === '' || $nachname === '') {
    $errors[] = 'name';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || kjs_has_line_breaks($email)) {
    $errors[] = 'email';
}
if (!in_array($betreff, $allowedBetreff, true)) {
    $errors[] = 'betreff';
}
if ($nachricht === '') {
    $errors[] = 'nachricht';
}

// Header-Injection-Schutz: keines der Felder, die später in einen
// Mail-Header einfließen (Name -> Reply-To-Anzeigename, Betreff -> Subject),
// darf Zeilenumbrüche enthalten. E-Mail wurde oben bereits geprüft.
foreach (['vorname' => $vorname, 'nachname' => $nachname, 'betreff' => $betreff] as $key => $value) {
    if (kjs_has_line_breaks($value) && !in_array($key, $errors, true)) {
        $errors[] = $key;
    }
}

if (!empty($errors)) {
    kjs_json_response(422, [
        'success' => false,
        'error'   => 'validation_failed',
        'fields'  => array_values(array_unique($errors)),
        'message' => 'Bitte prüfen Sie Ihre Angaben.',
    ]);
}

// --- Datenbank: primäre, ausfallsichere Speicherung ---------------------
// Wiederverwendung der bereits für Hundebörse/Waffenbörse aufgebauten
// PDO-Verbindung (api/lib/db.php) - dieselbe Datenbank, keine zweite
// Architektur. kjs_boerse_db() liefert bewusst null statt selbst zu
// antworten (anders als kjs_boerse_require_db()), damit dieser Endpunkt bei
// fehlender Konfiguration seine EIGENE, für Besucher verständliche Meldung
// ausgeben kann statt der boersen-spezifischen.
require __DIR__ . '/lib/response.php';
require __DIR__ . '/lib/db.php';

function kjs_contact_require_db(): PDO
{
    $pdo = kjs_boerse_db();
    if ($pdo === null) {
        // Punkt 5 des Auftrags ("Falls Datenbank noch nicht verbunden"):
        // ohne DB kann eine Anfrage hier NICHT sicher aufbewahrt werden -
        // dann lieber ehrlich einen Fehler zeigen, statt einen Erfolg
        // vorzutäuschen, der die Anfrage in Wirklichkeit verwirft.
        error_log('KJS Kontaktformular: keine Datenbankverbindung konfiguriert - Anfrage konnte nicht gespeichert werden.');
        kjs_json_response(503, [
            'success' => false,
            'error'   => 'server_not_configured',
            'message' => 'Der Server ist gerade nicht erreichbar. Bitte versuchen Sie es später erneut oder schreiben Sie uns direkt eine E-Mail.',
        ]);
    }
    return $pdo;
}

$pdo = kjs_contact_require_db();
$vollerName = trim($vorname . ' ' . $nachname);

try {
    $insertStmt = $pdo->prepare(
        'INSERT INTO kontakt_anfragen
            (name, email, telefon, anliegen, bereits_jaeger, hegering, nachricht, status)
         VALUES
            (:name, :email, :telefon, :anliegen, :bereits_jaeger, :hegering, :nachricht, \'neu\')'
    );
    $insertStmt->execute([
        'name'           => $vollerName,
        'email'          => $email,
        'telefon'        => $telefon,
        'anliegen'       => $betreff,
        'bereits_jaeger' => $bereitsJaeger,
        'hegering'       => $hegering,
        'nachricht'      => $nachricht,
    ]);
    $anfrageId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    // Auch hier: nicht als Erfolg ausgeben, wenn die Speicherung selbst
    // fehlgeschlagen ist (z.B. Tabelle fehlt noch, weil database/schema.sql
    // auf dem echten Server noch nicht eingespielt wurde) - siehe
    // Abschlussbericht.
    error_log('KJS Kontaktformular: Speichern der Anfrage fehlgeschlagen - ' . $e->getMessage());
    kjs_json_response(500, [
        'success' => false,
        'error'   => 'internal_error',
        'message' => 'Ihre Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut.',
    ]);
}

// Ab hier ist die Anfrage sicher in der Datenbank - alles Folgende ist nur
// noch eine zusätzliche Benachrichtigung. Kein Fehler ab hier darf dem
// Besucher noch als Fehlschlag der gesamten Anfrage angezeigt werden.
kjs_contact_mail_versuchen($pdo, $anfrageId, $vorname, $nachname, $vollerName, $email, $telefon, $bereitsJaeger, $hegering, $betreff, $nachricht);

kjs_json_response(200, ['success' => true]);

/**
 * Versucht die Benachrichtigungs-Mail zu versenden und vermerkt das
 * Ergebnis an der bereits gespeicherten Zeile (mail_versendet/mail_fehler).
 * Wirft absichtlich NIE nach außen - jeder Fehlerfall wird hier abgefangen,
 * geloggt und in der Datenbank vermerkt, damit die HTTP-Antwort an den
 * Besucher (siehe oben) in jedem Fall "success" bleibt.
 */
function kjs_contact_mail_versuchen(
    PDO $pdo,
    int $anfrageId,
    string $vorname,
    string $nachname,
    string $vollerName,
    string $email,
    string $telefon,
    string $bereitsJaeger,
    string $hegering,
    string $betreff,
    string $nachricht
): void {
    require __DIR__ . '/lib/mail_config.php';
    $config = kjs_load_mail_config();
    if ($config === null) {
        kjs_contact_mark_mail_status($pdo, $anfrageId, false, 'server_not_configured');
        return;
    }

    require __DIR__ . '/../vendor/autoload.php';

    $zeilen = [];
    $zeilen[] = 'Neue Nachricht über das Kontaktformular der KJS Segeberg';
    $zeilen[] = '';
    $zeilen[] = 'Name:      ' . $vollerName;
    $zeilen[] = 'E-Mail:    ' . $email;
    if ($telefon !== '') {
        $zeilen[] = 'Telefon:   ' . $telefon;
    }
    if ($bereitsJaeger !== '') {
        $zeilen[] = 'Jäger/in:  ' . $bereitsJaeger;
    }
    if ($hegering !== '') {
        $zeilen[] = 'Hegering:  ' . $hegering;
    }
    $zeilen[] = 'Anliegen:  ' . $betreff;
    $zeilen[] = 'Datum:     ' . date('d.m.Y, H:i \U\h\r');
    $zeilen[] = '';
    $zeilen[] = 'Nachricht:';
    $zeilen[] = $nachricht;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->Port       = $config['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->CharSet    = 'UTF-8';

        // Werte exakt wie in config/mail.example.php dokumentiert: 'tls' =
        // STARTTLS, 'ssl' = implizites TLS, '' = ausdrücklich keine
        // Verschlüsselung (nur für internes Testing gedacht). Fehlt die
        // Environment-Variable SMTP_ENCRYPTION komplett, setzt bereits
        // kjs_load_mail_config() sicherheitshalber 'tls' als Standard, bevor
        // wir hier ankommen - ein bewusst leerer Wert wird dagegen respektiert.
        if ($config['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
        }

        // From ist ausschließlich die serverseitig konfigurierte, auf dem
        // SMTP-Server erlaubte KJS-Absenderadresse - niemals die Adresse des
        // Formular-Absenders (die landet stattdessen in Reply-To).
        $mail->setFrom($config['smtp_from'], $config['smtp_from_name']);
        $mail->addAddress($config['contact_recipient']);
        $mail->addReplyTo($email, $vollerName !== '' ? $vollerName : $email);

        $mail->Subject = 'Neue Kontaktanfrage – ' . $betreff;
        $mail->isHTML(false);
        $mail->Body = implode("\n", $zeilen);

        $mail->send();
        kjs_contact_mark_mail_status($pdo, $anfrageId, true, null);
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('KJS Kontaktformular: Mailversand fehlgeschlagen (Anfrage #' . $anfrageId . ') - ' . $mail->ErrorInfo);
        kjs_contact_mark_mail_status($pdo, $anfrageId, false, 'send_failed');
    } catch (Throwable $e) {
        // Verteidigung in der Tiefe: irgendein anderer, unerwarteter Fehler
        // beim Mailversand darf trotzdem nie die bereits erfolgreiche
        // Speicherung/Antwort gefährden.
        error_log('KJS Kontaktformular: unerwarteter Fehler beim Mailversand (Anfrage #' . $anfrageId . ') - ' . $e->getMessage());
        kjs_contact_mark_mail_status($pdo, $anfrageId, false, 'send_failed');
    }
}

function kjs_contact_mark_mail_status(PDO $pdo, int $anfrageId, bool $versendet, ?string $fehler): void
{
    try {
        $stmt = $pdo->prepare('UPDATE kontakt_anfragen SET mail_versendet = :versendet, mail_fehler = :fehler WHERE id = :id');
        $stmt->execute([
            'versendet' => $versendet ? 1 : 0,
            'fehler'    => $fehler,
            'id'        => $anfrageId,
        ]);
    } catch (Throwable $e) {
        // Die Anfrage selbst ist bereits sicher gespeichert - ein Fehler
        // beim Nachtragen des reinen Mail-Status darf das nicht rückgängig
        // machen oder dem Besucher angezeigt werden, nur geloggt.
        error_log('KJS Kontaktformular: Mail-Status konnte nicht vermerkt werden (Anfrage #' . $anfrageId . ') - ' . $e->getMessage());
    }
}
