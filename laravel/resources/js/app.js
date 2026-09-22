/* =========================================================
   KJS Segeberg – Laravel-Fundament-JavaScript (Phase 1 + Phase 2)
   =========================================================

   Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration) ergaenzt
   diese Datei um genau die Bausteine, die von jetzt server-gerendertem
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
   Bewusst NICHT uebernommen: die JS-seitigen Bild-Varianten-Helfer
   (kjsVariantUrl/kjsThumbUrl/kjsCardUrl) und der JSON-Platzhalter-Baustein
   kjsEmbedPlaceholder() - beide bauen im alten Code HTML aus rohem JSON
   zusammen, das jetzt bereits fertig aus Blade kommt (siehe
   App\Support\Images fuer die serverseitige Entsprechung der Bild-
   Varianten-Ableitung).

   Herkunft: bewusst KEINE 1:1-Kopie von js/main.js (1565 Zeilen) und
   js/components.js (389 Zeilen) aus dem alten Webroot, sondern eine
   kuratierte Teilmenge - genau der Teil, der Header/Topbar/Hauptnavigation/
   mobiles Menue/Breadcrumb/Footer (die "Chrome", die auf jeder Seite
   gleich aussieht) zum Laufen bringt. Alles, was zu konkreten Inhalts-
   seiten gehoert (Formulare, Bildergalerien, Tabellen-Layout, Downloads-
   Box pro Seite, Markdown-Rendering, Sondermodul-Formulare), wurde bewusst
   NICHT uebernommen - das kommt in Phase 2/6, wenn die jeweiligen Inhalte
   selbst auf Laravel/Blade umgestellt werden.

   Wichtiger Unterschied zum alten Aufbau: js/components.js hat bisher den
   KOMPLETTEN Header/Topbar/Mobile-Nav-Rahmen selbst per JS ins DOM injiziert
   (var mount = document.getElementById('siteHeader'); mount.outerHTML = ...),
   weil es im alten System keine echte Server-Templating-Schicht gab. Mit
   Blade gibt es diese Schicht jetzt - der Rahmen (Logo, Topbar-Kontakt-
   Platzhalter, die leeren <ul id="mainNav">/<ul id="mobileNavList">-
   Container) wird deshalb direkt serverseitig gerendert (siehe
   resources/views/components/site-header.blade.php). Diese Datei uebernimmt
   nur noch das, was echte Laufzeit-Daten braucht: die NAV-EINTRAEGE selbst
   (aus navigation.json/navigation-extra.json + dynamischen Admin-Unterseiten
   zusammengebaut) und den FOOTER-INHALT (aus footer.json) werden weiterhin
   per JS nachgeladen - serverseitiges Rendering dieser beiden aus Eloquent
   ist bewusst erst Phase 4 des Migrationsplans ("Navigation serverseitig"),
   nicht Teil des Blade-Fundaments hier.

   Ladereihenfolge: @vite(...) im Layout rendert dieses Skript als
   type="module", das automatisch erst NACH vollstaendigem Parsen des
   Dokuments ausgefuehrt wird (Browser-Standardverhalten fuer Modul-Skripte,
   vergleichbar mit "defer") - #mainNav/#mobileNavList/#siteFooter/
   #siteBreadcrumb existieren dadurch bereits, wenn dieser Code laeuft.
   Kein setTimeout()/Polling noetig, genau wie im Original.
   ========================================================= */

