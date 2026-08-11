# Nächste Schritte – Go-Live

**Quelle:** Gespräch Laurin ↔ Frank Hülser (Geschäftsführer), 02.08.2026 – von Laurin zusammengefasst und hier strukturiert zur späteren Abarbeitung.

---

## Hintergrund

- Zwischenzeitlicher Versuch, mit Oliver auf Joomla umzusteigen – hat sich als unpraktikabel erwiesen.
- Grund für den Rückzug von Joomla: Laurin will mit KI (Claude) an der Seite weiterarbeiten – das ist mit einem Standard-CMS wie Joomla nicht sinnvoll möglich, mit dem selbstgebauten CMS schon.
- Frank ist überzeugt: das selbstgebaute CMS-System ist sehr gut und soll die Basis bleiben.
- Vor dem Joomla-Zwischenstopp wurde an der Testseite bereits ein Fundament gelegt (Einstellungen, Formularstruktur etc.), das später auf die restlichen Admin-Bereiche übertragen werden soll.

## Priorität 1 (jetzt): Seite go-live-fähig machen

Ziel laut Frank: **die Seite muss endlich online gehen**, möglichst in kürzester Zeit.

### Rechtssicherheit
- Impressum (`impressum.html` / `content/impressum.json`) und Datenschutz sind inhaltlich vorhanden, keine Platzhalter gefunden.
- **Zu prüfen/korrigieren:** `content/impressum.json` enthält vermutliche Tippfehler –
  „Frank Hülser, **Schrift** & Geschäftsführer" (vermutlich „Schriftführer" oder Titel korrigieren) und „**Vorsitzener** Oliver Jürgens" (Tippfehler, sollte „Vorsitzender" heißen). Mit Frank abklären, welche Rolle/Schreibweise korrekt ist.
- Rechtliche Vollständigkeit (Impressum/Datenschutz) sollte trotzdem einmal von einer sachkundigen Person / Anwalt gegengeprüft werden – das kann ich inhaltlich nicht verbindlich beurteilen.

### Kontaktformular / E-Mail
- **Korrektur zur Annahme aus dem Gespräch:** Das Kontaktformular ist technisch **bereits verkabelt**, nicht offen. `js/main.js` (Zeile 543 ff.) sendet die Formulardaten per `fetch` an `https://formsubmit.co/ajax/frank.huelser@kjs-segeberg.de`.
- **Trotzdem prüfen vor Go-Live:**
  - Ist `frank.huelser@kjs-segeberg.de` die richtige Zieladresse? (E-Mail-Domain in `content/einstellungen.json`/`impressum.json` ist `info@kjs-segeberg.de` – ggf. Adresse abgleichen.)
  - FormSubmit.co verschickt bei der **ersten** Zustellung an eine neue Adresse eine Bestätigungsmail, die erst angeklickt werden muss, bevor Formulare tatsächlich ankommen – unbedingt einmal live testen.
  - FormSubmit.co ist ein kostenloser Drittanbieter-Dienst (kein eigener Mailserver) – kurz mit Frank abstimmen, ob das für den Produktivbetrieb so gewünscht ist oder ob später ein eigener Versand (z.B. über Netlify Functions + Mailanbieter) sinnvoller wäre.

### Domain-Inkonsistenz (gefunden bei der Recherche) — GEKLÄRT (08.08.2026)
- Im Code werden **zwei verschiedene Domains** für E-Mail-Adressen verwendet: `@kjs-bad-segeberg.de` (viele ältere Seiten/Topbar/Kontaktfelder) und `@kjs-segeberg.de` (neuere Stellen wie `einstellungen.json`, `impressum.json`, Formular-Zieladresse).
- **Laurin hat bestätigt: die richtige Domain ist `kjs-segeberg.de`** (nicht `kjs-bad-segeberg.de`). Alle `mailto:`-/Kontakt-Adressen müssen im Code auf `kjs-segeberg.de` vereinheitlicht werden.
- Kontaktformular-Zieladresse (`frank.huelser@kjs-segeberg.de`) wird demnächst gemeinsam getestet/bestätigt.

### Sonstiges vor Go-Live (noch zu sammeln/prüfen)
- [ ] Alle Inhalte final durchgehen (echte Texte statt Platzhalter, besonders neue Seiten wie Testseite/Infomobil)
- [ ] Domain/DNS-Umstellung auf Netlify final klären, falls noch nicht geschehen
- [ ] Kurzer End-to-End-Test: Kontaktformular, Navigation, Downloads, Admin-Login
- [ ] Postadresse der Geschäftsstelle (Hauptzentrale, "Am Schießstand") als zusätzlicher Kontaktpunkt auf der Kontaktseite ergänzen (aus Gespräch mit Frank, 02.08.2026)
- [ ] Cookie-Banner vor Go-Live aktivieren (aktuell noch nicht aktiv)
- [ ] **Login-Link im Footer vor dem finalen Go-Live entfernen** – aktuell führt ein Link ganz unten in der Fußzeile direkt in den Adminbereich (praktisch für die Entwicklung, aber ein Sicherheitsrisiko, sobald die Seite live/öffentlich ist)
- [ ] Impressum/Datenschutz-Texte juristisch prüfen lassen (Frank will das mit Carsten/einem Dienst wie "IT-Recht" klären – Olivers Einwand war, es sei "nicht rechtssicher" wegen Hacking-Risiko)
- [x] Top-Menü: "Test"-Menüpunkt vor Go-Live entfernen — erledigt 11.08.2026, Testseite bleibt im Admin editierbar, ist nur aus Desktop-/Mobile-Navigation raus; ein weiterer Menüpunkt-Name gefällt Frank nicht – noch offen, was stattdessen stehen soll

Ausführlichere Notizen zum gesamten Gespräch (inkl. mittelfristiger CMS-Themen und Feature-Backlog) stehen in `GESPRAECH-FRANK-2026-08-02.md`.

## Priorität 2 (danach): CMS-Fundament vereinheitlichen

Sobald die Seite live ist: das auf der Testseite gebaute Fundament (Formular-Struktur, `form:'tiptap'`, Einstellungen-Pattern) schrittweise auf die **übrigen Admin-Bereiche** übertragen, damit alles einheitlich funktioniert. Nicht alles auf einmal – nach und nach.

Zwei, drei Anpassungen an der Testseite selbst sind vorher evtl. noch nötig, bevor das Pattern übernommen wird (laut Frank/Laurin im Gespräch angedeutet, aber nicht konkretisiert – bei Bedarf nachfragen, was genau gemeint war).

## Organisatorisches

- Laurin möchte einen **Zugangsdaten-Ordner** (Netlify-Login, wichtige Links zur Seite/zum Admin/zu Netlify) anlegen, damit er schnell darauf zugreifen kann. Wird in einem separaten Schritt eingerichtet (siehe Rückfrage im Chat).
- Laurin möchte generell (projektunabhängig) Ordnerstruktur für Zugangsdaten/Links/Passwörter auf seinem Rechner ordnen.
