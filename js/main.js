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
          (d.email    ? '<p>📧 <a href="mailto:' + d.email + '">' + d.email + '</a></p>' : '') +
          (d.telefon  ? '<p>📞 <a href="tel:' + d.telefon.replace(/\s|-/g,'') + '">' + d.telefon + '</a></p>' : '') +
          (adresseHtml ? '<p>🏠 ' + adresseHtml + '</p>' : '');
      });
    })
    .catch(function() {});
})();

// Dynamische Seiten in Navigation einbinden
(function() {
  fetch('/content/seiten.json')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var seiten = (data.seiten || []).filter(function(s) {
        return s.veroeffentlicht === true && s.in_navigation === true;
      });
      if (!seiten.length) return;

      var dropdown = document.getElementById('jaeger-dropdown');
      if (!dropdown) {
        dropdown = document.querySelector('.main-nav .dropdown--wide');
      }
      if (!dropdown) return;

      var header = document.createElement('div');
      header.className = 'dropdown-header';
      header.textContent = 'Weitere Themen';
      dropdown.appendChild(header);

      seiten.forEach(function(seite) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = '/seiten/?s=' + seite.slug;
        a.textContent = seite.nav_label || seite.titel;
        li.appendChild(a);
        dropdown.appendChild(li);
      });
    })
    .catch(function() {});
})();

// Contact form handler (Netlify Forms)
var contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = contactForm.querySelector('button[type="submit"]');
    var data = new FormData(contactForm);
    fetch('/', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString()
    })
    .then(function() {
      btn.textContent = 'Nachricht gesendet ✓';
      btn.disabled = true;
      btn.style.background = 'var(--green-mid)';
      contactForm.reset();
    })
    .catch(function() {
      btn.textContent = 'Fehler – bitte erneut versuchen';
      btn.style.background = '#c0392b';
    });
  });
}
