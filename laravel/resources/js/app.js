/* =========================================================
   KJS Segeberg – Laravel-Fundament-JavaScript (Phase 1 + Phase 2 + Phase 4)
   =========================================================

   Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration) ergaenzte
   diese Datei um genau die Bausteine, die von server-gerendertem
   Blade-Markup weiterhin als reines Laufzeit-/UI-Verhalten gebraucht
   werden (kein fetch() von content/*.json mehr - alle Daten stehen schon
   im HTML):
     - kjsImgFallback (Bild-Variante fehlt -> Original nachladen)
     - kjsActivateEmbed (datenschutzfreundliche Zwei-Klick-Einbindung fuer
       YouTube-/Google-Kalender-Embeds, deren Platzhalter jetzt direkt in
       Blade gebaut wird statt per JS-Helper-Funktion)
     - "Verwandte Seiten" (data-related-nav, Vorstand/Obleute/
       Kreisjägermeister-Sidebar)
     - Bildergalerie-Lightbox (.galerie-item, Aktuelles-Beitragsseite)
     - Tabellen-Responsiveness (wrapContentTables/labelTableCells/
       applyTableLayout, fuer admin-/Markdown-generierte Tabellen und die
       Termine-Tabelle)

   Phase 4 (Startseite + komplette Laravel-Navigation, "100% Laravel")
   entfernte anschliessend die letzten vier Laufzeit-Module dieser Datei,
   die noch per fetch() Content-/Navigations-JSON nachgeladen hatten:
     - "Topbar & Geschäftsstelle dynamisch laden" (content/einstellungen.json)
       -> jetzt App\View\Composers\TopbarComposer + components/
       kontaktbox.blade.php (settings-Tabelle direkt serverseitig).
     - "ZENTRALE NAVIGATION" (navigation.json/navigation-extra.json +
       seiten-kjs.json/seiten-aufgaben.json/seiten-verbraucher.json)
       -> jetzt App\View\Composers\NavigationComposer + App\Support\
       Navigation (settings-Tabelle + Page-Modell direkt serverseitig,
       siehe components/site-header.blade.php).
     - "ZENTRALER FOOTER" (content/footer.json)
       -> jetzt App\View\Composers\FooterComposer (settings-Tabelle +
       footer_links-Tabelle direkt serverseitig, siehe components/
       site-footer.blade.php).
     - "ZENTRALE BREADCRUMB-KOMPONENTE" (Pfad + navigation.json, inkl. der
       window.setBreadcrumbTrail()/setBreadcrumbCurrentTitle()-Hooks, die
       zuvor in einzelnen Blade-Views per Inline-<script> aufgerufen
       wurden - u.a. Ursache des vom Kunden gemeldeten "Wird geladen …"
       auf /aufgaben/jagdhundeschule)
       -> jetzt vollstaendig serverseitig ueber <x-breadcrumbs :items="...">
       (siehe components/breadcrumbs.blade.php, per :breadcrumbs-Prop von
       <x-page-hero> gefuellt).
   Die zugehoerige reine UI-Interaktion bleibt erhalten: das verzoegerte
   Oeffnen/Schliessen der Dropdown-Flyouts per Hover (siehe "Hover-Flyouts"
   weiter unten) - die Dropdowns selbst funktionieren dank CSS (:hover)
   auch ganz ohne dieses Skript, die Verzoegerung ist eine reine UX-
   Verbesserung ohne jede Datenabhaengigkeit.

   Herkunft: bewusst KEINE 1:1-Kopie von js/main.js (1565 Zeilen) und
   js/components.js (389 Zeilen) aus dem alten Webroot, sondern eine
   kuratierte Teilmenge - genau der Teil, der noch echtes Laufzeit-/UI-
   Verhalten ist. Formulare, Bildergalerien, Tabellen-Layout, Downloads-Box
   pro Seite, Markdown-Rendering, Sondermodul-Formulare gehoeren zu
   einzelnen, bereits auf Laravel/Blade umgestellten Inhaltsseiten.

   Ladereihenfolge: @vite(...) im Layout rendert dieses Skript als
   type="module", das automatisch erst NACH vollstaendigem Parsen des
   Dokuments ausgefuehrt wird (Browser-Standardverhalten fuer Modul-Skripte,
   vergleichbar mit "defer") - #mainNav/#mobileNavList/#siteFooter/
   #siteBreadcrumb sind dadurch bereits vom Server fertig gerendert, wenn
   dieser Code laeuft. Kein setTimeout()/Polling noetig.
   ========================================================= */

