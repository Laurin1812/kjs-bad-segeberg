/* KJS Segeberg – components.js

   Zentraler Header/Topbar (Architektur-Audit Phase 3B, 01.09.2026).

   Vorher: pro HTML-Datei ein komplett ausgeschriebener Block aus
   <div class="topbar">…</div>, <header class="site-header">…</header>
   und <nav class="mobile-nav">…</nav> (~42 Dateien). Die Bestandsaufnahme
   vor diesem Umbau hat gezeigt: anders als beim Footer (Phase 3A) gab es
   hier KEINE echten inhaltlichen Unterschiede zwischen den Seiten - alle
   42 Topbar-/Header-/Mobile-Nav-Blöcke waren bei vollständigem Vergleich
   (Whitespace/Kommentare ausgenommen) byte-identisch, bis auf die von der
   Verzeichnistiefe abhängigen relativen Pfade (z.B. "../images/logo.png"
   vs. "images/logo.png", "../index.html" vs. "index.html"). Eine
   fachliche Rückfrage war hier deshalb nicht nötig.

   Jetzt: jede umgestellte Seite bindet nur noch einen leeren Container
   ein (<div id="siteHeader"></div>), der hier zentral befüllt wird - mit
   root-relativen Pfaden (/images/logo.png, /), unabhängig von der
   Verzeichnistiefe, genau wie bei Navigation (Phase 2) und Footer
   (Phase 3A). Die Kontaktdaten in der Topbar (E-Mail/Telefon) werden
   NICHT hier neu/zusätzlich hartcodiert - das bereits bestehende
   Hydration-Skript in main.js ("Topbar & Geschäftsstelle dynamisch
   laden", liest aus content/einstellungen.json) überschreibt die
   Platzhalter-Werte unten unverändert weiter, genau wie es das vorher
   bei der statischen Topbar auch schon getan hat. Es entsteht dadurch
   keine zweite Datenquelle für Kontaktdaten.

   Bewusst NICHT hierher verschoben: main.js bleibt für diese Phase
   komplett unverändert (fetchContent(), die zentrale Navigation aus
   Phase 2, der zentrale Footer aus Phase 3A, die Mobile-Nav-Toggle-
   Verdrahtung) - all das funktioniert bereits heute rein über
   document.getElementById()-Lookups zur Laufzeit von main.js (das am
   Ende von <body> lädt) und ist dadurch VÖLLIG UNABHÄNGIG davon, ob die
   gesuchten Elemente (#mainNav, #mobileNavList, #navToggle, #mobileNav,
   .topbar__left a, ...) aus statischem HTML stammen oder - wie ab jetzt
   auf den umgestellten Seiten - aus dieser Datei. Das ist der Grund,
   warum ein schrittweiser Rollout (Punkt 6 der Phase-3B-Vorgabe) hier
   überhaupt sicher möglich ist: noch nicht umgestellte Seiten behalten
   ihren bisherigen statischen Header/Topbar/Mobile-Nav vollständig bei
   und sind von dieser Datei überhaupt nicht betroffen, während
   umgestellte Seiten optisch/funktional identisch bleiben. main.js muss
   dafür nicht angefasst werden - eine Vermischung/Race Condition
   zwischen "alter" und "neuer" Logik in main.js selbst entsteht dadurch
   gar nicht erst. Erst nach vollständigem Rollout auf alle Seiten (wenn
   keine Seite mehr die alte statische Variante nutzt) wird das in einem
   separaten, klar benannten Aufräumschritt relevant - siehe Abschluss-
   bericht.

   Ladereihenfolge: diese Datei wird auf umgestellten Seiten als ERSTES
   <script> direkt nach <body> eingebunden, main.js unverändert am Ende
   von <body>. Der Header wird dadurch synchron gebaut, BEVOR der Rest
   der Seite überhaupt geparst wird - optisch identisch zum vorherigen
   statischen Header, kein sichtbarer Sprung/keine Layoutverschiebung.
   #mainNav/#mobileNavList existieren dadurch bereits, wenn das zentrale
   Navigations-Modul in main.js sie befüllt; #navToggle/#mobileNav/
   .topbar existieren bereits, wenn main.js später die Mobile-Nav-
   Interaktion verdrahtet bzw. die Topbar-Kontaktdaten hydriert. Keine
   setTimeout()/Polling-Lösung nötig - reine Skript-Ladereihenfolge.
   ========================================================= */
(function () {
  var mount = document.getElementById('siteHeader');
  if (!mount) return;

  var html =
    '<div class="topbar"><div class="container">' +
      '<div class="topbar__left">' +
        '<span>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></span>' +
        '<span>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></span>' +
      '</div>' +
      '<div class="topbar__right">' +
        '<div class="topbar__social">' +
          '<a href="#" aria-label="Facebook" title="Facebook" class="topbar__social--facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>' +
          '<a href="#" aria-label="Instagram" title="Instagram" class="topbar__social--instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg></a>' +
        '</div>' +
      '</div>' +
    '</div></div>' +
    '<header class="site-header">' +
      '<div class="container header-inner">' +
        '<a href="/" class="site-logo">' +
          '<img src="/images/logo.png" alt="KJS Segeberg Logo" style="height:76px;width:auto;">' +
          '<div class="site-logo__text">' +
            '<span class="site-logo__name">Kreisjägerschaft</span>' +
            '<span class="site-logo__sub">Segeberg <span class="no-caps">e.V.</span></span>' +
          '</div>' +
        '</a>' +
        '<nav aria-label="Hauptnavigation">' +
          '<ul class="main-nav" id="mainNav"></ul>' +
        '</nav>' +
        '<button class="nav-toggle" id="navToggle" aria-label="Menü öffnen"><span></span><span></span><span></span></button>' +
      '</div>' +
    '</header>' +
    '<nav class="mobile-nav" id="mobileNav" aria-label="Mobile Navigation">' +
      '<button class="mobile-nav__close" id="mobileNavClose" aria-label="Menü schließen">✕</button>' +
      '<ul id="mobileNavList"></ul>' +
    '</nav>';

  mount.outerHTML = html;
})();
