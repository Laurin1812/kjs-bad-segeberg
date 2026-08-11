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

// ── Tabellen aus dem Admin/TipTap scrollbar machen ──────────────────────
// Wichtig: die <table> selbst bleibt display:table (Spaltenberechnung des
// Browsers funktioniert nur so korrekt) – nur eine umschließende Box
// bekommt overflow-x:auto. Die Termine-Tabelle hat ihre eigene Lösung und
// wird hier bewusst ausgeschlossen. Per MutationObserver, weil viele
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
document.querySelectorAll('.main-content, #seite-inhalt, #page-inhalt').forEach(function (container) {
  wrapContentTables(container);
  new MutationObserver(function () { wrapContentTables(container); })
    .observe(container, { childList: true, subtree: true });
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
(function() {
  var topbarLinks = document.querySelectorAll('.topbar__left a');
  var boxes = document.querySelectorAll('.contact-box');
  if (!topbarLinks.length && !boxes.length) return;
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
      // Geschäftsstelle-Boxen aktualisieren
      var adresseHtml = d.adresse ? d.adresse.trim().split('\n').join('<br>') : '';
      var postadresseHtml = d.postadresse ? d.postadresse.trim().split('\n').join('<br>') : '';
      boxes.forEach(function(box) {
        box.innerHTML =
          '<h4>Geschäftsstelle</h4>' +
          (d.email    ? '<p><span class="cb-icon">📧</span><a href="mailto:' + d.email + '">' + d.email + '</a></p>' : '') +
          (d.telefon  ? '<p><span class="cb-icon">📞</span><a href="tel:' + d.telefon.replace(/\s|-/g,'') + '">' + d.telefon + '</a></p>' : '') +
          (d.telefon_festnetz ? '<p><span class="cb-icon">☎️</span><a href="tel:' + d.telefon_festnetz.replace(/\s|-/g,'') + '">' + d.telefon_festnetz + '</a></p>' : '') +
          (adresseHtml ? '<p><span class="cb-icon">🏠</span><span>' + adresseHtml + '</span></p>' : '') +
          (postadresseHtml ? '<p><span class="cb-icon">✉️</span><span>' + postadresseHtml + '</span></p>' : '');
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
    var KEY_HREF = {
      startseite: /^(\.\.\/)*index\.html$/,
      jaeger:     /jaeger\/index\.html/,
      verbraucher:/verbraucher\/index\.html/,
      termine:    /termine\/index\.html/,
      aktuelles:  /aktuelles\/index\.html/,
      faq:        /faq\/index\.html/,
      kontakt:    /kontakt\/index\.html/
    };

    var jaegerDD = document.getElementById('jaeger-dropdown');
    var mainNav  = document.querySelector('.main-nav');

    fetchContent('/content/navigation.json').then(function(r) { return r.json(); }).then(function(d) {

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
            if (a && KEY_HREF.jaeger.test(a.getAttribute('href') || '')) renameNavLink(a, sn.jaeger);
          });
        }
        // Verbraucher main nav label
        if (sn.verbraucher && mainNav) {
          mainNav.querySelectorAll(':scope > li').forEach(function(li) {
            var a = li.querySelector(':scope > a');
            if (a && KEY_HREF.verbraucher.test(a.getAttribute('href') || '')) renameNavLink(a, sn.verbraucher);
          });
        }
      }

      // ── 3. Main menu reordering (FEATURE 3) ────────────────────
      if (d.hauptmenu && d.hauptmenu.length && mainNav) {
        d.hauptmenu.forEach(function(key) {
          var pattern = KEY_HREF[key];
          if (!pattern) return;
          mainNav.querySelectorAll(':scope > li').forEach(function(li) {
            var a = li.querySelector(':scope > a');
            if (a && pattern.test(a.getAttribute('href') || '')) {
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
          'kjs-segeberg':        function(li) { var a = li.querySelector(':scope > a'); return a && /jaeger\/(index(\.html)?)?$/.test(a.getAttribute('href') || ''); },
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

// ── TEMPORÄR: Testseite ganz unten ins Hauptmenü einfügen ────────────────
// Nur zum Testen des TipTap-Editors – diesen Block nach dem Test wieder
// entfernen (siehe content/test/testseite.json + test/testseite.html).
(function() {
  var mainNav = document.querySelector('.main-nav');
  if (mainNav) {
    var li = document.createElement('li');
    var a  = document.createElement('a');
    a.href = '/test/testseite.html';
    a.textContent = 'Testseite';
    li.appendChild(a);
    mainNav.appendChild(li);
  }
  var mobileNavList = document.querySelector('#mobileNav ul');
  if (mobileNavList) {
    var mli = document.createElement('li');
    var ma  = document.createElement('a');
    ma.href = '/test/testseite.html';
    ma.textContent = 'Testseite';
    mli.appendChild(ma);
    mobileNavList.appendChild(mli);
  }
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
