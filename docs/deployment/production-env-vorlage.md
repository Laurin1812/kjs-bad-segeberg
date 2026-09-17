# Produktions-Environment-Vorlage (Phase 8B)

Diese Datei dokumentiert **alle** Umgebungsvariablen/Konfigurationswerte, die
auf dem echten Produktivserver (Carsten) benoetigt werden - fuer die
Laravel-CMS-Ebene UND fuer die bestehenden PHP-Sondermodule (Hundeboerse/
Waffenboerse/Kontakt). Enthaelt **keine echten Werte/Secrets**, nur
Platzhalter und Erklaerungen. Ersetzt keine der bestehenden
`*.example.php`-Vorlagen (`config/db.example.php`, `config/mail.example.php`,
`config/identity.example.php`, `laravel/.env.example`) - fasst sie nur an
einer Stelle zusammen und macht die Zusammenhaenge zwischen beiden PHP-
Ebenen explizit, die beim Ausfuellen von zwei getrennten Vorlagen leicht
uebersehen werden.

Hintergrund/Herleitung: siehe Abschlussberichte Phase 7 (Architektur-Analyse
der drei Sondermodule) und Phase 8 (Server-Readiness-Analyse, insbesondere
die dort gefundene `DB_HOST`/`DB_PORT`-Namenskollision und die fehlende
`sessions`-Tabelle).

---

## 1. Laravel (`laravel/.env`)

Ausgangspunkt ist immer `laravel/.env.example` - hier nur die Werte, die
sich fuer **Produktion** von den dortigen lokalen Dev-Defaults
unterscheiden muessen, bzw. die zusaetzlich erklaerungsbeduerftig sind:

```dotenv
APP_NAME="KJS Bad Segeberg"
APP_ENV=production
APP_KEY=                     # NICHT den Dev-Wert uebernehmen - per
                              # `php artisan key:generate` frisch erzeugen,
                              # siehe docs/deployment/server-kommandos.md
APP_DEBUG=false               # ZWINGEND false - bei true leaken Stacktraces
                              # mit Dateipfaden/Konfigwerten an jeden Client
APP_URL=https://www.kjs-segeberg.de   # Platzhalter - echte Produktions-URL eintragen
APP_TIMEZONE=Europe/Berlin

DB_CONNECTION=mysql
DB_HOST=                      # echter DB-Host von Carsten
DB_PORT=3306
DB_DATABASE=                  # eigene Laravel-CMS-Datenbank, siehe Abschnitt 3
DB_USERNAME=                  # eigener DB-User NUR fuer die Laravel-DB
DB_PASSWORD=

# Phase 8B-Entscheidung (siehe Abschnitt 4 "Sessions"): bewusst `file`,
# NICHT den Skeleton-Default `database` - siehe Begruendung dort.
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Phase-8-Empfehlung: bei der bestehenden Datenmenge unkritisch,
# `database`-Treiber nutzt die bereits migrierten Tabellen `cache`/`jobs`.
CACHE_STORE=database
QUEUE_CONNECTION=database

# Laravel selbst verschickt keine E-Mails (verifiziert in Phase 8: kein
# Mail::/Mailable/Notification::-Aufruf in app/) - `log` oder `null` reicht,
# das SMTP-Setup unten (Abschnitt 2) ist fuer das PHP-Kontaktformular.
MAIL_MAILER=log

# Phase 4/7: dasselbe Secret wie IDENTITY_JWT_SECRET der PHP-Sondermodule
# (Abschnitt 2) - siehe Abschnitt 3 fuer die empfohlene Aufteilung, WARUM
# hier trotzdem eine eigene Variable steht statt einer gemeinsamen Datei.
IDENTITY_JWT_SECRET=
# IDENTITY_JWT_ISSUER=
# IDENTITY_JWT_AUDIENCE=

# Phase 5B.1 - nur setzen, falls Carstens Server-Pfade von den Defaults
# (Repo-Wurzel/images, Repo-Wurzel/downloads) abweichen:
# KJS_IMAGES_PATH=/pfad/auf/carstens/server/images
# KJS_IMAGES_URL_PREFIX=/images
# KJS_DOWNLOADS_PATH=/pfad/auf/carstens/server/downloads
# KJS_DOWNLOADS_URL_PREFIX=/downloads
# KJS_MAX_IMAGE_BYTES=15728640
# KJS_MAX_PDF_BYTES=20971520
```

## 2. PHP-Sondermodule (Hundeboerse/Waffenboerse/Kontakt)

**Empfohlene Produktionsvariante (siehe Begruendung in Abschnitt 3):**
Datei-basierte Konfiguration statt Environment-Variablen, um die
`DB_HOST`/`DB_PORT`-Namenskollision mit Laravel von vornherein
auszuschliessen.

`config/db.local.php` (aus `config/db.example.php` kopieren):
```php
<?php
return [
    'host' => 'CHANGE-ME',   // eigener Wert, unabhaengig von Laravels DB_HOST
    'port' => 3306,
    'name' => 'CHANGE-ME',   // eigene Sondermodul-Datenbank, siehe Abschnitt 3
    'user' => 'CHANGE-ME',   // eigener DB-User NUR fuer diese Datenbank
    'pass' => 'CHANGE-ME',
];
```

