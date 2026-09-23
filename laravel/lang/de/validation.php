<?php

/*
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Deutsche Uebersetzung der Standard-Validierungsregeln, die die neuen
 * Auth-Formulare (Login, Passwort vergessen, Passwort zuruecksetzen) ueber
 * Laravel Fortifys eigene interne Request-Klassen verwenden (E-Mail-
 * Pflichtfeld, gueltige E-Mail-Adresse, Mindestlaenge/Bestaetigung beim
 * neuen Passwort, siehe Laravel\Fortify\Http\Requests\...). Bewusst nur die
 * Regeln + Feldnamen, die dort tatsaechlich vorkommen - siehe Kommentar in
 * lang/de/auth.php fuer die Gesamtbegruendung ("lang/de" fehlte bislang
 * komplett, obwohl APP_LOCALE schon "de" ist).
 */

return [

    'required' => 'Das Feld :attribute ist ein Pflichtfeld.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'min' => [
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'max' => [
        'string' => 'Das Feld :attribute darf maximal :max Zeichen lang sein.',
    ],
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
    'current_password' => 'Das eingegebene Passwort ist nicht korrekt.',

    'attributes' => [
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'token' => 'Token',
    ],

];
