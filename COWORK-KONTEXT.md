# KJS Bad Segeberg – Projektkontext für Cowork

**Letzter Stand: 2026-08-02 · Letzter Commit: `cae753b`**

---

## 1. Was ist dieses Projekt?

**KJS Bad Segeberg** ist die Website der Kreisjägerschaft Bad Segeberg e.V.
Es ist eine **vollständig statische HTML/CSS/JS-Site** – kein npm, kein Build-Tool, kein Framework.

- **Hosting:** Netlify (automatischer Deploy aus GitHub-Repo)
- **Admin-Bereich:** `/admin/` – Netlify Identity + git-gateway (Netlify CMS-Konzept, aber komplett selbst gebaut)
- **Inhalte:** `content/*.json`-Dateien. Der Admin liest diese via `/.netlify/git/github/contents`-API und schreibt per GitHub Contents API zurück (Commit direkt auf `main`).
- **Repo:** `main`-Branch ist Production. Commits auf `main` werden sofort deployed.

**CLAUDE.md-Regel:** Commits auf `main` dürfen nach dem Erstellen automatisch zu `origin/main` gepusht werden, ohne vorher nachzufragen.

---

## 2. Dateistruktur (die wichtigsten Dateien)

```
/
├── admin/
│   ├── index.html          # Admin-SPA-Shell (enthält tt-img-menu, tt-table-menu)
│   ├── admin.js            # gesamte Admin-Logik (~4200 Zeilen, eine IIFE)
│   └── admin.css           # Admin-Styles inkl. TipTap-Editor-CSS
├── css/
│   └── style.css           # Public-Site-CSS (inkl. img-25/50/75/100-Klassen)
├── js/
│   └── main.js             # Public-Site-JS (Navigation, Downloads, etc.)
├── content/
│   ├── test/
│   │   └── testseite.json  # Inhalt der Testseite (bearbeitbar im Admin)
│   └── *.json              # alle anderen Seiteninhalte
├── test/
│   └── testseite.html      # Öffentliche Testseite (Frontend-Darstellung)
├── jaeger/
│   └── infomobil.html      # Infomobil-Seite (nutzt dieselbe form:'tiptap'-Logik)
└── STRUKTUR-ANALYSE.md     # Übersicht aller Admin-Sektionen (wurde in diesem Projekt erstellt)
```

---

## 3. Der Admin-Bereich

### Aufbau

`admin/admin.js` ist eine große IIFE (~4200 Zeilen). Der Zustand liegt in `var S = { section, data, sha, mde, dirty, tiptapEditors, ... }`.

### NAV-Tree

Die Navigation des Admins ist als `var NAV = [...]`-Array hartcodiert (Zeile 38–117). Jeder Eintrag hat:
- `key`: eindeutiger Bezeichner
- `label`: Anzeigename im Admin-Menü
- `file`: Pfad zur JSON-Datei (relativ zum Repo-Root)
- `form`: Welches Formular gerendert wird (`'tiptap'`, `'standard'`, `'personen'`, etc.)

**Testseite-Eintrag (Zeile 116):**
```js
{ key:'testseite', label:'🧪 Testseite', file:'content/test/testseite.json', form:'tiptap' }
```

### Form-Typen

| `form`-Wert | Funktion | Beschreibung |
|---|---|---|
| `'tiptap'` | `renderInfomobil()` | Rich-Text-Felder mit TipTap v2. Wird von **Infomobil** UND **Testseite** genutzt. |
| `'standard'` | `renderStandard()` | EasyMDE Markdown-Editor |
| `'personen'` | `renderPersonen()` | Listen mit drag & drop |
| `'termine'` | `renderTermine()` | Kalender-Einträge |
| `'aktuelles'` | `renderAktuelles()` | News-Artikel |
| `'faq'` | `renderFaq()` | FAQ-Akkordeon |
| `'downloads'` | `renderDownloads()` | Dateiliste mit Upload |
| `'medien'` | `renderMedien()` | Bildverwaltung (Upload/Löschen) |

---

## 4. TipTap-Editor (form:'tiptap') – ALLES was wichtig ist

### Warum TipTap?

Infomobil und die neu gebaute Testseite brauchen Rich-Text (Überschriften, Fett, Listen, Tabellen, Bilder mit Float). EasyMDE (Markdown) reicht dafür nicht.

