<?php
/**
 * KJS Kontaktformular - serverseitiger Versand-Endpunkt.
 *
 * Ersetzt den bisherigen Weg über formsubmit.co: Das öffentliche Formular
 * (kontakt/index.html, Logik in js/main.js) sendet seine Daten jetzt per
 * POST/JSON an diesen Endpunkt. Dieser validiert serverseitig, wendet
 * Honeypot- und Rate-Limit-Schutz an und verschickt die Nachricht per
 * PHPMailer über SMTP an die feste, serverseitig konfigurierte
 * KJS-Empfängeradresse (niemals eine vom Browser übergebene Adresse).
 *
 * WICHTIG - Deploy-Ziel: Dieser Endpunkt braucht eine PHP-Laufzeitumgebung
 * und funktioniert NICHT auf der Netlify-Staging-Umgebung (Netlify führt
 * kein PHP aus - siehe _redirects/netlify.toml, dort wird /api/* bewusst
 * blockiert). Der echte Einsatz- und Versandtest erfolgt auf
 * https://kjs.mysolution-webservice.de - siehe Abschlussbericht für die
 * Details, was dafür noch von Carsten geliefert werden muss (SMTP-
 * Zugangsdaten in config/mail.local.php oder als Environment-Variablen).
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
 *    ohne dass tatsächlich etwas verschickt wird.
 *  - Einfacher dateibasierter Rate-Limit-Schutz pro IP (siehe
 *    api/lib/rate_limit.php).
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

// --- Konfiguration laden ----------------------------------------------
require __DIR__ . '/lib/mail_config.php';
$config = kjs_load_mail_config();
if ($config === null) {
    // Bewusst kein interner Serverfehler ohne Kontext - klare Ansage, dass
    // die SMTP-Konfiguration fehlt/unvollständig ist (siehe
    // config/mail.example.php und Abschlussbericht).
    kjs_json_response(500, [
        'success' => false,
        'error'   => 'server_not_configured',
        'message' => 'Der Mailversand ist auf diesem Server noch nicht konfiguriert.',
    ]);
}

// --- Mail zusammenbauen und versenden (PHPMailer) ----------------------
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$vollerName = trim($vorname . ' ' . $nachname);

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

$mail = new PHPMailer(true);
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
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($config['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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
} catch (PHPMailerException $e) {
    error_log('KJS Kontaktformular: Mailversand fehlgeschlagen - ' . $mail->ErrorInfo);
    kjs_json_response(502, [
        'success' => false,
        'error'   => 'send_failed',
        'message' => 'Die Nachricht konnte nicht versendet werden. Bitte versuchen Sie es später erneut.',
    ]);
}

kjs_json_response(200, ['success' => true]);
