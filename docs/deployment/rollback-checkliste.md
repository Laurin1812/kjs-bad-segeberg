# Rollback-Checkliste

Kein Live-Deploy ohne diesen schriftlichen Ablauf griffbereit. Bei jedem
Smoke-Test-Fehlschlag oder unerwarteten Problem nach dem Umschalten:
sofort hierher, nicht improvisieren.

## Code-Rollback

- [ ] Falls Release-Ordner-/Symlink-Muster verwendet wurde (siehe
      Phase-8-Bericht Punkt 9): DocumentRoot-Symlink zurueck auf den
      vorherigen Release-Ordner - keine Dateioperationen noetig, sofort
      wirksam
- [ ] Falls kein Symlink-Verfahren moeglich war: Datei-Backup aus dem
      "Vor Deploy"-Schritt der Deployment-Checkliste zurueckspielen

## DB-Restore

- [ ] Laravel-DB: `mysqldump`-Backup (unmittelbar vor `migrate --force`/
      `kjs:import-content` gezogen) zurueckspielen
- [ ] Alternativ granularer: `php artisan migrate:rollback` (nur wenn ganz
      sicher, welche Migrationsbatches betroffen sind - im Zweifel ist ein
      vollstaendiger Dump-Restore sicherer)
- [ ] Sondermodul-DB: eigenes Backup zurueckspielen, falls dort ebenfalls
      etwas veraendert wurde (normalerweise nicht betroffen, siehe
      "Sondermodule pruefen" unten)

## Medien-Restore

- [ ] `images/`/`downloads/` aus dem separaten, VOR dem Deploy gezogenen
      Backup wiederherstellen, falls neue Uploads seit dem letzten
      Code-Deploy sonst verloren gehen wuerden (diese Ordner werden NICHT
      automatisch vom Code-Rollback mit zurueckgesetzt, da sie ausserhalb
      des Release-Ordner-Musters liegen/liegen sollten - siehe
      Phase-8-Bericht Punkt 9)

## `.env`/Secrets

- [ ] Falls `.env`/`config/*.local.php` im Rahmen des fehlgeschlagenen
      Deploys geaendert wurden: auf den vorherigen bekannten Stand
      zuruecksetzen
- [ ] Nach JEDER `.env`-Aenderung (auch beim Rollback):
      `php artisan config:cache` erneut ausfuehren, sonst wirkt der alte
      Stand scheinbar nicht

## Rewrite zurueck

- [ ] Falls die Root-`.htaccess`/nginx-Rewrite-Regel im Rahmen des Deploys
      geaendert wurde: auf den vorherigen funktionierenden Stand
      zuruecksetzen (siehe Git-Historie der `.htaccess`)
- [ ] Gegenpruefen: bestehende `/api/*.php`-Dateien wieder direkt
      erreichbar, keine Endlosschleife/kein 500 durch eine kaputte Regel

## Sondermodule pruefen

- [ ] Bestaetigen, dass ein Laravel-Rollback KEINE Auswirkung auf
      Hundeboerse/Waffenboerse/Kontakt hatte (komplett eigene DB/Codepfad) -
      einmal kurz oeffentlich UND im Admin gegenpruefen
- [ ] Falls die `.htaccess` mit zurueckgerollt wurde: sicherstellen, dass
      dabei nicht versehentlich ein alter Laravel-Code-Stand mit einer
      neueren Rewrite-Regel kombiniert wird (oder umgekehrt) - Code und
      Rewrite-Regel gehoeren als EIN Paket zusammen zurueckgerollt

## Nach dem Rollback

- [ ] Smoke-Test-Checkliste (`smoke-test-checkliste.md`) erneut komplett
      durchgehen, um zu bestaetigen, dass der vorherige Stand wieder
      vollstaendig funktioniert
- [ ] Ursache des Fehlschlags dokumentieren, bevor ein erneuter
      Deploy-Versuch unternommen wird
