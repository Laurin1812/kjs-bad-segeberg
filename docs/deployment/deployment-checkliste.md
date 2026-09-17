# Deployment-Checkliste (mit Carsten Punkt fuer Punkt abzuarbeiten)

Zugehoerige Dokumente: `production-env-vorlage.md` (Secrets/Env),
`server-kommandos.md` (konkrete Befehle), `smoke-test-checkliste.md`
(Tests nach dem Deploy), `rollback-checkliste.md` (falls etwas schiefgeht).

## Vor Deploy

- [ ] Vollstaendiges Backup der aktuell laufenden Site (Dateien) angelegt
- [ ] Backup der Sondermodul-DB angelegt, falls dort bereits Daten existieren
- [ ] Servervoraussetzungen mit Carsten geklaert (PHP-Version, Apache/nginx,
      DocumentRoot, Composer, PHP-Extensions, Schreibrechte, Upload-Limits,
      SMTP, SSH-Zugang - siehe Phase-8-Bericht Punkt 13, Nachricht an Carsten)
- [ ] Alle Secrets in `laravel/.env`, `config/db.local.php`,
      `config/mail.local.php`, `config/identity.local.php` echt ausgefuellt
      (siehe `production-env-vorlage.md`) - NICHT committet
- [ ] `IDENTITY_JWT_SECRET` auf beiden Ebenen (Laravel + PHP-Sondermodule)
      identisch gesetzt
- [ ] Zwei getrennte Datenbanken angelegt (Laravel-CMS-DB, Sondermodul-DB) -
      auf demselben MySQL-/MariaDB-Server moeglich, siehe Phase-8-Bericht
      Punkt 4
- [ ] Schreibrechte fuer `laravel/storage`, `laravel/bootstrap/cache`,
      `images/`, `downloads/`, `uploads/boersen/*` fuer den PHP-Prozess
      gesetzt

## Deploy

- [ ] Code auf den Server gebracht (siehe `server-kommandos.md`)
- [ ] `composer install --no-dev --optimize-autoloader` erfolgreich
- [ ] `php artisan key:generate` (nur beim allerersten Deploy!)
- [ ] `php artisan migrate --force` erfolgreich
- [ ] Sondermodul-Schema eingespielt (`database/schema.sql`) - nur einmalig,
      nur wenn diese DB noch leer war
- [ ] Initial-Import `php artisan kjs:import-content` erfolgreich (nur beim
      allerersten Deploy, siehe Reihenfolge/Warnung in Punkt 10 des
      Phase-8-Berichts)
- [ ] `php artisan kjs:compare-content` zeigt keine unerwarteten
      Abweichungen
- [ ] Rewrite-Regel aktiv: `/api/content/*` und `/api/admin/*` erreichen
      Laravel, bestehende `/api/*.php`-Dateien bleiben direkt erreichbar
      (siehe Phase-7-`.htaccess`-Aenderung bzw. nginx-Aequivalent)
- [ ] `php artisan config:cache && php artisan route:cache` ausgefuehrt

## Nach Deploy

- [ ] Smoke-Tests vollstaendig durchlaufen (siehe
      `smoke-test-checkliste.md`) - **vor** dem Umschalten des Live-Traffics
- [ ] Laravel-Logs (`laravel/storage/logs/laravel.log`) auf unerwartete
      Fehler geprueft
- [ ] Admin-Login funktioniert, mindestens ein Laravel-Bereich UND ein
      PHP-Sondermodul-Bereich lesbar/speicherbar
- [ ] Bild- und PDF-Upload funktionieren, Dateien landen an der erwarteten
      Stelle (`images/`, `downloads/`)
- [ ] Hundeboerse/Waffenboerse/Kontakt oeffentlich UND im Admin erreichbar

## Go-Live

- [ ] Finaler, garantiert aktueller `content/*.json`-Snapshot vor dem
      eigentlichen Cutover gezogen (siehe Phase-8-Bericht Punkt 10)
- [ ] Alter Schreibweg (Netlify/git-gateway-Admin) fuer die bereits auf
      Laravel migrierten Bereiche bewusst gestoppt/deaktiviert, damit keine
      zwei Systeme gleichzeitig unterschiedliche "Wahrheiten" schreiben
- [ ] DNS/Live-Umschaltung durchgefuehrt
- [ ] Nach der Umschaltung: `IS_PHP_HOST` in `admin/admin.js` greift jetzt
      automatisch (Hostname ist nicht mehr `*.netlify.app`) - keine
      Code-Aenderung noetig, aber einmal live gegenpruefen (siehe
      Smoke-Test-Checkliste, Abschnitt "Nach dem echten Cutover")