`config/mail.local.php` (aus `config/mail.example.php` kopieren):
```php
<?php
return [
    'smtp_host' => 'CHANGE-ME',
    'smtp_port' => 587,
    'smtp_user' => 'CHANGE-ME',
    'smtp_pass' => 'CHANGE-ME',
    'smtp_encryption' => 'tls',
    'smtp_from' => 'CHANGE-ME',
    'smtp_from_name' => 'KJS Segeberg Kontaktformular',
    'contact_recipient' => 'CHANGE-ME',
];
```

`config/identity.local.php` (aus `config/identity.example.php` kopieren):
```php
<?php
return [
    'jwt_secret' => 'CHANGE-ME',   // MUSS identisch mit Laravels IDENTITY_JWT_SECRET sein
];
```

Alle drei `*.local.php`-Dateien sind bereits in `.gitignore` eingetragen und
duerfen niemals committet werden. `config/.htaccess` sperrt den direkten
Web-Zugriff auf den ganzen Ordner zusaetzlich ab (nur unter Apache wirksam
- siehe `docs/deployment/deployment-checkliste.md`).

## 3. Warum diese Aufteilung (DB_HOST/DB_PORT-Kollision, Identity-Secret)

**DB-Konfiguration bewusst getrennt nach Mechanismus, nicht nur nach
Datenbank:** Wuerden beide PHP-Ebenen ueber Environment-Variablen
konfiguriert, wuerden sich `DB_HOST`/`DB_PORT` (von `api/lib/db.php`
gelesen) mit Laravels eigenen, gleichnamigen `DB_HOST`/`DB_PORT` aus
`laravel/.env` ueberschneiden, sobald beide im selben Prozess-/Pool-Kontext
(z.B. derselbe PHP-FPM-Pool oder dieselbe `SetEnv`-Ebene in der
Apache-Config) gesetzt werden - eine Ebene wuerde dann versehentlich die
DB-Zugangsdaten der jeweils anderen Ebene sehen. Deshalb: **Laravel** nutzt
seine eigene `.env` (dort sind `DB_HOST`/`DB_PORT`/... ohnehin nur fuer den
Laravel-Prozess sichtbar, da `.env` von Laravel selbst geladen wird, nicht
vom Webserver-Environment), **die PHP-Sondermodule** nutzen stattdessen die
datei-basierte Variante (`config/db.local.php`), die komplett unabhaengig
vom Server-Environment ist. Diese Aufteilung loest die Kollision
vollstaendig, ohne dass Carsten irgendetwas Besonderes am Server-Environment
konfigurieren muss.

**Identity-Secret bewusst als ZWEI separate Werte dokumentiert, nicht eine
gemeinsame Datei:** `IDENTITY_JWT_SECRET` muss auf beiden Ebenen **denselben
Wert** enthalten (sonst akzeptiert eine Ebene Tokens, die die andere
ablehnt, oder umgekehrt) - das ist ein bewusster, in Phase 7 begruendeter
Hand-Port (`api/lib/identity_auth.php` == `App\Support\NetlifyIdentity`,
siehe dortige Analyse), keine gemeinsame Bibliothek. Es waere technisch
moeglich, beide Ebenen dieselbe physische Datei lesen zu lassen (z.B.
`config/identity.local.php` im Repo-Wurzelverzeichnis auch von Laravel aus
lesen) - das wuerde aber eine Code-Aenderung in `App\Support\NetlifyIdentity`
noetig machen, um zusaetzlich zu `env()` auch diese Datei zu pruefen. Das
ist **nicht Teil dieser Phase** (waere eine funktionale Aenderung, keine
reine Vorbereitung) und wird hier bewusst nur dokumentiert: **beim
Einrichten auf Carstens Server denselben Secret-Wert zweimal eintragen**
(einmal `laravel/.env`, einmal `config/identity.local.php`).

## 4. Sessions-Entscheidung

Phase 8 hat festgestellt: `laravel/.env.example` (Laravel-13-Skeleton-
Default) setzt `SESSION_DRIVER=database`, aber es existiert **keine**
`sessions`-Tabellen-Migration in `database/migrations/`. Praktisch
unschaedlich, weil die `api`-Middleware-Gruppe in `bootstrap/app.php`
keine Session-Middleware einbindet (die gesamte Applikation ist als reine
JSON-API mit Bearer-Token-Auth aufgebaut, keine Cookie-Sessions) und die
einzige `web.php`-Route (`/`, Laravel-Standard-Willkommensseite) in der
geplanten Produktivtopologie nie erreicht wird (siehe Phase-8-Bericht,
Abschnitt Webserver-Konfiguration).

**Entscheidung (Phase 8B):** Produktionsvorlage setzt **explizit**
`SESSION_DRIVER=file` (siehe Abschnitt 1) statt des Skeleton-Defaults
`database`. Dadurch wird die Falle von vornherein umgangen, ohne dass eine
zusaetzliche Migration noetig ist oder irgendein Verhalten sich aendert
(Sessions werden ohnehin nie geschrieben) - reine Vorsichtsmassnahme fuer
den Fall, dass in einer spaeteren Phase doch einmal eine `web`-Route mit
Sessions hinzukommt.

## 5. Nicht Teil dieser Vorlage

Keine echten Zugangsdaten, keine Annahmen ueber Carstens tatsaechliche
Server-/DB-/SMTP-Werte - diese Vorlage wird erst ausgefuellt, sobald diese
Informationen vorliegen (siehe `docs/deployment/deployment-checkliste.md`,
Abschnitt "Vor Deploy").