### Laden (ESM, CDN, Retry)

TipTap v2 wird via `import()` aus `esm.sh` (primär) oder `cdn.jsdelivr.net` (Fallback) geladen. Das passiert in `ensureTiptap()` (admin.js:3922):

```js
var PKGS = ['core', 'starter-kit', 'extension-underline', 'extension-image',
            'extension-table', 'extension-table-row', 'extension-table-cell', 'extension-table-header'];
// 6 Versuche, abwechselnd esm.sh und jsdelivr, mit Backoff (400ms–2.5s)
```

Wenn alles erfolgreich war: `window.TipTap = { Editor, StarterKit, Underline, Image, Table, ... }`.

`tiptapReady()` (admin.js:3916) prüft, ob alle 8 Extensions geladen sind.

Beim Admin-Start wird `ensureTiptap()` sofort aufgerufen (eager loading), damit der Editor schon bereit ist wenn der Nutzer zu `form:'tiptap'` navigiert.

**WICHTIG:** Ein Editor darf niemals mit leerem Inhalt initialisiert werden, wenn das Modul noch lädt – deshalb zeigt `initTiptap()` erst "Editor wird geladen..." und initialisiert erst nach dem Laden.

### Initialisierung

`initTiptap(fieldId, rawContent)` (admin.js:3982):
1. Konvertiert alten Markdown via `convertMarkdownToHtml()` (automatische Migration)
2. Bereinigt `ProseMirror-selectednode`-Klassen-Artefakte aus gespeichertem HTML
3. Erstellt TipTap-Editor mit `ImageWithClass`-Extension (Custom-Klassen-Attribut)
4. Speichert Instanz in `S.tiptapEditors[fieldId]`

**Drei Felder:** `untertitel`, `intro`, `inhalt` (alle in `renderInfomobil` / `collectInfomobil`)

### Bild-System

Bilder werden als `<img class="SIZE POS">` gespeichert. **Kein div-Wrapper, kein clearfix, kein float-Wrapper.**

**Größen-Klassen:**
- `img-25` = 25% Breite
- `img-50` = 50% Breite
- `img-75` = 75% Breite
- `img-100` = 100% Breite (Standard)

**Positions-Klassen:**
- `img-links` = float:left
- `img-rechts` = float:right
- `img-zentriert` = kein float, zentriert

Die CSS-Klassen sind **identisch in Admin (`admin/admin.css`) und Public-Site (`css/style.css`)** → was der Admin zeigt ist 1:1 was der Besucher sieht.

**Bild einfügen:** Über den "Bild einfügen"-Button in der TipTap-Toolbar → öffnet Medien-Modal → `insertMdImage()` (admin.js:3224) fügt das Bild via `activeEditor.chain().setImage({ src, alt, class: 'img-50 img-links' })` ein.

**Liste-Schutz:** Wenn der Cursor beim Einfügen in einer List ist, wird das Bild NACH der Liste eingefügt (admin.js:3254–3262). Float-Bilder dürfen nicht in Listen sitzen.

**Bild-Kontextmenü:** Klick auf ein Bild im Editor → `showTtImgMenu()` → Buttons 25%/50%/75%/100% und Links/Zentriert/Rechts. Ändert die CSS-Klasse via `posAtDOM + setNodeMarkup`.

### Datenverlust-Schutz

`getTiptapValue(fieldId, oldValue, label)` (admin.js:4096):
- Wenn Editor nicht initialisiert → gibt `oldValue` zurück (nie leer speichern)
- Wenn HTML wäre leer, aber `oldValue` hatte Inhalt → `confirm()`-Dialog bevor gelöscht wird

### Markdown→HTML-Migration

Alter Inhalt (aus EasyMDE-Zeiten) war Markdown. TipTap braucht HTML. `convertMarkdownToHtml(input)` (admin.js:3875) erkennt automatisch:
- **(A)** Keine HTML-Tags → reiner Markdown → `marked.parse()`
- **(B)** HTML + Markdown-Marker (z.B. `<p>## Überschrift</p>`) → Top-Level-Knoten einzeln behandeln
- **(C)** Sauberes HTML → unverändert lassen

Beim Einfügen (Paste) von Markdown-Text: `handlePaste` in TipTap wandelt automatisch um.

---

## 5. Die Testseite – Kern der aktuellen Arbeit

