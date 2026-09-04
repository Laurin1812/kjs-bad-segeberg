/* KJS Segeberg – main.js */

// ── Markdown-Rendering: Backtick-Codespans deaktivieren ──────────────────
// Plattdeutsche Texte schreiben den Apostroph manchmal als Backtick
// (z.B. "op`n Stohl" = "op'n Stohl"). marked.js interpretiert ein Paar
// Backticks aber als Inline-Code-Span - enthält ein Beitrag zufällig zwei
// solcher Apostroph-Backticks, wird der komplette Text dazwischen fälschlich
// in Monospace-Schrift dargestellt (Frank-Bug-Report 20.08.2026, Beitrag
// "Plattschölers buuten Nistkastens"). Echte Code-Formatierung wird in den
// Vereinstexten nie gebraucht, deshalb wird die Codespan-Erkennung hier über
// marked.js' offizielle Erweiterungs-API abgeschaltet - zentral hier, damit
// es für jede Seite gilt, die diese main.js einbindet (kein Einzelfix pro
// Seite nötig). Einzelne Backticks werden dadurch als normales Zeichen
// dargestellt statt als Code-Formatierung interpretiert.
if (typeof marked !== 'undefined' && marked && typeof marked.use === 'function') {
  marked.use({
    tokenizer: {
      codespan: function () { return undefined; }
    }
  });
}

