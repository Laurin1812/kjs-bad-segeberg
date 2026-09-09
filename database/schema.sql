-- ============================================================================
-- KJS Bad Segeberg - Hundeboerse & Waffenboerse: MySQL-Schema
-- ============================================================================
--
-- Erzeugt alle Tabellen, die api/hundeboerse/*.php und api/waffenboerse/*.php
-- brauchen. Enthaelt KEINE echten Zugangsdaten - Verbindungsdaten kommen
-- ausschliesslich ueber Environment-Variablen bzw. config/db.local.php (siehe
-- config/db.example.php), niemals aus dieser Datei.
--
-- Anwenden (einmalig, auf dem echten Server mit den echten DB-Zugangsdaten):
--   mysql -u <user> -p <datenbank> < database/schema.sql
--
-- Designprinzip (bewusst, siehe Abschlussbericht "bisherige Datenmodelle"):
-- die Feldnamen/-typen orientieren sich eng am bestehenden JSON-Modell
-- (content/hundeboerse.json, content/waffenboerse.json) statt einer eigenen
-- "saubereren" Neumodellierung - das ist eine 1:1-Ablösung der bisherigen
-- Speicherform, keine fachliche Neuentwicklung. Deshalb bleiben z.B. Preis,
-- Datumsangaben (Geburts-/Wurfdatum), Rüden-/Hündinnenzahl als freie Strings
-- erhalten (exakt wie im bisherigen JSON), statt sie verlustbehaftet in
-- DECIMAL/DATE/INT zu zwingen - beide Module erlauben im bisherigen Admin
-- z.B. "1.850" oder "Verhandlungsbasis" als Preistext.
--
-- Alle Tabellen: utf8mb4/utf8mb4_unicode_ci (volle Unicode-/Emoji-Unterstuetzung,
-- konsistent mit dem Rest des Projekts), InnoDB (Transaktionen + Foreign Keys).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- HUNDEBOERSE
-- ============================================================================

CREATE TABLE IF NOT EXISTS hundeboerse_anzeigen (
  id                 VARCHAR(40)   NOT NULL,
  status             ENUM('pending','published','rejected','archived') NOT NULL DEFAULT 'pending',
  type               ENUM('single','litter') NOT NULL DEFAULT 'single',
  title              VARCHAR(190)  NOT NULL DEFAULT '',
  breed              VARCHAR(190)  NOT NULL DEFAULT '',
  color              VARCHAR(190)  NOT NULL DEFAULT '',
  coat               VARCHAR(190)  NOT NULL DEFAULT '',
  price_type         VARCHAR(20)   NOT NULL DEFAULT 'on_request',
  price              VARCHAR(40)   NOT NULL DEFAULT '',
  postal_code        VARCHAR(10)   NOT NULL DEFAULT '',
  city               VARCHAR(190)  NOT NULL DEFAULT '',
  description        MEDIUMTEXT    NULL,
  father             VARCHAR(190)  NOT NULL DEFAULT '',
  father_tests       VARCHAR(190)  NOT NULL DEFAULT '',
  mother             VARCHAR(190)  NOT NULL DEFAULT '',
  mother_tests       VARCHAR(190)  NOT NULL DEFAULT '',
  hunting_tests      VARCHAR(190)  NOT NULL DEFAULT '',
  training_level     MEDIUMTEXT    NULL,
  provider_name      VARCHAR(190)  NOT NULL DEFAULT '',
  contact_person     VARCHAR(190)  NOT NULL DEFAULT '',
  email              VARCHAR(190)  NOT NULL DEFAULT '',
  phone              VARCHAR(60)   NOT NULL DEFAULT '',
  contact_notes      MEDIUMTEXT    NULL,
  dog_name           VARCHAR(190)  NOT NULL DEFAULT '',
  birth_date         VARCHAR(20)   NOT NULL DEFAULT '',
  gender             VARCHAR(10)   NOT NULL DEFAULT '',
  litter_date        VARCHAR(20)   NOT NULL DEFAULT '',
  male_count         VARCHAR(10)   NOT NULL DEFAULT '',
  female_count       VARCHAR(10)   NOT NULL DEFAULT '',
  gallery_title      VARCHAR(190)  NOT NULL DEFAULT 'Bilder',
  has_zuchtverband   TINYINT(1)    NOT NULL DEFAULT 0,
  zuchtverband       VARCHAR(190)  NOT NULL DEFAULT '',
  lat                DECIMAL(10,7) NULL,
  lng                DECIMAL(10,7) NULL,
  created_at         DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at         DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_hb_status (status),
  KEY idx_hb_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bildergalerie je Anzeige. "pfad" ist eine server-relative URL (z.B.
-- "/uploads/boersen/hundeboerse/<random>.jpg" fuer oeffentliche Einreichungen
-- oder "/images/<dateiname>" fuer ueber die bestehende Medienverwaltung im
-- Admin ausgewaehlte Bilder) - NIE Bilddaten selbst (siehe Punkt 4 des
-- Auftrags: "Bilder nicht als Base64 in MySQL speichern").
CREATE TABLE IF NOT EXISTS hundeboerse_bilder (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  anzeige_id   VARCHAR(40)     NOT NULL,
  pfad         VARCHAR(255)    NOT NULL,
  titel        VARCHAR(190)    NOT NULL DEFAULT '',
  sortierung   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  erstellt_am  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_hb_bilder_anzeige (anzeige_id, sortierung),
  CONSTRAINT fk_hb_bilder_anzeige FOREIGN KEY (anzeige_id)
    REFERENCES hundeboerse_anzeigen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wachsende Vorschlagsliste "Zuchtverband" (entspricht content/hundeboerse.json
-- -> "zuchtverbaende"). Wird beim Admin-Speichern automatisch um neue, bisher
-- unbekannte Werte ergaenzt (siehe api/hundeboerse/admin/speichern.php).
CREATE TABLE IF NOT EXISTS hundeboerse_zuchtverbaende (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(190) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_hb_zuchtverband_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genau 1 Zeile: Hero-Bild (content/hundeboerse.json -> "hero_bild") + ein
-- optimistischer Versionszaehler fuer die Konfliktpruefung beim
-- Admin-Speichern (ersetzt die bisherige Git-SHA-Konflikterkennung aus
-- admin.js/doSave() - gleiches Prinzip, jetzt auf MySQL-Basis).
CREATE TABLE IF NOT EXISTS hundeboerse_meta (
  id         TINYINT UNSIGNED NOT NULL,
  version    BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  hero_bild  VARCHAR(255)     NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO hundeboerse_meta (id, version, hero_bild)
  VALUES (1, 0, NULL)
  ON DUPLICATE KEY UPDATE id = id;

-- ============================================================================
-- WAFFENBOERSE
-- ============================================================================

CREATE TABLE IF NOT EXISTS waffenboerse_anzeigen (
  id                                VARCHAR(40)   NOT NULL,
  status                            ENUM('pending','published','rejected','archived') NOT NULL DEFAULT 'pending',
  titel                             VARCHAR(190)  NOT NULL DEFAULT '',
  kategorie                         VARCHAR(190)  NOT NULL DEFAULT '',
  hersteller                        VARCHAR(190)  NOT NULL DEFAULT '',
  modell                            VARCHAR(190)  NOT NULL DEFAULT '',
  zustand                           VARCHAR(20)   NOT NULL DEFAULT '',
  preis                             VARCHAR(40)   NOT NULL DEFAULT '',
  preis_typ                         VARCHAR(20)   NOT NULL DEFAULT '',
  erwerbsberechtigung_erforderlich  TINYINT(1)    NOT NULL DEFAULT 0,
  -- HTML aus dem TipTap-Editor im Admin bzw. serverseitig aus Freitext
  -- erzeugtes, sicheres Absatz-HTML bei oeffentlichen Einreichungen (siehe
  -- api/waffenboerse/anzeigen.php) - wird von detail.html unveraendert per
  -- innerHTML ausgegeben, muss also beim Einfuegen serverseitig bereits
  -- gegen Script-/Event-Handler-Injection bereinigt sein.
  beschreibung                      MEDIUMTEXT    NULL,
  plz                               VARCHAR(10)   NOT NULL DEFAULT '',
  ort                               VARCHAR(190)  NOT NULL DEFAULT '',
  versand_moeglich                  TINYINT(1)    NOT NULL DEFAULT 0,
  versandkosten                     VARCHAR(40)   NOT NULL DEFAULT '',
  anbieter_name                     VARCHAR(190)  NOT NULL DEFAULT '',
  anbieter_email                    VARCHAR(190)  NOT NULL DEFAULT '',
  anbieter_telefon                  VARCHAR(60)   NOT NULL DEFAULT '',
  erstellt_am                       DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  aktualisiert_am                   DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_wb_status (status),
  KEY idx_wb_erstellt (erstellt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waffenboerse_bilder (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  anzeige_id   VARCHAR(40)     NOT NULL,
  pfad         VARCHAR(255)    NOT NULL,
  titel        VARCHAR(190)    NOT NULL DEFAULT '',
  sortierung   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  erstellt_am  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_wb_bilder_anzeige (anzeige_id, sortierung),
  CONSTRAINT fk_wb_bilder_anzeige FOREIGN KEY (anzeige_id)
    REFERENCES waffenboerse_anzeigen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kaliber sind im bestehenden Modell ein Array (content/waffenboerse.json ->
-- "kaliber": [...]), bewusst nicht komma-getrennt in einer Spalte (Kaliber
-- koennen selbst Kommas enthalten, z.B. "7x65R / 12/70 / 5,6x52R" - siehe
-- Kommentar in waffenboerse/anbieten.html) - deshalb eine eigene Zeile pro
-- Kaliber, analog zu den Bildern.
CREATE TABLE IF NOT EXISTS waffenboerse_kaliber (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  anzeige_id   VARCHAR(40)     NOT NULL,
  kaliber      VARCHAR(100)    NOT NULL,
  sortierung   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_wb_kaliber_anzeige (anzeige_id, sortierung),
  CONSTRAINT fk_wb_kaliber_anzeige FOREIGN KEY (anzeige_id)
    REFERENCES waffenboerse_anzeigen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Erweiterbare Kategorienliste (content/waffenboerse.json -> "kategorien"),
-- im Admin ueber "+ Neu"/🗑 verwaltet (waffenboerseKategorieAdd/-Delete in
-- admin/admin.js) - bleibt unveraendert, schreibt jetzt nur nach MySQL statt
-- in die JSON-Datei.
CREATE TABLE IF NOT EXISTS waffenboerse_kategorien (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(190) NOT NULL,
  sortierung SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_wb_kategorie_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO waffenboerse_kategorien (name, sortierung) VALUES
  ('Büchsen', 1), ('Flinten', 2), ('Kombinierte Waffen', 3), ('Kurzwaffen', 4),
  ('Optik', 5), ('Zubehör', 6), ('Sonstiges', 7)
  ON DUPLICATE KEY UPDATE name = name;

CREATE TABLE IF NOT EXISTS waffenboerse_meta (
  id       TINYINT UNSIGNED NOT NULL,
  version  BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO waffenboerse_meta (id, version)
  VALUES (1, 0)
  ON DUPLICATE KEY UPDATE id = id;

-- ============================================================================
-- KONTAKTFORMULAR (09.09.2026, "Kontaktformular ausfallsicher machen")
-- ============================================================================
--
-- Ziel: keine Kontaktanfrage darf verloren gehen, auch wenn SMTP (noch)
-- nicht konfiguriert ist oder gerade ausfaellt (siehe api/contact.php: die
-- Anfrage wird IMMER zuerst hier gespeichert, der Mailversand ist danach
-- eine zusaetzliche, vom Speichern unabhaengige Benachrichtigung).
--
-- Nutzt dieselbe Datenbank/Verbindung wie die Hundeboerse/Waffenboerse
-- (api/lib/db.php, DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS bzw.
-- config/db.local.php) - bewusst keine zweite Datenbankarchitektur.
--
-- Speichert bewusst mehr als die im Auftrag genannten Mindestfelder
-- (zusaetzlich bereits_jaeger/hegering): beide werden im bestehenden
-- Formular (kontakt/index.html) bereits abgefragt und von api/contact.php
-- bereits eingelesen - sie ebenfalls zu verwerfen wuerde dem eigentlichen
-- Ziel ("keine Anfrage darf verloren gehen") widersprechen.
CREATE TABLE IF NOT EXISTS kontakt_anfragen (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(190)    NOT NULL DEFAULT '',
  email           VARCHAR(190)    NOT NULL DEFAULT '',
  telefon         VARCHAR(60)     NOT NULL DEFAULT '',
  anliegen        VARCHAR(190)    NOT NULL DEFAULT '',
  bereits_jaeger  VARCHAR(20)     NOT NULL DEFAULT '',
  hegering        VARCHAR(190)    NOT NULL DEFAULT '',
  nachricht       MEDIUMTEXT      NULL,
  status          ENUM('neu','bearbeitet') NOT NULL DEFAULT 'neu',
  -- Ob/warum der Benachrichtigungs-Mailversand (zusaetzlich zur hier immer
  -- erfolgten Speicherung) fehlgeschlagen ist - siehe api/contact.php.
  -- mail_fehler enthaelt bewusst nur eine kurze, interne Fehlerkategorie
  -- (z.B. "server_not_configured"/"send_failed"), NIE die rohe SMTP-
  -- Fehlermeldung (koennte Zugangsdaten/Serverdetails enthalten).
  mail_versendet  TINYINT(1)      NOT NULL DEFAULT 0,
  mail_fehler     VARCHAR(190)    NULL,
  erstellt_am     DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  -- Zeitpunkt der letzten Statusaenderung durch einen Admin/Redakteur
  -- (siehe api/kontakt/admin/status.php) - NULL, solange nur "neu".
  bearbeitet_am   DATETIME(3)     NULL,
  PRIMARY KEY (id),
  KEY idx_ka_status (status),
  KEY idx_ka_erstellt (erstellt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
