<?php
/**
 * BEISPIEL-KONFIGURATION FÜR DIE HUNDEBÖRSE/WAFFENBÖRSE-DATENBANK - NUR EINE
 * VORLAGE. Diese Datei enthält bewusst keine echten Zugangsdaten und wird
 * von den api/hundeboerse/*.php bzw. api/waffenboerse/*.php-Endpunkten
 * nicht eingelesen.
 *
 * api/lib/db.php prüft die Konfiguration in dieser Reihenfolge:
 *
 *   1) Environment-Variablen (empfohlen, falls vom Hosting unterstützt):
 *        DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *      Diese z.B. über die Hosting-Verwaltung, eine serverseitige
 *      .htaccess ("SetEnv DB_HOST ...") oder php.ini/php-fpm-Pool setzen -
 *      je nachdem, was das Hosting erlaubt. Genau dasselbe Prinzip wie
 *      bereits beim Kontaktformular (siehe config/mail.example.php).
 *
 *   2) Falls Environment-Variablen NICHT möglich sind: diese Datei nach
 *        config/db.local.php
 *      kopieren und dort unten die echten Werte eintragen.
 *      config/db.local.php steht in .gitignore und wird NIE mit
 *      committet. Der Ordner config/ ist zusätzlich per config/.htaccess
 *      gegen direkten Web-Zugriff gesperrt (Apache) - liegt euer Hosting
 *      nicht auf Apache, bitte config/ nach Möglichkeit ganz aus dem
 *      öffentlich erreichbaren Webroot herausnehmen und stattdessen den
 *      vollen Dateipfad in der Environment-Variable DB_CONFIG_PATH
 *      eintragen.
 *
 * Vor der ersten Nutzung muss außerdem einmalig das Datenbankschema
 * angelegt werden:
 *   mysql -u <user> -p <datenbank> < database/schema.sql
 *
 * Fehlt am Ende ein Pflichtwert oder schlägt die Verbindung fehl, antworten
 * die Hundebörse-/Waffenbörse-Endpunkte mit einem klaren 503-Fehler
 * ("server_not_configured") statt fehlzuschlagen oder falsche/leere Daten
 * zurückzugeben - das bestehende Frontend erkennt das und zeigt
 * stattdessen automatisch die letzten statischen Inhalte aus
 * content/hundeboerse.json bzw. content/waffenboerse.json an (siehe
 * Abschlussbericht, Abschnitt "JSON-Fallback").
 */

return [
    // Hostname bzw. IP des MySQL-/MariaDB-Servers, z.B. "localhost" oder
    // "db.beispiel-hosting.de"
    'host' => 'localhost',

    // Standardport für MySQL/MariaDB ist 3306
    'port' => 3306,

    // Name der Datenbank, in der database/schema.sql angelegt wurde
    'name' => 'kjs_boersen',

    // Datenbank-Benutzername
    'user' => 'kjs_boersen_user',

    // Datenbank-Passwort - NIEMALS echte Werte in diese Beispieldatei eintragen
    'pass' => 'CHANGE-ME',
];
