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

/* =========================================================
   Zentrale Breadcrumb-Komponente (Architektur-Audit Phase 3C, 01.09.2026).

   Vorher: pro HTML-Datei ein von Hand ausgeschriebener
   <nav class="breadcrumb">...</nav>-Block mit Links/Labels, die bei jeder
   Umbenennung/Umsortierung im Admin (navigation.json) von Hand nachgepflegt
   werden mussten - Bestandsaufnahme vor diesem Umbau hat dabei mehrere
   Alt-Fehler gefunden (z.B. alle Aufgaben-Unterseiten zeigten "Jäger" statt
   "Aufgaben der Kreisjägerschaft" als Zwischenebene; kreisjjaegermeister/
   index.html hatte gar keine "Jäger"-Zwischenebene, obwohl seine
   Geschwisterseiten ueber-uns.html/infomobil.html sie zeigen).

   Jetzt: jede umgestellte Seite bindet nur noch ein leeres
   <nav class="breadcrumb" aria-label="Breadcrumb" id="siteBreadcrumb"></nav>
   ein (gleiche CSS-Klassen wie vorher, keine optische Änderung). Die
   Hierarchie wird automatisch aus dem aktuellen URL-Pfad + der bereits
   bestehenden zentralen Struktur in content/navigation.json abgeleitet
   (sektionsnamen, hauptmenu_meta, jaeger_dropdown_meta, kjs/aufgaben/
   verbraucher-Arrays) - keine neue, zusätzlich zu pflegende Breadcrumb-JSON
   (Punkt 2 der Phase-3C-Vorgabe). Diese Datei holt sich navigation.json
   dafür bewusst über einen EIGENEN fetch() (statt über js/main.js), damit
   main.js für diese Phase komplett unverändert bleiben kann (siehe
   Kommentar zum Header-Modul oben) - der doppelte Request auf dieselbe,
   ohnehin schon zentrale Datei ist kein zweiter Datenbestand, nur ein
   zweiter Leser desselben Bestands, und wird vom Browser-Cache in der
   Praxis kaum spürbar sein.

   Fachliche Tiefe (mit Laurin am 01.09.2026 abgestimmt, siehe
   Abschlussbericht Phase 3C für die vollständige Bestandsaufnahme):
     Startseite > Jäger > KJS Segeberg > <Seite>              (kjs-Array)
     Startseite > Jäger > Aufgaben der Kreisjägerschaft > <Seite>  (aufgaben-Array)
     Startseite > Verbraucher > <Seite>                       (verbraucher-Array, unverändert)
     Startseite > Jäger > <Seite>                             (Über uns/Infomobil/Kreisjägermeister)
   "KJS Segeberg" und "Aufgaben der Kreisjägerschaft" sind reine
   Dropdown-Gruppierungen ohne eigene Zielseite (navigation.json:
   jaeger_dropdown_meta.{kjs-segeberg,aufgaben} = {dropdown:true}, kein
   href) - ihr Zwischenebenen-Eintrag bleibt deshalb zwangsläufig
   unverlinkter Text, genau wie "Jäger" es für sie bisher schon war.
   "Jäger"/"Verbraucher"/"Hundebörse"/"Aktuelles" haben dagegen eine echte
   Indexseite und werden deshalb (anders als bisher bei Jäger/Aufgaben)
   als Zwischenebene verlinkt - dieselbe Regel, die Hundebörse/Verbraucher
   für ihre eigenen Unterseiten schon immer befolgt haben.

   Dynamische Seiten (aktuelles/beitrag.html, hundeboerse/detail.html,
   aufgaben/jagdhundeschule.html, seiten/index.html): deren finaler Titel
   (bzw. bei seiten/index.html die GESAMTE Zwischenebene, da dort je nach
   ?s=-Parameter ganz unterschiedliche Bereiche gemeint sein können) steht
   erst nach einem eigenen fetch() der jeweiligen Seite fest. Zwei kleine,
   global verfügbare Funktionen lösen das ohne setTimeout()/Polling, rein
   reihenfolge-sicher über normale Promise-/Callback-Verkettung an genau
   der Stelle, an der die jeweilige Seite ihren Titel ohnehin schon kennt:
     setBreadcrumbCurrentTitle(text)   - ersetzt nur das letzte Element
     setBreadcrumbTrail([{label,href?},...]) - ersetzt die komplette Kette
   Wird eine der beiden Funktionen aufgerufen BEVOR diese Datei ihren
   eigenen ersten Render fertig hat, wird der Wert nur zwischengespeichert
   und automatisch angewendet, sobald der erste Render steht - und
   umgekehrt sofort angewendet, falls der erste Render schon steht. Beide
   Reihenfolgen sind normal (abhängig davon, welcher der beiden fetch()-
   Aufrufe zuerst zurückkommt) und werden dadurch ohne Timer korrekt
   behandelt.

   Additiv/Opt-in wie das Header-Modul oben: Seiten, die noch kein
   <... id="siteBreadcrumb"> einbinden (weil sie noch nicht auf Phase 3C
   umgestellt wurden), werden von diesem Modul überhaupt nicht berührt -
   ihr bisheriger von Hand gepflegter Breadcrumb-Block bleibt unverändert
   bestehen, bis die jeweilige Seite einzeln umgestellt wird (Punkt 6 der
   Phase-3C-Vorgabe, schrittweiser Rollout).
   ========================================================= */
