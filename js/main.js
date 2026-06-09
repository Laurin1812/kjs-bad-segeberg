/* KJS Segeberg – main.js */

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
  fetch('/content/einstellungen.json')
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
      boxes.forEach(function(box) {
        box.innerHTML =
          '<h4>Geschäftsstelle</h4>' +
          (d.email    ? '<p><span class="cb-icon">📧</span><a href="mailto:' + d.email + '">' + d.email + '</a></p>' : '') +
          (d.telefon  ? '<p><span class="cb-icon">📞</span><a href="tel:' + d.telefon.replace(/\s|-/g,'') + '">' + d.telefon + '</a></p>' : '') +
          (adresseHtml ? '<p><span class="cb-icon">🏠</span><span>' + adresseHtml + '</span></p>' : '');
      });
    })
    .catch(function() {});
})();

// Eigene Seiten nach Bereich in die Navigation verteilen
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

  sektionen.forEach(function(s) {
    fetch(s.url)
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
  fetch('/content/seiten-weitere.json')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var publishedSeiten = (data.seiten || []).filter(function(s) {
        return s.veroeffentlicht === true && s.in_navigation === true;
      });
      if (!publishedSeiten.length) return;
      var weitereItem = document.getElementById('weitere-themen-item');
      var weltereSub  = document.getElementById('weitere-themen-sub');
      if (!weitereItem || !weltereSub) return;
      weitereItem.style.display = '';   // Flyout-Punkt sichtbar machen
      einfuegenInNav(publishedSeiten, weltereSub);
    })
    .catch(function() {});
})();

// Eigene Hauptpunkte aus navigation-extra.json in die Hauptnavigation einfügen
(function() {
  fetch('/content/navigation-extra.json')
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

// Navigationsreihenfolge aus Admin-JSON anwenden
(function() {
  function reorderSub(sub, items) {
    if (!sub || !items || !items.length) return;
    items.forEach(function(item) {
      var filename = item.href.split('/').pop();
      sub.querySelectorAll(':scope > li').forEach(function(li) {
        var a = li.querySelector('a');
        if (a && a.getAttribute('href') && a.getAttribute('href').indexOf(filename) !== -1) {
          sub.appendChild(li);
        }
      });
    });
  }

  var jaegerDD = document.getElementById('jaeger-dropdown');

  // KJS Segeberg
  fetch('/content/nav-reihenfolge-kjs.json').then(function(r){return r.json();}).then(function(d){
    if (!jaegerDD || !d.reihenfolge) return;
    jaegerDD.querySelectorAll('.has-sub').forEach(function(hs){
      var a = hs.querySelector(':scope > a');
      if (a && a.textContent.indexOf('KJS') !== -1) reorderSub(hs.querySelector('ul.dropdown--sub'), d.reihenfolge);
    });
  }).catch(function(){});

  // Aufgaben der Kreisjägerschaft
  fetch('/content/nav-reihenfolge-aufgaben.json').then(function(r){return r.json();}).then(function(d){
    if (!jaegerDD || !d.reihenfolge) return;
    jaegerDD.querySelectorAll('.has-sub').forEach(function(hs){
      var a = hs.querySelector(':scope > a');
      if (a && a.textContent.indexOf('Aufgaben') !== -1) reorderSub(hs.querySelector('ul.dropdown--sub'), d.reihenfolge);
    });
  }).catch(function(){});

  // Verbraucher
  fetch('/content/nav-reihenfolge-verbraucher.json').then(function(r){return r.json();}).then(function(d){
    if (!d.reihenfolge) return;
    document.querySelectorAll('.main-nav > li').forEach(function(li){
      var a = li.querySelector(':scope > a');
      if (a && a.textContent.indexOf('Verbraucher') !== -1) reorderSub(li.querySelector('ul.dropdown'), d.reihenfolge);
    });
  }).catch(function(){});

  // Weitere Themen (dynamisch eingefügte Seiten aus seiten-weitere.json)
  fetch('/content/nav-reihenfolge-weitere.json').then(function(r){return r.json();}).then(function(d){
    if (!jaegerDD || !d.reihenfolge || !d.reihenfolge.length) return;
    // Kleine Verzögerung damit die dynamisch eingefügten Seiten bereits im DOM sind
    setTimeout(function() {
      reorderSub(jaegerDD, d.reihenfolge);
    }, 300);
  }).catch(function(){});
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
