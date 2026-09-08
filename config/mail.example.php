<?php
/**
 * BEISPIEL-KONFIGURATION FÜR DAS KJS-KONTAKTFORMULAR - NUR EINE VORLAGE.
 * Diese Datei enthält bewusst keine echten Zugangsdaten und wird von
 * api/contact.php nicht eingelesen.
 *
 * api/lib/mail_config.php prüft die Konfiguration in dieser Reihenfolge:
 *
 *   1) Environment-Variablen (empfohlen, falls vom Hosting unterstützt):
 *        SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION,
 *        SMTP_FROM, SMTP_FROM_NAME, CONTACT_RECIPIENT
 *      Diese z.B. über die Hosting-Verwaltung, eine serverseitige
 *      .htaccess ("SetEnv SMTP_HOST ...") oder php.ini/php-fpm-Pool
 *      setzen - je nachdem, was das Hosting erlaubt.
 *
 *   2) Falls Environment-Variablen NICHT möglich sind: diese Datei nach
 *        config/mail.local.php
 *      kopieren und dort unten die echten Werte eintragen.
 *      config/mail.local.php steht in .gitignore und wird NIE mit
 *      committet. Der Ordner config/ ist zusätzlich per config/.htaccess
 *      gegen direkten Web-Zugriff gesperrt (Apache) - liegt euer Hosting
 *      nicht auf Apache, bitte config/ nach Möglichkeit ganz aus dem
 *      öffentlich erreichbaren Webroot herausnehmen (z.B. eine Ebene
 *      über public_html/) und stattdessen den vollen Dateipfad in der
 *      Environment-Variable MAIL_CONFIG_PATH eintragen.
 *
 * Fehlt am Ende ein Pflichtwert, antwortet /api/contact.php mit einem
 * klaren 500-Fehler ("server_not_configured") statt fehlzuschlagen oder
 * eine falsche/leere Mail zu verschicken.
 */

return [
    // Hostname des SMTP-Servers, z.B. "smtp.strato.de" oder "smtp.office365.com"
    'smtp_host' => 'smtp.example.com',

    // Übliche Ports: 587 (STARTTLS, empfohlen) oder 465 (SSL/TLS direkt)
    'smtp_port' => 587,

    // Vollständiger SMTP-Benutzername (meist die komplette E-Mail-Adresse)
    'smtp_user' => 'user@example.com',

    // SMTP-Passwort - NIEMALS echte Werte in diese Beispieldatei eintragen
    'smtp_pass' => 'CHANGE-ME',

    // 'tls' = STARTTLS (Port meist 587), 'ssl' = implizites TLS (Port meist 465),
    // '' = keine Verschlüsselung (nicht empfohlen, nur für internes Testing)
    'smtp_encryption' => 'tls',

    // Technischer Absender - muss eine auf dem SMTP-Server erlaubte/freigegebene
    // Adresse sein, sonst weisen viele Mailserver den Versand zurück (SPF/DKIM)
    'smtp_from' => 'noreply@kjs-segeberg.de',

    // Anzeigename des Absenders im Postfach des Empfängers
    'smtp_from_name' => 'KJS Segeberg Kontaktformular',

    // Empfänger-Adresse für alle Kontaktformular-Nachrichten
    'contact_recipient' => 'frank.huelser@kjs-segeberg.de',
];
