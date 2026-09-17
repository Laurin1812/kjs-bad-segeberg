# Server-Kommandoliste (Entwurf, noch NICHT ausfuehren)

Konkrete, in der richtigen Reihenfolge dokumentierte Befehle fuer den
eigentlichen Deploy auf Carstens Server. Diese Liste wird erst nach Klaerung
der offenen Serverdaten (SSH-Zugang, DB-Zugaenge, PHP-Version, siehe
Phase-8-Bericht Abschnitt 13) tatsaechlich ausgefuehrt.

Legende: 🟢 nur beim allerersten Deploy | 🔁 bei jedem Deploy | ⚠️ gefaehrlich/nicht leichtfertig wiederholen

```sh
# --- Repo/Code ---
cd /pfad/zum/webroot                              # 🔁 DocumentRoot = Repo-Wurzel, siehe production-env-vorlage.md
git fetch origin
git checkout <produktions-branch>                  # 🔁 NICHT feature/laravel direkt - siehe Hinweis unten
git pull

# --- Laravel-Abhaengigkeiten ---
cd laravel
composer install --no-dev --optimize-autoloader    # 🔁 kein npm/Vite noetig, siehe Phase-8-Bericht Punkt 2

# --- .env / Config ---
cp .env.example .env                                # 🟢 danach ECHTE Werte eintragen, siehe production-env-vorlage.md
php artisan key:generate                            # 🟢 NUR beim ersten Setup - ueberschreibt APP_KEY, danach
                                                      #     werden bereits verschluesselte Daten (falls vorhanden)
                                                      #     unlesbar. Bei einem Redeploy NIE erneut ausfuehren.
# config/db.local.php, config/mail.local.php, config/identity.local.php
# manuell aus den *.example.php-Vorlagen anlegen (siehe production-env-vorlage.md) # 🟢

# --- Datenbank ---
php artisan migrate --force                         # 🔁 bei jedem Deploy sicher (idempotent, ueberspringt
                                                      #     bereits gelaufene Migrationen automatisch)
mysql -u <user> -p <sondermodul_db> < ../database/schema.sql   # 🟢 NUR einmalig, NUR wenn diese DB noch leer ist -
                                                      #     ⚠️ NICHT erneut ausfuehren, wenn dort bereits Boersen-/
                                                      #     Kontaktdaten existieren (kein "CREATE OR REPLACE",
                                                      #     schema.sql ist nicht idempotent - vorher pruefen!)

php artisan kjs:import-content                       # 🟢 ⚠️ NUR einmalig auf einer frisch migrierten, LEEREN
                                                      #     Laravel-DB. Bricht von selbst ab, wenn Zieltabellen
                                                      #     bereits Daten enthalten (siehe --force unten) - das
                                                      #     ist der eingebaute Schutz, nicht einfach uebergehen.
php artisan kjs:import-content --force               # ⚠️⚠️ GEFAEHRLICH - TRUNCATE + Neuimport aller CMS-Tabellen
                                                      #     (siehe managedTables in ImportContent.php). NUR
                                                      #     verwenden, wenn bewusst ein vorheriger Importstand
                                                      #     verworfen werden soll UND NOCH KEIN Redakteur ueber
                                                      #     die Laravel-Admin-Oberflaeche eigene Aenderungen
                                                      #     gespeichert hat - sonst gehen genau diese verloren.
                                                      #     Siehe Phase-8-Bericht Punkt 10 fuer die vollstaendige
                                                      #     Begruendung/Reihenfolge.
php artisan kjs:compare-content                      # 🔁 read-only, gefahrlos - Verifikation nach jedem Import

# --- Schreibrechte (Platzhalter - echte User/Gruppe von Carsten erfragen) ---
chown -R www-data:www-data storage bootstrap/cache   # 🔁 Platzhalter-User "www-data" anpassen
chmod -R ug+rwX storage bootstrap/cache
# Zusaetzlich (Repo-Wurzel): images/ downloads/ uploads/boersen/* beschreibbar
# machen - siehe Phase-8-Bericht Punkt 6.

# --- Webserver ---
# Apache: sicherstellen, dass mod_rewrite + AllowOverride aktiv sind (siehe
# .htaccess im Repo-Root) - kein Kommando hier, serverseitige vHost-Config.
# nginx: siehe Phase-8-Bericht Punkt 3 fuer die Vorlage.

# --- Cache ---
php artisan config:cache                             # 🔁 ⚠️ friert aktuelle env()-Werte ein - nach JEDER
                                                      #     spaeteren .env-Aenderung erneut ausfuehren, sonst
                                                      #     wirkt die Aenderung scheinbar nicht
php artisan route:cache                              # 🔁

# --- Smoke-Tests ---
# siehe docs/deployment/smoke-test-checkliste.md - VOR dem Umschalten des
# Live-Traffics durchgehen.
```

## Wichtiger Hinweis zum Branch

`feature/laravel` ist der aktuelle Arbeits-/Review-Branch. Fuer den
tatsaechlichen Produktivdeploy wird zu gegebener Zeit ein bewusster Merge-
Schritt noetig (`feature/laravel` → ein Produktions-Branch, z.B. `main` nach
Freigabe) - **das ist ausdruecklich NICHT Teil dieser Phase** (Auftrag:
"main/staging nicht anfassen", "Kein Merge nach main oder staging"). Die
Kommandoliste oben geht von einem bereits gemergten/freigegebenen Stand aus.

## Redeploy (nach dem ersten Mal)

Bei jedem weiteren Deploy entfaellt alles mit 🟢 (insbesondere
`key:generate` und die einmaligen DB-Importe) - nur die mit 🔁 markierten
Schritte werden wiederholt. `migrate --force` ist bei jedem Redeploy sicher
(Laravel ueberspringt automatisch bereits ausgefuehrte Migrationen).
