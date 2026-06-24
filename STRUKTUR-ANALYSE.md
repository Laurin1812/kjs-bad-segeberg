# CMS Struktur-Analyse

> Reine Bestandsaufnahme des Admin-Bereichs (`admin/admin.js`).
> Stand: automatisch erzeugt aus dem `NAV`-Baum und den `render*`-Funktionen.
> **Es wurde kein Code geändert** – dies ist nur eine Übersicht.

---

## 1. Alle Admin-Seiten (alle `data-navkey` Einträge)

Der `NAV`-Baum (ab `admin/admin.js:38`) definiert folgende Einträge. `group:true` = nur Ordner/Container (kein eigenes Formular).

| # | navkey | Label | Formular-Typ | Content-Datei |
|---|--------|-------|--------------|---------------|
| – | `startseite` | 🏠 Startseite | `startseite` | `content/startseite.json` |
| – | `jaeger` | 🦌 Jäger | *(Gruppe)* | – |
| 1 | `jaeger-ueber-uns` | Über uns | `standard` | `content/jaeger/ueber-uns.json` |
| – | `kjs` | KJS Segeberg | *(Gruppe)* | – |
| 2 | `kjs-uebersicht` | Übersicht | `standard` | `content/jaeger/uebersicht.json` |
| 3 | `vorstand` | Vorstand | `personen` | `content/vorstand.json` |
| 4 | `obleute` | Obleute | `personen` | `content/obleute.json` |
| 5 | `hegeringe` | Hegeringe | `hegeringe` | `content/hegeringe.json` |
| 6 | `mitglied-werden` | Mitglied werden | `standard` (+Sonderfeld) | `content/jaeger/mitglied-werden.json` |
| 7 | `jaeger-werden` | Jäger/in werden | `standard` | `content/jaeger/jaeger-werden.json` |
| 8 | `niederwild` | Niederwild | `standard` | `content/jaeger/niederwild.json` |
| 9 | `hochwild` | Hochwild | `standard` | `content/jaeger/hochwild.json` |
| 10 | `schiessobleute` | Schießobleute | `standard` | `content/jaeger/schiessobleute.json` |
| 11 | `satzung` | Satzung | `standard` | `content/jaeger/satzung.json` |
| 12 | `landesjagdverband` | Landesjagdverband | `standard` | `content/jaeger/landesjagdverband.json` |
| – | `new-kjs` | ➕ Neue KJS-Unterseite | `neueSeite` (Add) | dyn. → `content/seiten-kjs` |
| 13 | `kjm` | Kreisjägermeister | `kjm` | `content/kreisjjaegermeister.json` |
| – | `aufgaben` | Aufgaben der KJS | *(Gruppe)* | – |
| 14 | `auf-schiessen` | Schießwesen | `standard` | `content/aufgaben/schiessen.json` |
| – | `auf-hunde` | Hundeausbildung | *(Gruppe)* | – |
| 15 | `auf-hunde-uebersicht` | Übersichtsseite | `standard` | `content/aufgaben/hundeausbildung.json` |
| – | `jagdhundeschule-gruppe` | 🐕 Jagdhundeschule (21 Seiten) | *(Gruppe)* | – |
| – | `new-jagdhundeschule` | ➕ Neue Seite | `neueSeite` (Add) | dyn. → `content/aufgaben/hundeausbildung` |
| 16 | `auf-schweiss` | Schweißhundeführer | `standard` | `content/aufgaben/schweisshunde.json` |
| 17 | `auf-jugend` | Jugendarbeit | `standard` | `content/aufgaben/jugend.json` |
| 18 | `auf-jagdhorn` | Jagdhornblasen | `standard` | `content/aufgaben/jagdhorn.json` |
| 19 | `auf-natur` | Naturschutz | `standard` | `content/aufgaben/naturschutz.json` |
| 20 | `auf-jungwild` | Jungwildrettung | `standard` | `content/aufgaben/jungwildrettung.json` |
| – | `new-aufgaben` | ➕ Neue Aufgaben-Unterseite | `neueSeite` (Add) | dyn. → `content/seiten-aufgaben` |
| 21 | `infomobil` | Infomobil | **`tiptap`** | `content/jaeger/infomobil.json` |
| – | `weitere` | Weitere Themen | *(Gruppe, dynamisch)* | dyn. → `content/seiten-weitere` |
| – | `verbraucher` | 🌿 Verbraucher | *(Gruppe)* | – |
| – | `verbraucher-wild` | Wildfleisch | *(Gruppe)* | – |
| 22 | `verbraucher-wild-inhalt` | Seiteninhalt | `standard` | `content/verbraucher/wildfleisch.json` |
| – | `new-sub-wild` | ➕ Neue Unterseite | `neueSeite` (Add) | dyn. → `content/seiten-sub-wildfleisch` |
| – | `verbraucher-lernort` | Lernort Natur | *(Gruppe)* | – |
| 23 | `verbraucher-lernort-inhalt` | Seiteninhalt | `standard` | `content/verbraucher/lernort-natur.json` |
| – | `new-sub-lernort` | ➕ Neue Unterseite | `neueSeite` (Add) | dyn. → `content/seiten-sub-lernort-natur` |
| – | `verbraucher-gruen` | Grünes Klassenzimmer | *(Gruppe)* | – |
| 24 | `verbraucher-gruen-inhalt` | Seiteninhalt | `standard` | `content/verbraucher/gruenes-klassenzimmer.json` |
| – | `new-sub-gruen` | ➕ Neue Unterseite | `neueSeite` (Add) | dyn. → `content/seiten-sub-gruenes-klassenzimmer` |
| – | `new-verbraucher` | ➕ Neue Verbraucher-Seite | `neueSeite` (Add) | dyn. → `content/seiten-verbraucher` |
| 25 | `termine` | 📅 Termine | `termine` | `content/termine.json` |
| 26 | `aktuelles` | 📰 Aktuelles | `aktuelles` | `content/aktuelles.json` |
| 27 | `faq` | ❓ FAQ | `faq` | `content/faq.json` |
| – | `einstellungen` | ⚙️ Einstellungen | *(Gruppe)* | – |
| 28 | `kontakt` | Kontakt & Öffnungszeiten | `einstellungen` | `content/einstellungen.json` |
| 29 | `footer` | Fußzeile | `footer` | `content/footer.json` |
| 30 | `design` | Design & Farben | `design` | `content/design.json` |
| 31 | `impressum` | Impressum | `impressum` | `content/impressum.json` |
| 32 | `nav-extra` | 🧭 Hauptnavigation erweitern | `navExtra` | `content/navigation-extra.json` |
| 33 | `nav-reihenfolge` | 🔀 Navigation & Reihenfolge | `navReihenfolge` | `content/navigation.json` |
| 34 | `benutzer` | 👥 Benutzerverwaltung | `benutzer` | *(keine Datei)* |
| 35 | `downloads` | 📥 Downloads | `downloads` | `content/downloads.json` |
| 36 | `medien` | 🖼️ Medien & Bilder | `medien` | *(keine Datei)* |
| 37 | `testseite` | 🧪 Testseite | **`tiptap`** | `content/test/testseite.json` |

