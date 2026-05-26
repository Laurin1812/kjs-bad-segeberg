/* KJS Bad Segeberg – main.js */

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
  var boxes = document.querySelectorAll('.contact-box');
  if (!boxes.length) return;
  fetch('/content/einstellungen.json')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      // Topbar aktualisieren
      var topbarLinks = document.querySelectorAll('.topbar__left a');
      topbarLinks.forEach(function(a) {
        if (a.href.indexOf('mailto:') > -1 && d.email) {
          a.href = 'mailto:' + d.email;
          a.textContent = d.email;
        }
        if (a.href.indexOf('tel:') > -1 && d.telefon) {
          a.href = 'tel:' + d.telefon.replace(/\s|-/g,'');
          a.textContent = d.telefon;
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

// Weitere Themen: feste Seiten + eigene Seiten dynamisch ins Flyout-Menü laden
(function() {
  var item = document.getElementById('weitere-themen-item');
  var sub  = document.getElementById('weitere-themen-sub');
  if (!item || !sub) return;

  // Feste Seiten unter "Weitere Themen"
  var festSeiten = [
    { label: 'Infomobil', href: '/jaeger/infomobil.html' }
  ];

  // Prüfen ob wir uns bereits auf der Infomobil-Seite befinden
  var currentPath = window.location.pathname;

  festSeiten.forEach(function(s) {
    // Nicht doppelt einfügen – prüfe alle möglichen href-Varianten
    var exists = sub.querySelector('a[href="' + s.href + '"]') ||
                 sub.querySelector('a[href="../jaeger/infomobil.html"]') ||
                 sub.querySelector('a[href="infomobil.html"]');
    if (exists) return;
    var li = document.createElement('li');
    var a  = document.createElement('a');
    a.href = s.href;
    a.textContent = s.label;
    if (currentPath.includes('infomobil')) a.classList.add('active');
    li.appendChild(a);
    sub.insertBefore(li, sub.firstChild);
  });

  item.style.display = '';

  // Dynamische eigene Seiten
  fetch('/content/seiten.json')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var seiten = (data.seiten || []).filter(function(s) {
        return s.veroeffentlicht === true && s.in_navigation === true;
      });
      seiten.forEach(function(s) {
        var li = document.createElement('li');
        var a  = document.createElement('a');
        a.href = '/seiten/?s=' + encodeURIComponent(s.slug);
        a.textContent = s.nav_label || s.titel;
        li.appendChild(a);
        sub.appendChild(li);
      });
    })
    .catch(function(){});
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
      _subject: 'Neue Kontaktanfrage – KJS Bad Segeberg',
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