/* =========================================================
   Mobiles Menue: oeffnen/schliessen (unveraendert aus main.js, Zeilen
   "Mobile Navigation" bis "Close mobile nav on ESC")
   ========================================================= */
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

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobileNav(); });

// Bugfix (Frank-Report, 04.09.2026): Menuezustaende nach bfcache-Restore
// (Browser-Zurueck) zuruecksetzen - siehe main.js fuer die vollstaendige
// Begruendung. Unveraendert uebernommen, weil rein clientseitiges
// Chrome-Verhalten, unabhaengig vom Rendering-Backend.
window.addEventListener('pageshow', function (e) {
  if (!e.persisted) return;
  document.querySelectorAll('.main-nav .nav-open').forEach(function (el) {
    el.classList.remove('nav-open');
  });
  closeMobileNav();
  document.querySelectorAll('#mobileNavList details[open]').forEach(function (d) {
    d.removeAttribute('open');
  });
});

/* =========================================================
   Externe Links automatisch in neuem Tab oeffnen (unveraendert aus
   main.js) - generisches Verhalten, gilt fuer Header/Footer/Breadcrumb-
   Links genauso wie spaeter fuer Inhaltslinks.
   ========================================================= */
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

  scan(document.documentElement);

  if (window.MutationObserver) {
    new MutationObserver(function(mutations) {
      mutations.forEach(function(m) {
        m.addedNodes.forEach(scan);
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();

/* =========================================================
   Smooth Scroll fuer Sprungmarken-Links (unveraendert aus main.js)
   ========================================================= */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    let target;
    try {
      target = document.querySelector(href);
    } catch (err) {
      return;
    }
    if (target) {
      e.preventDefault();
      const offset = 100;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

/* =========================================================
   Hover-Flyouts der Hauptnavigation (Phase 4: aus dem bisherigen
   "ZENTRALE NAVIGATION"-Modul herausgeloest - reine UI-Verzoegerung beim
   Schliessen der Dropdowns per Hover, ohne jede Datenabhaengigkeit. Die
   Navigation selbst ist jetzt serverseitig gerendert (siehe
   components/site-header.blade.php + App\Support\Navigation), die
   Dropdowns oeffnen/schliessen dank CSS (":hover") auch ganz ohne dieses
   Skript - es sorgt nur fuer das sanftere, leicht verzoegerte Schliessen
   statt eines abrupten CSS-":hover"-Wegklappens.
   ========================================================= */
(function () {
  var CLOSE_DELAY = 350;
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
  wire('.main-nav > li');
  wire('.has-sub');
})();

// Die Hauptnavigation steht (Phase 4) bereits fertig serverseitig gerendert
// im HTML, sobald dieses Skript laeuft - kein fetch() mehr noetig, auf das
// andere Module (z.B. "Verwandte Seiten" weiter unten) warten muessten.
// window.__navReady bleibt als Hook erhalten (bereits aufgeloest), damit
// diese Module unveraendert funktionieren.
window.__navReady = Promise.resolve();

/* =========================================================
   Phase 2: echte Thumbnail-/Vorschaubilder - Fallback aufs Original
   (1:1 aus js/main.js uebernommen). Blade liefert bereits die kleinere
   Bild-Variante als src (siehe App\Support\Images), data-full traegt den
   Original-Pfad fuer den Fehlerfall.
   ========================================================= */
window.kjsImgFallback = function (imgEl) {
  imgEl.onerror = null;
  var full = imgEl.getAttribute('data-full');
  if (full) imgEl.src = full;
};

/* =========================================================
   Phase 2: datenschutzfreundliche Zwei-Klick-Einbindung fuer Drittanbieter-
   Embeds (1:1 aus js/main.js uebernommen, nur der Platzhalter-HTML-Bau
   entfaellt - der kommt jetzt direkt aus der jeweiligen Blade-View).
   ========================================================= */
window.kjsActivateEmbed = function (btn) {
  var wrap = btn.closest('.kjs-embed-placeholder');
  if (!wrap) return;
  var src = wrap.getAttribute('data-embed-src');
  if (!src) return;
  var iframe = document.createElement('iframe');
  iframe.src = src;
  iframe.setAttribute('allowfullscreen', '');
  iframe.loading = 'lazy';
  iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;';
  wrap.innerHTML = '';
  wrap.appendChild(iframe);
};

/* =========================================================
   Phase 2: Tabellen aus dem Admin/TipTap bzw. aus serverseitig gerendertem
   Markdown (Str::markdown()) responsiv machen (1:1 aus js/main.js
   uebernommen - siehe dortiger Kommentar fuer die vollstaendige
   Begruendung). Bewusst weiterhin per MutationObserver, auch wenn Phase 2
   selbst nichts mehr per innerHTML nachlaedt: der Termine-Kategorie-Filter
   (siehe termine.blade.php) aendert keine Tabellenzeilen-Struktur, andere
   spaetere Phasen koennten das aber wieder tun.
   ========================================================= */
function wrapContentTables(root) {
  root.querySelectorAll('table:not(.termine-table)').forEach(function (table) {
    if (table.parentElement && table.parentElement.classList.contains('content-table-wrap')) return;
    var wrap = document.createElement('div');
    wrap.className = 'content-table-wrap';
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
}

function labelTableCells(root) {
  root.querySelectorAll('table').forEach(function (table) {
    var headRow = table.querySelector('thead tr');
    var bodyRows;
    if (headRow) {
      bodyRows = table.querySelectorAll('tbody tr');
    } else {
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
    Array.prototype.forEach.call(headCells, function (cell, i) {
      if (labels[i]) cell.setAttribute('data-label', labels[i]);
    });
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

/* =========================================================
   Phase 2: "Verwandte Seiten" - generische rechte Navigation (1:1 aus
   js/main.js uebernommen). Fuellt jede <ul class="sidebar-nav"
   data-related-nav> automatisch mit den "Geschwister-Seiten" der aktuellen
   Seite, direkt aus dem echten Hauptmenue ausgelesen (Phase 4: die
   Hauptnavigation steht bereits serverseitig im DOM, siehe
   window.__navReady oben - dieses Modul liest weiterhin nur das bereits
   gerenderte <nav aria-label="Hauptnavigation"> aus, unveraendert
   gegenueber Phase 2). Gebraucht von Vorstand/Obleute/Hegeringe/
   Kreisjägermeister (siehe jeweilige Blade-View).
   ========================================================= */
(function () {
  var targets = document.querySelectorAll('[data-related-nav]');
  if (!targets.length) return;

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

/* =========================================================
   Phase 2: Bildergalerie-Lightbox (site-weit, 1:1 aus js/main.js
   uebernommen). Klick auf ein .galerie-item (Aktuelles-Beitragsseite)
   oeffnet ein Overlay mit Vor/Zurueck-Navigation statt der Bilddatei als
   eigene Seite. Per Event-Delegation registriert, funktioniert dadurch
   automatisch fuer jede kuenftige Galerie.
   ========================================================= */
(function () {
  var overlay = null, imgEl = null, captionEl = null, counterEl = null;
  var items = [];
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