> **Dynamisch erzeugte Seiten:** Die `neueSeite`-Einträge (`new-*`) sowie „Weitere Themen" legen zur Laufzeit weitere Seiten an. Diese laufen über das `standard`-Formular (mit `isDynamic:true`) und tauchen hier nicht einzeln auf.

---

## 2. Formular-Typen im Überblick

Dispatch erfolgt in `renderForm()` (`admin/admin.js:933`).

| Formular-Typ | Render-Funktion | Zweck |
|--------------|-----------------|-------|
| `standard` | `renderStandard` | Klassische Inhaltsseite (Titel/Text/Bilder), **Markdown-Editor** für Inhalt |
| `tiptap` | `renderInfomobil` | Inhaltsseite mit **TipTap Rich-Text-Editor** (HTML) |
| `startseite` | `renderStartseite` | Startseiten-Baukasten (Hero, Willkommen, Sektionen) |
| `personen` | `renderPersonen` | Personenliste (Array, Drag&Drop) – Vorstand, Obleute |
| `hegeringe` | `renderHegeringe` | Hegering-Liste (eigene Struktur) |
| `kjm` | `renderKJM` | Kreisjägermeister (Einzelperson + Grußwort + Aufgaben) |
| `aktuelles` | `renderAktuelles` | News-/Artikelliste |
| `termine` | `renderTermine` | Terminliste |
| `faq` | `renderFAQ` | FAQ-Kategorien & Fragen |
| `downloads` | `renderDownloads` | Globale Download-Liste |
| `einstellungen` | `renderEinstellungen` | Kontakt & Öffnungszeiten |
| `footer` | `renderFooter` | Fußzeilen-Texte |
| `design` | `renderDesign` | Farben, Schriften, Schriftgrößen |
| `impressum` | `renderImpressum` | Impressum-Text |
| `navExtra` | `renderNavExtra` | Zusätzliche Hauptnav-Punkte |
| `navReihenfolge` | `renderNavReihenfolge` | Navigations-Reihenfolge |
| `benutzer` | `renderBenutzer` | Benutzerverwaltung |
| `medien` | *(eigene Logik)* | Medien-/Bildverwaltung |
| `neueSeite` | *(Add-Dialog)* | Erstellt neue dynamische Seite |

