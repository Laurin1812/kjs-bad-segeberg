<?php
/**
 * BEISPIEL-KONFIGURATION FÜR DIE ADMIN-AUTHENTIFIZIERUNG (Netlify Identity) -
 * NUR EINE VORLAGE. Enthält bewusst kein echtes Secret und wird von den
 * Admin-Endpunkten (api/hundeboerse/admin/*.php, api/waffenboerse/admin/*.php)
 * nicht eingelesen.
 *
 * WOZU: Das bestehende Admin-Panel (admin/admin.js) meldet Benutzer über
 * das Netlify-Identity-Widget an. Damit die neuen PHP-Admin-Endpunkte auf
 * kjs.mysolution-webservice.de selbstständig prüfen können, ob ein
 * mitgeschicktes Zugriffstoken echt und gültig ist (und welche Rolle/
 * Berechtigungen der Benutzer hat), brauchen sie das Netlify-Identity-
 * "JWT secret" dieser Website - siehe api/lib/identity_auth.php für die
 * ausführliche technische Begründung.
 *
 * SO FINDET IHR DAS SECRET:
 *   Netlify-Dashboard -> Site configuration -> Identity ->
 *   "Settings and usage" -> Abschnitt "JSON Web Tokens" -> "JWT secret"
 *   kopieren (bei Bedarf dort auch neu generierbar - ACHTUNG: ein neu
 *   generiertes Secret macht alle bis dahin ausgestellten Zugriffstoken
 *   ungültig, betroffene Admins müssten sich einmal neu einloggen).
 *
 * Konfiguration in dieser Reihenfolge (identisch zum Muster bei
 * config/db.example.php/config/mail.example.php):
 *   1) Environment-Variable IDENTITY_JWT_SECRET (empfohlen)
 *   2) Diese Datei nach config/identity.local.php kopieren und dort den
 *      echten Wert eintragen. config/identity.local.php steht in
 *      .gitignore und wird NIE committet; der Ordner config/ ist
 *      zusätzlich per config/.htaccess gegen direkten Web-Zugriff
 *      gesperrt.
 *
 * Ohne dieses Secret lehnen die Admin-Endpunkte JEDEN Zugriff ab (fail-
 * closed, kein Admin-Zugriff ist sicherer als ein ungeprüfter) - das
 * bestehende Admin-Panel würde dann beim Öffnen von Hundebörse/Waffenbörse
 * einen Fehler "Nicht angemeldet" bzw. HTTP 401 anzeigen, obwohl der
 * Benutzer im Netlify-Identity-Sinn durchaus eingeloggt ist.
 */

return [
    'jwt_secret' => 'CHANGE-ME',
];