### Wozu?

Die Testseite ist eine vollwertige Admin-Seite mit `form:'tiptap'`, auf der alle TipTap-Features getestet und demonstriert werden können, ohne echten Produktionsinhalte zu gefährden. Sie ist **auch öffentlich zugänglich** (aber als interne Testseite gekennzeichnet).

### Dateien

| Datei | Zweck |
|---|---|
| `content/test/testseite.json` | Inhalt (bearbeitbar im Admin unter "🧪 Testseite") |
| `test/testseite.html` | Frontend-Darstellung der Testseite |

### content/test/testseite.json – Datenstruktur

```json
{
  "titel": "...",
  "untertitel": "<html>...",     // TipTap-HTML
  "intro": "<html>...",          // TipTap-HTML
  "inhalt": "<html>...",         // TipTap-HTML
  "hero_bild": "...",            // URL zum Hero-Hintergrundbild
  "bild": "...",                 // URL zum Inhaltsbild
  "bild_groesse": "img-50",      // img-25/50/75/100 (neues Schema)
  "bild_alt": "...",
  "kontakt_name": "...",
  "kontakt_email": "...",
  "downloads": []
}
```

### test/testseite.html – Rendering

Die Seite fetcht `/content/test/testseite.json` und rendert:
1. `renderField(d.untertitel)` → direkt als innerHTML (HTML-Inhalte vom Admin)
2. `renderField(d.intro)` → direkt als innerHTML
3. Inhaltsbild mit `bildGroesseClass(d.bild_groesse)` als CSS-Klasse
4. `renderField(d.inhalt)` → direkt als innerHTML
5. Kontaktblock (optional)
6. Google Kalender (optional, aus `content/einstellungen.json`)

`renderField()` erkennt: Hat es `<`-Tags → direkt HTML. Kein HTML → `marked.parse()` (Abwärtskompatibilität).

`bildGroesseClass()` mappt alte Werte (`'klein'`, `'mittel'`, `'gross'`) auf neue (`img-25`, `img-50`, `img-100`).

### Admin-Formular für Testseite

`renderInfomobil(def, data)` (admin.js:1222) wird für **beide** Seiten genutzt – Infomobil UND Testseite.

**Unterschied:** Wenn `def.key === 'testseite'`, werden **Feld-Hilfetexte** angezeigt (grauer erklärungstext zwischen Label und Eingabefeld). Bei Infomobil nicht.

```js
var isTestseite = def.key === 'testseite';
var hint = isTestseite ? ttFieldHint : function() { return ''; };
```

**Feld-Hilfetexte** (nur Testseite):
- Seitentitel: "Die große Überschrift ganz oben auf der Seite. Kurz und klar halten."
- Untertitel: "Kurzer Text direkt unter dem Titel, als Einstieg in die Seite. Optional."
- Einleitungstext: "Der erste Textblock der Seite, oberhalb des Hauptinhalts. Formatierung, Listen und Tabellen sind möglich."
- Textinhalt: "Der Haupttext der Seite. Hier kommt der eigentliche Inhalt rein – mit Formatierung, Listen, Tabellen und Bildern."
- Sowie alle Bilder- und Kontaktfelder

Die Hilfetexte werden via `insertHintAfterLabel()` direkt nach dem `</label>` und vor dem Eingabefeld eingefügt.

### Inhaltsbild-Größe (fBildGroesse)

Kein Dropdown mehr, sondern 4 Buttons: **25% | 50% | 75% | 100%**

```js
function fBildGroesse(val) { ... }
window.bildGroesseSet = function(v) { ... };
```

Das rendert Button-Group + verstecktes `<input id="f-bild_groesse">`. `collectInfomobil` liest via `gv('bild_groesse')`.

### Testseite im Hauptmenü (TEMPORÄR)

In `js/main.js` gibt es einen Block (mit `// TEMPORÄR`-Kommentar), der einen "🧪 Testseite"-Link in die öffentliche Navigation einfügt. Das soll irgendwann entfernt werden, wenn die Testseite nicht mehr gebraucht wird.

---

## 6. Downloads-Box (js/main.js)

Die Downloads-Box ist eine generische Komponente, die auf mehreren Seiten genutzt wird. Letzte Änderung (Commit `cae753b`):

