<?php

/*
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Deutsche Uebersetzung der Status-Schluessel, die Laravels Passwort-
 * Broker (Illuminate\Auth\Passwords\PasswordBroker, von Laravel Fortifys
 * PasswordResetLinkController/NewPasswordController per trans($status)
 * ausgegeben, siehe dortiger Code) fuer "Passwort vergessen"/"Passwort
 * zuruecksetzen" zurueckgibt. Siehe lang/de/auth.php-Kommentar fuer die
 * Gesamtbegruendung (lang/de fehlte bislang komplett).
 */

return [

    'reset' => 'Ihr Passwort wurde zurückgesetzt.',
    'sent' => 'Wir haben Ihnen einen Link zum Zurücksetzen des Passworts per E-Mail gesendet.',
    'throttled' => 'Bitte warten Sie, bevor Sie es erneut versuchen.',
    'token' => 'Dieser Link zum Zurücksetzen des Passworts ist ungültig oder abgelaufen.',
    'user' => 'Wir konnten keinen Benutzer mit dieser E-Mail-Adresse finden.',

];