// ── content/*.json laden (unveraendert aus main.js uebernommen) ─────────
// Leitet jeden Aufruf transparent auf die Laravel-Read-API um (Phase 3,
// bereits produktiv) und haengt einen Cache-Buster an.
function fetchContent(path) {
  if (path.indexOf('/content/') === 0) {
    path = '/api' + path;
  }
  return fetch(path + (path.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
}

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
   Topbar & Geschaeftsstelle dynamisch laden (unveraendert aus main.js,
   liest weiterhin content/einstellungen.json ueber die Laravel-Read-API)
   ========================================================= */
var ICONS = {
  mail:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
  phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
  home:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7"/><path d="M9 22V12h6v10"/><path d="M5 10v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10"/></svg>',
  pin:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>'
};

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

  topbarLinks.forEach(function(a) {
    var span = a.parentElement;
    if (!span || span.querySelector('svg')) return;
    var icon = a.href.indexOf('mailto:') > -1 ? ICONS.mail
             : a.href.indexOf('tel:') > -1 ? ICONS.phone
             : null;
    if (!icon) return;
    Array.prototype.slice.call(span.childNodes).forEach(function(node) {
      if (node.nodeType === 3) span.removeChild(node);
    });
    span.insertAdjacentHTML('afterbegin', '<span class="topbar__icon">' + icon + '</span>');
  });

  fetchContent('/content/einstellungen.json')
    .then(function(r) { return r.json(); })
    .then(function(d) {
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
      var adresseHtml = d.adresse ? d.adresse.trim().split('\n').join('<br>') : '';
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
   ZENTRALE NAVIGATION (unveraendert aus main.js uebernommen - liefert
   weiterhin Desktop-/Mobile-Menue aus navigation.json + navigation-extra.json
   + den dynamischen Admin-Unterseiten-Registries. Server-seitiges Rendering
   dieser Daten aus Eloquent ist Phase 4 des Migrationsplans, nicht Teil des
   Blade-Fundaments.)
   ========================================================= */
(function () {
  var mainNavRoot = document.getElementById('mainNav');
  var mobileNavRoot = document.getElementById('mobileNavList');
  if (!mainNavRoot || !mobileNavRoot) { window.__navReady = Promise.resolve(); return; }

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

  function wireHoverFlyouts() {
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
  }

  function fetchJsonSafe(path) {
    return fetchContent(path).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  function filteredSeiten(list) {
    return (list || []).filter(function (s) { return s.veroeffentlicht === true && s.in_navigation === true; })
      .map(function (s) { return { label: s.nav_label || s.titel, href: '/seiten/?s=' + encodeURIComponent(s.slug) }; });
  }

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
    var merged = mergeDynamicSeiten(FALLBACK_NAV, []);
    renderDesktopNav(merged);
    renderMobileNav(merged);
    wireHoverFlyouts();
  });
})();

/* =========================================================
   ZENTRALER FOOTER (unveraendert aus main.js uebernommen - liefert
   weiterhin den Footer-Inhalt aus footer.json. Server-seitiges Rendering
   ist Phase 4 des Migrationsplans, nicht Teil des Blade-Fundaments.)
   ========================================================= */
(function () {
  var footerRoot = document.getElementById('siteFooter');
  if (!footerRoot) return;

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
            '<img src="/images/logo-dunkel.png" alt="KJS Logo" style="height:58px;width:auto;margin-bottom:1rem;">' +
            '<span class="footer-about__name">Kreisjägerschaft Segeberg e.V.</span>' +
            '<span class="footer-about__sub">Mitglied im Landesjagdverband Schleswig-Holstein</span>' +
            (d.ueber_text ? '<p>' + escHtml(d.ueber_text) + '</p>' : '') +
            '<div class="footer-social">' +
              '<a href="' + escHtml(d.facebook_url || '#') + '" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Facebook" class="footer-social--facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>' +
              '<a href="' + escHtml(d.instagram_url || '#') + '" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Instagram" class="footer-social--instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg></a>' +
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

    if (d.facebook_url) {
      var fbTop = document.querySelector('.topbar__social a[aria-label="Kreisjägerschaft Segeberg auf Facebook"]');
      if (fbTop) fbTop.href = d.facebook_url;
    }
    if (d.instagram_url) {
      var igTop = document.querySelector('.topbar__social a[aria-label="Kreisjägerschaft Segeberg auf Instagram"]');
      if (igTop) igTop.href = d.instagram_url;
    }
  }

  fetchContent('/content/footer.json')
    .then(function (r) { return r.json(); })
    .then(renderFooter)
    .catch(function () { renderFooter(FALLBACK_FOOTER); });
})();

/* =========================================================
   ZENTRALE BREADCRUMB-KOMPONENTE (unveraendert aus js/components.js
   uebernommen, Kernlogik). Rendert weiterhin in den von Blade gerenderten
   Container <nav id="siteBreadcrumb"> (siehe
   resources/views/components/breadcrumbs.blade.php), abgeleitet aus dem
   aktuellen URL-Pfad + navigation.json ueber die Laravel-Read-API.
   ========================================================= */
(function () {
  var mount = null;
  var current = null;
  var pendingTitle = null;
  var pendingTrail = null;
  var ready = false;

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render(items) {
    current = items;
    mount.innerHTML = items.map(function (it, i) {
      var isLast = i === items.length - 1;
      var sepHtml = i > 0 ? '<span class="sep">/</span>' : '';
      if (it.href && !isLast) {
        return sepHtml + '<a href="' + escHtml(it.href) + '">' + escHtml(it.label) + '</a>';
      }
      return sepHtml + '<span' + (isLast ? ' aria-current="page"' : '') + '>' + escHtml(it.label) + '</span>';
    }).join('');
  }

  function applyPending() {
    if (pendingTrail) {
      var t = pendingTrail;
      pendingTrail = null;
      pendingTitle = null;
      render(t);
      return;
    }
    if (pendingTitle != null && current && current.length) {
      current[current.length - 1] = { label: pendingTitle };
      render(current);
      pendingTitle = null;
    }
  }

  // Global verfuegbar, damit spaeter migrierte Inhaltsseiten (Phase 2+)
  // ihren finalen Titel selbst nachtragen koennen, sobald sie ihn kennen.
  window.setBreadcrumbCurrentTitle = function (text) {
    if (ready && current && current.length) {
      current[current.length - 1] = { label: text };
      render(current);
    } else {
      pendingTitle = text;
    }
  };

  window.setBreadcrumbTrail = function (items) {
    if (ready && current) render(items);
    else pendingTrail = items;
  };

  function normPath(p) {
    p = (p || '/').split('?')[0].split('#')[0];
    p = p.replace(/index\.html?$/i, '');
    p = p.replace(/\.html?$/i, '');
    if (p.length > 1) p = p.replace(/\/+$/, '');
    return p || '/';
  }

  function buildFromNav(nav) {
    var sn = nav.sektionsnamen || {};
    var hm = nav.hauptmenu_meta || {};
    var jd = nav.jaeger_dropdown_meta || {};
    var path = normPath(window.location.pathname);

    var START = { label: (hm.startseite && hm.startseite.label) || 'Startseite', href: '/' };
    var JAEGER_IDX = { label: sn.jaeger || 'Jäger', href: '/jaeger/index.html' };
    var VERBRAUCHER_IDX = { label: sn.verbraucher || 'Verbraucher', href: '/verbraucher/index.html' };

    function findByPath(list) {
      return (list || []).filter(Boolean).find(function (it) { return it.href && normPath(it.href) === path; });
    }

    if (path === '/aufgaben/jagdhundeschule') {
      var hundeausbildung = (nav.aufgaben || []).find(function (it) { return it.href && normPath(it.href) === '/aufgaben/hundeausbildung'; });
      return [START, JAEGER_IDX, { label: sn.aufgaben || 'Aufgaben der Kreisjägerschaft' },
        { label: (hundeausbildung && hundeausbildung.label) || 'Hundeausbildung', href: '/aufgaben/hundeausbildung.html' },
        { label: 'Wird geladen …' }];
    }

    var kjsMatch = findByPath(nav.kjs);
    if (kjsMatch) return [START, JAEGER_IDX, { label: sn.kjs || 'KJS Segeberg' }, { label: kjsMatch.label }];

    var aufgabenMatch = findByPath(nav.aufgaben);
    if (aufgabenMatch) return [START, JAEGER_IDX, { label: sn.aufgaben || 'Aufgaben der Kreisjägerschaft' }, { label: aufgabenMatch.label }];

    var verbraucherMatch = findByPath(nav.verbraucher);
    if (verbraucherMatch) return [START, VERBRAUCHER_IDX, { label: verbraucherMatch.label }];

    var jaegerLeaf = null;
    Object.keys(jd).forEach(function (k) {
      var m = jd[k];
      if (m && m.href && normPath(m.href) === path) jaegerLeaf = m;
    });
    if (jaegerLeaf) return [START, JAEGER_IDX, { label: jaegerLeaf.label }];

    if (path === '/jaeger') return [START, { label: sn.jaeger || 'Jäger' }];
    if (path === '/verbraucher') return [START, { label: sn.verbraucher || 'Verbraucher' }];
    if (path === '/aktuelles') return [START, { label: (hm.aktuelles && hm.aktuelles.label) || 'Aktuelles' }];

    if (path === '/hundeboerse/anbieten') {
      return [START, JAEGER_IDX, { label: (jd.hundeboerse && jd.hundeboerse.label) || 'Hundebörse', href: '/hundeboerse/index.html' }, { label: 'Hund / Wurf anbieten' }];
    }
    if (path === '/hundeboerse/detail') {
      return [START, JAEGER_IDX, { label: (jd.hundeboerse && jd.hundeboerse.label) || 'Hundebörse', href: '/hundeboerse/index.html' }, { label: 'Wird geladen …' }];
    }
    if (path === '/waffenboerse/detail') {
      return [START, JAEGER_IDX, { label: (jd.waffenboerse && jd.waffenboerse.label) || 'Waffenbörse', href: '/waffenboerse/index.html' }, { label: 'Wird geladen …' }];
    }
    if (path === '/partner/detail') {
      return [START, JAEGER_IDX, { label: (jd.partner && jd.partner.label) || 'Partner', href: '/partner/index.html' }, { label: 'Wird geladen …' }];
    }
    if (path === '/waffenboerse/anbieten') {
      return [START, JAEGER_IDX, { label: (jd.waffenboerse && jd.waffenboerse.label) || 'Waffenbörse', href: '/waffenboerse/index.html' }, { label: 'Anzeige aufgeben' }];
    }
    if (path === '/aktuelles/beitrag') {
      return [START, { label: (hm.aktuelles && hm.aktuelles.label) || 'Aktuelles', href: '/aktuelles/index.html' }, { label: 'Wird geladen …' }];
    }
    if (path === '/seiten' || path === '/seiten/index') {
      return [START, { label: 'Wird geladen …' }];
    }

    var topMatch = null;
    Object.keys(hm).forEach(function (k) {
      var m = hm[k];
      if (m && m.href && normPath(m.href) === path && m.label) topMatch = m;
    });
    if (topMatch) return [START, { label: topMatch.label }];

    var OFFNAV_LABELS = {
      '/datenschutz': 'Datenschutz',
      '/impressum': 'Impressum',
      '/downloads': 'Downloads',
      '/test/testseite': 'Testseite'
    };
    if (OFFNAV_LABELS[path]) return [START, { label: OFFNAV_LABELS[path] }];

    var h1 = document.querySelector('h1');
    var fallbackLabel = (h1 && h1.textContent && h1.textContent.trim()) || (document.title || '').split(' – ')[0].trim();
    if (fallbackLabel) return [START, { label: fallbackLabel }];

    return null;
  }

  function init() {
    mount = document.getElementById('siteBreadcrumb');
    if (!mount) { ready = true; return; }

    fetch('/api/content/navigation.json')
      .then(function (r) { return r.json(); })
      .catch(function () { return {}; })
      .then(function (nav) {
        var items = buildFromNav(nav || {});
        ready = true;
        if (items) render(items);
        applyPending();
      });
  }

  // Kein DOMContentLoaded-Guard noetig: dieses Skript laeuft bereits als
  // deferred Modul-Skript (siehe Datei-Kopfkommentar), das Dokument ist zu
  // diesem Zeitpunkt vollstaendig geparst.
  init();
})();

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
   Seite, direkt aus dem echten (weiterhin aus navigation.json gespeisten,
   siehe Phase-1-Navigationsmodul oben) Hauptmenue ausgelesen. Gebraucht von
   Vorstand/Obleute/Hegeringe/Kreisjägermeister (siehe jeweilige Blade-View).
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