**Download-Dateiname basiert jetzt auf dem eingegebenen Titel, nicht dem technischen Dateinamen.**

```js
function sanitizeFilenamePart(s) { /* entfernt \/:*?"<>|, kappt Punkte/Leerzeichen an Rändern */ }

function buildDownloadFilename(titel, datei) {
  // 1. Titel vorhanden → bereinigt + NFC-normalisiert (macOS-Umlaute)
  // 2. Kein Titel → Dateiname ohne Zeitstempel-Präfix (\d{10,}-)
  // 3. Immer: Original-Endung anhängen, falls nicht schon enthalten
}
```

Der `download="..."` Attribut-Wert wird via `buildActions(datei, name, item.titel)` gesetzt.

---

## 7. CSS: Bild-Klassen (admin.css + style.css)

### Admin (admin/admin.css)
```css
.tt-mount .ProseMirror { overflow: auto; }           /* BFC – verhindert float-Probleme */
.tt-mount .ProseMirror ul,
.tt-mount .ProseMirror ol { padding-left: 1.6rem; margin: .4rem 0; overflow: hidden; }
.tt-mount .ProseMirror img.img-25  { width: 25%; }
.tt-mount .ProseMirror img.img-50  { width: 50%; }
.tt-mount .ProseMirror img.img-75  { width: 75%; }
.tt-mount .ProseMirror img.img-100 { width: 100%; }
.tt-mount .ProseMirror img.img-links     { float: left;  margin: .25rem 1rem .5rem 0; }
.tt-mount .ProseMirror img.img-rechts    { float: right; margin: .25rem 0 .5rem 1rem; }
.tt-mount .ProseMirror img.img-zentriert { float: none;  display: block; margin: 1rem auto; }
```

### Public-Site (css/style.css)
```css
.main-content img.img-25,  #seite-inhalt img.img-25,  #page-inhalt img.img-25  { width: 25%;  max-width: 25%; }
/* ... img-50, img-75, img-100 analog ... */
.main-content img.img-links  { float: left;  margin-right: 1rem; margin-bottom: 1rem; max-width: 50%; }
.main-content img.img-rechts { float: right; margin-left: 1rem;  margin-bottom: 1rem; max-width: 50%; }
.main-content ul, .main-content ol { overflow: hidden; } /* BFC gegen float-Überlappung */
```

---

## 8. Bekannte Design-Entscheidungen & Fallstricke

### Float-Bild / Listen-Problem
Float-Bilder können Listen-Bullet-Points überlappen, wenn ein Bild mit `float:left/right` neben einer `<ul>/<ol>` steht. Gelöst durch:
1. `overflow: hidden` auf ul/ol (BFC-Trick)
2. Bilder werden beim Einfügen NACH der Liste positioniert, wenn der Cursor in einer Liste ist

### ProseMirror-selectednode-Klasse
TipTap fügt beim Selektieren eines Bildes die CSS-Klasse `ProseMirror-selectednode` zum `<img>`-Element hinzu. Diese darf NIE in den gespeicherten Inhalt gelangen. Bereinigung:
- In `getTiptapValue()`: `editor.getHTML().replace(/\s*ProseMirror-selectednode/g, '')`
- In `initTiptap()`: gleiche Bereinigung beim Laden (falls alte Daten die Klasse enthalten)

### TipTap-Lade-Race-Condition
ESM-Module laden asynchron. Wenn `renderInfomobil` aufgerufen wird bevor TipTap geladen ist, wird "Editor wird geladen..." gezeigt und erst nach erfolgreichem `ensureTiptap()` initialisiert. Niemals leere Editoren erzeugen.

### Tabellencheck beim Bild-Einfügen
Es gab viele Versuche, beim Bild-Einfügen zu erkennen ob der Cursor in einer Tabelle ist (um das Bild nach der Tabelle zu platzieren). **Wurde letztendlich gestrichen** – der Nutzer entschied, dieses Feature ist nicht nötig. Der aktuelle Code behandelt nur Listen, nicht Tabellen.

### Datenverlust-Schutz (getData + confirm)
Ursache des ursprünglichen Problems: TipTap lädt nicht → `getHTML()` gibt `''` zurück → leer gespeichert. Fix: `getTiptapValue(fieldId, oldValue, label)` – wenn Editor nicht da → `oldValue` zurück. Wenn Inhalt wäre leer aber vorher nicht → `confirm()`.

