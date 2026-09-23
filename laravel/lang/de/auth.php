<?php

/*
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Deutsche Uebersetzung der von Laravel selbst / Laravel Fortify intern per
 * trans('auth.*') ausgegebenen Texte (siehe z.B. Laravel\Fortify\Actions\
 * AttemptToAuthenticate bzw. dessen LoginRequest: bei falschen Zugangsdaten
 * oder zu vielen Fehlversuchen wird genau eine dieser drei Zeilen erzeugt).
 *
 * Grund fuer diese Datei: APP_LOCALE steht in .env bereits seit einer
 * frueheren Phase auf "de" (siehe config/app.php), es gab bislang aber
 * keinen "lang/de"-Ordner im Projekt - Laravel faellt dadurch fuer JEDE
 * trans()-Ausgabe ohne eigene deutsche Datei automatisch auf
 * APP_FALLBACK_LOCALE ("en") zurueck. Genau das ist die vom Auftrag
 * ("Login-/Passwort-Reset-Texte teilweise Englisch") beschriebene Ursache -
 * diese Datei behebt sie fuer den Auth-Bereich, ohne APP_LOCALE selbst
 * anzufassen (war schon korrekt "de"). Alle anderen Seiten der Website
 * schreiben ihre deutschen Texte ohnehin direkt in Blade/PHP statt ueber
 * das lang()-System (siehe z.B. KontaktRequest::messages()) - diese Datei
 * ist bewusst nur so gross wie fuer den Auth-Bereich noetig, keine
 * vollstaendige Uebersetzung des gesamten Laravel-Frameworks.
 */

return [

    'failed' => 'Diese Zugangsdaten wurden nicht in unseren Unterlagen gefunden.',

    'password' => 'Das eingegebene Passwort ist nicht korrekt.',

    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',

];