(function () {
  // WICHTIG: diese Datei wird als ERSTES <script> direkt nach <body>
  // geladen (siehe Kommentar zum Header-Modul oben) - der Breadcrumb-
  // Container weiter unten im Dokument (in .page-hero) existiert zu
  // diesem Zeitpunkt im DOM noch gar nicht. Der eigentliche Aufbau
  // (mount-Suche, fetch, Rendern) läuft deshalb erst nach dem Parsen des
  // restlichen Dokuments (siehe init()/DOMContentLoaded weiter unten) -
  // die öffentliche API (setBreadcrumbCurrentTitle/setBreadcrumbTrail)
  // wird davon UNABHÄNGIG schon jetzt, synchron, bereitgestellt, damit
  // ein Aufruf durch eine dynamische Seite (siehe Kommentar oben) so oder
  // so - egal ob vor oder nach dem eigentlichen Rendern - sicher
  // ankommt (kein setTimeout()/Polling, reine Zwischenspeicherung).
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

  // Global verfügbar, damit dynamische Seiten (siehe Kommentar oben) sie
  // direkt aus ihrem eigenen fetch(...).then(...) heraus aufrufen können -
  // unabhängig davon, ob der erste Render (siehe init() weiter unten)
  // bereits stattgefunden hat oder nicht (dann wird nur zwischengespeichert).
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

  // Normalisiert sowohl den aktuellen Browser-Pfad als auch die href-Werte
  // aus navigation.json auf dieselbe endungslose Form, damit der Vergleich
  // unabhängig davon funktioniert, ob Netlifys "Pretty URLs" (siehe
  // gleichnamiger Kommentar zu prettyHref() in main.js) im Einzelfall
  // gerade die ".html"-Endung entfernt hat oder nicht.
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

    // Sonderfall: aufgaben/jagdhundeschule.html ist bewusst NICHT im
    // "aufgaben"-Array (kein eigener Hauptmenü-Eintrag, nur über
    // hundeausbildung.html erreichbar) - deshalb vor der generischen
    // Aufgaben-Prüfung eigens behandelt.
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
    if (path === '/hundeboerse') return [START, { label: (hm.hundeboerse && hm.hundeboerse.label) || 'Hundebörse' }];
    if (path === '/aktuelles') return [START, { label: (hm.aktuelles && hm.aktuelles.label) || 'Aktuelles' }];
    // Waffenbörse: seit 02.09.2026 (Korrektur zu Phase 1) eigener
    // Hauptnavigations-Eintrag in navigation.json (hauptmenu_meta.waffenboerse) -
    // Label kommt daher wie bei Hundebörse/Aktuelles direkt von dort, der
    // feste Text bleibt nur als Absicherung, falls navigation.json einmal
    // nicht ladbar ist.
    if (path === '/waffenboerse') return [START, { label: (hm.waffenboerse && hm.waffenboerse.label) || 'Waffenbörse' }];

    if (path === '/hundeboerse/anbieten') {
      return [START, { label: (hm.hundeboerse && hm.hundeboerse.label) || 'Hundebörse', href: '/hundeboerse/index.html' }, { label: 'Hund / Wurf anbieten' }];
    }
    if (path === '/hundeboerse/detail') {
      return [START, { label: (hm.hundeboerse && hm.hundeboerse.label) || 'Hundebörse', href: '/hundeboerse/index.html' }, { label: 'Wird geladen …' }];
    }
    if (path === '/waffenboerse/detail') {
      return [START, { label: (hm.waffenboerse && hm.waffenboerse.label) || 'Waffenbörse', href: '/waffenboerse/index.html' }, { label: 'Wird geladen …' }];
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

    // Seiten außerhalb der Hauptnavigation (kein Eintrag in navigation.json)
    // - Label bewusst hier fest hinterlegt statt vom <h1> übernommen, weil
    // die bisherigen Breadcrumb-Labels teils kürzer sind als die jeweilige
    // <h1>-Überschrift (z.B. "Datenschutz" vs. "Datenschutzerklärung",
    // "Downloads" vs. "Downloads & Dokumente") - unverändert übernommen,
    // um an dieser Stelle keine Formulierung zu ändern.
    var OFFNAV_LABELS = {
      '/datenschutz': 'Datenschutz',
      '/impressum': 'Impressum',
      '/downloads': 'Downloads',
      '/test/testseite': 'Testseite'
    };
    if (OFFNAV_LABELS[path]) return [START, { label: OFFNAV_LABELS[path] }];

    // Letzter Fallback für sonstige, hier nicht bekannte Seiten: Label vom
    // sichtbaren <h1> bzw. <title> übernehmen, damit wenigstens ein
    // sinnvoller, nicht-leerer Breadcrumb entsteht statt gar keinem.
    var h1 = document.querySelector('h1');
    var fallbackLabel = (h1 && h1.textContent && h1.textContent.trim()) || (document.title || '').split(' – ')[0].trim();
    if (fallbackLabel) return [START, { label: fallbackLabel }];

    return null;
  }

  function init() {
    mount = document.getElementById('siteBreadcrumb');
    if (!mount) { ready = true; return; } // Seite noch nicht auf Phase 3C umgestellt

    fetch('/content/navigation.json')
      .then(function (r) { return r.json(); })
      .catch(function () { return {}; })
      .then(function (nav) {
        var items = buildFromNav(nav || {});
        ready = true;
        if (items) render(items);
        applyPending();
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