---

## 3. Gruppierung der Seiten

### a) Normale Inhaltsseiten (Titel + Text + Bilder)

Diese Seiten teilen das gleiche **konzeptionelle** Schema (Seitentitel, Untertitel, Einleitung, Textinhalt, Hero-Bild, Inhaltsbild, Kontakt, Downloads) – nutzen aber **zwei verschiedene Editoren** (siehe Inkonsistenzen):

**Via `standard` (Markdown-Editor):**
- Über uns, Übersicht
- Mitglied werden, Jäger/in werden, Niederwild, Hochwild, Schießobleute, Satzung, Landesjagdverband
- Schießwesen, Hundeausbildung-Übersicht, Schweißhundeführer, Jugendarbeit, Jagdhornblasen, Naturschutz, Jungwildrettung
- Wildfleisch, Lernort Natur, Grünes Klassenzimmer (je „Seiteninhalt")
- alle dynamisch erzeugten Unterseiten

**Via `tiptap` (Rich-Text-Editor):**
- **Infomobil**
- **Testseite**

### b) Spezial-Seiten (eigene Datenstruktur)

| Seite | Struktur |
|-------|----------|
| **Startseite** | Baukasten aus Hero / Willkommen / Sektionen – kein klassischer „Textinhalt" |
| **Vorstand / Obleute** (`personen`) | Array aus Personen `{rolle, name, email, telefon, bild}` |
| **Hegeringe** (`hegeringe`) | Eigene Hegering-Datenstruktur |
| **Kreisjägermeister** (`kjm`) | Einzelperson `{name, bild, email, telefon}` + Aufgaben (Markdown) + Grußwort |
| **Aktuelles** (`aktuelles`) | Artikel-/News-Liste |
| **Termine** (`termine`) | Terminliste |
| **FAQ** (`faq`) | Kategorien mit Fragen/Antworten |
| **Downloads** (`downloads`) | Globale Dateiliste |
| **Einstellungen / Footer / Design / Impressum** | Reine Konfigurationsformulare |
| **Nav erweitern / Nav-Reihenfolge / Benutzer / Medien** | Verwaltungswerkzeuge (keine öffentliche 1:1-Seite) |

---

## 4. Inkonsistenzen

### 4.1 Zwei verschiedene Editoren für denselben Seitentyp ⚠️ (wichtigster Punkt)

„Normale Inhaltsseiten" sind **nicht** einheitlich. Felder im Vergleich:

| Feld | `standard` (`renderStandard`) | `tiptap` (`renderInfomobil`) |
|------|-------------------------------|------------------------------|
| `titel` | `fText` (Klartext) | `fText` (Klartext) |
| `untertitel` | `fText` (**Klartext**) | `fTipTap` (**Rich-Text/HTML**) |
| `intro` | `fTextarea` (**Klartext**) | `fTipTap` (**Rich-Text/HTML**) |
| `inhalt` | **Markdown** (EasyMDE) | **TipTap/HTML** |
| `bild_groesse` | ❌ nicht vorhanden | ✅ Dropdown (klein/mittel/groß) |

→ Gleicher Seitentyp, aber unterschiedliche Datenformate (Markdown vs. HTML) und unterschiedliche Felder. Eine Migration von `standard` nach `tiptap` würde Inhalts-Konvertierung erfordern.

### 4.2 Inline-Bilder im Textinhalt funktionieren grundverschieden ⚠️

| | `standard` | `tiptap` |
|---|-----------|----------|
| Editor | EasyMDE | TipTap |
| Speicherformat | Markdown `![alt](url){.klassen}` | HTML `<img class="...">` |
| Float-Bild | direkt am `<img>` / `<p>` | in `<div style="float:...">` **gewrappt** |
| Größen-Klassen | `img-klein/mittel/gross/voll` | dieselben Klassen, aber via TipTap-Extension |

→ Ein Bild, das im einen Editor eingefügt wurde, ist im anderen nicht ohne Weiteres editierbar.

### 4.3 Uneinheitliche Bild-Feldnamen

Direkte Bildfelder (`fImage`) heißen je nach Formular unterschiedlich:

- `bild`, `hero_bild` (Standard/Infomobil)
- `p-bild` (Personen)
- `kjm-bild` (Kreisjägermeister)
- `vorschaubild` (Jagdhundeschule-Sonderfall)

→ Keine einheitliche Namenskonvention für „das Bild einer Seite/Person".

### 4.4 `bild_groesse` nur bei TipTap

Das Größen-Dropdown fürs Inhaltsbild gibt es nur bei `tiptap` (Infomobil/Testseite). Standard-Seiten haben festes Inhaltsbild ohne Größenwahl.

### 4.5 Sonderfelder hart an `def.key` gekoppelt (innerhalb `standard`)

`renderStandard` / `collectStandard` enthalten Spezialzweige:
- **`mitglied-werden`** → zusätzliches Feld `antrag_url`
- **`jagdhundeschule*`** → zusätzliche Felder `vorschaubild` + `kurzbeschreibung` (Kachel-Vorschau)

→ Seitenspezifische Logik steckt im generischen Standard-Formular (per `if (def.key === ...)`), statt über einen sauberen Konfig-Mechanismus.

### 4.6 „Person" in drei verschiedenen Formen

Personen-Daten (`name`, `bild`, `email`, `telefon`) existieren in drei unterschiedlichen Strukturen:
- **`personen`**: Array via `dataKey` (Vorstand, Obleute)
- **`kjm`**: einzelnes Objekt auf oberster Ebene + Inhaltsfelder gemischt
- **`hegeringe`**: eigene Struktur

### 4.7 Gemischte Editor-Technologien im selben CMS

Aktuell drei parallele Text-Editier-Technologien:
- **EasyMDE / Markdown** → `standard`, `kjm` (Aufgaben)
- **TipTap / HTML** → `infomobil`, `testseite`
- **reine `<textarea>`** → z. B. `intro` (Standard), `grußwort` (KJM)

→ Erhöht Wartungsaufwand und Inkonsistenz im gespeicherten Datenformat.

---

## Fazit / Ansatzpunkte für das Aufräumen

1. **Editor vereinheitlichen:** Entscheidung Markdown **oder** TipTap für alle „normalen Inhaltsseiten". Aktuell ist Infomobil/Testseite der TipTap-Prototyp; die übrigen ~24 Seiten laufen noch auf Markdown.
2. **Feld-Schema vereinheitlichen:** `untertitel`/`intro` überall gleich behandeln (entweder überall Klartext oder überall Rich-Text); `bild_groesse` überall verfügbar machen oder bewusst weglassen.
3. **Bild-Handling konsolidieren:** ein gemeinsames Inline-Bild-Format + einheitliche Feldnamen.
4. **Sonderfelder entkoppeln:** `antrag_url`, `vorschaubild`, `kurzbeschreibung` aus den `if (def.key===...)`-Zweigen in eine deklarative Feld-Konfiguration pro Seite überführen.

> Hinweis: Dieses Dokument beschreibt nur den Ist-Zustand. Es wurden **keine** funktionalen Änderungen vorgenommen.
