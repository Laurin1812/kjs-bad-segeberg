# Deployment-Dokumentation (Phase 8/8B)

Diese Dokumente bereiten den Laravel-Go-Live vor, ohne bereits zu deployen
oder `main`/`staging` anzufassen. Reihenfolge beim eigentlichen Deploy:

1. **`production-env-vorlage.md`** - alle benoetigten Environment-
   Variablen/Config-Werte fuer Laravel UND die PHP-Sondermodule, inkl. der
   `DB_HOST`/`DB_PORT`-Kollisionsloesung und der Sessions-Entscheidung.
2. **`dormante-boersen-tabellen.md`** - warum Laravel bereits Migrationen/
   Models fuer Hundeboerse/Waffenboerse/Kontakt besitzt, diese aber bewusst
   NICHT verwendet werden (produktive Wahrheit bleiben die PHP-
   Sondermodule).
3. **`server-kommandos.md`** - die konkrete, richtig geordnete Befehlsliste
   fuer den Deploy, mit Markierung fuer "nur beim ersten Mal" und
   "gefaehrlich".
4. **`deployment-checkliste.md`** - Punkt-fuer-Punkt-Ablauf (Vor Deploy /
   Deploy / Nach Deploy / Go-Live), zum gemeinsamen Abarbeiten mit Carsten.
5. **`smoke-test-checkliste.md`** - abhakbare Tests nach jedem Deploy, vor
   dem Umschalten des Live-Traffics.
6. **`rollback-checkliste.md`** - was zu tun ist, wenn ein Smoke-Test
   fehlschlaegt.

Hintergrund/Herleitung: Abschlussberichte Phase 7 (Architektur der drei
PHP-Sondermodule) und Phase 8 (Server-Readiness-Analyse). Diese Ordner
ersetzen NICHT die aelteren, inzwischen groesstenteils veralteten
Planungsdokumente im Repo-Wurzelverzeichnis (`NAECHSTE-SCHRITTE-GO-LIVE.md`,
`COWORK-KONTEXT.md`) - diese stammen aus der Zeit vor der Laravel-
Migration.