// ── content/*.json laden ─────────────────────────────────────────────────
// Direkt von der Netlify-Website (relative Pfade), kein GitHub Raw, kein CDN.
// Jedes Speichern im Admin erzeugt einen GitHub-Commit → Netlify löst
// automatisch einen Redeploy aus, die neue Version ist danach live.
// Cache-Buster (?_=Date.now()) verhindert, dass der Browser eine ältere
// Version aus seinem eigenen HTTP-Cache anzeigt.
function fetchContent(path) {
  return fetch(path + (path.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
}

// ── Tabellen aus dem Admin/TipTap scrollbar machen (Desktop-Fallback) ────
// Wichtig: die <table> selbst bleibt display:table (Spaltenberechnung des
// Browsers funktioniert nur so korrekt) – nur eine umschließende Box
// bekommt overflow-x:auto. Die Termine-Tabelle hat ihre eigene Wrapper-Box
// und wird hier bewusst ausgeschlossen. Per MutationObserver, weil viele
// Seiten ihren Inhalt erst nach einem fetch() per innerHTML einfügen.
function wrapContentTables(root) {
  root.querySelectorAll('table:not(.termine-table)').forEach(function (table) {
    if (table.parentElement && table.parentElement.classList.contains('content-table-wrap')) return;
    var wrap = document.createElement('div');
    wrap.className = 'content-table-wrap';
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
}

// ── Tabellen auf dem Handy: Karten-Ansicht statt seitlichem Wischen ──────
// Frank-Wunsch: das horizontale Scrollen/Swipen in Tabellen (bisher über
// .content-table-wrap/.termine-table-wrap mit overflow-x:auto gelöst) soll
// komplett entfallen – auf schmalen Screens muss man alles ohne Wischen
// sehen können. Lösung: jede <td> bekommt per data-label die zugehörige
// Spaltenüberschrift zugewiesen; eine CSS-Regel (siehe style.css) stapelt
// die Zeilen dann unterhalb einer bestimmten Bildschirmbreite zu Karten und
// zeigt data-label per ::before als Feldbezeichnung vor dem Wert – ganz
// ohne die Tabellen im Admin manuell anpassen zu müssen. Funktioniert für
// JEDE Tabelle (Admin/TipTap-Inhalte UND die Termine-Tabelle), da hier rein
// aus den vorhandenen <th>-Texten gelesen wird. Läuft bewusst bei jedem
// Observer-Durchlauf erneut (kein "nur einmal"-Schutz), weil z.B. die
// Termine-Tabelle ihren <tbody>-Inhalt beim Filtern komplett neu aufbaut.
function labelTableCells(root) {
  root.querySelectorAll('table').forEach(function (table) {
    var headRow = table.querySelector('thead tr');
    var bodyRows;
    if (headRow) {
      bodyRows = table.querySelectorAll('tbody tr');
    } else {
      // Keine <thead> vorhanden (z.B. einfache Markdown-Tabelle ohne
      // explizite Kopfzeile) – erste Zeile der Tabelle dient als Kopfzeile
      // und wird bei den Datenzeilen ausgeklammert.
      var allRows = table.querySelectorAll('tr');
      headRow = allRows[0];
      bodyRows = Array.prototype.slice.call(allRows, 1);
    }
    if (!headRow) return;
    var headCells = headRow.querySelectorAll('th,td');
    var labels = Array.prototype.map.call(headCells, function (c) {
      return c.textContent.trim();
    });
    if (!labels.length) return;
    // Kopfzeilen-Zellen bekommen ihr eigenes data-label ebenfalls (bisher
    // nur die Datenzellen). Wirkt sich für sich genommen auf nichts aus
    // (die Kopfzeile wird im Karten-Layout ohnehin ausgeblendet), macht
    // aber Spalten wie "E-Mail"/"Mobil" für style.css gezielt ansprechbar -
    // z.B. um Kontakt-Tabellen an ihrer Spalten-Kombination zu erkennen und
    // ihre Spaltenbreiten zentral zu verteilen (siehe style.css).
    Array.prototype.forEach.call(headCells, function (cell, i) {
      if (labels[i]) cell.setAttribute('data-label', labels[i]);
    });
    // Falls TipTap ein <colgroup> erzeugt hat: dieselben Labels auch auf die
    // <col>-Elemente übertragen. So kann eine CSS-Regel eine bestimmte
    // Spalte (z.B. "col[data-label='E-Mail']") gezielt in der Breite
    // steuern, unabhängig von ihrer Position oder der Gesamt-Spaltenzahl.
    var cols = table.querySelectorAll(':scope > colgroup > col');
    Array.prototype.forEach.call(cols, function (col, i) {
      if (labels[i]) col.setAttribute('data-label', labels[i]);
    });
    Array.prototype.forEach.call(bodyRows, function (row) {
      Array.prototype.forEach.call(row.querySelectorAll('td'), function (cell, i) {
        if (labels[i]) cell.setAttribute('data-label', labels[i]);
      });
    });
  });
}

// ── Tabellen: dynamisch stapeln statt fester Mobile-Grenze ──────────────
// Frank-Wunsch: "ob Handy, ob Bildschirm" – nie mehr seitliches Scrollen in
// Tabellen, unabhängig von der Fensterbreite. Ein fester @media-Breakpoint
// reicht nicht, weil manche Tabellen schon bei mittleren Breiten (z.B.
// Tablet/schmales Desktop-Fenster) nicht mehr reinpassen, andere auch auf
// dem Handy noch locker passen. Deshalb wird pro Tabelle live gemessen, ob
// ihre natürliche Breite den verfügbaren Platz im Wrapper überschreitet,
// und danach die .is-stacked-Klasse gesetzt/entfernt (siehe style.css für
// die eigentliche Karten-Darstellung). Läuft initial, bei jeder DOM-
// Änderung (MutationObserver, z.B. Termine-Filter) und bei jedem Resize.
//
// Wichtig: hier bewusst OHNE white-space:nowrap gemessen – normales
// Umbrechen von Zellinhalt (mehrzeilige Zellen) ist völlig in Ordnung und
// soll NICHT zum Stapeln führen, nur weil eine Zeile "nicht auf eine Zeile
// passt". Gestapelt werden soll nur, wenn selbst mit normalem Umbruch noch
// echtes horizontales Überlaufen entsteht (z.B. ein einzelnes sehr langes
// Wort/URL, die auch nach Umbruch die Spalte sprengt). Voraussetzung dafür:
// .main-content td hat KEIN word-break:break-word mehr (siehe style.css) –
// das ließ Spalten sonst bis zur Unlesbarkeit schrumpfen, ohne je als "zu
// breit" erkannt zu werden.
function applyTableLayout(root) {
  root.querySelectorAll('table').forEach(function (table) {
    var wrap = table.closest('.content-table-wrap') || table.closest('.termine-table-wrap');
    if (!wrap) return;
    wrap.classList.remove('is-stacked');
    var needsStack = table.scrollWidth > wrap.clientWidth + 1;
    wrap.classList.toggle('is-stacked', needsStack);
  });
}

var _tableRelayoutContainers = [];
var _tableRelayoutTimer = null;
function scheduleTableRelayout() {
  if (_tableRelayoutTimer) clearTimeout(_tableRelayoutTimer);
  _tableRelayoutTimer = setTimeout(function () {
    _tableRelayoutContainers.forEach(function (container) {
      applyTableLayout(container);
    });
  }, 150);
}
window.addEventListener('resize', scheduleTableRelayout);

document.querySelectorAll('.main-content, #seite-inhalt, #page-inhalt').forEach(function (container) {
  _tableRelayoutContainers.push(container);
  wrapContentTables(container);
  labelTableCells(container);
  applyTableLayout(container);
  new MutationObserver(function () {
    wrapContentTables(container);
    labelTableCells(container);
    applyTableLayout(container);
  }).observe(container, { childList: true, subtree: true });
});

// Mobile Navigation
const navToggle = document.getElementById('navToggle');
const mobileNav = document.getElementById('mobileNav');
const mobileNavClose = document.getElementById('mobileNavClose');

function openMobileNav() {
  mobileNav.classList.add('open');
  document.body.style.overflow = 'hidden';
  navToggle.classList.add('open');
}
function closeMobileNav() {
  mobileNav.classList.remove('open');
  document.body.style.overflow = '';
  navToggle.classList.remove('open');
}

if (navToggle)     navToggle.addEventListener('click', openMobileNav);
if (mobileNavClose) mobileNavClose.addEventListener('click', closeMobileNav);

// Close mobile nav on ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobileNav(); });

// Markdown-Bilder mit Größen-/Ausrichtungsklassen: ![alt](pfad){.img-mittel .img-rechts}
// marked.js kennt diese Attribut-Syntax nicht von Haus aus. Wir registrieren dafür eine
// eigene Inline-Extension über die offizielle marked.use()-API (marked.parse selbst ist
// in dieser Version ein schreibgeschützter Getter und kann nicht überschrieben werden).
(function() {
  if (typeof marked === 'undefined' || !marked || typeof marked.use !== 'function') return;
  var RULE = /^!\[([^\]]*)\]\(([^)\s]+)\)\{([^}]+)\}/;

  marked.use({
    extensions: [{
      name: 'imageWithClasses',
      level: 'inline',
      start: function(src) {
        var m = src.match(/!\[/);
        return m ? m.index : void 0;
      },
      tokenizer: function(src) {
        var match = RULE.exec(src);
        if (!match) return;
        var classes = match[3].trim().split(/\s+/)
          .map(function(c) { return c.replace(/^\./, ''); })
          .filter(Boolean)
          .join(' ');
        return { type: 'imageWithClasses', raw: match[0], alt: match[1], href: match[2], classes: classes };
      },
      renderer: function(token) {
        var altSafe = String(token.alt).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        var srcSafe = String(token.href).replace(/"/g, '&quot;');
        return '<img src="' + srcSafe + '" alt="' + altSafe + '" class="' + token.classes + '" loading="lazy">';
      }
    }]
  });
})();

// Externe Links automatisch in neuem Tab öffnen (target="_blank" + rel="noopener noreferrer")
// Betrifft alle Links, die auf eine andere Domain zeigen (z.B. Mitgliedsantrag, PDF-Downloads,
// Landesjagdverband, externe Seiten) – egal ob sie schon im HTML stehen oder erst später aus
// Markdown-Inhalten / JSON-Daten per fetch() + innerHTML nachgeladen werden.
(function() {
  function isExternal(a) {
    var href = a.getAttribute('href');
    if (!href) return false;
    try {
      var url = new URL(href, window.location.href);
      return (url.protocol === 'http:' || url.protocol === 'https:') &&
             url.hostname !== window.location.hostname;
    } catch (e) {
      return false;
    }
  }

  function applyExternal(a) {
    a.setAttribute('target', '_blank');
    var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
    if (rel.indexOf('noopener') === -1) rel.push('noopener');
    if (rel.indexOf('noreferrer') === -1) rel.push('noreferrer');
    a.setAttribute('rel', rel.join(' '));
  }

  function scan(node) {
    if (!node || node.nodeType !== 1) return;
    if (node.tagName === 'A' && isExternal(node)) applyExternal(node);
    if (node.querySelectorAll) {
      node.querySelectorAll('a[href]').forEach(function(a) {
        if (isExternal(a)) applyExternal(a);
      });
    }
  }

  // Initialer Durchlauf über die bereits vorhandenen Links
  scan(document.documentElement);

  // Beobachtet das Dokument dauerhaft auf neu eingefügte Links (z.B. asynchron geladene
  // Markdown-/JSON-Inhalte) und versieht auch diese automatisch mit target="_blank"
  if (window.MutationObserver) {
    new MutationObserver(function(mutations) {
      mutations.forEach(function(m) {
        m.addedNodes.forEach(scan);
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();

// Kaputte Bilder in Inhaltsbereichen still ausblenden (statt Fragezeichen-Platzhalter)
(function() {
  var CONTENT_SELECTORS = '#page-inhalt, #seite-inhalt, .main-content, .page-hero__bg';
  function hideIfBroken(img) {
    if (!img || img.complete === false) return;  // noch nicht geladen – onerror greift später
    img.onerror = function() {
      this.style.display = 'none';
    };
  }
  function scanImages(root) {
    if (!root || root.nodeType !== 1) return;
    if (root.tagName === 'IMG') { hideIfBroken(root); return; }
    if (root.querySelectorAll) {
      root.querySelectorAll('img').forEach(hideIfBroken);
    }
  }
  // Initial scan
  document.querySelectorAll(CONTENT_SELECTORS + ' img').forEach(hideIfBroken);
  // Dynamisch nachgeladene Inhalte
  if (window.MutationObserver) {
    new MutationObserver(function(mutations) {
      mutations.forEach(function(m) { m.addedNodes.forEach(scanImages); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();

// Hinweis: Die frühere separate "Active nav link highlighting" hier wurde
// mit Architektur-Audit Phase 2 (31.08.2026) entfernt - sie funktionierte
// wegen unterschiedlich tiefer relativer Links (z.B. "../hundeboerse/index.html")
// ohnehin nur zufällig (z.B. gar nicht auf den Hundebörse-Unterseiten) und ist
// jetzt durch die URL-basierte Berechnung im zentralen Navigations-Modul
// weiter oben (isActiveSection(), direkt beim Rendern von .main-nav/.mobile-nav
// aus navigation.json) ersetzt, die zuverlässig für alle Seitentiefen gilt.

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      const offset = 100;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

// Topbar & Geschäftsstelle dynamisch laden (aus content/einstellungen.json)
//
// Einheitliche Icons: Frank wollte, dass überall dieselben Icons verwendet
// werden wie auf der Kontakt-Seite (dort als KONTAKT_ICONS definiert) –
// statt der bisherigen Emojis (📧 📞 🏠 ✉️) in Kopfzeile und Kontaktbox.
// Da diese beiden Bereiche auf JEDER Seite vorkommen, werden die Icons hier
// zentral an einer Stelle eingesetzt statt in jeder HTML-Datei einzeln.
var ICONS = {
  mail:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
  phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
  home:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7"/><path d="M9 22V12h6v10"/><path d="M5 10v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10"/></svg>',
  pin:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>'
};

// Zieht aus dem freien Postadresse-Text eine "Tel: ..."- und "E-Mail: ..."-Zeile
// heraus (case-insensitive, auch "Telefon:"), damit sie separat als klickbare
// Icon-Zeile angezeigt werden können statt als reiner Fließtext in der
// Postadresse selbst zu stehen. Identische Logik wie in kontakt/index.html.
function splitPostadresse(raw) {
  var telefon = '', email = '';
  var lines = (raw || '').split('\n').filter(function(line) {
    var telMatch = line.match(/^\s*tel(?:efon)?\s*[:.]?\s*(.+)$/i);
    if (telMatch) { telefon = telMatch[1].trim(); return false; }
    var mailMatch = line.match(/^\s*e-?mail\s*[:.]?\s*(.+)$/i);
    if (mailMatch) { email = mailMatch[1].trim(); return false; }
    return true;
  });
  return { text: lines.join('\n'), telefon: telefon, email: email };
}

(function() {
  var topbarLinks = document.querySelectorAll('.topbar__left a');
  var boxes = document.querySelectorAll('.contact-box');
  if (!topbarLinks.length && !boxes.length) return;

  // Kopfzeile: Emoji vor dem Link durch das einheitliche SVG-Icon ersetzen
  topbarLinks.forEach(function(a) {
    var span = a.parentElement;
    if (!span || span.querySelector('svg')) return;
    var icon = a.href.indexOf('mailto:') > -1 ? ICONS.mail
             : a.href.indexOf('tel:') > -1 ? ICONS.phone
             : null;
    if (!icon) return;
    Array.prototype.slice.call(span.childNodes).forEach(function(node) {
      if (node.nodeType === 3) span.removeChild(node); // altes Emoji (Textknoten)
    });
    span.insertAdjacentHTML('afterbegin', '<span class="topbar__icon">' + icon + '</span>');
  });

  fetchContent('/content/einstellungen.json')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      // Topbar aktualisieren (eigene Telefonnummer für die Kopfzeile, fällt sonst auf die
      // allgemeine Kontakt-Telefonnummer zurück – so kann Frank beide unabhängig im Admin pflegen)
      var topbarTel = d.telefon_header || d.telefon;
      topbarLinks.forEach(function(a) {
        if (a.href.indexOf('mailto:') > -1 && d.email) {
          a.href = 'mailto:' + d.email;
          a.textContent = d.email;
        }
        if (a.href.indexOf('tel:') > -1 && topbarTel) {
          a.href = 'tel:' + topbarTel.replace(/\s|-/g,'');
          a.textContent = topbarTel;
        }
      });
      // Kontaktbox aktualisieren: gleiche Struktur wie der Geschäftsstelle-Block
      // auf der Kontaktseite (kontakt/index.html) – Überschrift, Reihenfolge und
      // Feld-Aufteilung müssen exakt übereinstimmen (Frank hatte die abweichende
      // Groß-/Kleinschreibung "Adresse KJS" vs. "GESCHÄFTLICHE POSTADRESSE"
      // bemängelt). Ablauf: Geschäftsstelle-Überschrift → Adresse → Telefon →
      // E-Mail → Postadresse (Frank persönlich) → deren eigene Telefon-/
      // E-Mail-Zeilen als separate klickbare Icon-Zeilen.
      var adresseHtml = d.adresse ? d.adresse.trim().split('\n').join('<br>') : '';

      // Postadresse (Frank persönlich): Telefon/E-Mail sollen als eigene,
      // klickbare Icon-Zeilen erscheinen – genau wie beim Geschäftsstelle-Block
      // oben. Bevorzugt werden die dedizierten Felder postadresse_telefon/
      // postadresse_email; ist eins leer, wird automatisch aus dem
      // Postadresse-Fließtext eine Zeile "Tel: ..." bzw. "E-Mail: ..."
      // herausgelöst (self-migrierend, kein manuelles Nacharbeiten im Admin
      // nötig, bis Frank die neuen Felder separat pflegt).
      var postadresseSplit = splitPostadresse(d.postadresse || '');
      var postadresseHtml = postadresseSplit.text.split('\n').join('<br>');
      var postadresseTelefon = d.postadresse_telefon || postadresseSplit.telefon;
      var postadresseEmail   = d.postadresse_email   || postadresseSplit.email;

      boxes.forEach(function(box) {
        box.innerHTML =
          '<h4>Geschäftsstelle</h4>' +
          (adresseHtml ? '<p><span class="cb-icon">' + ICONS.home + '</span><span>' + adresseHtml + '</span></p>' : '') +
          (d.telefon  ? '<p><span class="cb-icon">' + ICONS.phone + '</span><a href="tel:' + d.telefon.replace(/\s|\/|\./g,'') + '">' + d.telefon + '</a></p>' : '') +
          (d.email    ? '<p><span class="cb-icon">' + ICONS.mail + '</span><a href="mailto:' + d.email + '">' + d.email + '</a></p>' : '') +
          (postadresseHtml ?
            '<h4 class="contact-box__sub">Postadresse</h4>' +
            '<p><span class="cb-icon">' + ICONS.pin + '</span><span>' + postadresseHtml + '</span></p>'
            : '') +
          (postadresseTelefon ? '<p><span class="cb-icon">' + ICONS.phone + '</span><a href="tel:' + postadresseTelefon.replace(/\s|\/|\./g,'') + '">' + postadresseTelefon + '</a></p>' : '') +
          (postadresseEmail   ? '<p><span class="cb-icon">' + ICONS.mail + '</span><a href="mailto:' + postadresseEmail + '">' + postadresseEmail + '</a></p>' : '');
      });
    })
    .catch(function() {});
})();

/* =========================================================
   ZENTRALE NAVIGATION (Architektur-Audit Phase 2, 31.08.2026)

   Vorher: pro HTML-Datei komplett ausgeschriebenes <ul class="main-nav">
   und eine eigene flache <details>-Liste fürs Handy-Menü (~42 Dateien),
   zusätzlich nachträglich per main.js umsortiert/umbenannt anhand von
   navigation.json, UND ein drittes, unabhängiges System, das eigene
   Admin-Unterseiten (seiten-kjs.json usw.) direkt ins bereits gerenderte
   DOM nachschob. Drei Mechanismen für eine einzige sichtbare Navigation -
   das führte u.a. dazu, dass "Hundevermittlung" (per Admin angelegt) am
   Desktop im Aufgaben-Flyout auftauchte, im Handy-Menü aber komplett fehlte,
   weil die Handy-Spiegelung nur bestehende <li> umsortierte, nie aber neue
   einfügte - und dazu, dass der aktive Menüpunkt auf den Hundebörse-
   Unterseiten (anbieten.html/detail.html) nie gesetzt wurde, weil dort schlicht
   niemand das statische class="active" von Hand ergänzt hatte.

   Jetzt: navigation.json ist die EINE Quelle für Reihenfolge, Beschriftung,
   Link und Sichtbarkeit des Hauptmenüs. Die bereits bestehenden, im
   Admin-Panel "Navigation & Reihenfolge" per Drag & Drop editierbaren Felder
   (sektionsnamen/hauptmenu/jaeger_dropdown/kjs/aufgaben/verbraucher) bleiben
   unverändert in Struktur und Bedeutung, damit dieses Panel unverändert
   weiterfunktioniert - ergänzt wurden nur zwei neue, rein strukturelle
   Metadaten-Felder (hauptmenu_meta/jaeger_dropdown_meta) mit Label/Link für
   die Punkte, die vorher ausschließlich im statischen HTML standen. Eigene
   Admin-Unterseiten werden jetzt VOR dem Rendern in dieselben Datenarrays
   gemischt statt hinterher per DOM-Manipulation eingefügt - Desktop und
   Handy entstehen dadurch aus exakt denselben Daten (Punkt 5 der
   Phase-2-Vorgabe), inklusive derselben eigenen Unterseiten.
   ========================================================= */
(function () {
  var mainNavRoot = document.getElementById('mainNav');
  var mobileNavRoot = document.getElementById('mobileNavList');
  if (!mainNavRoot || !mobileNavRoot) { window.__navReady = Promise.resolve(); return; }

  // Absichtlich KEINE zweite vollständige Navigationsstruktur als Fallback
  // (das wäre wieder eine zweite, dauerhaft mitzupflegende Datenquelle,
  // Punkt 6 der Phase-2-Vorgabe) - nur ein minimaler Not-Anker, falls
  // navigation.json ausnahmsweise nicht ladbar ist, damit die Seite nicht
  // komplett ohne Hauptnavigation dasteht.
  var FALLBACK_NAV = {
    sektionsnamen: {},
    hauptmenu: ['startseite', 'jaeger', 'verbraucher', 'aktuelles', 'termine', 'faq', 'service', 'kontakt'],
    hauptmenu_meta: {
      startseite:  { label: 'Startseite',  href: '/',                        navkey: 'startseite' },
      jaeger:      { href: '#', navkey: 'jaeger' },
      verbraucher: { href: '#', navkey: 'verbraucher' },
      aktuelles:   { label: 'Aktuelles',   href: '/aktuelles/index.html',    navkey: 'aktuelles' },
      termine:     { label: 'Termine',     href: '/termine/index.html',      navkey: 'termine' },
      faq:         { label: 'FAQ',         href: '/faq/index.html',          navkey: 'faq' },
      service:     { label: 'Service',     href: '/service.html',            navkey: 'service' },
      kontakt:     { label: 'Kontakt',     href: '/kontakt/index.html',      navkey: 'kontakt' }
    },
    // Hundebörse/Waffenbörse (04.09.2026, Frank-Wunsch): kein eigener
    // Hauptmenü-Eintrag mehr, sondern flache Leaf-Einträge im Jäger-
    // Dropdown, direkt hinter Infomobil einsortiert (vor Partner).
    jaeger_dropdown: ['kreisjjaegermeister', 'ueber-uns', 'kjs-segeberg', 'aufgaben', 'infomobil', 'hundeboerse', 'waffenboerse', 'partner'],
    jaeger_dropdown_meta: {
      'kreisjjaegermeister': { label: 'Kreisjägermeister', href: '/kreisjjaegermeister/index.html' },
      'ueber-uns':           { label: 'Über uns',          href: '/jaeger/ueber-uns.html' },
      'kjs-segeberg':        { dropdown: true },
      'aufgaben':            { dropdown: true },
      'infomobil':           { label: 'Infomobil', href: '/jaeger/infomobil.html' },
      'hundeboerse':         { label: 'Hundebörse', href: '/hundeboerse/index.html' },
      'waffenboerse':        { label: 'Waffenbörse', href: '/waffenboerse/index.html' },
      'partner':             { label: 'Partner', href: '/partner/index.html' }
    },
    kjs: [], aufgaben: [], verbraucher: []
  };

  // Netlify liefert intern verlinkte Seiten ohne ".html"-Endung aus und kürzt
  // ".../index.html" sogar auf nur den Ordner ("Pretty URLs") - das greift
  // aber nur bei Links, die schon beim Deploy als statisches HTML vorliegen.
  // Von main.js per JS erzeugte <a>-Elemente durchlaufen diese Umschreibung
  // nicht. Damit per JSON gerenderte Links optisch/im href-Attribut exakt
  // gleich aussehen wie die vorher statischen (kein ".html" im Linkziel),
  // wird dieselbe Kürzung hier clientseitig nachgebildet.
  function prettyHref(href) {
    if (!href || href === '#' || /^https?:\/\//i.test(href)) return href;
    var h = href.replace(/(^|\/)index\.html?$/i, '$1');
    if (h === '') h = '/';
    h = h.replace(/\.html?$/i, '');
    return h;
  }

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Welcher Hauptpunkt auf der aktuell angezeigten Seite als aktiv gilt.
  // Ersetzt die frühere zweigleisige Lösung (von Hand gesetztes
  // class="active" pro Datei UND eine zusätzliche, mit relativen Pfaden
  // nicht zuverlässig funktionierende Laufzeit-Erkennung, siehe Kommentar
  // weiter oben in dieser Datei) durch eine einzige, aus dem aktuellen
  // URL-Pfad berechnete Regel (Punkt 4 der Phase-2-Vorgabe).
  // Hundebörse/Waffenbörse (04.09.2026): kein eigener Hauptmenü-Eintrag
  // mehr (siehe FALLBACK_NAV/jaeger_dropdown oben) - die eigenen Prefixe
  // sind deshalb entfernt und stattdessen unter "jaeger" einsortiert, damit
  // der Hauptpunkt "Jäger" auf diesen Seiten als aktiv markiert wird.
  var SECTION_PREFIXES = {
    jaeger: ['/jaeger/', '/kreisjjaegermeister/', '/aufgaben/', '/partner/', '/hundeboerse/', '/waffenboerse/'],
    verbraucher: ['/verbraucher/'],
    aktuelles: ['/aktuelles/'],
    termine: ['/termine/'],
    faq: ['/faq/'],
    kontakt: ['/kontakt/']
  };
  function isActiveSection(key) {
    var path = window.location.pathname;
    if (key === 'startseite') return /^\/(index\.html?)?$/i.test(path);
    if (key === 'service') return /^\/service(\.html)?$/i.test(path);
    return (SECTION_PREFIXES[key] || []).some(function (p) { return path.indexOf(p) === 0; });
  }

  function leafHtml(item) {
    return '<li><a href="' + escHtml(prettyHref(item.href)) + '">' + escHtml(item.label) + '</a></li>';
  }

  function flyoutHtml(labelText, items) {
    return '<li class="has-sub"><a href="#">' + escHtml(labelText) + ' <span class="arrow-right">&#9658;</span></a>' +
      '<ul class="dropdown dropdown--sub">' + items.map(leafHtml).join('') + '</ul></li>';
  }

  function mobileLeafHtml(item) {
    return '<li><a href="' + escHtml(prettyHref(item.href)) + '">' + escHtml(item.label) + '</a></li>';
  }

  function mobileDetailsHtml(labelText, itemsHtml) {
    return '<li><details><summary>' + escHtml(labelText) + '</summary>' +
      '<ul class="mobile-nav__sub">' + itemsHtml + '</ul></details></li>';
  }

  // Inhalt des Jäger-Dropdowns wird für Desktop (verschachtelte Flyouts) UND
  // Handy (eine flache Liste, Punkt 5 der Phase-2-Vorgabe) aus demselben
  // jaeger_dropdown-Array + denselben kjs/aufgaben-Daten gebaut - nur die
  // Ausgabe (onLeaf/onFlyout) unterscheidet sich.
  function buildJaegerChildren(nav, onLeaf, onFlyout) {
    var sn = nav.sektionsnamen || {};
    var jdMeta = nav.jaeger_dropdown_meta || {};
    var out = '';
    (nav.jaeger_dropdown || []).forEach(function (jkey) {
      var jmeta = jdMeta[jkey];
      if (!jmeta || jmeta.hidden) return;
      if (jkey === 'kjs-segeberg') out += onFlyout(sn.kjs || 'KJS Segeberg', nav.kjs || []);
      else if (jkey === 'aufgaben') out += onFlyout(sn.aufgaben || 'Aufgaben der Kreisjägerschaft', nav.aufgaben || []);
      else out += onLeaf(jmeta);
    });
    return out;
  }

  function renderDesktopNav(nav) {
    var sn = nav.sektionsnamen || {};
    var html = '';

    (nav.hauptmenu || []).forEach(function (key) {
      if (key === 'jaeger') {
        var sub = buildJaegerChildren(nav, leafHtml, flyoutHtml);
        // "Weitere Themen": seit 22.08.2026 auf Laurin-Wunsch deaktiviert
        // (siehe admin.js) - Platzhalter bleibt unsichtbar im DOM (kein
        // funktionales Risiko, spätere Aufräumaktion außerhalb dieser
        // Phase). Bewusst nicht Teil von jaeger_dropdown_meta, da dauerhaft
        // leer/inaktiv.
        sub += '<li class="has-sub" id="weitere-themen-item" style="display:none;">' +
          '<a href="#">Weitere Themen <span class="arrow-right">&#9658;</span></a>' +
          '<ul class="dropdown dropdown--sub" id="weitere-themen-sub"></ul></li>';
        html += '<li' + (isActiveSection('jaeger') ? ' class="active"' : '') + '>' +
          '<a href="#" data-navkey="jaeger">' + escHtml(sn.jaeger || 'Jäger') + ' <span class="arrow">▾</span></a>' +
          '<ul class="dropdown" id="jaeger-dropdown">' + sub + '</ul></li>';
        return;
      }
      if (key === 'verbraucher') {
        var vSub = (nav.verbraucher || []).map(leafHtml).join('');
        html += '<li' + (isActiveSection('verbraucher') ? ' class="active"' : '') + '>' +
          '<a href="#" data-navkey="verbraucher">' + escHtml(sn.verbraucher || 'Verbraucher') + ' <span class="arrow">▾</span></a>' +
          '<ul class="dropdown">' + vSub + '</ul></li>';
        return;
      }
      var meta = (nav.hauptmenu_meta || {})[key];
      if (!meta) return;
      html += '<li' + (isActiveSection(key) ? ' class="active"' : '') + '>' +
        '<a href="' + escHtml(prettyHref(meta.href)) + '" data-navkey="' + escHtml(meta.navkey || key) + '">' +
        escHtml(meta.label || key) + '</a></li>';
    });

    mainNavRoot.innerHTML = html;
  }

  function renderMobileNav(nav) {
    var sn = nav.sektionsnamen || {};
    var html = '';

    (nav.hauptmenu || []).forEach(function (key) {
      if (key === 'jaeger') {
        var sub = buildJaegerChildren(nav, mobileLeafHtml, function (label, items) {
          return items.map(mobileLeafHtml).join('');
        });
        html += mobileDetailsHtml(sn.jaeger || 'Jäger', sub);
        return;
      }
      if (key === 'verbraucher') {
        html += mobileDetailsHtml(sn.verbraucher || 'Verbraucher', (nav.verbraucher || []).map(mobileLeafHtml).join(''));
        return;
      }
      var meta = (nav.hauptmenu_meta || {})[key];
      if (!meta) return;
      html += '<li><a href="' + escHtml(prettyHref(meta.href)) + '">' + escHtml(meta.label || key) + '</a></li>';
    });

    mobileNavRoot.innerHTML = html;
  }

  // Hauptmenü/Dropdowns beim Verlassen mit der Maus mit kurzer Verzögerung
  // schließen (Frank-Bug-Report: sofortiges Zuklappen beim leicht diagonalen
  // Rüberfahren zum Untermenü, weil das bisher rein per CSS ":hover"
  // gesteuert war). Läuft jetzt erst NACH dem Rendern (vorher eine
  // eigenständige IIFE weiter unten in dieser Datei, siehe Hinweis dort),
  // weil .main-nav/.has-sub erst nach dem Laden von navigation.json existieren.
  function wireHoverFlyouts() {
    var CLOSE_DELAY = 350; // ms
    var timers = new WeakMap();
    function openNow(elm) {
      var t = timers.get(elm);
      if (t) { clearTimeout(t); timers.delete(elm); }
      elm.classList.add('nav-open');
    }
    function closeDelayed(elm) {
      var t = timers.get(elm);
      if (t) clearTimeout(t);
      t = setTimeout(function () { elm.classList.remove('nav-open'); timers.delete(elm); }, CLOSE_DELAY);
      timers.set(elm, t);
    }
    function wire(selector) {
      document.querySelectorAll(selector).forEach(function (elm) {
        elm.addEventListener('mouseenter', function () { openNow(elm); });
        elm.addEventListener('mouseleave', function () { closeDelayed(elm); });
      });
    }
    wire('.main-nav > li');   // Hauptmenü-Dropdowns (Jäger, Verbraucher, ...)
    wire('.has-sub');         // Verschachtelte Flyout-Untermenüs (KJS Segeberg, Aufgaben ...)
  }

  function fetchJsonSafe(path) {
    return fetchContent(path).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  function filteredSeiten(list) {
    return (list || []).filter(function (s) { return s.veroeffentlicht === true && s.in_navigation === true; })
      .map(function (s) { return { label: s.nav_label || s.titel, href: '/seiten/?s=' + encodeURIComponent(s.slug) }; });
  }

  // Eigene Admin-Unterseiten (seiten-kjs.json/seiten-aufgaben.json/
  // seiten-verbraucher.json + legacy seiten.json mit bereich-Feld) werden
  // VOR dem Rendern in die kjs/aufgaben/verbraucher-Arrays gemischt, an den
  // Anfang gestellt (das entspricht der bisherigen sichtbaren Reihenfolge,
  // die durch das alte nachträgliche Umsortieren zufällig entstand - siehe
  // vorherige main.js-Version). Dadurch entstehen Desktop- und Handy-Menü
  // aus derselben, bereits vollständigen Datenbasis.
  function mergeDynamicSeiten(nav, results) {
    var dynKjs = filteredSeiten(results[1] && results[1].seiten);
    var dynAufgaben = filteredSeiten(results[2] && results[2].seiten);
    var dynVerbraucher = filteredSeiten(results[3] && results[3].seiten);

    ((results[4] && results[4].seiten) || [])
      .filter(function (p) { return p.veroeffentlicht === true && p.in_navigation === true; })
      .forEach(function (p) {
        var entry = { label: p.nav_label || p.titel, href: '/seiten/?s=' + encodeURIComponent(p.slug) };
        var bereich = p.bereich || 'weitere-themen';
        if (bereich === 'kjs') dynKjs.push(entry);
        else if (bereich === 'aufgaben') dynAufgaben.push(entry);
        else if (bereich === 'verbraucher') dynVerbraucher.push(entry);
        // 'weitere-themen': Flyout ist seit 22.08.2026 deaktiviert, Eintrag bleibt ungenutzt.
      });

    return {
      sektionsnamen: nav.sektionsnamen || {},
      hauptmenu: nav.hauptmenu || FALLBACK_NAV.hauptmenu,
      hauptmenu_meta: nav.hauptmenu_meta || FALLBACK_NAV.hauptmenu_meta,
      jaeger_dropdown: nav.jaeger_dropdown || FALLBACK_NAV.jaeger_dropdown,
      jaeger_dropdown_meta: nav.jaeger_dropdown_meta || FALLBACK_NAV.jaeger_dropdown_meta,
      kjs: dynKjs.concat(nav.kjs || []),
      aufgaben: dynAufgaben.concat(nav.aufgaben || []),
      verbraucher: dynVerbraucher.concat(nav.verbraucher || [])
    };
  }

  // Eigene Hauptpunkte aus navigation-extra.json, vor FAQ eingefügt - jetzt
  // in Desktop UND Handy (vorher fehlte diese Einfügung im Handy-Menü
  // komplett, aktuell nicht sichtbar, da navigation-extra.json derzeit leer ist).
  function insertNavigationExtra() {
    return fetchJsonSafe('/content/navigation-extra.json').then(function (data) {
      if (!data || !data.hauptpunkte || !data.hauptpunkte.length) return;

      data.hauptpunkte.forEach(function (hp) {
        var seiten = (hp.seiten || []).filter(function (s) { return s.veroeffentlicht === true && s.in_navigation === true; });
        if (!seiten.length || !hp.label) return;

        var desktopLi = document.createElement('li');
        var mobileHtml;
        if (seiten.length === 1) {
          var href = escHtml('/seiten/?s=' + encodeURIComponent(seiten[0].slug));
          var label = escHtml(hp.label);
          desktopLi.innerHTML = '<a href="' + href + '">' + label + '</a>';
          mobileHtml = '<li><a href="' + href + '">' + label + '</a></li>';
        } else {
          var subHtml = seiten.map(function (s) {
            return '<li><a href="' + escHtml('/seiten/?s=' + encodeURIComponent(s.slug)) + '">' + escHtml(s.nav_label || s.titel) + '</a></li>';
          }).join('');
          desktopLi.innerHTML = '<a href="#">' + escHtml(hp.label) + ' <span class="arrow">&#9662;</span></a>' +
            '<ul class="dropdown">' + subHtml + '</ul>';
          mobileHtml = mobileDetailsHtml(hp.label, subHtml);
        }

        var faqLink = Array.prototype.find.call(mainNavRoot.querySelectorAll(':scope > li > a'), function (a) {
          return a.textContent.trim() === 'FAQ';
        });
        if (faqLink) mainNavRoot.insertBefore(desktopLi, faqLink.closest('li'));
        else mainNavRoot.appendChild(desktopLi);

        var mobileFaqLi = Array.prototype.find.call(mobileNavRoot.querySelectorAll(':scope > li'), function (li) {
          var a = li.querySelector(':scope > a');
          return a && a.textContent.trim() === 'FAQ';
        });
        var mobileWrap = document.createElement('div');
        mobileWrap.innerHTML = mobileHtml;
        var mobileEl = mobileWrap.firstElementChild;
        if (mobileFaqLi) mobileNavRoot.insertBefore(mobileEl, mobileFaqLi);
        else mobileNavRoot.appendChild(mobileEl);
      });
    }).catch(function () {});
  }

  // WICHTIG: window.__navReady muss SOFORT (synchron) ein echtes Promise sein
  // - sonst liest die "Verwandte Seiten"-Rechtsnavigation (weiter unten in
  // dieser Datei, per Promise.resolve(window.__navReady).then(...)) noch
  // "undefined" aus und wartet dadurch gar nicht wirklich.
  window.__navReady = Promise.all([
    fetchJsonSafe('/content/navigation.json'),
    fetchJsonSafe('/content/seiten-kjs.json'),
    fetchJsonSafe('/content/seiten-aufgaben.json'),
    fetchJsonSafe('/content/seiten-verbraucher.json'),
    fetchJsonSafe('/content/seiten.json')
  ]).then(function (results) {
    var merged = mergeDynamicSeiten(results[0] || FALLBACK_NAV, results);
    renderDesktopNav(merged);
    renderMobileNav(merged);
    wireHoverFlyouts();
    return insertNavigationExtra();
  }).catch(function () {
    // navigation.json nicht ladbar (oder unerwarteter Fehler beim Rendern):
    // minimaler Not-Anker (siehe FALLBACK_NAV oben), damit die Seite nicht
    // komplett ohne Hauptnavigation dasteht.
    var merged = mergeDynamicSeiten(FALLBACK_NAV, []);
    renderDesktopNav(merged);
    renderMobileNav(merged);
    wireHoverFlyouts();
  });
})();

/* =========================================================
   ZENTRALER FOOTER (Architektur-Audit Phase 3A, 01.09.2026)

   Vorher: pro HTML-Datei ein komplett ausgeschriebener
   <footer class="site-footer">…</footer>-Block (~42 Dateien) mit
   fachlich unterschiedlichem Spalteninhalt je nach Seite (eigene
   "Aufgaben"-Spalte auf den 8 aufgaben/*.html-Seiten, eigene
   "Verbraucher"-Spalte auf der Verbraucher-Seite, 2 Rechtsseiten
   (impressum.html/datenschutz.html) sogar nur mit einer Mini-Fußzeile
   ohne Logo/Spalten) - dazu ein eigenes <script> pro Seite, das
   content/footer.json lud und nur Über-uns-Text/Copyright/Social-Links
   nachträglich ins bereits vorhandene statische Markup schrieb (inkl.
   Spiegelung der Social-Links in die Topbar).

   Jetzt: content/footer.json ist die EINE Quelle für den gesamten
   Footer-Inhalt - Über-uns-Text, Copyright, Social-Links UND (neu,
   analog zu hauptmenu_meta/jaeger_dropdown_meta in navigation.json aus
   Phase 2) die drei laut Laurin vereinheitlichten Spalten-Linklisten
   (spalte_ueber_kjs/spalte_uebersicht/spalte_informationen). Jede Seite
   bindet nur noch einen leeren Container ein
   (<footer class="site-footer" id="siteFooter"></footer>), der hier
   zentral befüllt wird - identisch auf jeder Seite, unabhängig von der
   Verzeichnistiefe (root-relative Links + prettyHref(), gleiches
   Prinzip wie im Navigations-Modul weiter oben).
   ========================================================= */
(function () {
  var footerRoot = document.getElementById('siteFooter');
  if (!footerRoot) return;

  // Absichtlich KEIN zweiter vollständiger Footer als Fallback (Punkt 8
  // der Phase-3A-Vorgabe - keine dauerhaft zweite, separat zu pflegende
  // Footer-Struktur) - nur ein minimaler Not-Anker (Copyright +
  // Impressum/Datenschutz/Login), falls footer.json ausnahmsweise nicht
  // ladbar ist, damit die Seite nicht komplett ohne Fußzeile dasteht.
  var FALLBACK_FOOTER = {
    ueber_text: '', facebook_url: '', instagram_url: '',
    copyright: 'Kreisjägerschaft Segeberg e.V.',
    spalte_ueber_kjs: [], spalte_uebersicht: [], spalte_informationen: []
  };

  function prettyHref(href) {
    if (!href || href === '#' || /^https?:\/\//i.test(href)) return href;
    var h = href.replace(/(^|\/)index\.html?$/i, '$1');
    if (h === '') h = '/';
    h = h.replace(/\.html?$/i, '');
    return h;
  }

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function colHtml(title, items) {
    if (!items || !items.length) return '';
    var lis = items.map(function (it) {
      return '<li><a href="' + escHtml(prettyHref(it.href)) + '">' + escHtml(it.label) + '</a></li>';
    }).join('');
    return '<div class="footer-col"><h5>' + escHtml(title) + '</h5><ul>' + lis + '</ul></div>';
  }

  function renderFooter(d) {
    var html =
      '<div class="container">' +
        '<div class="footer-grid">' +
          '<div class="footer-about">' +
            '<img src="/images/logo.png" alt="KJS Logo" style="height:58px;width:auto;margin-bottom:1rem;">' +
            '<span class="footer-about__name">Kreisjägerschaft Segeberg e.V.</span>' +
            '<span class="footer-about__sub">Mitglied im Landesjagdverband Schleswig-Holstein</span>' +
            (d.ueber_text ? '<p>' + escHtml(d.ueber_text) + '</p>' : '') +
            '<div class="footer-social">' +
              '<a href="' + escHtml(d.facebook_url || '#') + '" aria-label="Facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>' +
              '<a href="' + escHtml(d.instagram_url || '#') + '" aria-label="Instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></a>' +
            '</div>' +
          '</div>' +
          colHtml('Über die KJS', d.spalte_ueber_kjs) +
          colHtml('Schnellübersicht', d.spalte_uebersicht) +
          colHtml('Informationen', d.spalte_informationen) +
        '</div>' +
        '<div class="footer-bottom">' +
          '<span>' + escHtml(d.copyright) + '</span>' +
          '<div class="footer-bottom__links">' +
            '<a href="/impressum.html">Impressum</a>' +
            '<a href="/datenschutz.html">Datenschutz</a>' +
            '<a href="/admin/" class="admin-login-link" target="_blank" rel="noopener noreferrer">Login</a>' +
          '</div>' +
        '</div>' +
      '</div>';
    footerRoot.innerHTML = html;

    // Social-Links spiegeln sich zusätzlich in die Kopfzeile (Topbar) -
    // gleiches Verhalten wie vorher im Pro-Seiten-Skript.
    if (d.facebook_url) {
      var fbTop = document.querySelector('.topbar__social a[aria-label="Facebook"]');
      if (fbTop) fbTop.href = d.facebook_url;
    }
    if (d.instagram_url) {
      var igTop = document.querySelector('.topbar__social a[aria-label="Instagram"]');
      if (igTop) igTop.href = d.instagram_url;
    }
  }

  fetchContent('/content/footer.json')
    .then(function (r) { return r.json(); })
    .then(renderFooter)
    .catch(function () { renderFooter(FALLBACK_FOOTER); });
})();

// Contact form handler (Formsubmit.co)
var contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = contactForm.querySelector('button[type="submit"]');

    // Pflichtfelder prüfen
    var required = contactForm.querySelectorAll('[required]');
    var valid = true;
    required.forEach(function(field) {
      if (!field.value.trim()) { field.style.borderColor = '#c0392b'; valid = false; }
      else { field.style.borderColor = ''; }
    });
    if (!valid) return;

    btn.textContent = 'Wird gesendet …';
    btn.disabled = true;

    var data = new FormData(contactForm);
    var json = {
      _subject: 'Neue Kontaktanfrage – KJS Segeberg',
      _captcha: 'false'
    };
    data.forEach(function(val, key) {
      if (!key.startsWith('_') && key !== '_honey') json[key] = val;
    });

    fetch('https://formsubmit.co/ajax/frank.huelser@kjs-segeberg.de', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(json)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success === 'true' || res.success === true) {
        btn.textContent = '✓ Nachricht gesendet!';
        btn.style.background = 'var(--green-main)';
        contactForm.reset();
      } else { throw new Error(); }
    })
    .catch(function() {
      btn.textContent = 'Fehler – bitte erneut versuchen';
      btn.style.background = '#c0392b';
      btn.disabled = false;
    });
  });
}

// ── "Dokumente & Downloads" pro Seite ────────────────────────────────────
// Im Admin können pro Seite Dokumente (z.B. PDFs) mit eigener Beschriftung
// hinterlegt werden (data.downloads = [{titel, datei}]). Diese werden hier
// generisch – ohne Änderungen an den einzelnen Seiten-Templates – als
// eigene "Downloads"-Box am Ende des Seiteninhalts eingefügt, sobald die
// jeweilige content/*.json ein nicht-leeres "downloads"-Array enthält.
(function () {
  var path = window.location.pathname;
  // Netlify liefert "pretty URLs" ohne ".html"-Endung aus (z.B. /jaeger/infomobil
  // statt /jaeger/infomobil.html), ohne Redirect – window.location.pathname
  // enthält dann kein ".html". Für den Vergleich daher zuerst normalisieren.
  var basePath = path.replace(/\.html$/, '');

  // Sonderfall: Kreisjägermeister-Seite ist eine index.html mit eigenem
  // Content-Pfad und eigener Markup-Struktur (kein #page-inhalt/#page-main).
  var isKJM = /\/kreisjjaegermeister\/index$/.test(basePath) || /\/kreisjjaegermeister\/?$/.test(basePath);

  // Index-/Verzeichnis-Seiten (Startseite, .../index, .../) haben ihren
  // eigenen Aufbau und werden hier nicht behandelt.
  var isIndex = basePath === '' || /\/$/.test(basePath) || /\/index$/.test(basePath);
  if (!isKJM && isIndex) return;

  var contentPath = isKJM ? '/content/kreisjjaegermeister.json' : '/content' + basePath + '.json';

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Einheitliche, plattformunabhängige Icons (statt Emojis, die je nach
  // OS/Browser sehr unterschiedlich – teils unschön – dargestellt werden).
  var ICON_PDF    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
  var ICON_OPEN   = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>';
  var ICON_DOWNLOAD = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>';

  fetchContent(contentPath)
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      // Unterstützt verschiedene Feldnamen: downloads[] (Standard, via Admin
      // angelegt), documents[]/pdfs[] (alternative Strukturen).
      var list = Array.isArray(d.downloads) ? d.downloads
               : Array.isArray(d.documents) ? d.documents
               : Array.isArray(d.pdfs) ? d.pdfs
               : [];
      var items = list.filter(function (item) {
        return item && (item.datei || item.url || item.pfad);
      });
      if (!items.length) return;

      // Dateisystem-unzulässige Zeichen aus einem Dateinamen-Bestandteil
      // entfernen (Windows-reservierte Zeichen \ / : * ? " < > |), führende/
      // abschließende Punkte & Leerzeichen entfernen, Mehrfach-Leerzeichen
      // zusammenfassen. Umlaute und normale Leerzeichen bleiben erhalten.
      function sanitizeFilenamePart(s) {
        return s
          .replace(/[\\/:*?"<>|]/g, '')
          .replace(/^[.\s]+|[.\s]+$/g, '')
          .replace(/\s+/g, ' ')
          .trim();
      }

      // Download-Dateiname für den "Herunterladen"-Link: basiert auf dem
      // eingegebenen Titel (nicht auf dem technischen Dateinamen mit
      // Zeitstempel-Präfix), mit der Original-Endung. .normalize('NFC')
      // behebt zerlegte macOS-Umlaute (z.B. "o" + Combining-Diaeresis -> "ö").
      function buildDownloadFilename(titel, datei) {
        var baseName = datei.split('/').pop();
        var extMatch = baseName.match(/\.([a-zA-Z0-9]+)$/);
        var ext = extMatch ? extMatch[1] : '';
        var base = (titel && titel.trim()) ? sanitizeFilenamePart(titel.trim().normalize('NFC')) : '';
        if (!base) {
          // Titel leer -> bereinigter Original-Dateiname, Zeitstempel-Präfix
          // (z.B. "1781194530416-") entfernen, falls vorhanden.
          var withoutTimestamp = baseName.replace(/^\d{10,}-/, '');
          var stem = withoutTimestamp.replace(/\.[a-zA-Z0-9]+$/, '');
          base = sanitizeFilenamePart(stem.normalize('NFC')) ||
                 sanitizeFilenamePart(baseName.replace(/\.[a-zA-Z0-9]+$/, '').normalize('NFC')) || 'datei';
        }
        if (ext && !new RegExp('\\.' + ext + '$', 'i').test(base)) base += '.' + ext;
        return base;
      }

      // Zwei Varianten: als schmale Sidebar-Karte (neben dem Geschäftsstelle-
      // Block) oder als volle Breite (Seiten ohne .sidebar-Layout, z.B.
      // seiten/index.html). Beide nutzen dieselben Icons/Aktionen.
      function buildActions(datei, name, titel) {
        var downloadName = buildDownloadFilename(titel, datei);
        return '<span class="download-item__actions">' +
          '<a href="' + escHtml(datei) + '" target="_blank" rel="noopener noreferrer" class="download-action" title="Öffnen" aria-label="' + escHtml(name) + ' öffnen">' + ICON_OPEN + '</a>' +
          '<a href="' + escHtml(datei) + '" download="' + escHtml(downloadName) + '" class="download-action" title="Herunterladen" aria-label="' + escHtml(name) + ' herunterladen">' + ICON_DOWNLOAD + '</a>' +
        '</span>';
      }

      var itemsSidebar = items.map(function (item) {
        var datei = item.datei || item.url || item.pfad;
        var name = item.titel && item.titel.trim() ? item.titel.trim() : datei.split('/').pop();
        var iconHtml = item.vorschau
          ? '<a href="' + escHtml(datei) + '" target="_blank" rel="noopener noreferrer" class="download-item__thumb"><img src="' + escHtml(item.vorschau) + '" alt="' + escHtml(name) + '" loading="lazy"></a>'
          : '<span class="download-item__icon">' + ICON_PDF + '</span>';
        return '<div class="download-item--sidebar">' +
          iconHtml +
          '<span class="download-item__name">' + escHtml(name) + '</span>' +
          buildActions(datei, name, item.titel) +
        '</div>';
      }).join('');

      var itemsWide = items.map(function (item) {
        var datei = item.datei || item.url || item.pfad;
        var name = item.titel && item.titel.trim() ? item.titel.trim() : datei.split('/').pop();
        var iconHtml = item.vorschau
          ? '<a href="' + escHtml(datei) + '" target="_blank" rel="noopener noreferrer" class="download-item__thumb"><img src="' + escHtml(item.vorschau) + '" alt="' + escHtml(name) + '" loading="lazy"></a>'
          : '<div class="download-item__icon">' + ICON_PDF + '</div>';
        return '<div class="download-item">' +
          iconHtml +
          '<div class="download-item__meta"><div class="download-item__name">' + escHtml(name) + '</div></div>' +
          buildActions(datei, name, item.titel) +
        '</div>';
      }).join('');

      var htmlSidebar = '<div class="sidebar-widget sidebar-widget--downloads">' +
        '<h4><span class="download-heading-icon">' + ICON_PDF + '</span> Dokumente &amp; Downloads</h4>' +
        '<div class="downloads-list downloads-list--sidebar">' + itemsSidebar + '</div></div>';

      var htmlWide = '<div class="download-section-title"><span class="download-heading-icon">' + ICON_PDF + '</span> Dokumente &amp; Downloads</div>' +
        '<div class="downloads-list">' + itemsWide + '</div>';

      // Der Seiteninhalt wird von einem eigenen <script> auf der jeweiligen
      // Seite asynchron nachgeladen (fetch → innerHTML). Wir warten daher,
      // bis #page-inhalt bzw. #page-main befüllt ist, bevor wir die
      // Downloads-Box anhängen.
      var attempts = 0;
      var iv = setInterval(function () {
        attempts++;
        var inhalt = document.getElementById('page-inhalt');
        var main = document.getElementById('page-main');
        var kjm = isKJM ? document.getElementById('kjm-content') : null;
        var sidebar = document.querySelector('.sidebar');
        var target = inhalt || main || kjm;
        var ready = inhalt || (main && !main.querySelector('.loading-spinner')) ||
          (kjm && kjm.textContent.indexOf('Wird geladen') === -1);
        if (ready && (target || sidebar)) {
          clearInterval(iv);
          var box = document.createElement('div');
          if (sidebar) {
            // Als eigene Sidebar-Karte einfügen – direkt vor dem grünen
            // Geschäftsstelle-Kontaktblock, falls vorhanden, sonst am Ende.
            box.innerHTML = htmlSidebar;
            var widget = box.firstChild;
            var contactBox = sidebar.querySelector('.contact-box');
            if (contactBox) {
              contactBox.parentNode.insertBefore(widget, contactBox);
            } else {
              sidebar.appendChild(widget);
            }
          } else if (target) {
            // Seiten ohne .sidebar-Layout (z.B. volle Breite): Karte in der
            // Breiten-Variante am Ende des Hauptinhalts anhängen.
            box.innerHTML = htmlWide;
            var parent = inhalt ? inhalt.parentNode : (main || kjm);
            while (box.firstChild) parent.appendChild(box.firstChild);
          }
        } else if (attempts > 60) {
          clearInterval(iv);
        }
      }, 150);
    })
    .catch(function () {});
})();

// ── Bildergalerie pro Seite ────────────────────────────────────────────
// Gleiches Prinzip wie "Dokumente & Downloads" oben: Im Admin können pro
// Seite mehrere Bilder mit eigener Beschriftung hinterlegt werden
// (data.galerie = [{bild, titel}]). Wird hier generisch als Bilder-Raster
// am Ende des Seiteninhalts eingefügt, sobald die jeweilige content/*.json
// ein nicht-leeres "galerie"-Array enthält. Erscheint NICHT auf reinen
// Personen-Seiten (Vorstand/Obleute/Hegeringe) – dort gibt es im Admin gar
// kein Galerie-Feld, die content-Datei enthält also nie ein galerie-Array.
(function () {
  var path = window.location.pathname;
  var basePath = path.replace(/\.html$/, '');
  var isKJM = /\/kreisjjaegermeister\/index$/.test(basePath) || /\/kreisjjaegermeister\/?$/.test(basePath);
  var isIndex = basePath === '' || /\/$/.test(basePath) || /\/index$/.test(basePath);
  if (!isKJM && isIndex) return;
  var contentPath = isKJM ? '/content/kreisjjaegermeister.json' : '/content' + basePath + '.json';

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  fetchContent(contentPath)
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      var items = (Array.isArray(d.galerie) ? d.galerie : []).filter(function (g) { return g && g.bild; });
      if (!items.length) return;

      var cards = items.map(function (g) {
        var caption = g.titel && g.titel.trim() ? '<div class="galerie-item__caption">' + escHtml(g.titel.trim()) + '</div>' : '';
        return '<a href="' + escHtml(g.bild) + '" target="_blank" rel="noopener noreferrer" class="galerie-item">' +
          '<img src="' + escHtml(g.bild) + '" alt="' + escHtml(g.titel || '') + '" loading="lazy">' +
          caption +
        '</a>';
      }).join('');

      var galerieTitel = (d.galerie_titel && d.galerie_titel.trim()) || 'Bildergalerie';
      var html = '<div class="galerie-section">' +
        '<div class="galerie-section__title">' + escHtml(galerieTitel) + '</div>' +
        '<div class="galerie-grid">' + cards + '</div>' +
      '</div>';

      var attempts = 0;
      var iv = setInterval(function () {
        attempts++;
        var inhalt = document.getElementById('page-inhalt');
        var main = document.getElementById('page-main');
        var kjm = isKJM ? document.getElementById('kjm-content') : null;
        var target = inhalt || main || kjm;
        var ready = inhalt || (main && !main.querySelector('.loading-spinner')) ||
          (kjm && kjm.textContent.indexOf('Wird geladen') === -1);
        if (ready && target) {
          clearInterval(iv);
          var box = document.createElement('div');
          box.innerHTML = html;
          var parent = inhalt ? inhalt.parentNode : (main || kjm);
          while (box.firstChild) parent.appendChild(box.firstChild);
        } else if (attempts > 60) {
          clearInterval(iv);
        }
      }, 150);
    })
    .catch(function () {});
})();

// ── "Verwandte Seiten" – generische rechte Navigation ────────────────────
// Füllt jede <ul class="sidebar-nav" data-related-nav> automatisch mit den
// "Geschwister-Seiten" der aktuellen Seite, direkt aus dem echten Hauptmenü
// oben ausgelesen. Die aktuelle Seite selbst wird dabei weggelassen (man ist
// ja schon drauf). Menüpunkte, die selbst nur eine Klapp-Überschrift ohne
// eigene Seite sind (z.B. "KJS Segeberg", href="#"), werden als
// nicht-klickbare Zwischenüberschrift mit eingerückten Unterpunkten gezeigt –
// genau wie im Hauptmenü selbst.
//
// Dadurch muss diese Liste nirgends mehr von Hand gepflegt werden: ändert
// sich später ein Menüpunkt, zieht die rechte Navigation automatisch nach.
//
// Für Seiten, die selbst nicht direkt im Hauptmenü stehen (z.B. die
// Jagdhundeschule-Kachelübersicht, die "unter" Hundeausbildung hängt), kann
// per data-related-for="<href-wie-im-menü>" festgelegt werden, für welchen
// Menüpunkt die Geschwister-Liste gelten soll.
(function () {
  var targets = document.querySelectorAll('[data-related-nav]');
  if (!targets.length) return;

  // Erst starten, wenn das Hauptmenü vollständig ist (inkl. eigener
  // Unterseiten aus dem Admin, siehe window.__navReady weiter oben in
  // dieser Datei) – sonst fehlen z.B. selbst angelegte Verbraucher-Seiten
  // in der rechten Navigation, weil deren Einfügung ins Menü noch läuft.
  Promise.resolve(window.__navReady).then(run).catch(run);

  function normPath(href) {
    try {
      var u = new URL(href, location.href);
      return u.pathname.replace(/index\.html?$/, '').replace(/\.html$/, '').replace(/\/$/, '') || '/';
    } catch (e) {
      return null;
    }
  }

  function findLink(navRoot, pathOrHref) {
    var wantPath = normPath(pathOrHref);
    var links = navRoot.querySelectorAll('a[href]');
    var found = null;
    links.forEach(function (a) {
      if (found) return;
      var href = a.getAttribute('href');
      if (!href || href === '#') return;
      if (normPath(href) === wantPath) found = a;
    });
    return found;
  }

  function groupHeaderHtml(label) {
    return '<li style="font-size:.75rem;font-weight:700;text-transform:uppercase;' +
      'letter-spacing:.05em;color:var(--green-dark);padding:.5rem 0 .25rem;' +
      'margin-top:.5rem;border-top:1px solid var(--border);pointer-events:none;">' +
      label.replace(/&/g, '&amp;') + '</li>';
  }

  function itemHtml(label, href, indent) {
    return '<li><a href="' + href + '"' + (indent ? ' style="padding-left:.75rem;"' : '') + '>' +
      label.replace(/&/g, '&amp;') + '</a></li>';
  }

  function renderSiblings(ul, excludeLi) {
    var html = '';
    ul.querySelectorAll(':scope > li').forEach(function (li) {
      if (li === excludeLi) return;
      var a = li.querySelector(':scope > a');
      if (!a) return;
      var href = a.getAttribute('href');
      var label = a.textContent.trim();
      var childUl = li.querySelector(':scope > ul');
      var childItems = childUl ? childUl.querySelectorAll(':scope > li > a') : null;
      if (childItems && childItems.length && (!href || href === '#')) {
        // Klapp-Überschrift ohne eigene Seite (z.B. "KJS Segeberg"):
        // als Zwischenüberschrift + eingerückte Unterpunkte anzeigen.
        html += groupHeaderHtml(label);
        childItems.forEach(function (childA) {
          html += itemHtml(childA.textContent.trim(), childA.getAttribute('href'), true);
        });
      } else if (href && href !== '#') {
        html += itemHtml(label, href, false);
      }
    });
    return html;
  }

  function run() {
    var navRoot = document.querySelector('nav[aria-label="Hauptnavigation"] > ul');
    if (!navRoot) return;

    targets.forEach(function (target) {
      var forHref = target.getAttribute('data-related-for');
      var matched = findLink(navRoot, forHref || location.pathname);
      if (!matched) return;
      var parentLi = matched.closest('li');
      var parentUl = parentLi ? parentLi.parentElement : null;
      if (!parentUl) return;
      var html = renderSiblings(parentUl, forHref ? null : parentLi);
      if (html) target.innerHTML = html;
    });
  }
})();

// Hinweis: Das Öffnen/Schließen der Dropdowns mit kurzer Verzögerung beim
// Rüberfahren mit der Maus (Frank-Bug-Report, "KJS Segeberg"-Flyout schloss
// beim leicht diagonalen Rüberfahren sofort) läuft seit Architektur-Audit
// Phase 2 (31.08.2026) als wireHoverFlyouts() im zentralen Navigations-Modul
// weiter oben in dieser Datei - dort erst NACH dem Rendern von .main-nav aus
// navigation.json aufgerufen, da diese Elemente vorher noch nicht existieren.

// ── Bildergalerie-Lightbox (site-weit) ────────────────────────────────────
// Frank-Wunsch: Klick auf ein Galerie-Bild soll es NICHT mehr als eigene
// Bilddatei-Seite öffnen (target="_blank"), sondern als Overlay direkt auf
// der aktuellen Seite, mit Vor/Zurück-Navigation (Pfeile, Tastatur, Swipe)
// durch alle Bilder derselben Galerie. Per Event-Delegation auf document
// registriert – funktioniert dadurch automatisch für JEDE Bildergalerie
// (Standard-Seiten via main.js-Renderer und aktuelles/beitrag.html), ohne
// dass jede Stelle einzeln verdrahtet werden muss.
(function () {
  var overlay = null, imgEl = null, captionEl = null, counterEl = null;
  var items = []; // aktuelle Galerie-Bilder (Array von {href, alt, caption})
  var idx = 0;

  function build() {
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.className = 'kjs-lightbox';
    overlay.innerHTML =
      '<button type="button" class="kjs-lightbox__close" aria-label="Schließen">&times;</button>' +
      '<button type="button" class="kjs-lightbox__prev" aria-label="Vorheriges Bild">&#10094;</button>' +
      '<button type="button" class="kjs-lightbox__next" aria-label="Nächstes Bild">&#10095;</button>' +
      '<div class="kjs-lightbox__stage">' +
        '<img class="kjs-lightbox__img" alt="">' +
        '<div class="kjs-lightbox__caption"></div>' +
        '<div class="kjs-lightbox__counter"></div>' +
      '</div>';
    document.body.appendChild(overlay);
    imgEl = overlay.querySelector('.kjs-lightbox__img');
    captionEl = overlay.querySelector('.kjs-lightbox__caption');
    counterEl = overlay.querySelector('.kjs-lightbox__counter');

    overlay.querySelector('.kjs-lightbox__close').addEventListener('click', close);
    overlay.querySelector('.kjs-lightbox__prev').addEventListener('click', function (e) { e.stopPropagation(); show(idx - 1); });
    overlay.querySelector('.kjs-lightbox__next').addEventListener('click', function (e) { e.stopPropagation(); show(idx + 1); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });

    // Touch-Swipe
    var touchX = null;
    overlay.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
    overlay.addEventListener('touchend', function (e) {
      if (touchX === null) return;
      var dx = e.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 40) show(idx + (dx < 0 ? 1 : -1));
      touchX = null;
    }, { passive: true });
  }

  function show(i) {
    if (!items.length) return;
    idx = (i + items.length) % items.length;
    var it = items[idx];
    imgEl.src = it.href;
    imgEl.alt = it.alt || '';
    captionEl.textContent = it.caption || '';
    captionEl.style.display = it.caption ? '' : 'none';
    counterEl.textContent = items.length > 1 ? (idx + 1) + ' / ' + items.length : '';
  }

  function open(galleryItems, startIdx) {
    build();
    items = galleryItems;
    overlay.classList.add('kjs-lightbox--open');
    document.body.classList.add('kjs-lightbox-lock');
    show(startIdx);
  }

  function close() {
    if (!overlay) return;
    overlay.classList.remove('kjs-lightbox--open');
    document.body.classList.remove('kjs-lightbox-lock');
    items = [];
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('.galerie-item') : null;
    if (!link) return;
    e.preventDefault();
    var grid = link.closest('.galerie-grid') || document;
    var links = Array.prototype.slice.call(grid.querySelectorAll('.galerie-item'));
    var galleryItems = links.map(function (a) {
      var img = a.querySelector('img');
      var capEl = a.querySelector('.galerie-item__caption');
      return { href: a.getAttribute('href'), alt: img ? img.getAttribute('alt') : '', caption: capEl ? capEl.textContent : '' };
    });
    open(galleryItems, links.indexOf(link));
  });

  document.addEventListener('keydown', function (e) {
    if (!overlay || !overlay.classList.contains('kjs-lightbox--open')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
  });
})();
