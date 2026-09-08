<?php
/**
 * Minimaler, handgebauter Autoloader für die vendored PHPMailer-Bibliothek.
 *
 * Warum kein "echter" composer-generierter Autoloader?
 * In der Umgebung, in der dieser Code vorbereitet wurde, war kein Zugriff
 * auf packagist.org möglich (Netzwerk-/Firewall-Einschränkung). Die
 * PHPMailer-Quelldateien wurden daher direkt von GitHub bezogen (Tag
 * v6.9.3, offizielles PHPMailer/PHPMailer-Repository) und hier manuell
 * unter vendor/phpmailer/phpmailer/ abgelegt - inhaltlich identisch zu dem,
 * was "composer require phpmailer/phpmailer" erzeugt hätte.
 *
 * Falls auf Carstens Hosting Composer verfügbar ist, kann diese Datei durch
 * einen echten "composer install" (siehe composer.json im Projekt-Root)
 * ersetzt werden - api/contact.php bindet ausschließlich
 * "vendor/autoload.php" ein und funktioniert mit beiden Varianten identisch.
 */

spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/phpmailer/phpmailer/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
