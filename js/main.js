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

// Contact form handler
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', e => {
    e.preventDefault();
    const btn = contactForm.querySelector('button[type="submit"]');
    btn.textContent = 'Nachricht gesendet ✓';
    btn.disabled = true;
    btn.style.background = 'var(--green-mid)';
  });
}