---

## 9. Was als nächstes kommt / Ziel

Das Ziel war, die **Testseite als solide Grundbasis** aufzubauen, auf der man alle TipTap-Features vollständig testen und demonstrieren kann. Aktueller Stand: Die Testseite ist fertig und funktioniert – sowohl im Admin (mit Hilfetexten, Button-Größenwahl, allen TipTap-Feldern) als auch als öffentlich zugängliche Seite.

**Offener Punkt aus der letzten Session (wurde unterbrochen, noch nicht umgesetzt):**
> Beim Umwandeln von Markdown zu HTML entstehen manchmal LEERE Listenpunkte (`<li></li>` ohne Inhalt), wenn im Markdown ein einzelnes `*` oder `-` ohne Text dahinter steht. In `convertMarkdownToHtml` (und ggf. beim Laden/Einfügen): leere Listenpunkte automatisch entfernen. Wenn dadurch eine ganze Liste leer wird, auch die leere `<ul>/<ol>` entfernen.

---

## 10. Git-Workflow

- Branch: `main` (einziger Branch)
- Remote: `origin/main` (GitHub, auto-deploy auf Netlify)
- Commits dürfen sofort gepusht werden (CLAUDE.md-Regel)
- Commit-Nachrichten auf Deutsch oder Englisch, kurz und präzise
- Netlify macht Auto-Deploy bei jedem Push auf `main`

---

## 11. Admin-Bereiche im Überblick

(Vollständige Analyse in `STRUKTUR-ANALYSE.md`)

| Admin-Sektion | Datei(en) | Form-Typ |
|---|---|---|
| 🏠 Startseite | content/startseite.json | startseite |
| 🦌 Jäger > Über uns | content/jaeger/ueber-uns.json | standard |
| Vorstand / Obleute | content/vorstand.json | personen |
| Hegeringe | content/hegeringe.json | hegeringe |
| Infomobil | content/jaeger/infomobil.json | **tiptap** |
| 📅 Termine | content/termine.json | termine |
| 📰 Aktuelles | content/aktuelles.json | aktuelles |
| ❓ FAQ | content/faq.json | faq |
| ⚙️ Einstellungen | content/einstellungen.json | einstellungen |
| 📥 Downloads | content/downloads.json | downloads |
| 🖼️ Medien | – | medien |
| 🧪 Testseite | content/test/testseite.json | **tiptap** |

---

## 12. Schnellreferenz: Wichtige Funktionen in admin.js

| Funktion | Zeile | Was sie macht |
|---|---|---|
| `renderInfomobil(def, data)` | 1222 | Rendert das Admin-Formular für Infomobil + Testseite |
| `collectInfomobil(data)` | 1273 | Liest alle Felder aus (zum Speichern) |
| `initTiptap(fieldId, rawContent)` | 3982 | Initialisiert einen TipTap-Editor |
| `getTiptapValue(fieldId, oldValue, label)` | 4096 | Liest TipTap-Inhalt sicher aus |
| `destroyAllTiptaps()` | 4117 | Räumt alle Editor-Instanzen auf |
| `ensureTiptap()` | 3922 | Lädt TipTap-ESM mit Retry + Fallback-CDN |
| `tiptapReady()` | 3916 | Prüft ob alle 8 Extensions geladen sind |
| `convertMarkdownToHtml(input)` | 3875 | Markdown→HTML (3-Pfad-Erkennung) |
| `hasMarkdownMarkers(s)` | 3857 | Erkennt Markdown in Text |
| `insertMdImage()` | 3224 | Fügt gewähltes Bild in aktiven Editor ein |
| `showTtImgMenu(imgEl)` | 4131 | Zeigt Bild-Kontextmenü (Größe/Position) |
| `applyTtImgClass(newClass)` | 4159 | Ändert Bild-Klassen via setNodeMarkup |
| `fBildGroesse(val)` | 1175 | Rendert 4-Button-Größenwahl für Inhaltsbild |
| `ttFieldHint(text)` | 1210 | Rendert grauen Hilfetext unter Label |
| `insertHintAfterLabel(html, hint)` | 1217 | Positioniert Hilfetext zwischen Label und Feld |
| `buildDownloadFilename(titel, datei)` | js/main.js:659 | Berechnet Dateiname für Download-Link |
