/* KJS Segeberg – main.js */

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
    var labels = Array.prototype.map.call(headRow.querySelectorAll('th,td'), function (c) {
      return c.textContent.trim();
    });
    if (!labels.length) return;
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

// Active nav link highlighting
(function() {
  const path = window.location.pathname;
  document.querySelectorAll('.main-nav a, .mobile-nav a').forEach(a => {
    if (a.getAttribute('href') && path.includes(a.getAttribute('href')) && a.getAttribute('href') !== '/') {
      a.closest('li')?.classList.add('active');
    }
  });
})();

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

// Eigene Seiten nach Bereich in die Navigation verteilen + anschließend
// die gespeicherte Gesamt-Reihenfolge aus navigation.json anwenden
(function() {
  var weitereItem = document.getElementById('weitere-themen-item');
  var weltereSub  = document.getElementById('weitere-themen-sub');
  if (!weitereItem || !weltereSub) return;

  // "Weitere Themen"-Unterpunkt bleibt versteckt –
  // Seiten aus seiten-weitere.json erscheinen direkt im Jäger-Dropdown

  // Hilfsfunktion: Unter-Dropdown im Jäger-Menü per Link-Text finden
  function findJaegerSub(textSnippet) {
    var dd = document.getElementById('jaeger-dropdown');
    if (!dd) return null;
    var hasSubs = dd.querySelectorAll('.has-sub');
    for (var i = 0; i < hasSubs.length; i++) {
      var a = hasSubs[i].querySelector(':scope > a');
      if (a && a.textContent.includes(textSnippet)) {
        return hasSubs[i].querySelector('ul.dropdown--sub');
      }
    }
    return null;
  }

  // Hilfsfunktion: Top-Level-Dropdown per Link-Text finden
  function findTopDropdown(textSnippet) {
    var items = document.querySelectorAll('.main-nav > li');
    for (var i = 0; i < items.length; i++) {
      var a = items[i].querySelector(':scope > a');
      if (a && a.textContent.includes(textSnippet)) {
        return items[i].querySelector('ul.dropdown');
      }
    }
    return null;
  }

  // Hilfsfunktion: Seiten-Liste in Ziel-Dropdown einfügen
  function einfuegenInNav(seiten, target) {
    (seiten || []).filter(function(s) {
      return s.veroeffentlicht === true && s.in_navigation === true;
    }).forEach(function(s) {
      var href = '/seiten/?s=' + encodeURIComponent(s.slug);
      if (target && !target.querySelector('a[href="' + href + '"]')) {
        var li = document.createElement('li');
        var a  = document.createElement('a');
        a.href = href;
        a.textContent = s.nav_label || s.titel;
        li.appendChild(a);
        target.appendChild(li);
      }
    });
  }

  // Alle Sektions-Dateien laden
  var sektionen = [
    { url: '/content/seiten-kjs.json',        target: function() { return findJaegerSub('KJS Segeberg'); } },
    { url: '/content/seiten-aufgaben.json',    target: function() { return findJaegerSub('Aufgaben'); } },
    { url: '/content/seiten-verbraucher.json', target: function() { return findTopDropdown('Verbraucher'); } },
    // Legacy: alte seiten.json mit bereich-Feld
    { url: '/content/seiten.json', bereich: true }
  ];

  // insertJobs sammelt alle Promises, die eigene Unterseiten in die Menüs
  // einfügen. Die finale Sortierung anhand von navigation.json darf erst
  // starten, NACHDEM alle diese Seiten im DOM stehen – sonst landen frisch
  // eingefügte Seiten nach der Umsortierung wieder am Ende und die per
  // Drag & Drop im Admin gespeicherte Reihenfolge wird auf der Website
  // nicht korrekt angezeigt.
  var insertJobs = sektionen.map(function(s) {
    return fetchContent(s.url)
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (s.bereich) {
          // Legacy seiten.json: bereich-Feld auswerten
          (data.seiten || []).filter(function(p) {
            return p.veroeffentlicht === true && p.in_navigation === true;
          }).forEach(function(p) {
            var bereich = p.bereich || 'weitere-themen';
            var t = bereich === 'kjs' ? findJaegerSub('KJS Segeberg')
                  : bereich === 'aufgaben' ? findJaegerSub('Aufgaben')
                  : bereich === 'verbraucher' ? findTopDropdown('Verbraucher')
                  : document.getElementById('weitere-themen-sub');
            var href = '/seiten/?s=' + encodeURIComponent(p.slug);
            if (t && !t.querySelector('a[href="' + href + '"]')) {
              var li = document.createElement('li');
              var a  = document.createElement('a');
              a.href = href; a.textContent = p.nav_label || p.titel;
              li.appendChild(a); t.appendChild(li);
            }
          });
        } else {
          einfuegenInNav(data.seiten, s.target());
        }
      })
      .catch(function(){});
  });

  // seiten-weitere.json → "Weitere Themen"-Flyout im Jäger-Menü
  insertJobs.push(
    fetchContent('/content/seiten-weitere.json')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var publishedSeiten = (data.seiten || []).filter(function(s) {
          return s.veroeffentlicht === true && s.in_navigation === true;
        });
        if (!publishedSeiten.length) return;
        weitereItem.style.display = '';   // Flyout-Punkt sichtbar machen
        einfuegenInNav(publishedSeiten, weltereSub);
      })
      .catch(function() {})
  );

  // ── Navigationsreihenfolge, Sektionsnamen und Hauptmenü-Reihenfolge
  //    aus navigation.json (läuft erst, wenn alle obigen Seiten eingefügt
  //    sind, siehe Kommentar bei insertJobs oben) ─────────────────────
  Promise.all(insertJobs).then(function() {
    // Reorder <li> children of `sub` to match `items` array order
    // (items kann statische Seiten UND eigene Unterseiten enthalten,
    // z.B. href="/jaeger/vorstand.html" oder href="/seiten/?s=mein-slug")
    function reorderSub(sub, items) {
      if (!sub || !items || !items.length) return;
      // Netlify liefert interne Links ohne ".html" aus (Pretty URLs) – deshalb
      // Dateiname beidseitig ohne Endung/Query vergleichen, sonst matcht nichts.
      function baseName(href) {
        return (href || '').split('/').pop().replace(/\.html$/i, '').split(/[?#]/)[0];
      }
      items.forEach(function(item) {
        var filename = baseName(item.href);
        sub.querySelectorAll(':scope > li').forEach(function(li) {
          var a = li.querySelector('a');
          if (a && a.getAttribute('href') && filename && baseName(a.getAttribute('href')) === filename) {
            sub.appendChild(li);
          }
        });
      });
    }

    // Rename the text node of a nav link (preserves inner <span> elements)
    function renameNavLink(el, newText) {
      if (!el) return;
      el.childNodes.forEach(function(node) {
        if (node.nodeType === 3 && node.textContent.trim()) {
          node.textContent = newText + ' ';
        }
      });
    }

    // Map nav key to main-nav <li> by matching first <a> href pattern
    // Netlify liefert interne Links ohne ".html"-Endung aus (Pretty URLs) –
    // UND kürzt "/ordner/index.html" sogar auf "/ordner/" (kein "index" mehr
    // im Pfad, nur der Ordner mit Schrägstrich). Alle drei Formen müssen hier
    // erkannt werden, sonst matcht z.B. "termine" nach dem Deploy gar nichts
    // mehr (siehe auch reorderSub()/baseName() oben für dieselbe Ursache).
    var KEY_HREF = {
      startseite: /^(\.\.\/)*(index(\.html)?)?\/?$/,
      jaeger:     /jaeger\/(index(\.html)?)?$/,
      verbraucher:/verbraucher\/(index(\.html)?)?$/,
      termine:    /termine\/(index(\.html)?)?$/,
      aktuelles:  /aktuelles\/(index(\.html)?)?$/,
      faq:        /faq\/(index(\.html)?)?$/,
      service:    /service(\.html)?$/,
      kontakt:    /kontakt\/(index(\.html)?)?$/
    };

    // Robusterer Abgleich für Hauptmenü-Punkte: bevorzugt das feste
    // data-navkey-Attribut (unabhängig vom href, funktioniert auch wenn ein
    // Punkt wie "Jäger" bewusst auf "#" zeigt, siehe KJS-Segeberg-Fix weiter
    // unten). Fällt nur zurück auf den href-Regex, falls data-navkey auf
    // einer Seite mal fehlen sollte.
    function matchesNavKey(a, key) {
      if (!a) return false;
      var dk = a.getAttribute('data-navkey');
      if (dk) return dk === key;
      var pattern = KEY_HREF[key];
      return !!(pattern && pattern.test(a.getAttribute('href') || ''));
    }

    var jaegerDD = document.getElementById('jaeger-dropdown');
    var mainNav  = document.querySelector('.main-nav');

    // window.__navReady: wird erst erfüllt, wenn ALLE dynamisch eingefügten
    // Seiten (KJS Segeberg/Aufgaben/Verbraucher/Weitere Themen) UND die
    // Umsortierung/Umbenennung aus navigation.json fertig im Menü stehen.
    // Die generische "Verwandte Seiten"-Navigation weiter unten in dieser
    // Datei wartet darauf, damit sie das Menü nicht zu früh (unvollständig)
    // ausliest.
    window.__navReady = fetchContent('/content/navigation.json').then(function(r) { return r.json(); }).then(function(d) {

      // ── 1. Sub-menu item ordering (FEATURE 1) ──────────────────
      if (jaegerDD) {
        // KJS Segeberg sub-menu
        if (d.kjs && d.kjs.length) {
          jaegerDD.querySelectorAll(':scope > .has-sub').forEach(function(hs) {
            var a = hs.querySelector(':scope > a');
            if (a && a.textContent.indexOf('KJS') !== -1) {
              reorderSub(hs.querySelector('ul.dropdown--sub'), d.kjs);
            }
          });
        }
        // Aufgaben sub-menu
        if (d.aufgaben && d.aufgaben.length) {
          jaegerDD.querySelectorAll(':scope > .has-sub').forEach(function(hs) {
            var a = hs.querySelector(':scope > a');
            if (a && a.textContent.indexOf('Aufgaben') !== -1) {
              reorderSub(hs.querySelector('ul.dropdown--sub'), d.aufgaben);
            }
          });
        }
      }
      // Verbraucher dropdown
      if (d.verbraucher && d.verbraucher.length && mainNav) {
        mainNav.querySelectorAll(':scope > li').forEach(function(li) {
          var a = li.querySelector(':scope > a');
          if (a && a.textContent.indexOf('Verbraucher') !== -1) {
            reorderSub(li.querySelector('ul.dropdown'), d.verbraucher);
          }
        });
      }

      // ── 2. Section name renaming (FEATURE 2) ───────────────────
      if (d.sektionsnamen) {
        var sn = d.sektionsnamen;
        if (jaegerDD) {
          // KJS Segeberg label
          if (sn.kjs) {
            jaegerDD.querySelectorAll(':scope > .has-sub').forEach(function(hs) {
              var a = hs.querySelector(':scope > a');
              if (a && a.textContent.indexOf('KJS') !== -1) renameNavLink(a, sn.kjs);
            });
          }
          // Aufgaben label
          if (sn.aufgaben) {
            jaegerDD.querySelectorAll(':scope > .has-sub').forEach(function(hs) {
              var a = hs.querySelector(':scope > a');
              if (a && a.textContent.indexOf('Aufgaben') !== -1) renameNavLink(a, sn.aufgaben);
            });
          }
        }
        // Jäger main nav label
        if (sn.jaeger && mainNav) {
          mainNav.querySelectorAll(':scope > li').forEach(function(li) {
            var a = li.querySelector(':scope > a');
            if (matchesNavKey(a, 'jaeger')) renameNavLink(a, sn.jaeger);
          });
        }
        // Verbraucher main nav label
        if (sn.verbraucher && mainNav) {
          mainNav.querySelectorAll(':scope > li').forEach(function(li) {
            var a = li.querySelector(':scope > a');
            if (matchesNavKey(a, 'verbraucher')) renameNavLink(a, sn.verbraucher);
          });
        }
      }

      // ── 3. Main menu reordering (FEATURE 3) ────────────────────
      if (d.hauptmenu && d.hauptmenu.length && mainNav) {
        d.hauptmenu.forEach(function(key) {
          if (!KEY_HREF[key]) return;
          mainNav.querySelectorAll(':scope > li').forEach(function(li) {
            var a = li.querySelector(':scope > a');
            if (matchesNavKey(a, key)) {
              mainNav.appendChild(li); // move to end in specified order
            }
          });
        });
      }

      // ── 4. Jäger-Dropdown Direktpunkte umsortieren (FEATURE 4) ───
      if (d.jaeger_dropdown && d.jaeger_dropdown.length && jaegerDD) {
        var JAEGER_MATCH = {
          'ueber-uns':           function(li) { var a = li.querySelector(':scope > a'); return a && /ueber-uns/.test(a.getAttribute('href') || ''); },
          'kreisjjaegermeister': function(li) { var a = li.querySelector(':scope > a'); return a && /kreisjjaegermeister/.test(a.getAttribute('href') || ''); },
          'kjs-segeberg':        function(li) { var a = li.querySelector(':scope > a'); return a && li.classList.contains('has-sub') && /KJS/.test(a.textContent || ''); },
          'aufgaben':            function(li) { var a = li.querySelector(':scope > a'); return a && /Aufgaben/.test(a.textContent || ''); },
          'infomobil':           function(li) { var a = li.querySelector(':scope > a'); return a && /infomobil/.test(a.getAttribute('href') || ''); },
          'weitere-themen':      function(li) { return li.id === 'weitere-themen-item'; }
        };
        d.jaeger_dropdown.forEach(function(key) {
          var match = JAEGER_MATCH[key];
          if (!match) return;
          jaegerDD.querySelectorAll(':scope > li').forEach(function(li) {
            if (match(li)) jaegerDD.appendChild(li);
          });
        });
      }

      // ── 5. Mobile-Nav: gleiche Reihenfolge wie Desktop anwenden ──
      // Handy-Menü ist eine eigene flache Liste (kein has-sub-Flyout wie
      // am Desktop) – Gruppen werden über die href-Listen aus
      // navigation.json (kjs/aufgaben) erkannt und dann als zusammen-
      // hängender Block in der gespeicherten Reihenfolge einsortiert.
      (function() {
        function hrefBase(href) {
          return (href || '').split('/').pop().replace(/\.html$/i, '').split(/[?#]/)[0];
        }
        var jaegerDetails = Array.prototype.filter.call(
          document.querySelectorAll('#mobileNav details'),
          function(det) {
            var sum = det.querySelector('summary');
            return sum && sum.textContent.trim() === 'Jäger';
          }
        )[0];
        var mobileSub = jaegerDetails && jaegerDetails.querySelector('ul.mobile-nav__sub');
        if (!mobileSub) return;

        var kjsHrefs = (d.kjs || []).map(function(i) { return hrefBase(i.href); });
        var aufgabenHrefs = (d.aufgaben || []).map(function(i) { return hrefBase(i.href); });

        var groups = { 'ueber-uns': [], 'kreisjjaegermeister': [], infomobil: [], kjs: [], aufgaben: [], rest: [] };
        Array.prototype.forEach.call(mobileSub.querySelectorAll(':scope > li'), function(li) {
          var a = li.querySelector('a');
          var href = a ? (a.getAttribute('href') || '') : '';
          var base = hrefBase(href);
          if (/ueber-uns/.test(href)) groups['ueber-uns'].push(li);
          else if (/kreisjjaegermeister/.test(href)) groups['kreisjjaegermeister'].push(li);
          else if (/infomobil/.test(href)) groups.infomobil.push(li);
          else if (kjsHrefs.indexOf(base) !== -1) groups.kjs.push({ li: li, base: base });
          else if (aufgabenHrefs.indexOf(base) !== -1) groups.aufgaben.push({ li: li, base: base });
          else groups.rest.push(li);
        });

        function sortByOrder(items, order) {
          var out = [];
          order.forEach(function(base) {
            var found = items.filter(function(it) { return it.base === base; })[0];
            if (found) out.push(found.li);
          });
          return out;
        }

        var KEY_LIS = {
          'ueber-uns':           groups['ueber-uns'],
          'kreisjjaegermeister': groups['kreisjjaegermeister'],
          'kjs-segeberg':        sortByOrder(groups.kjs, kjsHrefs),
          'aufgaben':            sortByOrder(groups.aufgaben, aufgabenHrefs),
          'infomobil':           groups.infomobil,
          'weitere-themen':      []
        };

        (d.jaeger_dropdown || []).forEach(function(key) {
          (KEY_LIS[key] || []).forEach(function(li) { mobileSub.appendChild(li); });
        });
        // Sicherheitsnetz: alles nicht Zugeordnete (z.B. neue Seiten) hinten anhängen
        groups.rest.forEach(function(li) { mobileSub.appendChild(li); });
      })();

    }).catch(function() {
      // navigation.json not yet present – silently keep original order
    });
  });
})();

// Eigene Hauptpunkte aus navigation-extra.json in die Hauptnavigation einfügen
(function() {
  fetchContent('/content/navigation-extra.json')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var nav = document.querySelector('.main-nav');
      if (!nav || !data.hauptpunkte || !data.hauptpunkte.length) return;

      data.hauptpunkte.forEach(function(hp) {
        var seiten = (hp.seiten || []).filter(function(s) {
          return s.veroeffentlicht === true && s.in_navigation === true;
        });
        if (!seiten.length) return;

        var li = document.createElement('li');

        if (seiten.length === 1) {
          // Einzelne Seite → direkt verlinken
          var a = document.createElement('a');
          a.href = '/seiten/?s=' + encodeURIComponent(seiten[0].slug);
          a.textContent = hp.label;
          li.appendChild(a);
        } else {
          // Mehrere Seiten → Dropdown
          var a = document.createElement('a');
          a.href = '#';
          a.innerHTML = hp.label + ' <span class="arrow">&#9662;</span>';
          li.appendChild(a);
          var ul = document.createElement('ul');
          ul.className = 'dropdown';
          seiten.forEach(function(s) {
            var sli = document.createElement('li');
            var sa = document.createElement('a');
            sa.href = '/seiten/?s=' + encodeURIComponent(s.slug);
            sa.textContent = s.nav_label || s.titel;
            sli.appendChild(sa);
            ul.appendChild(sli);
          });
          li.appendChild(ul);
        }

        // Vor FAQ einfügen (oder am Ende der Nav)
        var faqItem = Array.from(nav.querySelectorAll(':scope > li > a')).find(function(a) {
          return a.textContent.trim() === 'FAQ';
        });
        if (faqItem) {
          nav.insertBefore(li, faqItem.closest('li'));
        } else {
          nav.appendChild(li);
        }
      });
    })
    .catch(function() {});
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

// ── Hauptmenü: Dropdowns schließen mit kurzer Verzögerung ────────────────
// Frank hatte gemeldet, dass sich die Menüs (z.B. "KJS Segeberg" → Flyout
// mit Landesjagdverband, Vorstand, ...) beim Rüberfahren mit der Maus sofort
// schließen, sobald man nur minimal von der geraden Linie abweicht. Das
// liegt daran, dass die Anzeige bisher rein per CSS ":hover" gesteuert war –
// verlässt der Mauszeiger auch nur für einen Sekundenbruchteil den Menüpunkt
// (z.B. beim diagonalen Rüberfahren zum Untermenü), klappt alles sofort zu.
// Diese Funktion ergänzt eine kurze "Toleranzzeit": Beim Verlassen wird das
// Menü nicht sofort geschlossen, sondern erst nach einer kurzen Verzögerung –
// fährt man in der Zwischenzeit zurück oder ins Untermenü, bleibt es offen.
(function () {
  var CLOSE_DELAY = 350; // ms
  var timers = new WeakMap();

  function openNow(el) {
    var t = timers.get(el);
    if (t) { clearTimeout(t); timers.delete(el); }
    el.classList.add('nav-open');
  }
  function closeDelayed(el) {
    var t = timers.get(el);
    if (t) clearTimeout(t);
    t = setTimeout(function () {
      el.classList.remove('nav-open');
      timers.delete(el);
    }, CLOSE_DELAY);
    timers.set(el, t);
  }

  function wire(selector) {
    document.querySelectorAll(selector).forEach(function (el) {
      el.addEventListener('mouseenter', function () { openNow(el); });
      el.addEventListener('mouseleave', function () { closeDelayed(el); });
    });
  }

  wire('.main-nav > li');   // Hauptmenü-Dropdowns (Jäger, Verbraucher, ...)
  wire('.has-sub');         // Verschachtelte Flyout-Untermenüs (KJS Segeberg, Aufgaben ...)
})();

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
