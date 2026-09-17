# Smoke-Test-Checkliste (nach jedem Server-Deploy abzuhaken)

Vor dem Umschalten des Live-Traffics vollstaendig durchgehen. Bei jedem
Fehlschlag: NICHT weiter-deployen, siehe `rollback-checkliste.md`.

## Oeffentlicher Bereich

- [ ] Startseite laedt vollstaendig
- [ ] Navigation (Haupt- und Unterpunkte) vollstaendig und korrekt
- [ ] Footer korrekt (Links, Copyright)
- [ ] Aktuelles-Liste + mindestens ein Einzelbeitrag
- [ ] Termine-Liste
- [ ] Eine normale Inhaltsseite (z.B. eine Jaeger-Unterseite)

## Admin-Bereich (Laravel-Weg)

- [ ] Admin-Login funktioniert
- [ ] Admin speichern: eine Inhaltsseite lesen UND speichern (Round-Trip)
- [ ] Bild-Upload (landet in `images/`, Thumb/Card-Varianten werden erzeugt)
- [ ] PDF-Upload (landet in `downloads/`)
- [ ] Neue Seite/Unterseite anlegen
- [ ] Seite/Eintrag loeschen
- [ ] Drag&Drop-Sortierung einer Seiten-/Navigationsliste

## Sondermodule (PHP-Weg, unveraendert)

- [ ] Hundeboerse oeffentlich (Liste + Einzelanzeige)
- [ ] Hundeboerse Admin (lesen + speichern)
- [ ] Waffenboerse oeffentlich (Liste + Einzelanzeige)
- [ ] Waffenboerse Admin (lesen + speichern)
- [ ] Kontaktformular absenden (Testeintrag)
- [ ] Kontakt Admin: Testeintrag sichtbar, Status aenderbar

## Auth-/Routing-Grenzfaelle

- [ ] Zugriff ohne Token auf einen geschuetzten Endpunkt → 401
- [ ] Zugriff mit Token, aber ohne passendes Recht → 403
- [ ] `/api/content/navigation.json` liefert echtes JSON (nicht 404)
- [ ] Unbekannte Laravel-Route (`/api/content/does-not-exist`) → Laravels
      eigenes, sauberes 404-JSON (kein Stacktrace, keine rohe Apache-Seite)
- [ ] Bestehende `/api/*.php`-Dateien (z.B.
      `/api/hundeboerse/anzeigen.php`) bleiben unveraendert direkt
      erreichbar

## Nach dem echten Cutover (Domain ist nicht mehr `*.netlify.app`)

- [ ] `IS_PHP_HOST` greift automatisch (kein Code-Redeploy noetig) - im
      Admin-Panel pruefen, dass Bild-/PDF-Upload und Seiten-Verwaltung ueber
      die Laravel-API laufen (Netzwerk-Tab: Aufrufe gehen an `/api/...`,
      nicht an `/.netlify/git/...`)
- [ ] Admin-Bereich „Service" zeigt die in Phase 8B ergaenzte, klare
      Fehlermeldung ("... noch nicht verfuegbar ...") statt eines
      raetselhaften Netzwerkfehlers - siehe `NICHT_MIGRIERTE_DATEIEN_PHP_HOST`
      in `admin/admin.js` (Abschlussbericht Phase 8B, Abschnitt "Git-/JSON-
      Reste"), bis dieser Bereich auf Laravel migriert ist
