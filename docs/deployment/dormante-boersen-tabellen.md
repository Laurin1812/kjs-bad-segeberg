# Dormante Laravel-Strukturen fuer Hundeboerse/Waffenboerse/Kontakt

**Kurzfassung:** Laravel besitzt seit Phase 1 vollstaendige Migrationen UND
Eloquent-Models fuer Hundeboerse, Waffenboerse und Kontakt - diese sind
aktuell **komplett ungenutzt (dormant)**. Die produktive Wahrheit fuer diese
drei Bereiche sind und bleiben bis auf Weiteres die bestehenden PHP-
Sondermodule mit ihrer eigenen, separaten MySQL-Datenbank.

## Was existiert (dormant)

- Migrationen: `laravel/database/migrations/2026_09_14_0001{00,01,02,03,10,
  11,12,13,14,20}_*.php` (10 Dateien, legen `hundeboerse_anzeigen`,
  `hundeboerse_bilder`, `hundeboerse_zuchtverbaende`, `hundeboerse_meta`,
  `waffenboerse_anzeigen`, `waffenboerse_bilder`, `waffenboerse_kaliber`,
  `waffenboerse_kategorien`, `waffenboerse_meta`, `kontakt_anfragen` in der
  **Laravel-DB** an - bereits ausgefuehrt, siehe `php artisan
  migrate:status`).
- Models: `laravel/app/Models/{Hundeboerse,Waffenboerse}*.php`,
  `KontaktAnfrage.php` (10 Dateien).
- Alle 10 Tabellen sind in der lokalen Dev-DB leer bis auf
  `waffenboerse_kategorien` (7 Seed-Zeilen aus einem frueheren Seeder).

## Was tatsaechlich verwendet wird (produktiv)

- `api/hundeboerse/*.php`, `api/waffenboerse/*.php`, `api/kontakt/*.php`,
  `api/contact.php` - eigenstaendige PHP-Endpunkte.
- Eigene Datenbank (Vorlage: `config/db.example.php`, Schema:
  `database/schema.sql`), verbunden ueber `api/lib/db.php` - **komplett
  unabhaengig** von Laravels `DB_*`-Konfiguration/DB.
- Bestaetigt per `grep -rl "Hundeboerse\|Waffenboerse" app/Http/Controllers/`
  (Phase 7): **kein Treffer**. Kein einziger Laravel-Controller/keine Route
  in `routes/api.php` referenziert die obigen Models/Tabellen.

## Warum das so bleibt (fuer diese Phase)

Die bestehenden PHP-Module sind bereits sicher, vollstaendig und produktiv
im Einsatz (siehe Phase-7-Sicherheitspruefung: keine echten
Sicherheitsluecken gefunden). Eine Migration nach Laravel waere reine
Neuentwicklung ohne technischen Zwang - mit echtem Risiko (Datenmigration,
neue Bugs) und ohne funktionalen Gewinn fuer den anstehenden Go-Live. Diese
Entscheidung wurde bereits in Phase 7 (Abschnitt "Go-Live-Zielarchitektur")
getroffen und gilt unveraendert fort.

## Warum die dormanten Strukturen NICHT geloescht werden

- Sie sind komplett inert (keine Route/kein Controller ruft sie auf) - kein
  Sicherheitsrisiko, kein Betriebsrisiko.
- Loeschen/Zuruecksrollen waere eine funktionale Entscheidung ueber die
  langfristige Zielarchitektur (ganz aufgeben vs. spaeter doch migrieren),
  die explizit **nicht** Teil dieser Phase ist.
- Falls in einer sehr viel spaeteren Phase doch einmal eine Migration der
  Boersen-/Kontakt-Daten nach Laravel gewuenscht wird, ist die Struktur
  bereits vorbereitet (Schema 1:1 an `database/schema.sql` angelehnt).

## Wie das beim Go-Live sichtbar gemacht wird

Um zu verhindern, dass beim Deploy oder bei einer spaeteren Code-Aenderung
jemand faelschlich annimmt, Laravel sei fuer diese drei Bereiche zustaendig
(Auftrag Phase 8B, Punkt 3), wurde in dieser Phase **zusaetzlich** ein
deutlicher Dormant-Hinweis direkt im Code ergaenzt (reine Kommentare, keine
Verhaltensaenderung):

- Jede der 10 Migrationsdateien hat jetzt einen Kommentarblock direkt vor
  `return new class extends Migration`.
- Jedes der 10 Model-Dateien hat jetzt einen Kommentarblock direkt vor der
  `class ... extends Model`-Zeile.

Beide verweisen auf dieses Dokument. Keine Migration wurde zurueckgerollt,
keine Tabelle/Datei geloescht, kein Verhalten geaendert - `php -l` bestaetigt
fuer alle 20 Dateien fehlerfreie Syntax nach der Aenderung.

## Wann diese Entscheidung ueberdacht werden sollte

Nicht Teil dieser Phase, aber fuer eine spaetere Phase festgehalten: sobald
der PHP-Sondermodul-Code selbst aus irgendeinem Grund abgeloest werden soll
(z.B. weil Carstens Server aus anderen Gruenden keine zwei getrennten
MySQL-Datenbanken erlaubt), waeren diese Strukturen der naheliegende
Ausgangspunkt - aber das ist eine bewusste, separate Entscheidung, kein
Nebeneffekt des jetzigen Go-Live.
