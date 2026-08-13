/* ═══════════════════════════════════════════════════════════════
   KJS Admin – admin.js
   Vanilla JS SPA · Netlify Identity + git-gateway
   ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ────────────────────────────────────────────────────────────
     CONSTANTS
  ──────────────────────────────────────────────────────────── */
  var GIT   = '/.netlify/git/github/contents';
  // Admin-Änderungen landen ab jetzt auf dem "staging"-Branch (Vorschau-Adresse
  // staging--kjs-bad-segeberg.netlify.app), NICHT mehr direkt auf der echten
  // Live-Seite. Erst nach Prüfung/Freigabe wird staging -> main übertragen
  // ("veröffentlichen") – das macht aktuell Claude auf Zuruf, kein Automatik-
  // Knopf hier im Admin (bewusst, um kurz vor Go-Live kein Risiko einzubauen).
  var BRANCH = 'staging';

  var KAT_NEWS    = ['Allgemein','Naturschutz','Jagd','Jungwildrettung','Hundeausbildung','Schießwesen','Jugend','Jagdhornblasen','Veranstaltung','Pressemitteilung'];
  var KAT_TERMINE = ['Vorstand','Schießwesen','Hundeausbildung','Jagdhornblasen','Jugend','Hegering','Naturschutz','Ausbildung','Kreisveranstaltung','Hauptversammlung','Tradition'];

  /* ────────────────────────────────────────────────────────────
     STATE
  ──────────────────────────────────────────────────────────── */
  var S = {
    section: null,   // current nav def object
    data:    null,   // loaded JSON
    sha:     null,   // current SHA
    mde:     null,   // EasyMDE instance
    dirty:   false,
    imgTarget:   null, // field id receiving chosen image
    _tableMode:  false,
    _segments:   null, // parsed segments when in table view
    _tableField: null, // 'inhalt' or 'ns-inhalt'
    _mdeWrap:    null, // cached EasyMDEContainer element
    tiptapEditors:     {},   // fieldId → TipTap Editor instance (Infomobil)
    _tiptapImageField: null, // fieldId des TipTap-Editors, der gerade ein Bild erwartet
  };

  /* ────────────────────────────────────────────────────────────
     NAVIGATION TREE
  ──────────────────────────────────────────────────────────── */
  var NAV = [
    { key:'startseite',  label:'🏠 Startseite',           file:'content/startseite.json',               form:'startseite' },
    { key:'jaeger', label:'🦌 Jäger', group:true, open:true, children:[
      { key:'jaeger-ueber-uns', label:'Über uns', file:'content/jaeger/ueber-uns.json', form:'standard' },
      { key:'kjs', label:'KJS Segeberg', group:true, open:true, children:[
        // 'Übersicht' (content/jaeger/uebersicht.json) bewusst aus dem Admin-Menü
        // entfernt (11.08.2026) - die Seite jaeger/index.html mit den 6 Kacheln
        // (Vorstand/Hegeringe/Obleute/Mitglied werden/Jäger werden/Kreisjägermeister)
        // ist aktuell nirgends verlinkt ("KJS Segeberg" im Menü ist nicht mehr
        // anklickbar), daher wäre dieser Bearbeitungsbereich tot/verwirrend.
        // Bei Bedarf einfach die folgende Zeile wieder einkommentieren:
        // { key:'kjs-uebersicht',   label:'Übersicht',         file:'content/jaeger/uebersicht.json',       form:'standard' },
        { key:'vorstand',         label:'Vorstand',           file:'content/vorstand.json',                form:'personen', dataKey:'mitglieder', fields:['rolle','name','email','telefon','bild'], drag:true },
        { key:'obleute',          label:'Obleute',            file:'content/obleute.json',                 form:'personen', dataKey:'obleute',   fields:['rolle','name','email','telefon','bild'], drag:true },
        { key:'hegeringe',        label:'Hegeringe',          file:'content/hegeringe.json',               form:'hegeringe', drag:true },
        { key:'mitglied-werden',  label:'Mitglied werden',    file:'content/jaeger/mitglied-werden.json',  form:'standard', drag:true },
        { key:'jaeger-werden',    label:'Jäger/in werden',    file:'content/jaeger/jaeger-werden.json',    form:'standard', drag:true },
        { key:'niederwild',       label:'Niederwild',         file:'content/jaeger/niederwild.json',       form:'standard', drag:true },
        { key:'hochwild',         label:'Hochwild',           file:'content/jaeger/hochwild.json',         form:'standard', drag:true },
        { key:'schiessobleute',   label:'Schießobleute',      file:'content/jaeger/schiessobleute.json',   form:'standard', drag:true },
        { key:'satzung',          label:'Satzung',            file:'content/jaeger/satzung.json',          form:'standard', drag:true },
        { key:'landesjagdverband',label:'Landesjagdverband',  file:'content/jaeger/landesjagdverband.json',form:'standard', drag:true },
        { key:'new-kjs', label:'➕ Neue KJS-Unterseite',  form:'neueSeite', isAdd:true,
          navFile:'content/seiten-kjs.json', navKey:'seiten', dir:'content/seiten-kjs' },
      ]},
      { key:'kjm', label:'Kreisjägermeister', file:'content/kreisjjaegermeister.json', form:'kjm' },
      { key:'aufgaben', label:'Aufgaben der KJS', group:true, open:false, children:[
        { key:'auf-schiessen',  label:'Schießwesen',          file:'content/aufgaben/schiessen.json',      form:'standard', drag:true },
        { key:'auf-hunde', label:'Hundeausbildung', group:true, open:false, drag:true, children:[
          { key:'auf-hunde-uebersicht', label:'Übersichtsseite', file:'content/aufgaben/hundeausbildung.json', form:'standard' },
          { key:'jagdhundeschule-gruppe', label:'🐕 Jagdhundeschule (21 Seiten)', group:true, open:false, children:[
            { key:'new-jagdhundeschule', label:'➕ Neue Seite', form:'neueSeite', isAdd:true,
              navFile:'content/aufgaben/hundeausbildung-seiten.json', navKey:'seiten', dir:'content/aufgaben/hundeausbildung' },
          ]},
        ]},
        { key:'auf-schweiss',   label:'Schweißhundeführer',    file:'content/aufgaben/schweisshunde.json',  form:'standard', drag:true },
        { key:'auf-jugend',     label:'Jugendarbeit',          file:'content/aufgaben/jugend.json',         form:'standard', drag:true },
        { key:'auf-jagdhorn',   label:'Jagdhornblasen',        file:'content/aufgaben/jagdhorn.json',       form:'standard', drag:true },
        { key:'auf-natur',      label:'Naturschutz',           file:'content/aufgaben/naturschutz.json',    form:'standard', drag:true },
        { key:'auf-jungwild',   label:'Jungwildrettung',       file:'content/aufgaben/jungwildrettung.json',form:'standard', drag:true },
        { key:'new-aufgaben', label:'➕ Neue Aufgaben-Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-aufgaben.json', navKey:'seiten', dir:'content/seiten-aufgaben' },
      ]},
      { key:'infomobil', label:'Infomobil', file:'content/jaeger/infomobil.json', form:'tiptap' },
      { key:'weitere', label:'Weitere Themen', group:true, open:false, dynamicChildren:true,
        navFile:'content/seiten-weitere.json', navKey:'seiten', dir:'content/seiten-weitere',
        newItemKey:'new-weitere' },
    ]},
    { key:'verbraucher', label:'🌿 Verbraucher', group:true, open:false, children:[
      { key:'verbraucher-wild', label:'Wildfleisch', group:true, open:false, children:[
        { key:'verbraucher-wild-inhalt', label:'Seiteninhalt', file:'content/verbraucher/wildfleisch.json', form:'standard' },
        { key:'new-sub-wild', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-wildfleisch.json', navKey:'seiten', dir:'content/seiten-sub-wildfleisch',
          parentSlug:'wildfleisch' },
      ]},
      { key:'verbraucher-lernort', label:'Lernort Natur', group:true, open:false, children:[
        { key:'verbraucher-lernort-inhalt', label:'Seiteninhalt', file:'content/verbraucher/lernort-natur.json', form:'standard' },
        { key:'new-sub-lernort', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-lernort-natur.json', navKey:'seiten', dir:'content/seiten-sub-lernort-natur',
          parentSlug:'lernort-natur' },
      ]},
      { key:'verbraucher-gruen', label:'Grünes Klassenzimmer', group:true, open:false, children:[
        { key:'verbraucher-gruen-inhalt', label:'Seiteninhalt', file:'content/verbraucher/gruenes-klassenzimmer.json', form:'standard' },
        { key:'new-sub-gruen', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-gruenes-klassenzimmer.json', navKey:'seiten', dir:'content/seiten-sub-gruenes-klassenzimmer',
          parentSlug:'gruenes-klassenzimmer' },
      ]},
      { key:'new-verbraucher', label:'➕ Neue Verbraucher-Seite', form:'neueSeite', isAdd:true,
        navFile:'content/seiten-verbraucher.json', navKey:'seiten', dir:'content/seiten-verbraucher' },
    ]},
    { key:'termine',    label:'📅 Termine',   file:'content/termine.json',   form:'termine' },
    { key:'aktuelles',  label:'📰 Aktuelles', file:'content/aktuelles.json', form:'aktuelles' },
    { key:'faq',        label:'❓ FAQ',        file:'content/faq.json',       form:'faq' },
    { key:'einstellungen', label:'⚙️ Einstellungen', group:true, open:false, children:[
      { key:'kontakt',   label:'Kontakt & Öffnungszeiten', file:'content/einstellungen.json',    form:'einstellungen' },
      { key:'footer',    label:'Fußzeile',                  file:'content/footer.json',           form:'footer' },
      { key:'design',    label:'Design & Farben',           file:'content/design.json',           form:'design' },
      { key:'impressum', label:'Impressum',                  file:'content/impressum.json',        form:'impressum' },
      { key:'nav-extra', label:'🧭 Hauptnavigation erweitern', file:'content/navigation-extra.json', form:'navExtra' },
      { key:'nav-reihenfolge', label:'🔀 Navigation & Reihenfolge', file:'content/navigation.json', form:'navReihenfolge' },
      { key:'benutzer', label:'👥 Benutzerverwaltung', form:'benutzer' },
    ]},
    { key:'downloads', label:'📥 Downloads', file:'content/downloads.json', form:'downloads' },
    { key:'medien',    label:'🖼️ Medien & Bilder', form:'medien' },
    { key:'testseite', label:'🧪 Testseite', file:'content/test/testseite.json', form:'tiptap' },
  ];

  /* ────────────────────────────────────────────────────────────
     SIDEBAR DRAG & DROP – Mapping NAV-Key → navigation.json-Eintrag
     (für statische Seiten, deren Reihenfolge in den kjs/aufgaben-
     Arrays von content/navigation.json gepflegt wird)
  ──────────────────────────────────────────────────────────── */
  var STATIC_REORDER_MAPS = {
    kjs: {
      arrayKey: 'kjs',
      // Einträge, deren Position im Array sich nicht über die Sidebar
      // verschieben lässt (sie tauchen im KJS-Flyout nicht auf)
      fixed: [
        { label: 'Über uns',  href: '/jaeger/ueber-uns.html' },
        { label: 'Übersicht', href: '/jaeger/index.html' }
      ],
      map: {
        'vorstand':          { label: 'Vorstand',           href: '/jaeger/vorstand.html' },
        'obleute':           { label: 'Obleute',            href: '/jaeger/obleute.html' },
        'hegeringe':         { label: 'Hegeringe',          href: '/jaeger/hegeringe.html' },
        'mitglied-werden':   { label: 'Mitglied werden',    href: '/jaeger/mitglied-werden.html' },
        'jaeger-werden':     { label: 'Jäger/in werden',    href: '/jaeger/jaeger-werden.html' },
        'niederwild':        { label: 'Niederwild',         href: '/jaeger/niederwild.html' },
        'hochwild':          { label: 'Hochwild',           href: '/jaeger/hochwild.html' },
        'schiessobleute':    { label: 'Schießobleute',      href: '/jaeger/schiessobleute.html' },
        'satzung':           { label: 'Satzung',            href: '/jaeger/satzung.html' },
        'landesjagdverband': { label: 'Landesjagdverband',  href: '/jaeger/landesjagdverband.html' }
      }
    },
    aufgaben: {
      arrayKey: 'aufgaben',
      fixed: [],
      map: {
        'auf-schiessen': { label: 'Schießwesen',       href: '/aufgaben/schiessen.html' },
        'auf-hunde':     { label: 'Hundeausbildung',    href: '/aufgaben/hundeausbildung.html' },
        'auf-schweiss':  { label: 'Schweißhundeführer', href: '/aufgaben/schweisshunde.html' },
        'auf-jugend':    { label: 'Jugendarbeit',       href: '/aufgaben/jugend.html' },
        'auf-jagdhorn':  { label: 'Jagdhornblasen',     href: '/aufgaben/jagdhorn.html' },
        'auf-natur':     { label: 'Naturschutz',        href: '/aufgaben/naturschutz.html' },
        'auf-jungwild':  { label: 'Jungwildrettung',    href: '/aufgaben/jungwildrettung.html' }
      }
    }
  };

  /* ────────────────────────────────────────────────────────────
     API LAYER (git-gateway)
  ──────────────────────────────────────────────────────────── */
  async function getToken(forceRefresh) {
    var user = netlifyIdentity.currentUser();
    if (!user) throw new Error('Nicht angemeldet');
    return user.jwt ? await user.jwt(!!forceRefresh) : (user.token && user.token.access_token);
  }

  // Dekodiert den Payload eines JWT (reines Base64, keine Signaturprüfung) –
  // dient nur zur Diagnose, damit man sehen kann, welche Rollen/E-Mail das
  // Token tatsächlich enthält, das der Server ausgestellt hat.
  function decodeJwtPayload(tok) {
    try {
      var parts = (tok || '').split('.');
      if (parts.length < 2) return null;
      var b64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      while (b64.length % 4) b64 += '=';
      return JSON.parse(decodeURIComponent(escape(atob(b64))));
    } catch (e) {
      return null;
    }
  }

  async function apiGet(path) {
    var tok = await getToken();
    // Cache-busting: ohne dies liefert der Browser/Git-Gateway bei wiederholtem
    // Laden derselben Datei (z.B. nach dem Speichern einer neuen Reihenfolge)
    // unter Umständen einen gecachten, veralteten Inhalt zurück – die gerade
    // gespeicherte Änderung scheint dann "zurückgesprungen" zu sein.
    var r = await fetch(GIT + '/' + path + '?ref=' + BRANCH + '&_=' + Date.now(), {
      headers: {
        'Authorization': 'Bearer ' + tok,
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    });
    if (!r.ok) throw new Error('HTTP ' + r.status + ' beim Laden von ' + path);
    return r.json();
  }

  async function apiPut(path, jsonData, sha, message) {
    var tok = await getToken();
    var content = toBase64(JSON.stringify(jsonData, null, 2));
    var body = { message: message || 'Admin: Inhalt gespeichert', content: content, branch: BRANCH };
    if (sha) body.sha = sha;
    var r = await fetch(GIT + '/' + path, {
      method: 'PUT',
      headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    if (!r.ok) {
      var err = await r.json().catch(function() { return {}; });
      throw new Error((err.message || 'Fehler beim Speichern') + ' (' + r.status + ')');
    }
    return r.json();
  }

  async function apiGetDir(path) {
    var tok = await getToken();
    var r = await fetch(GIT + '/' + path + '?ref=' + BRANCH + '&_=' + Date.now(), {
      headers: {
        'Authorization': 'Bearer ' + tok,
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    });
    if (!r.ok) return [];
    var data = await r.json();
    return Array.isArray(data) ? data : [];
  }

  function encodeGitPath(path) {
    // Pfad-Segmente einzeln kodieren (z.B. Umlaute/Sonderzeichen in Dateinamen wie
    // "frank-grün-2.png"), dabei die "/"-Trenner erhalten.
    return String(path).split('/').map(encodeURIComponent).join('/');
  }

  async function apiDeleteFile(path, sha, message) {
    var tok = await getToken();
    var r = await fetch(GIT + '/' + encodeGitPath(path), {
      method: 'DELETE',
      headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message || '🗑️ Datei gelöscht: ' + path, sha: sha, branch: BRANCH })
    });
    if (!r.ok) {
      var err = await r.json().catch(function() { return {}; });
      throw new Error((err.message || 'Fehler beim Löschen') + ' (' + r.status + ')');
    }
    return true;
  }

  async function apiUploadImage(filename, base64Data) {
    var tok = await getToken();
    var safeName = Date.now() + '-' + filename.replace(/[^a-zA-Z0-9._-]/g, '-');
    var body = { message: 'Bild hochgeladen: ' + safeName, content: base64Data, branch: BRANCH };
    var r = await fetch(GIT + '/images/' + safeName, {
      method: 'PUT',
      headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error('Upload fehlgeschlagen');
    return '/images/' + safeName;
  }

  function toBase64(str) {
    return btoa(unescape(encodeURIComponent(str)));
  }
  function fromBase64(str) {
    return decodeURIComponent(escape(atob(str.replace(/\n/g, ''))));
  }

  /* ────────────────────────────────────────────────────────────
     AUTH
  ──────────────────────────────────────────────────────────── */
  function initAuth() {
    netlifyIdentity.on('init', function(user) {
      if (user) onLogin(user); else onLogout();
    });
    netlifyIdentity.on('login', function(user) {
      netlifyIdentity.close();
      onLogin(user);
    });
    netlifyIdentity.on('logout', onLogout);
    netlifyIdentity.init();

    id('login-btn').addEventListener('click', function() {
      netlifyIdentity.open('login');
    });
    id('logout-btn').addEventListener('click', function() {
      netlifyIdentity.logout();
    });
  }

  function onLogin(user) {
    var name = (user.user_metadata && user.user_metadata.full_name) || user.email || '';
    S.userName = name;
    id('user-name').textContent = name;
    id('login-screen').style.display = 'none';
    id('admin-app').style.display = '';
    initApp();
  }

  function onLogout() {
    id('login-screen').style.display = '';
    id('admin-app').style.display = 'none';
    id('admin-sidebar').innerHTML = '<div class="sidebar-loading">Wird geladen…</div>';
    id('admin-main').innerHTML = '';
    S.section = null; S.data = null; S.sha = null;
  }

  /* ────────────────────────────────────────────────────────────
     SEKTIONSNAMEN – INLINE DOPPELKLICK
  ──────────────────────────────────────────────────────────── */
  function initSektionsnameDblclick() {
    var keys = ['jaeger', 'kjs', 'aufgaben', 'verbraucher'];
    keys.forEach(function(k) {
      var el = document.querySelector('[data-navkey="' + k + '"]');
      if (!el) return;
      el.title = 'Doppelklick zum Umbenennen';
      el.addEventListener('dblclick', function(e) {
        e.stopPropagation();
        startSektionsEdit(el, k);
      });
    });
  }

  function startSektionsEdit(el, k) {
    var chevron = el.querySelector('.nav-chevron');
    // Collect text from text nodes only (skip chevron span)
    var currentText = '';
    el.childNodes.forEach(function(n) {
      if (n.nodeType === 3) currentText += n.textContent;
    });
    currentText = currentText.trim();

    // Replace content with editable input
    el.innerHTML = '';
    var inp = document.createElement('input');
    inp.type = 'text';
    inp.value = currentText;
    inp.className = 'sektions-edit-input';
    el.appendChild(inp);
    if (chevron) el.appendChild(chevron);
    inp.focus();
    inp.select();

    var done = false;
    async function commitEdit() {
      if (done) return;
      done = true;
      var newName = inp.value.trim() || currentText;
      el.innerHTML = escHtml(newName);
      if (chevron) el.appendChild(chevron);
      if (newName !== currentText) {
        await saveSektionsname(k, newName);
      }
    }
    function cancelEdit() {
      if (done) return;
      done = true;
      el.innerHTML = escHtml(currentText);
      if (chevron) el.appendChild(chevron);
    }
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter')  { e.preventDefault(); commitEdit(); }
      if (e.key === 'Escape') { cancelEdit(); }
    });
    inp.addEventListener('blur', commitEdit);
  }

  async function saveSektionsname(k, newName) {
    try {
      var fresh = await apiGet('content/navigation.json');
      var data = JSON.parse(fromBase64(fresh.content));
      data.sektionsnamen = data.sektionsnamen || {};
      data.sektionsnamen[k] = newName;
      await apiPut('content/navigation.json', data, fresh.sha, '✏️ Sektionsname: ' + newName);
      toast('✅ Name gespeichert');
    } catch(e) {
      toast('❌ Fehler: ' + e.message, true);
    }
  }

  /* ────────────────────────────────────────────────────────────
     SIDEBAR
  ──────────────────────────────────────────────────────────── */
  function initApp() {
    renderSidebar(NAV);
    Promise.all([
      loadDynamicChildren(),      // "Weitere Themen" (dynamicChildren:true)
      loadAllManifestItems()      // KJS / Aufgaben / Verbraucher custom pages
    ]).then(async function() {
      await applyStaticSidebarOrder(); // gespeicherte Reihenfolge aus navigation.json übernehmen
      initSidebarSortables();          // Drag & Drop erst aktivieren, wenn alles im DOM ist
    });
    initSearch();                // Suchfunktion initialisieren
    id('home-btn').addEventListener('click', showWelcome);
    initSektionsnameDblclick();  // Inline-Umbenennung via Doppelklick
  }

  // Die Reihenfolge der Sidebar (NAV-Konstante) ist statisch/hardcodiert.
  // Per Drag & Drop gespeicherte Reihenfolgen landen in content/navigation.json
  // (kjs/aufgaben-Arrays). Damit die Sidebar nach einem Reload nicht auf die
  // hardcodierte Reihenfolge zurückspringt, werden die betroffenen Container
  // hier anhand von navigation.json neu sortiert.
  async function applyStaticSidebarOrder() {
    try {
      var resp = await apiGet('content/navigation.json');
      var data = JSON.parse(fromBase64(resp.content));
      reorderStaticGroup('nc-kjs', data.kjs, STATIC_REORDER_MAPS.kjs.map, 'kjs-dyn');
      reorderStaticGroup('nc-aufgaben', data.aufgaben, STATIC_REORDER_MAPS.aufgaben.map, 'aufgaben-dyn');
    } catch(e) {
      console.warn('Sidebar-Reihenfolge nicht geladen:', e);
    }
  }

  // order kann sowohl statische Einträge (href aus `map`) als auch eigene
  // Unterseiten enthalten (href im Format "/seiten/?s=<slug>" → data-navkey
  // "<dynPrefix>-<slug>"), damit die im Admin per Drag & Drop gespeicherte
  // Gesamtreihenfolge (statisch + dynamisch gemischt) nach einem Reload
  // erhalten bleibt.
  function reorderStaticGroup(containerId, order, map, dynPrefix) {
    var el = id(containerId);
    if (!el || !order || !order.length) return;
    var hrefToKey = {};
    Object.keys(map).forEach(function(k) { hrefToKey[map[k].href] = k; });
    var anchor = el.querySelector(':scope > .is-add');
    order.forEach(function(item) {
      var key = hrefToKey[item.href];
      if (!key && dynPrefix) {
        var m = /^\/seiten\/\?s=(.+)$/.exec(item.href || '');
        if (m) key = dynPrefix + '-' + m[1];
      }
      if (!key) return;
      var child = el.querySelector(':scope > [data-navkey="' + key + '"]');
      if (!child) return;
      if (anchor) el.insertBefore(child, anchor);
      else el.appendChild(child);
    });
  }

  /* ────────────────────────────────────────────────────────────
     WELCOME / DASHBOARD
  ──────────────────────────────────────────────────────────── */
  function showWelcome() {
    destroyMDE();
    S.section = null;
    S.dirty = false;
    setActiveNav('');
    var greeting = S.userName
      ? 'Hallo ' + escHtml(S.userName) + ', willkommen im Admin-Bereich'
      : 'Willkommen im Admin-Bereich';
    id('admin-main').innerHTML =
      '<div class="welcome-screen">' +
        '<div class="welcome-icon"><img src="/images/logo.png" alt="KJS Segeberg e.V." style="height:72px;width:auto;opacity:.85;"></div>' +
        '<h2>' + greeting + '</h2>' +
        '<p>Wählen Sie links einen Bereich aus, um Inhalte zu bearbeiten.</p>' +
        '<div class="welcome-hints">' +
          '<div class="hint-card" onclick="document.querySelector(\'[data-navkey=aktuelles]\').click()" style="cursor:pointer">📰 <strong>Aktuelles</strong><br>Neuigkeiten hinzufügen</div>' +
          '<div class="hint-card" onclick="document.querySelector(\'[data-navkey=termine]\').click()" style="cursor:pointer">📅 <strong>Termine</strong><br>Veranstaltungen pflegen</div>' +
          '<div class="hint-card" onclick="document.querySelector(\'[data-navkey=jaeger]\').click()" style="cursor:pointer">🦌 <strong>Jäger</strong><br>Vereinsinfos bearbeiten</div>' +
        '</div>' +
      '</div>';
  }

  // Inserts custom pages created via "Neue Unterseite" into the sidebar
  async function loadAllManifestItems() {
    var sections = [
      { insertBeforeKey: 'new-kjs',         file: 'content/seiten-kjs.json',         navKey:'seiten', dir: 'content/seiten-kjs',         keyPrefix: 'kjs-dyn',         level: 2 },
      { insertBeforeKey: 'new-aufgaben',    file: 'content/seiten-aufgaben.json',    navKey:'seiten', dir: 'content/seiten-aufgaben',    keyPrefix: 'aufgaben-dyn',    level: 2 },
      { insertBeforeKey: 'new-verbraucher', file: 'content/seiten-verbraucher.json', navKey:'seiten', dir: 'content/seiten-verbraucher', keyPrefix: 'verbraucher-dyn', level: 2 },
      // Sub-pages under specific Verbraucher pages
      { insertBeforeKey: 'new-sub-wild',    file: 'content/seiten-sub-wildfleisch.json',            navKey:'seiten', dir: 'content/seiten-sub-wildfleisch',            keyPrefix: 'sub-wild-dyn',    level: 3 },
      { insertBeforeKey: 'new-sub-lernort', file: 'content/seiten-sub-lernort-natur.json',          navKey:'seiten', dir: 'content/seiten-sub-lernort-natur',          keyPrefix: 'sub-lernort-dyn', level: 3 },
      { insertBeforeKey: 'new-sub-gruen',   file: 'content/seiten-sub-gruenes-klassenzimmer.json',  navKey:'seiten', dir: 'content/seiten-sub-gruenes-klassenzimmer',  keyPrefix: 'sub-gruen-dyn',   level: 3 },
      // Jagdhundeschule sub-pages
      { insertBeforeKey: 'new-jagdhundeschule', file: 'content/aufgaben/hundeausbildung-seiten.json', navKey:'seiten', dir: 'content/aufgaben/hundeausbildung', keyPrefix: 'jagdhundeschule-dyn', level: 3 },
    ];

    for (var i = 0; i < sections.length; i++) {
      var sec = sections[i];
      try {
        var resp = await apiGet(sec.file);
        var data = JSON.parse(fromBase64(resp.content));
        var seiten = (data.seiten || []).filter(function(s) {
          return s.veroeffentlicht !== false;
        });
        if (!seiten.length) continue;

        var addBtn = document.querySelector('[data-navkey="' + sec.insertBeforeKey + '"]');
        if (!addBtn) continue;

        seiten.forEach(function(s) {
          // Skip if already in sidebar (avoid duplicates on re-render)
          var dynKey = sec.keyPrefix + '-' + s.slug;
          if (document.querySelector('[data-navkey="' + dynKey + '"]')) return;

          var def = {
            key:      dynKey,
            label:    s.nav_label || s.slug,
            file:     sec.dir + '/' + s.slug + '.json',
            form:     'standard',
            isDynamic: true,
            navFile:  sec.file,
            navKey:   sec.navKey || 'seiten',
            slug:     s.slug,
            dir:      sec.dir,
            drag:     true,
          };
          var li = navItemEl(def, sec.level, true);
          li.addEventListener('click', (function(d) {
            return function() { selectSection(d); };
          }(def)));
          addBtn.parentNode.insertBefore(li, addBtn);
        });
      } catch(e) {
        // Manifest not yet present — no pages created yet, silently ignore
      }
    }
  }

  function renderSidebar(items, container) {
    var el = container || id('admin-sidebar');
    if (!container) el.innerHTML = '';
    items.forEach(function(item) {
      if (item.dynamicChildren) {
        // Will be filled by loadDynamicChildren
        var wrap = document.createElement('div');
        wrap.id = 'dynchildren-' + item.key;
        var header = navItemEl(item, 1, false);
        wrap.appendChild(header);
        el.appendChild(wrap);
      } else if (item.children) {
        var level = item.group ? 1 : 2;
        var header = navItemEl(item, level, false);
        header.setAttribute('data-key', item.key);
        var chevron = header.querySelector('.nav-chevron');
        var childWrap = document.createElement('div');
        childWrap.className = 'nav-children';
        childWrap.id = 'nc-' + item.key;
        if (!item.open) childWrap.style.display = 'none';
        else if (chevron) chevron.classList.add('open');

        header.addEventListener('click', function(e) {
          if (e.target.closest && e.target.closest('.nav-drag-handle')) return;
          var isOpen = childWrap.style.display !== 'none';
          childWrap.style.display = isOpen ? 'none' : '';
          if (chevron) chevron.classList.toggle('open', !isOpen);
        });

        // Gruppen, die als Ganzes per Drag&Drop verschoben werden können
        // (z.B. "Hundeausbildung" innerhalb von "Aufgaben der KJS"),
        // werden zusammen mit ihren Kindern in einen .nav-group-Wrapper
        // gepackt, damit Sortable.js sie als ein Element behandelt.
        if (item.drag) {
          var groupWrap = document.createElement('div');
          groupWrap.className = 'nav-group';
          groupWrap.setAttribute('data-navkey', item.key);
          groupWrap.appendChild(header);
          renderSidebar(item.children, childWrap);
          groupWrap.appendChild(childWrap);
          el.appendChild(groupWrap);
        } else {
          el.appendChild(header);
          renderSidebar(item.children, childWrap);
          el.appendChild(childWrap);
        }
      } else {
        var li = navItemEl(item, 2, true);
        li.addEventListener('click', function() { selectSection(item); });
        el.appendChild(li);
      }
    });
  }

  function navItemEl(item, level, clickable) {
    var div = document.createElement('div');
    div.className = 'nav-item level-' + level;
    if (!clickable) div.classList.add('is-group');
    if (item.isAdd) div.classList.add('is-add');
    div.setAttribute('data-navkey', item.key);
    if (item.isDynamic) {
      div.setAttribute('data-dynamic', '1');
      div.setAttribute('data-slug', item.slug);
    }
    var html = '';
    if (item.drag) {
      html += '<span class="nav-drag-handle" title="Verschieben">⠿</span>';
    }
    html += '<span class="nav-item__label">' + escHtml(item.label) + '</span>';
    div.innerHTML = html;
    if (item.children || item.dynamicChildren) {
      div.innerHTML += '<span class="nav-chevron">&#9658;</span>';
    }
    return div;
  }

  function setActiveNav(key) {
    document.querySelectorAll('.nav-item').forEach(function(el) {
      el.classList.toggle('active', el.getAttribute('data-navkey') === key);
    });
  }

  async function loadDynamicChildren() {
    // Load "Weitere Themen" nav from its manifest
    var weitereNode = findByKey(NAV, 'weitere');
    if (!weitereNode) return;
    try {
      var resp = await apiGet(weitereNode.navFile);
      var data = JSON.parse(fromBase64(resp.content));
      var seiten = (data[weitereNode.navKey] || []).filter(function(s) { return s.veroeffentlicht !== false; });
      var wrap = id('dynchildren-' + weitereNode.key);
      if (!wrap) return;

      var childWrap = document.createElement('div');
      childWrap.className = 'nav-children';
      childWrap.id = 'nc-' + weitereNode.key;
      if (!weitereNode.open) childWrap.style.display = 'none';

      var header = wrap.querySelector('.nav-item');
      if (header) {
        var chevron = header.querySelector('.nav-chevron');
        header.addEventListener('click', function() {
          var isOpen = childWrap.style.display !== 'none';
          childWrap.style.display = isOpen ? 'none' : '';
          if (chevron) chevron.classList.toggle('open', !isOpen);
        });
      }

      seiten.forEach(function(s) {
        var def = {
          key: 'weitere-' + s.slug,
          label: s.nav_label || s.slug,
          file: weitereNode.dir + '/' + s.slug + '.json',
          form: 'standard',
          isDynamic: true,
          navFile: weitereNode.navFile,
          navKey: weitereNode.navKey,
          slug: s.slug,
          dir: weitereNode.dir,
          drag: true,
        };
        var li = navItemEl(def, 3, true);
        li.addEventListener('click', function() { selectSection(def); });
        childWrap.appendChild(li);
      });

      // Add "Neue Seite" button
      var addDef = { key:'new-weitere', label:'➕ Neue Seite', form:'neueSeite', isAdd:true,
        navFile: weitereNode.navFile, navKey: weitereNode.navKey, dir: weitereNode.dir };
      var addLi = navItemEl(addDef, 3, true);
      addLi.addEventListener('click', function() { selectSection(addDef); });
      childWrap.appendChild(addLi);

      wrap.appendChild(childWrap);
    } catch(e) {
      console.warn('Weitere Themen nicht geladen:', e);
    }
  }

  /* ────────────────────────────────────────────────────────────
     SIDEBAR DRAG & DROP – Reihenfolge von Seiten innerhalb einer
     Sektion per Sortable.js verschieben
  ──────────────────────────────────────────────────────────── */
  function initSidebarSortables() {
    if (!window.Sortable) return;

    function makeSortable(containerId, opts) {
      var el = id(containerId);
      if (!el || el._sortableInit) return;
      el._sortableInit = true;
      Sortable.create(el, {
        handle: '.nav-drag-handle',
        animation: 150,
        onEnd: function(evt) {
          if (evt.oldIndex === evt.newIndex) return;
          onSidebarReorder(el, opts);
        }
      });
    }

    // KJS Segeberg: statische Unterseiten (navigation.json → kjs) +
    // eigene KJS-Unterseiten (content/seiten-kjs.json)
    makeSortable('nc-kjs', {
      arrayKey:       STATIC_REORDER_MAPS.kjs.arrayKey,
      fixed:          STATIC_REORDER_MAPS.kjs.fixed,
      staticMap:      STATIC_REORDER_MAPS.kjs.map,
      dynamicNavFile: 'content/seiten-kjs.json',
      dynamicNavKey:  'seiten',
      label:          'KJS Segeberg'
    });

    // Aufgaben der Kreisjägerschaft: statische Aufgaben (navigation.json → aufgaben,
    // inkl. der Gruppe "Hundeausbildung" als Ganzes) + eigene Aufgaben-Unterseiten
    makeSortable('nc-aufgaben', {
      arrayKey:       STATIC_REORDER_MAPS.aufgaben.arrayKey,
      fixed:          STATIC_REORDER_MAPS.aufgaben.fixed,
      staticMap:      STATIC_REORDER_MAPS.aufgaben.map,
      dynamicNavFile: 'content/seiten-aufgaben.json',
      dynamicNavKey:  'seiten',
      label:          'Aufgaben der Kreisjägerschaft'
    });

    // Jagdhundeschule-Unterseiten (innerhalb von Aufgaben → Hundeausbildung)
    makeSortable('nc-jagdhundeschule-gruppe', {
      dynamicNavFile: 'content/aufgaben/hundeausbildung-seiten.json',
      dynamicNavKey:  'seiten',
      label:          'Jagdhundeschule'
    });

    // Weitere Themen (komplett dynamisch)
    makeSortable('nc-weitere', {
      dynamicNavFile: 'content/seiten-weitere.json',
      dynamicNavKey:  'seiten',
      label:          'Weitere Themen'
    });

    // Verbraucher-Unterseiten je Themenbereich
    makeSortable('nc-verbraucher-wild', {
      dynamicNavFile: 'content/seiten-sub-wildfleisch.json',
      dynamicNavKey:  'seiten',
      label:          'Wildfleisch'
    });
    makeSortable('nc-verbraucher-lernort', {
      dynamicNavFile: 'content/seiten-sub-lernort-natur.json',
      dynamicNavKey:  'seiten',
      label:          'Lernort Natur'
    });
    makeSortable('nc-verbraucher-gruen', {
      dynamicNavFile: 'content/seiten-sub-gruenes-klassenzimmer.json',
      dynamicNavKey:  'seiten',
      label:          'Grünes Klassenzimmer'
    });
  }

  // Liest die neue Reihenfolge aus dem DOM und speichert sie sowohl im
  // navigation.json-Array (statische Seiten) als auch im Reihenfolge-Manifest
  // (eigene/dynamische Seiten) der jeweiligen Sektion.
  async function onSidebarReorder(containerEl, opts) {
    try {
      // domOrder enthält die GESAMTE Reihenfolge des Containers (statische
      // Seiten UND eigene Unterseiten gemischt, exakt wie im Admin per
      // Drag & Drop angeordnet) – wichtig, damit die öffentliche Website
      // dieselbe (ggf. ineinander verschachtelte) Reihenfolge zeigt.
      var domOrder = [];
      var dynamicOrder = [];
      Array.from(containerEl.children).forEach(function(ch) {
        var key = ch.getAttribute('data-navkey');
        if (opts.staticMap && key && opts.staticMap[key]) {
          domOrder.push({ type: 'static', key: key });
        } else if (ch.getAttribute('data-dynamic') === '1') {
          var slug = ch.getAttribute('data-slug');
          var labelEl = ch.querySelector('.nav-item__label');
          domOrder.push({ type: 'dynamic', slug: slug, label: labelEl ? labelEl.textContent : slug });
          dynamicOrder.push(slug);
        }
      });

      var jobs = [];

      // 1) Gesamte Reihenfolge (statisch + eigene Seiten) → content/navigation.json
      //    (z.B. "kjs"/"aufgaben"-Array). Eigene Seiten werden mit ihrem
      //    /seiten/?s=<slug>-Link gespeichert, damit die Website sie an der
      //    richtigen Stelle zwischen den statischen Seiten einsortiert.
      if (opts.arrayKey && domOrder.length) {
        jobs.push((async function() {
          var resp = await apiGet('content/navigation.json');
          var navData = JSON.parse(fromBase64(resp.content));
          var newArr = (opts.fixed || []).slice();
          domOrder.forEach(function(entry) {
            if (entry.type === 'static') {
              newArr.push(opts.staticMap[entry.key]);
            } else {
              newArr.push({ label: entry.label, href: '/seiten/?s=' + entry.slug });
            }
          });
          navData[opts.arrayKey] = newArr;
          await doSave('content/navigation.json', navData, '🔀 Reihenfolge geändert (' + opts.label + ')');
          // Wichtig: falls gerade das Panel "Navigation & Reihenfolge" offen ist,
          // dessen im Speicher gehaltene Kopie (S.data) mit aktualisieren – sonst
          // überschreibt ein späterer Klick auf den normalen "Speichern"-Button
          // dieses Panels (der von der alten S.data-Kopie ausgeht) die gerade per
          // Drag & Drop gespeicherte neue Reihenfolge wieder mit dem alten Stand.
          if (S.section && S.section.file === 'content/navigation.json' && S.data) {
            S.data[opts.arrayKey] = newArr;
            // Ist das Panel "Navigation & Reihenfolge" gerade offen, zeigt es
            // dieselbe Liste ein zweites Mal (eigene Sortable-Liste, z.B.
            // "navreo-kjs") – die muss ebenfalls neu aufgebaut werden, sonst
            // liest ein späterer Klick auf "Speichern" dort die alte,
            // unveränderte Reihenfolge aus dem noch nicht aktualisierten DOM.
            if (id('navreo-' + opts.arrayKey)) {
              renderNavReihenfolge(S.section, S.data);
            }
          }
        })());
      }

      // 2) Eigene/dynamische Seiten → Reihenfolge-Manifest (z.B. seiten-kjs.json)
      if (opts.dynamicNavFile && dynamicOrder.length) {
        jobs.push((async function() {
          var resp = await apiGet(opts.dynamicNavFile);
          var data = JSON.parse(fromBase64(resp.content));
          var navKey = opts.dynamicNavKey || 'seiten';
          var seiten = data[navKey] || [];
          var bySlug = {};
          seiten.forEach(function(s) { bySlug[s.slug] = s; });
          var newSeiten = [];
          dynamicOrder.forEach(function(slug) {
            if (bySlug[slug]) { newSeiten.push(bySlug[slug]); delete bySlug[slug]; }
          });
          // Übrige Seiten (z.B. unveröffentlichte) am Ende anhängen
          seiten.forEach(function(s) { if (bySlug[s.slug]) newSeiten.push(s); });
          data[navKey] = newSeiten;
          await doSave(opts.dynamicNavFile, data, '🔀 Reihenfolge geändert (' + opts.label + ')');
        })());
      }

      if (!jobs.length) return;
      await Promise.all(jobs);
      toast('✅ Reihenfolge gespeichert', 'ok');
    } catch(e) {
      toast('❌ Fehler beim Speichern der Reihenfolge: ' + e.message, true);
    }
  }

  /* ────────────────────────────────────────────────────────────
     SECTION LOADING
  ──────────────────────────────────────────────────────────── */
  async function selectSection(def) {
    if (S.dirty && S.section) {
      if (!confirm('Es gibt ungespeicherte Änderungen. Trotzdem verlassen?')) return;
    }
    destroyMDE();
    S.section = def;
    S.dirty = false;
    setActiveNav(def.key);

    if (def.form === 'neueSeite') {
      renderNeueSeite(def);
      return;
    }

    if (def.form === 'medien') {
      renderMedian();
      return;
    }

    if (def.form === 'benutzer') {
      renderBenutzer();
      return;
    }

    if (!def.file) return;

    showPanelLoading(def.label);

    try {
      var resp = await apiGet(def.file);
      S.sha  = resp.sha;
      S.data = JSON.parse(fromBase64(resp.content));
      renderForm(def, S.data);
    } catch(e) {
      showPanelError(e.message);
    }
  }

  function showPanelLoading(title) {
    id('admin-main').innerHTML =
      '<div class="panel-header"><h2>' + escHtml(title) + '</h2></div>' +
      '<div class="panel-loading"><div class="spinner"></div> Wird geladen…</div>';
  }
  function showPanelError(msg) {
    var extra = '';
    if (S.section && S.section.isDynamic) {
      extra = '<p class="mt-1"><button class="btn btn-danger-outline" onclick="dynSeiteRemoveFromManifest()">🗑️ Aus Menü entfernen</button>' +
        ' <span style="color:var(--text-muted);font-size:.8rem">Entfernt den Eintrag aus der Navigation (Datei bleibt ggf. im Repo)</span></p>';
    }
    id('admin-main').innerHTML =
      '<div class="panel-body"><div class="form-card">' +
      '<p style="color:var(--danger)">⚠️ Fehler: ' + escHtml(msg) + '</p>' +
      '<p class="mt-1"><button class="btn btn-outline" onclick="location.reload()">Seite neu laden</button></p>' +
      extra +
      '</div></div>';
  }

  // Remove a dynamic page from its nav manifest (without deleting the file)
  window.dynSeiteRemoveFromManifest = async function() {
    var def = S.section;
    if (!def || !def.isDynamic) return;
    showConfirm('Aus Menü entfernen',
      'Den Eintrag „' + (def.label || def.slug) + '" aus dem Menü entfernen?',
      async function() {
        try {
          var manifestResp = await apiGet(def.navFile);
          var manifestData = JSON.parse(fromBase64(manifestResp.content));
          var key = def.navKey || 'seiten';
          manifestData[key] = (manifestData[key] || []).filter(function(s) { return s.slug !== def.slug; });
          await apiPut(def.navFile, manifestData, manifestResp.sha, '🗑️ Navigation: ' + (def.label || def.slug) + ' entfernt');
          toast('✅ Eintrag aus Menü entfernt. Seite lädt neu…', 'ok');
          setTimeout(function() { location.reload(); }, 1500);
        } catch(e) {
          toast('❌ Fehler: ' + e.message, 'err');
        }
      }
    );
  };

  // Delete a dynamic page fully (content file + manifest entry)
  window.dynSeiteDelete = async function() {
    var def = S.section;
    if (!def || !def.isDynamic) return;
    showConfirm('Seite löschen',
      'Seite „' + (def.label || def.slug) + '" wirklich dauerhaft löschen?',
      async function() {
        try {
          // 1. Remove from manifest
          var manifestResp = await apiGet(def.navFile);
          var manifestData = JSON.parse(fromBase64(manifestResp.content));
          var key = def.navKey || 'seiten';
          manifestData[key] = (manifestData[key] || []).filter(function(s) { return s.slug !== def.slug; });
          await apiPut(def.navFile, manifestData, manifestResp.sha, '🗑️ Navigation: ' + (def.label || def.slug) + ' entfernt');

          // 2. Delete content file (requires its SHA)
          try {
            var fileResp = await apiGet(def.file);
            var tok = await getToken();
            await fetch(GIT + '/' + def.file, {
              method: 'DELETE',
              headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
              body: JSON.stringify({ message: '🗑️ Seite gelöscht: ' + (def.label || def.slug), sha: fileResp.sha, branch: BRANCH })
            });
          } catch(e2) {
            // File may not exist — ignore
          }

          toast('✅ Seite gelöscht. Seite lädt neu…', 'ok');
          setTimeout(function() { location.reload(); }, 1500);
        } catch(e) {
          toast('❌ Fehler: ' + e.message, 'err');
        }
      }
    );
  };

  /* ────────────────────────────────────────────────────────────
     FORM DISPATCH
  ──────────────────────────────────────────────────────────── */
  function renderForm(def, data) {
    destroyAllTiptaps();
    switch(def.form) {
      case 'standard':     renderStandard(def, data);     break;
      case 'tiptap':       renderInfomobil(def, data);    break;
      case 'startseite':   renderStartseite(def, data);   break;
      case 'aktuelles':    renderAktuelles(def, data);     break;
      case 'termine':      renderTermine(def, data);       break;
      case 'personen':     renderPersonen(def, data);      break;
      case 'hegeringe':    renderHegeringe(def, data);     break;
      case 'kjm':          renderKJM(def, data);           break;
      case 'faq':          renderFAQ(def, data);           break;
      case 'einstellungen':renderEinstellungen(def, data); break;
      case 'footer':       renderFooter(def, data);        break;
      case 'design':       renderDesign(def, data);        break;
      case 'impressum':    renderImpressum(def, data);     break;
      case 'downloads':    renderDownloads(def, data);     break;
      case 'navExtra':        renderNavExtra(def, data);         break;
      case 'navReihenfolge':  renderNavReihenfolge(def, data);   break;
      case 'benutzer':        renderBenutzer();                  break;
      default:                renderStandard(def, data);
    }
    // Universeller "Dokumente & Downloads"-Bereich am Ende jeder Inhaltsseite
    // (für Einstellungs-/Verwaltungsseiten ohne öffentliche Entsprechung
    // nicht sinnvoll und daher ausgenommen).
    var NO_DOWNLOADS_FORMS = ['einstellungen','footer','design','impressum','downloads','navExtra','navReihenfolge','benutzer'];
    if (NO_DOWNLOADS_FORMS.indexOf(def.form) === -1) {
      injectDownloadsCard(data);
    }
  }

  // Hängt die "Dokumente & Downloads"-Karte ans Ende des aktuellen
  // Formulars an, falls das Formular sie nicht bereits selbst rendert
  // (z.B. Standard- und Kreisjägermeister-Seiten haben sie schon eingebaut).
  function injectDownloadsCard(data) {
    if (id('downloads-list')) { initDownloadsSortable(); return; }
    var body = document.querySelector('#admin-main .panel-body');
    if (!body) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderDownloadsCard(data);
    body.appendChild(wrap.firstChild);
    initDownloadsSortable();
  }

  /* ────────────────────────────────────────────────────────────
     FIELD BUILDERS
  ──────────────────────────────────────────────────────────── */
  function fText(id, label, val, hint) {
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<input class="field-input" type="text" id="f-' + id + '" value="' + escAttr(val || '') + '"' +
      (hint ? ' placeholder="' + escAttr(hint) + '"' : '') + '>' +
      '</div>';
  }
  function fTextarea(id, label, val, rows) {
    rows = rows || 3;
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<textarea class="field-textarea" id="f-' + id + '" rows="' + rows + '">' + escHtml(val || '') + '</textarea>' +
      '</div>';
  }
  function fMarkdown(id, label, val) {
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<textarea class="field-textarea" id="f-' + id + '" rows="8">' + escHtml(val || '') + '</textarea>' +
      '</div>';
  }
  function fSelect(id, label, val, options) {
    var opts = options.map(function(o) {
      var v = typeof o === 'object' ? o.value : o;
      var l = typeof o === 'object' ? o.label : o;
      return '<option value="' + escAttr(v) + '"' + (v === val ? ' selected' : '') + '>' + escHtml(l) + '</option>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<select class="field-select" id="f-' + id + '">' + opts + '</select>' +
      '</div>';
  }
  function fImage(id, label, val) {
    var hasImg = val && val.trim();
    return '<div class="field-row">' +
      '<label class="field-label">' + escHtml(label) + '</label>' +
      '<div class="image-preview-wrap">' +
        '<div class="image-preview' + (hasImg ? '' : ' empty') + '" id="prev-' + id + '">' +
          (hasImg ? '<img src="' + escAttr(val) + '" alt="">' : '') +
        '</div>' +
        '<div class="image-field-btns">' +
          '<button type="button" class="btn btn-outline btn-sm" onclick="openImgPicker(\'' + id + '\')">📷 Bild wählen</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" onclick="clearImg(\'' + id + '\')">✕ Entfernen</button>' +
        '</div>' +
      '</div>' +
      '<input type="hidden" id="f-' + id + '" value="' + escAttr(val || '') + '">' +
      '</div>';
  }
  function fToggle(id, label, val) {
    return '<div class="field-row">' +
      '<label class="field-label">' + escHtml(label) + '</label>' +
      '<div class="toggle-wrap">' +
        '<button type="button" class="toggle' + (val ? ' on' : '') + '" id="f-' + id + '" ' +
          'data-val="' + (val ? '1' : '0') + '" ' +
          'onclick="toggleBtn(this)" aria-pressed="' + (val ? 'true' : 'false') + '"></button>' +
        '<span class="toggle-label" id="tl-' + id + '">' + (val ? 'Ja' : 'Nein') + '</span>' +
      '</div>' +
      '</div>';
  }
  // Dropdown mit freier Texteingabe (datalist)
  function fCombobox(id, label, val, options) {
    var listId = 'dl-' + id;
    var opts = options.map(function(o) {
      return '<option value="' + escAttr(o) + '">';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<input class="field-input" list="' + listId + '" id="f-' + id + '" value="' + escAttr(val || '') + '" placeholder="Auswählen oder eigene Kategorie eingeben">' +
      '<datalist id="' + listId + '">' + opts + '</datalist>' +
      '<p class="field-hint">Vorschläge aus der Liste oder eigenen Text eingeben.</p>' +
    '</div>';
  }

  function fDate(id, label, val) {
    // Convert DD.MM.YYYY to YYYY-MM-DD for input[type=date]
    var iso = datumToIso(val || '');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + id + '">' + escHtml(label) + '</label>' +
      '<input class="field-input" type="date" id="f-' + id + '" value="' + escAttr(iso) + '" style="max-width:200px">' +
      '</div>';
  }

  /* ────────────────────────────────────────────────────────────
     STANDARD SEITE FORM
  ──────────────────────────────────────────────────────────── */
  function renderStandard(def, data) {
    var extraBtns = def.isDynamic
      ? '<button class="btn btn-sm btn-danger-outline" onclick="dynSeiteDelete()">🗑️ Seite löschen</button>'
      : '';
    // fH(fieldHtml, hintText): gleicher Hilfetext-Baustein wie bei der
    // Testseite/Infomobil (siehe insertHintAfterLabel) – jetzt einheitlich
    // auf allen Standard-Inhaltsseiten, nicht nur auf der Testseite.
    var fH = function(fieldHtml, hintText) { return insertHintAfterLabel(fieldHtml, ttFieldHint(hintText)); };
    var html = panelHeader(def.label, extraBtns) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Seiteninhalt</div>' +
          fH(fText('titel', 'Seitentitel', data.titel),
            'Die große Überschrift ganz oben auf der Seite. Kurz und klar halten.') +
          fH(fTipTap('untertitel', 'Untertitel', false),
            'Kurzer Text direkt unter dem Titel, als Einstieg in die Seite. Optional.') +
          fH(fTipTap('intro', 'Einleitungstext', true),
            'Der erste Textblock der Seite, oberhalb des Hauptinhalts. Formatierung, Listen und Bilder sind möglich.') +
          fH('<div class="field-row">' +
              '<label class="field-label">Textinhalt (Markdown)</label>' +
              '<div id="te-bar-inhalt" class="te-bar"></div>' +
              '<textarea class="field-textarea" id="f-inhalt" rows="8">' + escHtml(data.inhalt || '') + '</textarea>' +
              '<div id="te-content-inhalt" style="display:none"></div>' +
            '</div>',
            'Der Haupttext der Seite. Hier kommt der eigentliche Inhalt rein – mit Formatierung, Listen und Tabellen.') +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Bilder</div>' +
          fH(fImage('hero_bild', 'Hero-Hintergrundbild', data.hero_bild),
            'Das große Bild im Kopfbereich, hinter dem Titel. Quer-Format wirkt am besten.') +
          fH(fImage('bild', 'Inhaltsbild', data.bild),
            'Ein zusätzliches Bild, das im Textbereich erscheint. Optional.') +
          fH(fBildGroesse(data.bild_groesse),
            'Legt fest, wie groß das Inhaltsbild dargestellt wird (25 % = klein, 100 % = volle Breite).') +
          fH(fText('bild_alt', 'Bild-Beschreibung', data.bild_alt),
            'Kurze Beschreibung des Bildes. Hilft Suchmaschinen und wird angezeigt, falls das Bild mal nicht lädt.') +
          // Vorschaubild + Kurzbeschreibung für Jagdhundeschule-Seiten
          (def.key && def.key.indexOf('jagdhundeschule') !== -1
            ? '<div style="border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem;">' +
              '<p style="font-size:.82rem;color:var(--text-muted);margin-bottom:.75rem;">🐕 <strong>Kachel-Vorschau</strong> — wird in der Jagdhundeschule-Übersicht angezeigt</p>' +
              fImage('vorschaubild', 'Vorschaubild (für Kachel-Übersicht)', data.vorschaubild) +
              fText('kurzbeschreibung', 'Kurzbeschreibung (für Kachel-Übersicht)', data.kurzbeschreibung, 'Ein Satz, der die Seite beschreibt …') +
              '</div>'
            : '') +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Kontakt (optional)</div>' +
          fH(fText('kontakt_name', 'Kontaktname', data.kontakt_name),
            'Name der Ansprechperson, die unten auf der Seite angezeigt wird. Optional.') +
          fH(fText('kontakt_email', 'Kontakt E-Mail', data.kontakt_email),
            'E-Mail der Ansprechperson, wird als anklickbarer Link angezeigt. Optional.') +
        '</div>' +
        renderDownloadsCard(data) +
        (def.key === 'mitglied-werden' ?
          '<div class="form-card">' +
            '<div class="form-card-title">Mitgliedsantrag</div>' +
            fText('antrag_url', 'Mitgliedsantrag-URL', data.antrag_url, 'https://...') +
          '</div>'
        : '') +
      '</div>' +
      saveBar();
    id('admin-main').innerHTML = html;
    initTiptap('untertitel', data.untertitel || '');
    initTiptap('intro',      data.intro      || '');
    initMDE('inhalt');
    initTableEditor('inhalt', data.inhalt || '');
    initDownloadsSortable();
    bindSaveBtn();
  }

  function collectStandard(data) {
    data.titel         = gv('titel');
    data.untertitel    = getTiptapValue('untertitel', data.untertitel, 'Untertitel');
    data.intro         = getTiptapValue('intro',      data.intro,      'Einleitungstext');
    data.inhalt        = getMDE();
    data.hero_bild     = gv('hero_bild');
    data.bild          = gv('bild');
    data.bild_groesse  = gv('bild_groesse');
    data.bild_alt      = gv('bild_alt');
    data.kontakt_name  = gv('kontakt_name');
    data.kontakt_email = gv('kontakt_email');
    if (S.section && S.section.key === 'mitglied-werden') {
      data.antrag_url = gv('antrag_url');
    }
    // Jagdhundeschule-spezifische Felder (Kachel-Vorschau)
    if (S.section && S.section.key && S.section.key.indexOf('jagdhundeschule') !== -1) {
      data.vorschaubild     = gv('vorschaubild');
      data.kurzbeschreibung = gv('kurzbeschreibung');
    }
    data.downloads = collectDownloadsList();
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     INFOMOBIL – TipTap Rich-Text-Editor
     Felder: untertitel, intro, inhalt werden als HTML gespeichert
     und auf infomobil.html direkt via innerHTML gerendert.
  ──────────────────────────────────────────────────────────── */
  function fTipTap(fieldId, label, withImageBtn) {
    var imgBtn = withImageBtn
      ? '<span class="tt-sep"></span>' +
        '<button type="button" class="tt-btn" title="Bild einfügen" ' +
        'onclick="openTiptapImageModal(\'' + fieldId + '\')">📷 Bild</button>'
      : '';
    return '<div class="field-row">' +
      '<label class="field-label">' + escHtml(label) + '</label>' +
      '<div class="tt-wrap">' +
        '<div class="tt-toolbar" id="ttbar-' + fieldId + '">' +
          '<button type="button" class="tt-btn" data-cmd="bold"        onclick="ttCmd(\'' + fieldId + '\',\'bold\')"        title="Fett (Strg+B)"><b>B</b></button>' +
          '<button type="button" class="tt-btn" data-cmd="italic"      onclick="ttCmd(\'' + fieldId + '\',\'italic\')"      title="Kursiv (Strg+I)"><i>I</i></button>' +
          '<button type="button" class="tt-btn" data-cmd="underline"   onclick="ttCmd(\'' + fieldId + '\',\'underline\')"   title="Unterstreichen (Strg+U)"><u>U</u></button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" data-cmd="h2"          onclick="ttCmd(\'' + fieldId + '\',\'h2\')"          title="Überschrift H2">H2</button>' +
          '<button type="button" class="tt-btn" data-cmd="h3"          onclick="ttCmd(\'' + fieldId + '\',\'h3\')"          title="Überschrift H3">H3</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" data-cmd="bulletList"  onclick="ttCmd(\'' + fieldId + '\',\'bulletList\')"  title="Aufzählungsliste">&bull;&nbsp;Liste</button>' +
          '<button type="button" class="tt-btn" data-cmd="orderedList" onclick="ttCmd(\'' + fieldId + '\',\'orderedList\')" title="Nummerierte Liste">1.&nbsp;Liste</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" onclick="ttToggleTableMenu(event,\'' + fieldId + '\')" title="Tabelle">&#9638;&nbsp;Tabelle</button>' +
          imgBtn +
        '</div>' +
        '<div class="tt-mount" id="tt-' + fieldId + '"></div>' +
      '</div>' +
    '</div>';
  }

  // Bildgröße-Auswahl als Button-Gruppe (gleiche Konvention wie Inline-Bilder:
  // img-25/50/75/100). Gespeichert wird der Klassenname im versteckten Feld
  // f-bild_groesse, das collectInfomobil über gv('bild_groesse') ausliest.
  function fBildGroesse(val) {
    var cur = (val && val.indexOf('img-') === 0) ? val
            : val === 'klein'  ? 'img-25'
            : val === 'mittel' ? 'img-50'
            : val === 'gross'  ? 'img-100'
            : 'img-100';
    var opts = [
      { v: 'img-25',  l: '25%'  },
      { v: 'img-50',  l: '50%'  },
      { v: 'img-75',  l: '75%'  },
      { v: 'img-100', l: '100%' }
    ];
    var btns = opts.map(function(o) {
      return '<button type="button" class="mdimg-size-btn' +
        (o.v === cur ? ' mdimg-size-btn--active' : '') +
        '" data-val="' + o.v + '" onclick="bildGroesseSet(\'' + o.v + '\')">' + o.l + '</button>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label">Bildgröße</label>' +
      '<div class="mdimg-size-row" id="bild-groesse-group">' + btns + '</div>' +
      '<input type="hidden" id="f-bild_groesse" value="' + cur + '">' +
    '</div>';
  }

  window.bildGroesseSet = function(v) {
    var inp = id('f-bild_groesse');
    if (inp) inp.value = v;
    var grp = id('bild-groesse-group');
    if (grp) grp.querySelectorAll('.mdimg-size-btn').forEach(function(b) {
      b.classList.toggle('mdimg-size-btn--active', b.getAttribute('data-val') === v);
    });
  };

  // Kurzer grauer Hilfetext – exakt dasselbe Markup wie der bestehende
  // Hinweis im "Dokumente & Downloads"-Bereich (renderDownloadsCard).
  function ttFieldHint(text) {
    return '<p style="font-size:.84rem;color:var(--text-muted);margin:.25rem 0 .75rem;">' + escHtml(text) + '</p>';
  }
  // Setzt den Hilfetext direkt NACH dem Feld-Label und VOR dem Eingabefeld/
  // Editor ein (Label → Hilfetext → Feld), ohne die fText/fImage/fTipTap/
  // fBildGroesse-Helfer selbst anzufassen – reine Positionsänderung an der
  // bereits fertig gerenderten Feld-HTML dieser einen Stelle.
  function insertHintAfterLabel(fieldHtml, hintHtml) {
    if (!hintHtml) return fieldHtml;
    return fieldHtml.replace('</label>', '</label>' + hintHtml);
  }

  function renderInfomobil(def, data) {
    // Hilfetexte jetzt einheitlich auf allen Seiten mit diesem Formular
    // (Infomobil + Testseite) sichtbar – nicht mehr auf die Testseite
    // beschränkt, siehe renderStandard für dieselbe fH()-Konvention.
    var fH = function(fieldHtml, hintText) { return insertHintAfterLabel(fieldHtml, ttFieldHint(hintText)); };
    var html = panelHeader(def.label, '') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Seiteninhalt</div>' +
          fH(fText('titel', 'Seitentitel', data.titel),
            'Die große Überschrift ganz oben auf der Seite. Kurz und klar halten.') +
          fH(fTipTap('untertitel', 'Untertitel', false),
            'Kurzer Text direkt unter dem Titel, als Einstieg in die Seite. Optional.') +
          fH(fTipTap('intro',      'Einleitungstext', true),
            'Der erste Textblock der Seite, oberhalb des Hauptinhalts. Formatierung, Listen und Tabellen sind möglich.') +
          fH(fTipTap('inhalt',     'Textinhalt', true),
            'Der Haupttext der Seite. Hier kommt der eigentliche Inhalt rein – mit Formatierung, Listen, Tabellen und Bildern.') +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Bilder</div>' +
          fH(fImage('hero_bild', 'Hero-Hintergrundbild', data.hero_bild),
            'Das große Bild im Kopfbereich, hinter dem Titel. Quer-Format wirkt am besten.') +
          fH(fImage('bild',      'Inhaltsbild',          data.bild),
            'Ein zusätzliches Bild, das im Textbereich erscheint. Optional.') +
          fH(fBildGroesse(data.bild_groesse),
            'Legt fest, wie groß das Inhaltsbild dargestellt wird (25 % = klein, 100 % = volle Breite).') +
          fH(fText('bild_alt',   'Bild-Beschreibung',    data.bild_alt),
            'Kurze Beschreibung des Bildes. Hilft Suchmaschinen und wird angezeigt, falls das Bild mal nicht lädt.') +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Kontakt (optional)</div>' +
          fH(fText('kontakt_name',  'Kontaktname',   data.kontakt_name),
            'Name der Ansprechperson, die unten auf der Seite angezeigt wird. Optional.') +
          fH(fText('kontakt_email', 'Kontakt E-Mail', data.kontakt_email),
            'E-Mail der Ansprechperson, wird als anklickbarer Link angezeigt. Optional.') +
        '</div>' +
        renderDownloadsCard(data) +
      '</div>' +
      saveBar();
    id('admin-main').innerHTML = html;
    // TipTap-Instanzen initialisieren – vorhandener Inhalt kann noch
    // Markdown sein (Migration); initTiptap konvertiert das automatisch.
    initTiptap('untertitel', data.untertitel || '');
    initTiptap('intro',      data.intro      || '');
    initTiptap('inhalt',     data.inhalt     || '');
    initDownloadsSortable();
    bindSaveBtn();
  }

  function collectInfomobil(data) {
    data.titel         = gv('titel');
    // data ist eine Tiefkopie von S.data → data.X hält hier noch den ALTEN
    // gespeicherten Wert und dient als Rückfall-Anker (Schutz vor Datenverlust).
    data.untertitel    = getTiptapValue('untertitel', data.untertitel, 'Untertitel');
    data.intro         = getTiptapValue('intro',      data.intro,      'Einleitungstext');
    data.inhalt        = getTiptapValue('inhalt',     data.inhalt,     'Textinhalt');
    data.hero_bild     = gv('hero_bild');
    data.bild          = gv('bild');
    data.bild_groesse  = gv('bild_groesse');
    data.bild_alt      = gv('bild_alt');
    data.kontakt_name  = gv('kontakt_name');
    data.kontakt_email = gv('kontakt_email');
    data.downloads     = collectDownloadsList();
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     DOKUMENTE & DOWNLOADS (pro Seite, separat vom Markdown-Text)
     data.downloads = [{ titel: "...", datei: "/downloads/....pdf" }]
     Wird auf der Website am Seitenende als eigene Download-Liste
     gerendert (siehe js/main.js).
  ──────────────────────────────────────────────────────────── */
  var dlRowSeq = 0;
  function renderDownloadRow(d) {
    var datei = d.datei || '';
    var fname = datei.split('/').pop();
    var imgId = 'dl-vorschau-' + (dlRowSeq++);
    return '<div class="item-card download-row" data-img-id="' + imgId + '">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        '<input class="field-input dl-titel" type="text" value="' + escAttr(d.titel || '') + '" placeholder="Beschriftung (z.B. Anleitung Mai 2026)" style="margin-bottom:.35rem;">' +
        '<div class="item-meta">📄 ' + escHtml(fname) +
          (datei ? ' &middot; <a href="' + escAttr(datei) + '" target="_blank" rel="noopener">öffnen</a>' : '') +
        '</div>' +
        '<div style="margin-top:.5rem;">' + fImage(imgId, 'Vorschaubild (optional)', d.vorschau) + '</div>' +
      '</div>' +
      '<input type="hidden" class="dl-datei" value="' + escAttr(datei) + '">' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.download-row\').remove()">🗑️</button>' +
      '</div>' +
    '</div>';
  }

  function renderDownloadsCard(data) {
    var list = data.downloads || [];
    var rows = list.map(renderDownloadRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">📄 Dokumente &amp; Downloads</div>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:0 0 .75rem;">' +
        'Diese Dateien werden <strong>nicht</strong> in den Text oben eingefügt, sondern erscheinen automatisch ' +
        'als eigene, beschriftete Download-Liste am Ende dieser Seite. Reihenfolge per Drag &amp; Drop änderbar.' +
      '</p>' +
      '<div id="downloads-list">' + rows + '</div>' +
      '<p class="text-muted" id="downloads-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Dokumente hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="openPdfModal(\'downloads\')">📄 PDF hinzufügen</button>' +
    '</div>';
  }

  function initDownloadsSortable() {
    var el = id('downloads-list');
    if (el && window.Sortable && !el._sortableInit) {
      el._sortableInit = true;
      Sortable.create(el, { handle: '.item-drag', animation: 150 });
    }
  }

  function collectDownloadsList() {
    var result = [];
    document.querySelectorAll('#downloads-list .download-row').forEach(function(row) {
      var dateiEl = row.querySelector('.dl-datei');
      var titelEl = row.querySelector('.dl-titel');
      var imgId = row.getAttribute('data-img-id');
      var vorschauEl = imgId ? id('f-' + imgId) : null;
      var datei = dateiEl ? dateiEl.value.trim() : '';
      var titel = titelEl ? titelEl.value.trim() : '';
      var vorschau = vorschauEl ? vorschauEl.value.trim() : '';
      if (datei) result.push({ titel: titel, datei: datei, vorschau: vorschau });
    });
    return result;
  }

  // Fügt ein Dokument (neu hochgeladen oder aus der Galerie ausgewählt) zur
  // "Dokumente & Downloads"-Liste der aktuell geöffneten Seite hinzu.
  function addDownloadRow(url, filename) {
    var list = id('downloads-list');
    if (!list) return;
    var displayName = filename.replace(/^\d+-/, '').replace(/-/g, ' ').replace(/\.pdf$/i, '').trim() || filename;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderDownloadRow({ titel: displayName, datei: url });
    list.appendChild(wrap.firstChild);
    var empty = id('downloads-empty');
    if (empty) empty.style.display = 'none';
    initDownloadsSortable();
    S.dirty = true;
    toast('✅ Dokument hinzugefügt – Beschriftung prüfen & Seite speichern', 'ok');
  }

  /* ────────────────────────────────────────────────────────────
     STARTSEITE FORM
  ──────────────────────────────────────────────────────────── */
  function renderStartseite(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card" style="background:var(--green-light);border-color:var(--green);">' +
          '<div class="form-card-title" style="color:var(--green-dark);">💡 Schriftgrößen, Schriftarten &amp; Farben ändern</div>' +
          '<p style="margin:0 0 .85rem;color:var(--text-body);font-size:.9rem;">' +
            'Diese Einstellungen gelten für die <strong>gesamte Website</strong> (nicht nur die Startseite) ' +
            'und befinden sich daher in einem eigenen Bereich:' +
          '</p>' +
          '<button type="button" class="btn btn-primary btn-sm" onclick="(function(){' +
            'var wrap=document.getElementById(\'nc-einstellungen\');' +
            'var grp=document.querySelector(\'[data-navkey=einstellungen]\');' +
            'if(wrap&&grp&&wrap.style.display===\'none\')grp.click();' +
            'setTimeout(function(){var d=document.querySelector(\'[data-navkey=design]\');if(d)d.click();},80);' +
          '})()">' +
            '🎨 Zu „Design &amp; Farben" wechseln' +
          '</button>' +
          '<span style="margin-left:.6rem;color:var(--text-muted);font-size:.82rem;">⚙️ Einstellungen → Design &amp; Farben</span>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Hero-Bereich (Startseiten-Banner)</div>' +
          fText('hero_titel', 'Titel Zeile 1', data.hero_titel) +
          fText('hero_titel_zeile2', 'Titel Zeile 2', data.hero_titel_zeile2) +
          fTextarea('hero_untertitel', 'Untertitel-Text', data.hero_untertitel, 2) +
          fText('hero_button_text', 'Button-Text', data.hero_button_text) +
          fImage('hero_bild', 'Hero-Hintergrundbild', data.hero_bild) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Willkommen-Bereich ("Heger, Schützer und Botschafter der Natur")</div>' +
          fText('willkommen_tag', 'Badge-Text', data.willkommen_tag) +
          '<div class="field-row-2">' +
            fText('willkommen_titel_zeile1', 'Titel Zeile 1', data.willkommen_titel_zeile1) +
            fText('willkommen_titel_zeile2', 'Titel Zeile 2', data.willkommen_titel_zeile2) +
          '</div>' +
          fTextarea('willkommen_text', 'Einleitungstext', data.willkommen_text, 3) +
          fTextarea('willkommen_zitat', 'Zitat (Anführungszeichen werden automatisch ergänzt)', data.willkommen_zitat, 3) +
          fTextarea('willkommen_text2', 'Zweiter Absatz', data.willkommen_text2, 3) +
          '<div class="field-row-2">' +
            fText('willkommen_signatur_name', 'Unterschrift – Name/Funktion', data.willkommen_signatur_name) +
            fText('willkommen_signatur_rolle', 'Unterschrift – Zusatz', data.willkommen_signatur_rolle) +
          '</div>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Statistiken (3 Zahlen unter dem Hero)</div>' +
          '<div class="field-row-2">' +
            fText('statistik_1_zahl', 'Zahl 1', data.statistik_1_zahl) +
            fText('statistik_1_label', 'Bezeichnung 1', data.statistik_1_label) +
          '</div>' +
          '<div class="field-row-2">' +
            fText('statistik_2_zahl', 'Zahl 2', data.statistik_2_zahl) +
            fText('statistik_2_label', 'Bezeichnung 2', data.statistik_2_label) +
          '</div>' +
          '<div class="field-row-2">' +
            fText('statistik_3_zahl', 'Zahl 3', data.statistik_3_zahl) +
            fText('statistik_3_label', 'Bezeichnung 3', data.statistik_3_label) +
          '</div>' +
        '</div>' +
      '</div>' +
      saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  function collectStartseite(data) {
    ['hero_titel','hero_titel_zeile2','hero_untertitel','hero_button_text',
     'willkommen_tag','willkommen_titel_zeile1','willkommen_titel_zeile2',
     'willkommen_text','willkommen_zitat','willkommen_text2',
     'willkommen_signatur_name','willkommen_signatur_rolle',
     'statistik_1_zahl','statistik_1_label','statistik_2_zahl','statistik_2_label',
     'statistik_3_zahl','statistik_3_label'].forEach(function(k) {
      data[k] = gv(k);
    });
    data.hero_bild = gv('hero_bild');
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     AKTUELLES (list + item editor)
  ──────────────────────────────────────────────────────────── */
  function renderAktuelles(def, data) {
    var beitraege = data.beitraege || [];
    var einst = data.einstellungen || {};
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="aktuellesNeu()">➕ Neuer Beitrag</button>') +
      '<div class="panel-body">' +

      // ── Einstellungen ──────────────────────────────────────
      '<div class="form-card">' +
        '<div class="form-card-title">⚙️ Anzeigeeinstellungen</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;font-size:.85rem;">Steuert, welche Beiträge auf der Hauptseite erscheinen. Bei 0 = aktuelles Jahr.</p>' +
        '<div class="field-row" style="align-items:center;gap:1rem;flex-direction:row;flex-wrap:wrap;">' +
          '<label class="field-label" style="min-width:180px;margin:0">Anzahl anzeigen (0 = aktuelles Jahr)</label>' +
          '<input class="field-input" type="number" min="0" max="99" id="akt-anzahl" value="' + (einst.hauptseite_anzahl || 0) + '" style="width:80px">' +
          '<button class="btn btn-sm btn-outline" onclick="aktuellesEinstSave()">Speichern</button>' +
        '</div>' +
      '</div>' +

      '<p class="text-muted" style="margin-bottom:1rem;">' + beitraege.length + ' Beiträge. Klicken zum Bearbeiten.</p>';

    beitraege.forEach(function(b, i) {
      var archivBadge = b.archiviert
        ? '<span class="item-badge" style="background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;">📦 Archiv</span> '
        : '';
      html += '<div class="item-card" onclick="aktuellesEdit(' + i + ')">' +
        '<div class="item-body">' +
          '<div class="item-title">' + archivBadge + escHtml(b.titel || '(Kein Titel)') + '</div>' +
          '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
            (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm ' + (b.archiviert ? 'btn-outline' : 'btn-ghost') + '" ' +
            'title="' + (b.archiviert ? 'Aus Archiv zurückholen' : 'Ins Archiv verschieben') + '" ' +
            'onclick="event.stopPropagation();aktuellesArchivToggle(' + i + ')">' +
            (b.archiviert ? '↩️ Wiederherstellen' : '📦 Archivieren') +
          '</button>' +
          '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();aktuellesEdit(' + i + ')">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();aktuellesDelete(' + i + ')">Löschen</button>' +
        '</div>' +
      '</div>';
    });

    html += '</div>';
    id('admin-main').innerHTML = html;
  }

  window.aktuellesNeu = function() {
    var data = S.data;
    data.beitraege = data.beitraege || [];
    var newB = { titel:'', datum:'', kategorie:'Allgemein', bild:'', text:'', link:'', archiviert: false };
    data.beitraege.unshift(newB);
    aktuellesEdit(0);
  };

  window.aktuellesEdit = function(idx) {
    destroyMDE();
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    var html = panelHeader('📰 Beitrag bearbeiten',
        '<button class="btn btn-outline" onclick="renderAktuelles(S.section,S.data)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="aktuelleSave(' + idx + ')">💾 Speichern</button>') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          fText('b-titel', 'Titel', b.titel) +
          fDate('b-datum', 'Datum', b.datum) +
          fCombobox('b-kategorie', 'Kategorie', b.kategorie, KAT_NEWS) +
          fImage('b-bild', 'Bild', b.bild) +
          fMarkdown('b-text', 'Text (Markdown)', b.text) +
          fText('b-link', 'Externer Link (optional)', b.link) +
          '<div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">' +
            '<label class="field-label" style="min-width:160px;margin:0">Ins Archiv verschieben</label>' +
            '<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">' +
              '<input type="checkbox" id="b-archiviert"' + (b.archiviert ? ' checked' : '') + ' style="width:18px;height:18px;cursor:pointer;">' +
              '<span style="font-size:.85rem;color:var(--text-muted);">Erscheint nicht mehr auf der Hauptseite, bleibt im Archiv sichtbar</span>' +
            '</label>' +
          '</div>' +
        '</div>' +
      '</div>';
    id('admin-main').innerHTML = html;
    initMDE('b-text');
  };

  window.aktuelleSave = async function(idx) {
    var b = S.data.beitraege[idx];
    b.titel     = gv('b-titel');
    b.datum     = isoToDatum(gv('b-datum'));
    b.kategorie = gv('b-kategorie');
    b.bild      = gv('b-bild');
    b.text      = getMDE();
    b.link      = gv('b-link');
    var archCheck = id('b-archiviert');
    b.archiviert = archCheck ? archCheck.checked : (b.archiviert || false);
    await doSave(S.section.file, S.data, '📰 Aktuelles: Beitrag gespeichert');
    toast('✅ Beitrag gespeichert!', 'ok');
    renderAktuelles(S.section, S.data);
  };

  window.aktuellesArchivToggle = async function(idx) {
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    b.archiviert = !b.archiviert;
    await doSave(S.section.file, S.data, '📰 Aktuelles: Archivstatus geändert');
    toast(b.archiviert ? '📦 Ins Archiv verschoben' : '↩️ Aus Archiv zurückgeholt', 'ok');
    renderAktuelles(S.section, S.data);
  };

  window.aktuellesEinstSave = async function() {
    S.data.einstellungen = S.data.einstellungen || {};
    var anzEl = id('akt-anzahl');
    S.data.einstellungen.hauptseite_anzahl = anzEl ? (parseInt(anzEl.value, 10) || 0) : 0;
    await doSave(S.section.file, S.data, '⚙️ Aktuelles: Einstellungen gespeichert');
    toast('✅ Einstellungen gespeichert!', 'ok');
  };

  window.aktuellesDelete = function(idx) {
    showConfirm('Beitrag löschen', 'Diesen Beitrag wirklich löschen?', async function() {
      S.data.beitraege.splice(idx, 1);
      await doSave(S.section.file, S.data, '📰 Aktuelles: Beitrag gelöscht');
      toast('🗑️ Beitrag gelöscht', 'info');
      renderAktuelles(S.section, S.data);
    });
  };

  /* ────────────────────────────────────────────────────────────
     TERMINE
  ──────────────────────────────────────────────────────────── */
  function renderTermine(def, data) {
    var termine = data.termine || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="termineNeu()">➕ Neuer Termin</button>') +
      '<div class="panel-body">';

    if (termine.length === 0) {
      html += '<div class="form-card"><p class="text-muted">Noch keine Termine. Klicken Sie auf "Neuer Termin".</p></div>';
    } else {
      html += '<div class="form-card" style="padding:0;overflow:hidden"><table class="termine-table">' +
        '<thead><tr><th>Datum</th><th>Uhrzeit</th><th>Veranstaltung</th><th>Ort</th><th>Kategorie</th><th></th></tr></thead><tbody>';
      termine.forEach(function(t, i) {
        html += '<tr onclick="termineEdit(' + i + ')" style="cursor:pointer">' +
          '<td><strong>' + escHtml(t.datum || '') + '</strong></td>' +
          '<td>' + escHtml(t.uhrzeit || '') + '</td>' +
          '<td>' + escHtml(t.veranstaltung || '') + '</td>' +
          '<td>' + escHtml(t.ort || '') + '</td>' +
          '<td><span class="item-badge">' + escHtml(t.kategorie || '') + '</span></td>' +
          '<td class="td-actions">' +
            '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();termineEdit(' + i + ')">✏️</button>' +
            '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();termineDelete(' + i + ')">🗑️</button>' +
          '</td>' +
        '</tr>';
      });
      html += '</tbody></table></div>';
    }
    html += '</div>';
    id('admin-main').innerHTML = html;
  }

  window.termineNeu = function() {
    S.data.termine = S.data.termine || [];
    S.data.termine.unshift({ datum:'', uhrzeit:'', veranstaltung:'', ort:'', kategorie:'Kreisveranstaltung' });
    termineEdit(0);
  };

  window.termineEdit = function(idx) {
    var t = (S.data.termine || [])[idx];
    if (!t) return;
    var html = panelHeader('📅 Termin bearbeiten',
        '<button class="btn btn-outline" onclick="renderTermine(S.section,S.data)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="termineSave(' + idx + ')">💾 Speichern</button>') +
      '<div class="panel-body"><div class="form-card">' +
        fDate('t-datum', 'Datum', t.datum) +
        fText('t-uhrzeit', 'Uhrzeit', t.uhrzeit, 'z.B. 18:00 Uhr') +
        fText('t-veranstaltung', 'Veranstaltung', t.veranstaltung) +
        fText('t-ort', 'Ort', t.ort) +
        fCombobox('t-kategorie', 'Kategorie', t.kategorie, KAT_TERMINE) +
      '</div></div>';
    id('admin-main').innerHTML = html;
  };

  window.termineSave = async function(idx) {
    var t = S.data.termine[idx];
    t.datum        = isoToDatum(gv('t-datum'));
    t.uhrzeit      = gv('t-uhrzeit');
    t.veranstaltung= gv('t-veranstaltung');
    t.ort          = gv('t-ort');
    t.kategorie    = gv('t-kategorie');
    // Sort by date
    S.data.termine.sort(function(a, b) {
      return datumToIso(a.datum).localeCompare(datumToIso(b.datum));
    });
    await doSave(S.section.file, S.data, '📅 Termin gespeichert');
    toast('✅ Termin gespeichert!', 'ok');
    renderTermine(S.section, S.data);
  };

  window.termineDelete = function(idx) {
    showConfirm('Termin löschen', 'Diesen Termin wirklich löschen?', async function() {
      S.data.termine.splice(idx, 1);
      await doSave(S.section.file, S.data, '📅 Termin gelöscht');
      toast('🗑️ Termin gelöscht', 'info');
      renderTermine(S.section, S.data);
    });
  };

  /* ────────────────────────────────────────────────────────────
     PERSONEN (Vorstand, Obleute)
  ──────────────────────────────────────────────────────────── */
  function renderPersonen(def, data) {
    var liste = data[def.dataKey] || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="personAdd()">➕ Person hinzufügen</button>') +
      '<div class="panel-body">' +
        '<p class="text-muted" style="margin-bottom:1rem;">Klicken zum Bearbeiten. Reihenfolge per Drag &amp; Drop.</p>' +
        '<div id="personen-list">';

    liste.forEach(function(p, i) {
      var fotoSrc = p.bild || '';
      html += '<div class="item-card" data-idx="' + i + '">' +
        '<div class="item-drag">⠿</div>' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(p.name || '(Kein Name)') + '</div>' +
          '<div class="item-meta">' + escHtml(p.rolle || '') + '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-outline" onclick="personEdit(' + i + ')">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="personDelete(' + i + ')">Löschen</button>' +
        '</div></div>';
    });

    html += '</div></div>';
    id('admin-main').innerHTML = html;

    // Sortable
    var listEl = id('personen-list');
    if (listEl && window.Sortable) {
      Sortable.create(listEl, {
        handle: '.item-drag',
        animation: 150,
        onEnd: async function(evt) {
          var arr = data[def.dataKey];
          var moved = arr.splice(evt.oldIndex, 1)[0];
          arr.splice(evt.newIndex, 0, moved);
          await doSave(def.file, data, '👤 Reihenfolge geändert');
          toast('✅ Reihenfolge gespeichert', 'ok');
        }
      });
    }
  }

  window.personEdit = function(idx) {
    var def = S.section;
    var p = (S.data[def.dataKey] || [])[idx];
    if (!p) return;
    var html = panelHeader('👤 Person bearbeiten',
        '<button class="btn btn-outline" onclick="renderPersonen(S.section,S.data)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="personSave(' + idx + ')">💾 Speichern</button>') +
      '<div class="panel-body"><div class="form-card">' +
        fText('p-rolle', 'Funktion / Rolle', p.rolle) +
        fText('p-name', 'Name', p.name) +
        fText('p-email', 'E-Mail', p.email) +
        fText('p-telefon', 'Telefon', p.telefon) +
        fImage('p-bild', 'Foto', p.bild) +
      '</div></div>';
    id('admin-main').innerHTML = html;
  };

  window.personSave = async function(idx) {
    var def = S.section;
    var p = S.data[def.dataKey][idx];
    p.rolle   = gv('p-rolle');
    p.name    = gv('p-name');
    p.email   = gv('p-email');
    p.telefon = gv('p-telefon');
    p.bild    = gv('p-bild');
    await doSave(def.file, S.data, '👤 Person gespeichert');
    toast('✅ Gespeichert!', 'ok');
    renderPersonen(def, S.data);
  };

  window.personAdd = function() {
    var def = S.section;
    S.data[def.dataKey] = S.data[def.dataKey] || [];
    S.data[def.dataKey].push({ rolle:'', name:'', email:'', telefon:'', bild:'' });
    personEdit(S.data[def.dataKey].length - 1);
  };

  window.personDelete = function(idx) {
    var def = S.section;
    showConfirm('Person löschen', 'Diese Person wirklich löschen?', async function() {
      S.data[def.dataKey].splice(idx, 1);
      await doSave(def.file, S.data, '👤 Person gelöscht');
      toast('🗑️ Gelöscht', 'info');
      renderPersonen(def, S.data);
    });
  };

  /* ────────────────────────────────────────────────────────────
     HEGERINGE
  ──────────────────────────────────────────────────────────── */
  function renderHegeringe(def, data) {
    var liste = data.hegeringe || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="hegeringAdd()">➕ Hegering hinzufügen</button>') +
      '<div class="panel-body"><p class="text-muted" style="margin-bottom:1rem;">' + liste.length + ' Hegeringe</p>' +
      '<div id="hegering-list">';

    liste.forEach(function(h, i) {
      html += '<div class="item-card" data-idx="' + i + '">' +
        '<div class="item-drag">⠿</div>' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(h.nummer || '') + ' – ' + escHtml(h.name || '') + '</div>' +
          '<div class="item-meta">' + escHtml(h.obmann || '') + ' &nbsp;|&nbsp; ' + escHtml(h.gemeinden || '') + '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-outline" onclick="hegeringEdit(' + i + ')">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="hegeringDelete(' + i + ')">Löschen</button>' +
        '</div></div>';
    });
    html += '</div></div>';
    id('admin-main').innerHTML = html;

    if (window.Sortable) {
      Sortable.create(id('hegering-list'), {
        handle: '.item-drag', animation: 150,
        onEnd: async function(e) {
          var a = S.data.hegeringe;
          var m = a.splice(e.oldIndex,1)[0]; a.splice(e.newIndex,0,m);
          await doSave(def.file, S.data, '🗺️ Hegering-Reihenfolge geändert');
          toast('✅ Reihenfolge gespeichert', 'ok');
        }
      });
    }
  }

  window.hegeringEdit = function(idx) {
    var h = (S.data.hegeringe || [])[idx];
    if (!h) return;
    var html = panelHeader('🗺️ Hegering bearbeiten',
        '<button class="btn btn-outline" onclick="renderHegeringe(S.section,S.data)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="hegeringSave(' + idx + ')">💾 Speichern</button>') +
      '<div class="panel-body"><div class="form-card">' +
        fText('h-nummer', 'Hegering-Nummer', h.nummer, 'z.B. Hegering 1') +
        fText('h-name', 'Gebietsname', h.name) +
        fText('h-obmann', 'Name der Leitung', h.obmann) +
        fSelect('h-geschlecht', 'Titel', h.geschlecht || 'Hegeringsleiter/in', [
          {label:'Hegeringsleiter (Mann)', value:'Hegeringsleiter'},
          {label:'Hegeringsleiterin (Frau)', value:'Hegeringsleiterin'},
          {label:'Hegeringsleiter/in', value:'Hegeringsleiter/in'},
        ]) +
        fText('h-gemeinden', 'Gemeinden', h.gemeinden, 'Kommagetrennt') +
        fText('h-email', 'E-Mail', h.email) +
        fText('h-telefon', 'Telefon', h.telefon) +
      '</div></div>';
    id('admin-main').innerHTML = html;
  };

  window.hegeringSave = async function(idx) {
    var h = S.data.hegeringe[idx];
    h.nummer    = gv('h-nummer');
    h.name      = gv('h-name');
    h.obmann    = gv('h-obmann');
    h.geschlecht= gv('h-geschlecht');
    h.gemeinden = gv('h-gemeinden');
    h.email     = gv('h-email');
    h.telefon   = gv('h-telefon');
    await doSave(S.section.file, S.data, '🗺️ Hegering gespeichert');
    toast('✅ Gespeichert!', 'ok');
    renderHegeringe(S.section, S.data);
  };

  window.hegeringAdd = function() {
    S.data.hegeringe = S.data.hegeringe || [];
    S.data.hegeringe.push({ nummer:'', name:'', obmann:'', geschlecht:'Hegeringsleiter/in', gemeinden:'', email:'', telefon:'' });
    hegeringEdit(S.data.hegeringe.length - 1);
  };

  window.hegeringDelete = function(idx) {
    showConfirm('Hegering löschen', 'Diesen Hegering wirklich löschen?', async function() {
      S.data.hegeringe.splice(idx, 1);
      await doSave(S.section.file, S.data, '🗺️ Hegering gelöscht');
      toast('🗑️ Gelöscht', 'info');
      renderHegeringe(S.section, S.data);
    });
  };

  /* ────────────────────────────────────────────────────────────
     KREISJÄGERMEISTER
  ──────────────────────────────────────────────────────────── */
  function renderKJM(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          fText('kjm-name', 'Name', data.name) +
          fImage('kjm-bild', 'Foto', data.bild) +
          fText('kjm-email', 'E-Mail', data.email) +
          fText('kjm-telefon', 'Telefon', data.telefon) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Aufgaben & Zuständigkeiten (Markdown)</div>' +
          fMarkdown('kjm-aufgaben', 'Aufgaben', data.aufgaben) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Grußwort</div>' +
          fTextarea('kjm-grußwort', 'Grußwort', data.grußwort, 6) +
        '</div>' +
        renderDownloadsCard(data) +
      '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    initMDE('kjm-aufgaben');
    initDownloadsSortable();
    bindSaveBtn();
  }

  function collectKJM(data) {
    data.name     = gv('kjm-name');
    data.bild     = gv('kjm-bild');
    data.email    = gv('kjm-email');
    data.telefon  = gv('kjm-telefon');
    data.aufgaben = getMDE();
    data.grußwort = gv('kjm-grußwort');
    data.downloads = collectDownloadsList();
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     FAQ
  ──────────────────────────────────────────────────────────── */
  function renderFAQ(def, data) {
    var kats = data.kategorien || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="faqKatAdd()">➕ Kategorie hinzufügen</button>') +
      '<div class="panel-body">';

    kats.forEach(function(kat, ki) {
      html += '<div class="form-card">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">' +
          '<input class="field-input" style="flex:1;margin-right:.75rem;" ' +
            'value="' + escAttr(kat.titel) + '" ' +
            'onchange="S.data.kategorien[' + ki + '].titel=this.value" ' +
            'placeholder="Kategorie-Titel">' +
          '<button class="btn btn-sm btn-danger-outline" onclick="faqKatDelete(' + ki + ')">Kategorie löschen</button>' +
        '</div>';

      (kat.fragen || []).forEach(function(f, fi) {
        html += '<div style="border:1px solid var(--border);border-radius:6px;padding:.75rem;margin-bottom:.5rem;">' +
          '<input class="field-input" style="margin-bottom:.5rem;" ' +
            'value="' + escAttr(f.frage) + '" ' +
            'onchange="S.data.kategorien[' + ki + '].fragen[' + fi + '].frage=this.value" ' +
            'placeholder="Frage">' +
          '<textarea class="field-textarea" rows="2" ' +
            'onchange="S.data.kategorien[' + ki + '].fragen[' + fi + '].antwort=this.value" ' +
            'placeholder="Antwort">' + escHtml(f.antwort) + '</textarea>' +
          '<div style="text-align:right;margin-top:.4rem;">' +
            '<button class="btn btn-sm btn-ghost" onclick="faqFrageDelete(' + ki + ',' + fi + ')">✕ Frage löschen</button>' +
          '</div></div>';
      });

      html += '<button class="list-add-btn" onclick="faqFrageAdd(' + ki + ')">+ Frage hinzufügen</button>' +
        '</div>';
    });

    html += '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  window.faqKatAdd = function() {
    S.data.kategorien = S.data.kategorien || [];
    S.data.kategorien.push({ titel:'Neue Kategorie', fragen:[] });
    renderFAQ(S.section, S.data);
  };
  window.faqKatDelete = function(ki) {
    showConfirm('Kategorie löschen', 'Diese Kategorie und alle enthaltenen Fragen löschen?', function() {
      S.data.kategorien.splice(ki, 1);
      renderFAQ(S.section, S.data);
    });
  };
  window.faqFrageAdd = function(ki) {
    S.data.kategorien[ki].fragen = S.data.kategorien[ki].fragen || [];
    S.data.kategorien[ki].fragen.push({ frage:'', antwort:'' });
    renderFAQ(S.section, S.data);
  };
  window.faqFrageDelete = function(ki, fi) {
    S.data.kategorien[ki].fragen.splice(fi, 1);
    renderFAQ(S.section, S.data);
  };

  function collectFAQ(data) {
    // Data is mutated live via onchange handlers; sync is done inline
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     EINSTELLUNGEN (Kontakt & Öffnungszeiten)
  ──────────────────────────────────────────────────────────── */
  function renderEinstellungen(def, data) {
    var oz = data.oeffnungszeiten || [];
    var ozHtml = oz.map(function(o, i) {
      return '<div style="display:flex;gap:.5rem;margin-bottom:.5rem;" data-oz="' + i + '">' +
        '<input class="field-input" style="flex:1" value="' + escAttr(o.tage) + '" placeholder="Tag(e), z.B. Montag – Freitag" id="oz-tage-' + i + '">' +
        '<input class="field-input" style="flex:1" value="' + escAttr(o.zeiten) + '" placeholder="Uhrzeit, z.B. 10:00 – 12:00 Uhr" id="oz-zeiten-' + i + '">' +
        '<button class="btn btn-sm btn-ghost" onclick="ozDelete(' + i + ')">✕</button>' +
      '</div>';
    }).join('');

    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Kontaktdaten</div>' +
          '<p style="margin:-.4rem 0 .85rem;color:var(--text-muted);font-size:.82rem;">' +
            'Diese Angaben erscheinen automatisch in der grünen „Geschäftsstelle"-Kontaktbox auf allen Unterseiten sowie im Impressum und in der Datenschutzerklärung – einmal hier ändern, überall aktuell.' +
          '</p>' +
          fText('ei-telefon', 'Telefon (Geschäftsstelle / Kontaktbox)', data.telefon) +
          fText('ei-telefon-header', 'Telefonnummer in der Kopfzeile', data.telefon_header) +
          '<p style="margin:-.4rem 0 .85rem;color:var(--text-muted);font-size:.82rem;">' +
            'Diese Nummer wird ganz oben auf jeder Seite (Kopfzeile) angezeigt – unabhängig von der Telefonnummer der Geschäftsstelle.' +
          '</p>' +
          fText('ei-email', 'E-Mail', data.email) +
          fText('ei-telefon-festnetz', 'Telefon Festnetz (Geschäftsstelle)', data.telefon_festnetz) +
          fTextarea('ei-adresse', 'Adresse (jede Zeile = eine Zeile)', data.adresse, 3) +
          fTextarea('ei-postadresse', 'Postadresse (nur ausfüllen, falls abweichend von der Adresse oben)', data.postadresse, 3) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Öffnungszeiten</div>' +
          '<div id="oz-list">' + ozHtml + '</div>' +
          '<button class="list-add-btn" onclick="ozAdd()" style="margin-top:.5rem">+ Zeile hinzufügen</button>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Google Kalender (optional)</div>' +
          fText('ei-kal-url', 'Google Kalender URL', data.google_kalender_url, 'Einbettungs-URL aus Google Kalender') +
          fText('ei-kal-titel', 'Kalender-Überschrift', data.google_kalender_titel, 'z.B. Terminbuchung') +
        '</div>' +
      '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  window.ozDelete = function(i) {
    S.data.oeffnungszeiten.splice(i, 1);
    renderEinstellungen(S.section, S.data);
  };
  window.ozAdd = function() {
    S.data.oeffnungszeiten = S.data.oeffnungszeiten || [];
    S.data.oeffnungszeiten.push({ tage:'', zeiten:'' });
    renderEinstellungen(S.section, S.data);
  };

  function collectEinstellungen(data) {
    data.telefon = gv('ei-telefon');
    data.telefon_header = gv('ei-telefon-header');
    data.email   = gv('ei-email');
    data.telefon_festnetz = gv('ei-telefon-festnetz');
    data.adresse = gv('ei-adresse');
    data.postadresse = gv('ei-postadresse');
    data.google_kalender_url   = gv('ei-kal-url');
    data.google_kalender_titel = gv('ei-kal-titel');
    var oz = [];
    document.querySelectorAll('[data-oz]').forEach(function(row) {
      var i = row.getAttribute('data-oz');
      oz.push({ tage: val('oz-tage-' + i), zeiten: val('oz-zeiten-' + i) });
    });
    data.oeffnungszeiten = oz;
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     FOOTER
  ──────────────────────────────────────────────────────────── */
  function renderFooter(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body"><div class="form-card">' +
        fTextarea('ft-ueber', 'Über-uns Text', data.ueber_text, 3) +
        fText('ft-copyright', 'Copyright-Text', data.copyright) +
        fText('ft-fb', 'Facebook URL', data.facebook_url) +
        fText('ft-ig', 'Instagram URL', data.instagram_url) +
      '</div></div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  function collectFooter(data) {
    data.ueber_text    = gv('ft-ueber');
    data.copyright     = gv('ft-copyright');
    data.facebook_url  = gv('ft-fb');
    data.instagram_url = gv('ft-ig');
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     DESIGN
  ──────────────────────────────────────────────────────────── */
  function renderDesign(def, data) {
    var fonts_h = ['Playfair Display','Georgia','Merriweather','Lora','EB Garamond'];
    var fonts_t = ['Inter','Open Sans','Roboto','Lato','Source Sans Pro'];
    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Farben</div>' +
          '<div class="field-row"><label class="field-label">Hauptfarbe Grün</label>' +
            '<input type="color" id="f-farbe_gruen" value="' + escAttr(data.farbe_gruen || '#2e6b30') + '" style="width:60px;height:36px;cursor:pointer;border:none;padding:0;background:none"></div>' +
          '<div class="field-row"><label class="field-label">Dunkelgrün (Header/Footer)</label>' +
            '<input type="color" id="f-farbe_dunkelgruen" value="' + escAttr(data.farbe_dunkelgruen || '#1a4a1c') + '" style="width:60px;height:36px;cursor:pointer;border:none;padding:0;background:none"></div>' +
          '<div class="field-row"><label class="field-label">Akzentfarbe (Gold)</label>' +
            '<input type="color" id="f-farbe_akzent" value="' + escAttr(data.farbe_akzent || '#b8860b') + '" style="width:60px;height:36px;cursor:pointer;border:none;padding:0;background:none"></div>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Schriften</div>' +
          fSelect('schrift_ueberschrift', 'Überschrift-Schrift', data.schrift_ueberschrift, fonts_h) +
          fSelect('schrift_text', 'Text-Schrift', data.schrift_text, fonts_t) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Schriftgrößen</div>' +
          fText('schriftgroesse_h1', 'H1 (Haupttitel)', data.schriftgroesse_h1, '2.8rem') +
          fText('schriftgroesse_h2', 'H2 (Abschnittstitel)', data.schriftgroesse_h2, '2rem') +
          fText('schriftgroesse_h3', 'H3 (Unterabschnitt)', data.schriftgroesse_h3, '1.4rem') +
          fText('schriftgroesse_text', 'Fließtext', data.schriftgroesse_text, '1rem') +
        '</div>' +
      '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  function collectDesign(data) {
    ['farbe_gruen','farbe_dunkelgruen','farbe_akzent',
     'schrift_ueberschrift','schrift_text',
     'schriftgroesse_h1','schriftgroesse_h2','schriftgroesse_h3','schriftgroesse_text'
    ].forEach(function(k) { data[k] = gv(k); });
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     IMPRESSUM
  ──────────────────────────────────────────────────────────── */
  function renderImpressum(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body"><div class="form-card">' +
        fText('imp-verein', 'Vereinsname', data.verein) +
        fTextarea('imp-adresse', 'Adresse', data.adresse, 3) +
        fTextarea('imp-postadresse', 'Postadresse (nur ausfüllen, falls abweichend von der Adresse oben)', data.postadresse, 3) +
        fText('imp-vertreten', 'Vertreten durch', data.vertreten_durch) +
        fText('imp-telefon', 'Telefon', data.telefon) +
        fText('imp-email', 'E-Mail', data.email) +
        fText('imp-registergericht', 'Registergericht', data.registergericht) +
        fText('imp-registernummer', 'Registernummer', data.registernummer) +
        fTextarea('imp-verantwortlich', 'Verantwortlich (§18)', data.verantwortlich, 2) +
      '</div></div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  function collectImpressum(data) {
    data.verein           = gv('imp-verein');
    data.adresse          = gv('imp-adresse');
    data.postadresse      = gv('imp-postadresse');
    data.vertreten_durch  = gv('imp-vertreten');
    data.telefon          = gv('imp-telefon');
    data.email            = gv('imp-email');
    data.registergericht  = gv('imp-registergericht');
    data.registernummer   = gv('imp-registernummer');
    data.verantwortlich   = gv('imp-verantwortlich');
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     DOWNLOADS
  ──────────────────────────────────────────────────────────── */
  function renderDownloads(def, data) {
    var kats = data.kategorien || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="dlKatAdd()">➕ Kategorie hinzufügen</button>') +
      '<div class="panel-body">' +
        fText('dl-titel', 'Seitentitel', data.titel) +
        fTextarea('dl-intro', 'Einleitungstext', data.intro, 2);

    kats.forEach(function(kat, ki) {
      html += '<div class="form-card">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">' +
          '<input class="field-input" style="flex:1;margin-right:.75rem;" value="' + escAttr(kat.titel) + '" id="dlkat-titel-' + ki + '" placeholder="Kategorie-Titel">' +
          '<button class="btn btn-sm btn-danger-outline" onclick="dlKatDelete(' + ki + ')">Kategorie löschen</button>' +
        '</div>';
      (kat.downloads || []).forEach(function(d, di) {
        html += '<div style="border:1px solid var(--border);border-radius:6px;padding:.75rem;margin-bottom:.5rem;display:grid;grid-template-columns:1fr 1fr auto;gap:.5rem;align-items:start;">' +
          '<div>' +
            '<input class="field-input" value="' + escAttr(d.name) + '" id="dl-name-' + ki + '-' + di + '" placeholder="Dateiname / Titel" style="margin-bottom:.4rem;">' +
            '<input class="field-input" value="' + escAttr(d.beschreibung || '') + '" id="dl-beschr-' + ki + '-' + di + '" placeholder="Beschreibung (optional)">' +
          '</div>' +
          '<div>' +
            '<input class="field-input" value="' + escAttr(d.url || '') + '" id="dl-url-' + ki + '-' + di + '" placeholder="URL oder Dateipfad" style="margin-bottom:.4rem;">' +
            '<select class="field-select" id="dl-typ-' + ki + '-' + di + '">' +
              ['PDF','Word','Excel','ZIP','Sonstiges'].map(function(t) {
                return '<option' + (t === d.typ ? ' selected' : '') + '>' + t + '</option>';
              }).join('') +
            '</select>' +
          '</div>' +
          '<button class="btn btn-sm btn-ghost" onclick="dlDelete(' + ki + ',' + di + ')" style="margin-top:.2rem">✕</button>' +
        '</div>';
      });
      html += '<button class="list-add-btn" onclick="dlAdd(' + ki + ')">+ Download hinzufügen</button></div>';
    });

    html += '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  window.dlKatAdd = function() {
    S.data.kategorien = S.data.kategorien || [];
    S.data.kategorien.push({ titel:'Neue Kategorie', downloads:[] });
    renderDownloads(S.section, S.data);
  };
  window.dlKatDelete = function(ki) {
    showConfirm('Kategorie löschen', 'Kategorie und alle Downloads darin löschen?', function() {
      S.data.kategorien.splice(ki, 1);
      renderDownloads(S.section, S.data);
    });
  };
  window.dlAdd = function(ki) {
    S.data.kategorien[ki].downloads = S.data.kategorien[ki].downloads || [];
    S.data.kategorien[ki].downloads.push({ name:'', beschreibung:'', url:'', typ:'PDF' });
    renderDownloads(S.section, S.data);
  };
  window.dlDelete = function(ki, di) {
    S.data.kategorien[ki].downloads.splice(di, 1);
    renderDownloads(S.section, S.data);
  };

  function collectDownloads(data) {
    data.titel = gv('dl-titel');
    data.intro = gv('dl-intro');
    (data.kategorien || []).forEach(function(kat, ki) {
      kat.titel = val('dlkat-titel-' + ki) || kat.titel;
      (kat.downloads || []).forEach(function(d, di) {
        d.name        = val('dl-name-' + ki + '-' + di) || d.name;
        d.beschreibung= val('dl-beschr-' + ki + '-' + di) || '';
        d.url         = val('dl-url-' + ki + '-' + di) || '';
        d.typ         = val('dl-typ-' + ki + '-' + di) || 'PDF';
      });
    });
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     MEDIEN & BILDER (standalone gallery panel)
  ──────────────────────────────────────────────────────────── */
  function renderMedian() {
    var html = '<div class="panel-header"><h2>🖼️ Medien & Bilder</h2></div>' +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Bild hochladen</div>' +
          '<div class="upload-row" style="margin-bottom:1.25rem;">' +
            '<label class="btn btn-primary" for="medien-upload-input">📤 Bild hochladen</label>' +
            '<input type="file" id="medien-upload-input" accept="image/*" style="display:none">' +
            '<span id="medien-upload-status" style="margin-left:.75rem;color:var(--text-muted);font-size:.85rem;"></span>' +
          '</div>' +
          '<div class="form-card-title">Alle Bilder</div>' +
          '<div class="img-gallery" id="medien-gallery"><div class="gallery-loading">Wird geladen…</div></div>' +
        '</div>' +
      '</div>';
    id('admin-main').innerHTML = html;
    loadMedianGallery();

    id('medien-upload-input').addEventListener('change', async function() {
      var file = this.files[0];
      if (!file) return;
      var status = id('medien-upload-status');
      status.textContent = '⏳ Wird hochgeladen…';
      try {
        var b64 = await fileToBase64(file);
        await apiUploadImage(file.name, b64);
        status.textContent = '✅ Hochgeladen!';
        loadMedianGallery();
      } catch(e) {
        status.textContent = '❌ ' + e.message;
      }
    });
  }

  async function loadMedianGallery() {
    var gallery = id('medien-gallery');
    if (!gallery) return;
    gallery.innerHTML = '<div class="gallery-loading">Bilder werden geladen…</div>';
    try {
      var files = await apiGetDir('images');
      var imgs = files.filter(function(f) {
        return f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
      });
      if (!imgs.length) {
        gallery.innerHTML = '<div class="gallery-loading">Noch keine Bilder vorhanden.</div>';
        return;
      }
      gallery.innerHTML = imgs.map(function(f) {
        var url = '/images/' + f.name;
        return '<div class="gallery-img-wrap" data-path="' + escAttr(f.path) + '">' +
          '<img class="gallery-img" src="' + escAttr(url) + '" alt="' + escAttr(f.name) + '" loading="lazy">' +
          '<div class="gallery-img-name">' + escHtml(f.name) + '</div>' +
          '<div style="text-align:center;margin-top:.25rem;display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap;">' +
            '<button class="btn btn-sm btn-outline" onclick="medienCopyUrl(\'' + escAttr(url) + '\')">📋 URL kopieren</button>' +
            '<button class="btn btn-sm btn-outline" style="color:#c0392b;border-color:#c0392b;" onclick="medienDeleteImage(\'' + escAttr(f.path) + '\',\'' + escAttr(f.sha) + '\',\'' + escAttr(f.name) + '\')">🗑️ Löschen</button>' +
          '</div>' +
        '</div>';
      }).join('');
    } catch(e) {
      gallery.innerHTML = '<div class="gallery-loading">Fehler: ' + escHtml(e.message) + '</div>';
    }
  }

  window.medienCopyUrl = function(url) {
    navigator.clipboard.writeText(url).then(function() {
      toast('📋 URL kopiert: ' + url, 'ok');
    }).catch(function() {
      prompt('URL zum Kopieren:', url);
    });
  };

  window.medienDeleteImage = function(path, sha, name) {
    showConfirm('Bild löschen',
      'Bild „' + name + '" wirklich löschen?',
      async function() {
        try {
          await apiDeleteFile(path, sha, '🗑️ Bild gelöscht: ' + name);
          toast('✅ Bild gelöscht.', 'ok');
          // Direkt aus der Galerie entfernen statt neu zu laden: Die GitHub-API liefert
          // nach einem DELETE kurzzeitig noch die alte (zwischengespeicherte) Verzeichnis-
          // liste zurück – ein sofortiges Neuladen würde das gerade gelöschte Bild also
          // wieder anzeigen ("Geisterbild", das erst beim zweiten Klick verschwindet).
          var gallery = id('medien-gallery');
          if (gallery) {
            var escSel = (window.CSS && CSS.escape) ? CSS.escape(path) : path.replace(/(["\\\]])/g, '\\$1');
            var wrap = gallery.querySelector('[data-path="' + escSel + '"]');
            if (wrap) wrap.remove();
            if (!gallery.querySelector('.gallery-img-wrap')) {
              gallery.innerHTML = '<div class="gallery-loading">Noch keine Bilder vorhanden.</div>';
            }
          }
        } catch(e) {
          toast('❌ Fehler: ' + e.message, 'err');
        }
      }
    );
  };

  /* ────────────────────────────────────────────────────────────
     NAVIGATION EXTRA (Neue Hauptnavigationspunkte)
  ──────────────────────────────────────────────────────────── */
  function renderNavExtra(def, data) {
    var punkte = data.hauptpunkte || [];
    var html = panelHeader('🧭 Hauptnavigation erweitern',
      '<button class="btn btn-primary btn-sm" onclick="navExtraNeu()">➕ Nav-Punkt hinzufügen</button>') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Erklärung</div>' +
          '<p class="text-muted" style="margin-bottom:.5rem;">Hier können Sie neue Hauptnavigationspunkte hinzufügen, die <strong>vor FAQ</strong> in der Hauptmenüleiste erscheinen.</p>' +
          '<p class="text-muted">Jeder Punkt enthält eine oder mehrere Seiten. Die Seiteninhalte werden im Bereich <strong>„Weitere Themen"</strong> erstellt und verwaltet.</p>' +
        '</div>';

    if (punkte.length === 0) {
      html += '<div class="form-card"><p class="text-muted">Noch keine zusätzlichen Navigationspunkte vorhanden.</p></div>';
    } else {
      punkte.forEach(function(hp, hi) {
        var seiten = hp.seiten || [];
        html += '<div class="form-card" id="navex-card-' + hi + '">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">' +
            '<div class="form-card-title" style="margin-bottom:0;">Navigationspunkt ' + (hi + 1) + '</div>' +
            '<button class="btn btn-sm btn-danger-outline" onclick="navExtraDelete(' + hi + ')">🗑️ Entfernen</button>' +
          '</div>' +
          '<div class="field-row">' +
            '<label class="field-label">Menü-Bezeichnung *</label>' +
            '<input class="field-input" id="navex-label-' + hi + '" value="' + escAttr(hp.label || '') + '" placeholder="z.B. Jagdrecht">' +
          '</div>' +
          '<div class="field-row"><label class="field-label">Unterseiten in diesem Punkt</label></div>';

        seiten.forEach(function(s, si) {
          html += '<div class="list-item" style="display:flex;gap:.5rem;margin-bottom:.5rem;align-items:center;">' +
            '<input class="field-input" style="flex:1" id="navex-slug-' + hi + '-' + si + '" value="' + escAttr(s.slug || '') + '" placeholder="URL-Kürzel (Slug)">' +
            '<input class="field-input" style="flex:1" id="navex-nav-' + hi + '-' + si + '" value="' + escAttr(s.nav_label || '') + '" placeholder="Menü-Bezeichnung">' +
            '<button class="btn btn-sm btn-ghost" onclick="navExtraSeiteDelete(' + hi + ',' + si + ')">✕</button>' +
          '</div>';
        });

        html += '<button class="list-add-btn" onclick="navExtraSeiteAdd(' + hi + ')">+ Seite hinzufügen</button>' +
          '<p class="field-hint" style="margin-top:.5rem;">Der Slug muss mit einer Seite in „Weitere Themen" oder einer anderen Unterseite übereinstimmen.</p>' +
        '</div>';
      });
    }

    html += '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  window.navExtraNeu = function() {
    S.data.hauptpunkte = S.data.hauptpunkte || [];
    S.data.hauptpunkte.push({ label: '', seiten: [] });
    renderNavExtra(S.section, S.data);
  };

  window.navExtraDelete = function(hi) {
    showConfirm('Navigationspunkt entfernen', 'Diesen Navigationspunkt wirklich entfernen?', function() {
      S.data.hauptpunkte.splice(hi, 1);
      renderNavExtra(S.section, S.data);
    });
  };

  window.navExtraSeiteAdd = function(hi) {
    S.data.hauptpunkte[hi].seiten = S.data.hauptpunkte[hi].seiten || [];
    S.data.hauptpunkte[hi].seiten.push({ slug: '', nav_label: '', veroeffentlicht: true, in_navigation: true });
    renderNavExtra(S.section, S.data);
  };

  window.navExtraSeiteDelete = function(hi, si) {
    S.data.hauptpunkte[hi].seiten.splice(si, 1);
    renderNavExtra(S.section, S.data);
  };

  function collectNavExtra(data) {
    var punkte = data.hauptpunkte || [];
    punkte.forEach(function(hp, hi) {
      var labelEl = id('navex-label-' + hi);
      if (labelEl) hp.label = labelEl.value.trim();
      (hp.seiten || []).forEach(function(s, si) {
        var slugEl = id('navex-slug-' + hi + '-' + si);
        var navEl  = id('navex-nav-' + hi + '-' + si);
        if (slugEl) s.slug = makeSlug(slugEl.value.trim()) || slugEl.value.trim();
        if (navEl)  s.nav_label = navEl.value.trim();
      });
      // Remove empty seiten
      hp.seiten = (hp.seiten || []).filter(function(s) { return s.slug; });
    });
    // Remove punkten without label
    data.hauptpunkte = punkte.filter(function(hp) { return hp.label; });
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     NAV REIHENFOLGE & EINSTELLUNGEN
  ──────────────────────────────────────────────────────────── */
  function renderNavReihenfolge(def, data) {
    var sn  = data.sektionsnamen || {};
    var hm  = data.hauptmenu    || ['startseite','jaeger','verbraucher','termine','aktuelles','faq','kontakt'];
    var jd  = data.jaeger_dropdown || ['ueber-uns','kreisjjaegermeister','kjs-segeberg','aufgaben','infomobil','weitere-themen'];
    var kjs = data.kjs          || [];
    var auf = data.aufgaben     || [];
    var vbr = data.verbraucher  || [];

    var HM_LABELS = { startseite:'Startseite', jaeger:'Jäger', verbraucher:'Verbraucher',
                      termine:'Termine', aktuelles:'Aktuelles', faq:'FAQ', kontakt:'Kontakt' };
    var JD_LABELS = {
      'ueber-uns':           'Über uns',
      'kreisjjaegermeister': 'Kreisjägermeister',
      'kjs-segeberg':        'KJS Segeberg (Untermenü →)',
      'aufgaben':            'Aufgaben der Kreisjägerschaft (Untermenü →)',
      'infomobil':           'Infomobil',
      'weitere-themen':      'Weitere Themen (Untermenü →)'
    };

    function sortableList(listId, items, labelFn) {
      var rows = items.map(function(item, i) {
        return '<div class="navreo-item" data-idx="' + i + '">' +
          '<span class="navreo-handle" title="Verschieben">⠿</span>' +
          '<span class="navreo-label">' + escHtml(labelFn ? labelFn(item) : (item.label || item)) + '</span>' +
          '<input type="hidden" class="navreo-href" value="' + escAttr(item.href || item || '') + '">' +
          '<input type="hidden" class="navreo-key"  value="' + escAttr(item || '') + '">' +
        '</div>';
      }).join('');
      return '<div class="navreo-list" id="' + listId + '">' + rows + '</div>';
    }

    var html = panelHeader('🔀 Navigation & Reihenfolge') +
      '<div class="panel-body">' +

      // ── Sektionsnamen (FEATURE 2) ──────────────────────────
      '<div class="form-card">' +
        '<div class="form-card-title">🏷️ Sektionsnamen</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Hier können Sie die Bezeichnungen der Navigationspunkte anpassen. Die Änderungen erscheinen sofort auf der Website.</p>' +
        '<div class="navreo-names">' +
          '<div class="field-row">' +
            '<label class="field-label" for="sn-jaeger">Hauptpunkt „Jäger"</label>' +
            '<input class="field-input" id="sn-jaeger" value="' + escAttr(sn.jaeger || 'Jäger') + '">' +
          '</div>' +
          '<div class="field-row">' +
            '<label class="field-label" for="sn-kjs">Untergruppe „KJS Segeberg"</label>' +
            '<input class="field-input" id="sn-kjs" value="' + escAttr(sn.kjs || 'KJS Segeberg') + '">' +
          '</div>' +
          '<div class="field-row">' +
            '<label class="field-label" for="sn-aufgaben">Untergruppe „Aufgaben"</label>' +
            '<input class="field-input" id="sn-aufgaben" value="' + escAttr(sn.aufgaben || 'Aufgaben der Kreisjägerschaft') + '">' +
          '</div>' +
          '<div class="field-row">' +
            '<label class="field-label" for="sn-verbraucher">Hauptpunkt „Verbraucher"</label>' +
            '<input class="field-input" id="sn-verbraucher" value="' + escAttr(sn.verbraucher || 'Verbraucher') + '">' +
          '</div>' +
        '</div>' +
      '</div>' +

      // ── Hauptmenü-Reihenfolge (FEATURE 3) ─────────────────
      '<div class="form-card">' +
        '<div class="form-card-title">📋 Hauptmenü-Reihenfolge</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Ziehen Sie die Hauptpunkte in die gewünschte Reihenfolge.</p>' +
        sortableList('navreo-hauptmenu', hm, function(k) { return HM_LABELS[k] || k; }) +
      '</div>' +

      // ── Jäger-Dropdown Hauptpunkte ────────────────────────
      '<div class="form-card">' +
        '<div class="form-card-title">🦌 Jäger-Dropdown – Reihenfolge der Hauptpunkte</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Reihenfolge der Punkte im Jäger-Dropdown-Menü.</p>' +
        sortableList('navreo-jaegerdropdown', jd, function(k) { return JD_LABELS[k] || k; }) +
      '</div>' +

      // ── KJS-Unterseiten-Reihenfolge (FEATURE 1) ───────────
      '<div class="form-card">' +
        '<div class="form-card-title">🦌 KJS Segeberg – Unterseiten-Reihenfolge</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Reihenfolge der Seiten im Dropdown „KJS Segeberg".</p>' +
        sortableList('navreo-kjs', kjs, function(item) { return item.label; }) +
      '</div>' +

      // ── Aufgaben-Unterseiten-Reihenfolge ──────────────────
      '<div class="form-card">' +
        '<div class="form-card-title">🎯 Aufgaben – Unterseiten-Reihenfolge</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Reihenfolge der Seiten im Dropdown „Aufgaben".</p>' +
        sortableList('navreo-aufgaben', auf, function(item) { return item.label; }) +
      '</div>' +

      // ── Verbraucher-Unterseiten-Reihenfolge ───────────────
      '<div class="form-card">' +
        '<div class="form-card-title">🌿 Verbraucher – Unterseiten-Reihenfolge</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;">Reihenfolge der Seiten im Dropdown „Verbraucher".</p>' +
        sortableList('navreo-verbraucher', vbr, function(item) { return item.label; }) +
      '</div>' +

      '</div>' + saveBar();

    id('admin-main').innerHTML = html;
    bindSaveBtn();

    // Initialize Sortable.js on each list
    var lists = ['navreo-hauptmenu','navreo-jaegerdropdown','navreo-kjs','navreo-aufgaben','navreo-verbraucher'];
    lists.forEach(function(listId) {
      var el = id(listId);
      if (el && window.Sortable) {
        Sortable.create(el, {
          handle: '.navreo-handle',
          animation: 150,
          onEnd: function() { S.dirty = true; }
        });
      }
    });
  }

  function collectNavReihenfolge(data) {
    // Section names
    data.sektionsnamen = data.sektionsnamen || {};
    data.sektionsnamen.jaeger      = (id('sn-jaeger')      && id('sn-jaeger').value.trim())     || data.sektionsnamen.jaeger      || 'Jäger';
    data.sektionsnamen.kjs         = (id('sn-kjs')         && id('sn-kjs').value.trim())        || data.sektionsnamen.kjs         || 'KJS Segeberg';
    data.sektionsnamen.aufgaben    = (id('sn-aufgaben')    && id('sn-aufgaben').value.trim())   || data.sektionsnamen.aufgaben    || 'Aufgaben der Kreisjägerschaft';
    data.sektionsnamen.verbraucher = (id('sn-verbraucher') && id('sn-verbraucher').value.trim())|| data.sektionsnamen.verbraucher || 'Verbraucher';

    // Helper: read sorted keys from a navreo list
    function readOrder(listId, srcArray, keyField) {
      var el = id(listId);
      if (!el) return srcArray;
      var result = [];
      el.querySelectorAll('.navreo-item').forEach(function(item) {
        var keyEl  = item.querySelector('.navreo-key');
        var hrefEl = item.querySelector('.navreo-href');
        var labelEl = item.querySelector('.navreo-label');
        if (keyField === 'key') {
          if (keyEl) result.push(keyEl.value);
        } else {
          // object with label + href
          result.push({
            label: labelEl ? labelEl.textContent : '',
            href:  hrefEl  ? hrefEl.value : ''
          });
        }
      });
      return result.length ? result : srcArray;
    }

    data.hauptmenu      = readOrder('navreo-hauptmenu',      data.hauptmenu      || [], 'key');
    data.jaeger_dropdown= readOrder('navreo-jaegerdropdown', data.jaeger_dropdown|| [], 'key');
    data.kjs            = readOrder('navreo-kjs',            data.kjs            || [], 'href');
    data.aufgaben   = readOrder('navreo-aufgaben',   data.aufgaben   || [], 'href');
    data.verbraucher= readOrder('navreo-verbraucher',data.verbraucher|| [], 'href');

    return data;
  }

  /* ────────────────────────────────────────────────────────────
     BENUTZERVERWALTUNG
  ──────────────────────────────────────────────────────────── */
  function renderBenutzer() {
    var main = id('admin-main');
    main.innerHTML =
      panelHeader('👥 Benutzerverwaltung') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Neuen Benutzer einladen</div>' +
          '<p class="text-muted" style="margin-bottom:1rem;">Der Benutzer erhält eine Einladungs-E-Mail und kann sich dann anmelden.</p>' +
          '<div class="field-row">' +
            '<label class="field-label" for="bu-email">E-Mail-Adresse</label>' +
            '<input class="field-input" type="email" id="bu-email" placeholder="max@example.de">' +
          '</div>' +
          '<div class="form-actions">' +
            '<button class="btn btn-primary" onclick="benutzerInvite()">✉️ Einladen</button>' +
          '</div>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Aktuelle Benutzer</div>' +
          '<div id="bu-list"><div class="gallery-loading">Wird geladen…</div></div>' +
        '</div>' +
        '<div class="form-card" style="border-color:#f0ad4e;background:#fffbf0;">' +
          '<div class="form-card-title" style="color:#856404;">ℹ️ Admin-Berechtigung vergeben</div>' +
          '<p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.5rem;">Damit ein Benutzer Inhalte bearbeiten darf, muss er im Netlify-Dashboard die Rolle <strong>admin</strong> erhalten:</p>' +
          '<ol style="font-size:.85rem;color:var(--text-muted);padding-left:1.4rem;margin:0;">' +
            '<li>Netlify-Dashboard → <strong>Identity</strong></li>' +
            '<li>Benutzer auswählen → <strong>Edit</strong></li>' +
            '<li>Unter <em>Roles</em>: <code>admin</code> eintragen</li>' +
            '<li>Speichern</li>' +
          '</ol>' +
        '</div>' +
      '</div>';
    benutzerLoad();
  }

  window.benutzerLoad = benutzerLoad;
  async function benutzerLoad() {
    var list = id('bu-list');
    if (!list) return;
    try {
      // forceRefresh=true: ein zuvor ausgestelltes Token kennt eine neu vergebene
      // "admin"-Rolle in Netlify Identity nicht – erst ein frisches Token (vom
      // Identity-Server neu ausgestellt) enthält die aktuellen Rollen. Ohne dies
      // bleibt der Aufruf dauerhaft auf HTTP 401, selbst nachdem die Rolle im
      // Netlify-Dashboard korrekt gesetzt wurde.
      var tok = await getToken(true);
      var r = await fetch('/.netlify/identity/admin/users?per_page=100', {
        headers: { 'Authorization': 'Bearer ' + tok }
      });
      if (!r.ok) {
        // Rohen Fehlercode + Antwort in der Konsole protokollieren, damit sich
        // das Problem im Browser (F12 → Konsole) genau nachvollziehen lässt.
        var rawBody = await r.text().catch(function() { return ''; });
        console.error('[Benutzerverwaltung] GET /.netlify/identity/admin/users → HTTP ' + r.status, rawBody);
        // Server-Antwort (z.B. {"code":401,"msg":"..."}) für die Anzeige im UI
        // extrahieren, damit man den genauen Grund auch ohne Konsole sieht.
        var serverMsg = '';
        try { serverMsg = JSON.parse(rawBody).msg || ''; } catch(_e) { serverMsg = rawBody; }
        var detail = serverMsg ? ' Server-Antwort: "' + serverMsg + '"' : '';
        // Token-Inhalt zur Diagnose dekodieren (nur lokal, ohne Server-Anfrage):
        // zeigt, welche E-Mail/Rollen das tatsächlich ausgestellte Token enthält.
        var payload = decodeJwtPayload(tok);
        var tokenInfo = '';
        if (payload) {
          var pRoles = (payload.app_metadata && payload.app_metadata.roles) || [];
          tokenInfo = ' [Token-Inhalt: email=' + (payload.email || '?') +
            ', app_metadata.roles=' + JSON.stringify(pRoles) +
            ', exp=' + (payload.exp ? new Date(payload.exp * 1000).toLocaleString('de-DE') : '?') + ']';
        } else {
          tokenInfo = ' [Token konnte nicht dekodiert werden]';
        }
        if (r.status === 403) {
          throw new Error('HTTP 403 – Keine Admin-Berechtigung. Dein Konto hat (noch) nicht die Rolle "admin" ' +
            'in Netlify Identity. Bitte wende dich an den Netlify-Administrator: Die Rolle "admin" muss im ' +
            'Netlify-Dashboard unter Identity → Benutzer → Edit → Roles manuell vergeben werden – das ist ' +
            'per Code nicht möglich.' + detail + tokenInfo);
        }
        if (r.status === 401) {
          throw new Error('HTTP 401 – Anmeldung ungültig/abgelaufen ODER Rolle "admin" fehlt auf dem Zugriffstoken. ' +
            'Bitte einmal komplett ausloggen und wieder einloggen, damit ein neues Zugriffstoken ausgestellt wird. ' +
            'Falls die Rolle "admin" gerade erst vergeben wurde, kann es einige Minuten dauern, bis sie wirksam wird.' + detail + tokenInfo);
        }
        throw new Error('HTTP ' + r.status + ' beim Laden der Benutzerliste.' + detail + tokenInfo);
      }
      var d = await r.json();
      var users = d.users || [];
      if (!users.length) {
        list.innerHTML = '<p style="color:var(--text-muted);">Keine Benutzer gefunden.</p>';
        return;
      }
      list.innerHTML = users.map(function(u) {
        var uname = (u.user_metadata && u.user_metadata.full_name) || '';
        var roles = (u.app_metadata && u.app_metadata.roles) || [];
        return '<div class="bu-user-row">' +
          '<div class="bu-user-info">' +
            '<strong>' + escHtml(u.email) + '</strong>' +
            (uname ? ' <span class="bu-user-name">(' + escHtml(uname) + ')</span>' : '') +
            (roles.length ? ' <span class="bu-badge">' + escHtml(roles.join(', ')) + '</span>' : '') +
          '</div>' +
          '<button class="btn btn-sm btn-danger" onclick="benutzerRemove(\'' + escAttr(u.id) + '\',\'' + escAttr(u.email) + '\')">🗑️ Entfernen</button>' +
        '</div>';
      }).join('');
    } catch(e) {
      console.error('[Benutzerverwaltung] Fehler beim Laden der Benutzerliste:', e);
      list.innerHTML = '<p style="color:var(--danger);">❌ ' + escHtml(e.message) + '</p>' +
        '<p class="mt-1"><button class="btn btn-outline btn-sm" onclick="benutzerLoad()">🔄 Erneut versuchen</button> ' +
        '<button class="btn btn-outline btn-sm" onclick="netlifyIdentity.logout()">🚪 Aus- &amp; wieder einloggen</button></p>';
    }
  }

  window.benutzerInvite = async function() {
    var emailEl = id('bu-email');
    var email = emailEl ? emailEl.value.trim() : '';
    if (!email) { toast('❌ Bitte E-Mail-Adresse eingeben', true); return; }
    try {
      var tok = await getToken(true);
      var r = await fetch('/.netlify/identity/admin/users', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, role: 'admin' })
      });
      if (!r.ok) {
        var err = await r.json().catch(function() { return {}; });
        console.error('[Benutzerverwaltung] POST /.netlify/identity/admin/users → HTTP ' + r.status, err);
        if (r.status === 403) {
          throw new Error('Keine Admin-Berechtigung. Bitte wende dich an den Netlify-Administrator.');
        }
        if (r.status === 401) {
          throw new Error('Anmeldung ungültig oder abgelaufen. Bitte einmal aus- und wieder einloggen.');
        }
        throw new Error(err.msg || err.message || 'HTTP ' + r.status);
      }
      toast('✅ Einladung gesendet an ' + email);
      emailEl.value = '';
      benutzerLoad();
    } catch(e) {
      console.error('[Benutzerverwaltung] Fehler beim Einladen:', e);
      toast('❌ ' + e.message, true);
    }
  };

  window.benutzerRemove = async function(uid, email) {
    if (!confirm('Benutzer ' + email + ' wirklich entfernen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
    try {
      var tok = await getToken(true);
      var r = await fetch('/.netlify/identity/admin/users/' + uid, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + tok }
      });
      if (!r.ok) {
        var err = await r.json().catch(function() { return {}; });
        console.error('[Benutzerverwaltung] DELETE /.netlify/identity/admin/users/' + uid + ' → HTTP ' + r.status, err);
        if (r.status === 403) {
          throw new Error('Keine Admin-Berechtigung. Bitte wende dich an den Netlify-Administrator.');
        }
        if (r.status === 401) {
          throw new Error('Anmeldung ungültig oder abgelaufen. Bitte einmal aus- und wieder einloggen.');
        }
        throw new Error(err.msg || err.message || 'HTTP ' + r.status);
      }
      toast('✅ Benutzer entfernt');
      benutzerLoad();
    } catch(e) {
      console.error('[Benutzerverwaltung] Fehler beim Entfernen:', e);
      toast('❌ ' + e.message, true);
    }
  };

  /* ────────────────────────────────────────────────────────────
     NEUE SEITE
  ──────────────────────────────────────────────────────────── */
  function renderNeueSeite(def) {
    var html = panelHeader('➕ Neue Seite erstellen') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Neue Unterseite</div>' +
          '<p class="text-muted" style="margin-bottom:1.25rem;">Nach dem Speichern erscheint die Seite automatisch im Menü.</p>' +
          fText('ns-titel', 'Seitentitel *', '', 'z.B. Jagdrecht') +
          '<div class="field-row">' +
            '<label class="field-label">URL-Kürzel (Slug)</label>' +
            '<input class="field-input" type="text" id="f-ns-slug" placeholder="wird aus dem Titel generiert">' +
            '<p class="field-hint">Nur Kleinbuchstaben und Bindestriche. Wird automatisch aus dem Titel erzeugt.</p>' +
          '</div>' +
          fText('ns-nav_label', 'Menü-Bezeichnung', '', 'z.B. Jagdrecht') +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Inhalt</div>' +
          fTextarea('ns-intro', 'Einleitungstext', '', 2) +
          '<div class="field-row">' +
            '<label class="field-label">Textinhalt (Markdown)</label>' +
            '<div id="te-bar-ns-inhalt" class="te-bar"></div>' +
            '<textarea class="field-textarea" id="f-ns-inhalt" rows="8"></textarea>' +
            '<div id="te-content-ns-inhalt" style="display:none"></div>' +
          '</div>' +
          fImage('ns-hero_bild', 'Hero-Hintergrundbild', '') +
          fImage('ns-bild', 'Inhaltsbild', '') +
          fText('ns-kontakt_name', 'Kontaktname (optional)', '') +
          fText('ns-kontakt_email', 'Kontakt-E-Mail (optional)', '') +
          fToggle('ns-veroeffentlicht', 'Direkt veröffentlichen?', true) +
        '</div>' +
        // Jagdhundeschule-spezifisch: Kachel-Vorschau
        (def.navFile && def.navFile.indexOf('hundeausbildung-seiten') !== -1
          ? '<div class="form-card">' +
              '<div class="form-card-title">🐕 Kachel-Vorschau</div>' +
              '<p class="text-muted" style="margin-bottom:.75rem;font-size:.85rem;">Optional — erscheint in der Jagdhundeschule-Übersicht als Bildkachel.</p>' +
              fImage('ns-vorschaubild', 'Vorschaubild', '') +
              fText('ns-kurzbeschreibung', 'Kurzbeschreibung', '', 'Ein Satz, der die Seite beschreibt …') +
              fText('ns-gruppe', 'Gruppe (optional)', '', 'z.B. Kurse 1–6 oder VGP/VPS') +
            '</div>'
          : '') +
        '<div style="padding:0 1.5rem 1.5rem">' +
          '<button class="btn btn-primary btn-lg" onclick="neueSeiteSpeedSave()">💾 Seite erstellen & speichern</button>' +
        '</div>' +
      '</div>';
    id('admin-main').innerHTML = html;
    initMDE('ns-inhalt');
    initTableEditor('ns-inhalt', '');

    // Auto-generate slug from title
    var titelEl = id('f-ns-titel');
    var slugEl  = id('f-ns-slug');
    if (titelEl && slugEl) {
      titelEl.addEventListener('input', function() {
        slugEl.value = makeSlug(titelEl.value);
        var navEl = id('f-ns-nav_label');
        if (navEl && !navEl.value) navEl.value = titelEl.value;
      });
    }
  }

  window.neueSeiteSpeedSave = async function() {
    var def   = S.section;
    var titel = gv('ns-titel').trim();
    var slug  = gv('ns-slug').trim() || makeSlug(titel);
    var navLabel = gv('ns-nav_label').trim() || titel;

    if (!titel) { toast('⚠️ Bitte Seitentitel eingeben', 'err'); return; }
    if (!slug)  { toast('⚠️ Bitte URL-Kürzel eingeben', 'err'); return; }

    var newData = {
      titel:          titel,
      nav_label:      navLabel,
      slug:           slug,
      intro:          gv('ns-intro'),
      inhalt:         getMDE(),
      hero_bild:      gv('ns-hero_bild'),
      bild:           gv('ns-bild'),
      bild_alt:       '',
      kontakt_name:   gv('ns-kontakt_name'),
      kontakt_email:  gv('ns-kontakt_email'),
      in_navigation:  true,
      veroeffentlicht: toggleVal('ns-veroeffentlicht'),
    };

    var contentPath = def.dir + '/' + slug + '.json';

    setSaving(true);
    try {
      // 1. Create content file
      await apiPut(contentPath, newData, null, '➕ Neue Seite erstellt: ' + titel);

      // 2. Update nav manifest
      var manifestResp = await apiGet(def.navFile);
      var manifestData = JSON.parse(fromBase64(manifestResp.content));
      manifestData[def.navKey] = manifestData[def.navKey] || [];
      var manifestEntry = { slug: slug, nav_label: navLabel, in_navigation: true, veroeffentlicht: true };
      // Jagdhundeschule: Kachel-Vorschaufelder in das Manifest schreiben
      if (def.navFile && def.navFile.indexOf('hundeausbildung-seiten') !== -1) {
        manifestEntry.vorschaubild     = gv('ns-vorschaubild') || '';
        manifestEntry.kurzbeschreibung = gv('ns-kurzbeschreibung') || '';
        manifestEntry.gruppe           = gv('ns-gruppe') || '';
      }
      manifestData[def.navKey].push(manifestEntry);
      await apiPut(def.navFile, manifestData, manifestResp.sha, '➕ Navigation aktualisiert: ' + titel);

      toast('✅ Seite erstellt! Sie erscheint nach einem Reload im Menü.', 'ok');
      setSaving(false);

      // Reload sidebar to show new item
      setTimeout(function() { location.reload(); }, 2000);
    } catch(e) {
      setSaving(false);
      toast('❌ Fehler: ' + e.message, 'err');
    }
  };

  /* ────────────────────────────────────────────────────────────
     SAVE LOGIC
  ──────────────────────────────────────────────────────────── */
  function bindSaveBtn() {
    document.querySelectorAll('[data-save]').forEach(function(btn) {
      btn.addEventListener('click', saveCurrentSection);
    });
  }

  window.saveCurrentSection = async function() {
    var def = S.section;
    if (!def || !def.file) return;

    // Collect data
    var data = JSON.parse(JSON.stringify(S.data)); // deep copy
    switch(def.form) {
      case 'standard':      data = collectStandard(data);   break;
      case 'tiptap':        data = collectInfomobil(data);  break;
      case 'startseite':    data = collectStartseite(data); break;
      case 'kjm':           data = collectKJM(data); break;
      case 'faq':           data = collectFAQ(data); break;
      case 'einstellungen': data = collectEinstellungen(data); break;
      case 'footer':        data = collectFooter(data); break;
      case 'design':        data = collectDesign(data); break;
      case 'impressum':     data = collectImpressum(data); break;
      case 'downloads':     data = collectDownloads(data); break;
      case 'navExtra':        data = collectNavExtra(data); break;
      case 'navReihenfolge':  data = collectNavReihenfolge(data); break;
    }

    // Universeller "Dokumente & Downloads"-Bereich: falls auf dieser Seite
    // vorhanden, Liste unabhängig vom Formular-Typ mit speichern.
    if (id('downloads-list')) {
      data.downloads = collectDownloadsList();
    }

    setSaving(true);
    try {
      var result = await doSave(def.file, data, '💾 ' + def.label + ' gespeichert');
      S.data = data;
      setSaving(false);
      toast('✅ Gespeichert! Änderungen erscheinen auf der Website in max. 5 Minuten.', 'ok');
      setStatus('✅ Gespeichert');
      S.dirty = false;
    } catch(e) {
      setSaving(false);
      toast('❌ ' + e.message, 'err');
      setStatus('❌ Fehler: ' + e.message);
    }
  };

  async function doSave(filePath, data, message) {
    // Fetch SHA with cache-busting to avoid git-gateway stale-cache 409s
    async function fetchFreshSha() {
      var tok = await getToken();
      var r = await fetch(GIT + '/' + filePath + '?ref=' + BRANCH + '&_=' + Date.now(), {
        headers: {
          'Authorization': 'Bearer ' + tok,
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      if (!r.ok) return null;
      var d = await r.json();
      return d.sha || null;
    }

    var sha = await fetchFreshSha().catch(function() { return S.sha || null; });
    try {
      var result = await apiPut(filePath, data, sha, message);
      if (result && result.content && result.content.sha) {
        S.sha = result.content.sha;
      }
      return result;
    } catch(e) {
      // 409 = SHA still stale; wait briefly, re-fetch, retry once
      if (e.message && e.message.indexOf('409') !== -1) {
        await new Promise(function(res) { setTimeout(res, 600); });
        var retrySha = await fetchFreshSha().catch(function() { return null; });
        if (!retrySha) throw e;
        var result2 = await apiPut(filePath, data, retrySha, message);
        if (result2 && result2.content && result2.content.sha) {
          S.sha = result2.content.sha;
        }
        return result2;
      }
      throw e;
    }
  }

  function setSaving(saving) {
    document.querySelectorAll('[data-save]').forEach(function(btn) {
      btn.disabled = saving;
      btn.textContent = saving ? '⏳ Wird gespeichert…' : '💾 Speichern';
    });
  }

  function setStatus(msg) {
    var el = id('save-status');
    if (el) el.textContent = msg;
  }

  /* ────────────────────────────────────────────────────────────
     IMAGE PICKER
  ──────────────────────────────────────────────────────────── */
  window.openImgPicker = function(fieldId) {
    S.imgTarget = fieldId;
    id('img-modal').style.display = 'flex';
    loadGallery();
  };

  window.clearImg = function(fieldId) {
    var el = id('f-' + fieldId);
    var prev = id('prev-' + fieldId);
    if (el) el.value = '';
    if (prev) { prev.innerHTML = ''; prev.classList.add('empty'); }
  };

  function closeImgModal() {
    id('img-modal').style.display = 'none';
  }

  async function loadGallery() {
    var gallery = id('img-gallery');
    gallery.innerHTML = '<div class="gallery-loading">Bilder werden geladen…</div>';
    try {
      var files = await apiGetDir('images');
      var imgs = files.filter(function(f) {
        return f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
      });
      if (imgs.length === 0) {
        gallery.innerHTML = '<div class="gallery-loading">Noch keine Bilder vorhanden. Laden Sie ein Bild hoch.</div>';
        return;
      }
      gallery.innerHTML = imgs.map(function(f) {
        var url = '/images/' + f.name;
        return '<div class="gallery-img-wrap">' +
          '<img class="gallery-img" src="' + escAttr(url) + '" alt="' + escAttr(f.name) + '" ' +
            'onclick="pickImg(\'' + escAttr(url) + '\')" loading="lazy">' +
          '<div class="gallery-img-name">' + escHtml(f.name) + '</div>' +
        '</div>';
      }).join('');
    } catch(e) {
      gallery.innerHTML = '<div class="gallery-loading">Fehler: ' + escHtml(e.message) + '</div>';
    }
  }

  window.pickImg = function(url) {
    var target = S.imgTarget;
    if (!target) return;
    var el   = id('f-' + target);
    var prev = id('prev-' + target);
    if (el)   el.value = url;
    if (prev) {
      prev.classList.remove('empty');
      prev.innerHTML = '<img src="' + escAttr(url) + '" alt="">';
    }
    closeImgModal();
  };

  function initImageUpload() {
    var input = id('img-upload-input');
    if (!input) return;
    input.addEventListener('change', async function() {
      var file = input.files[0];
      if (!file) return;
      var status = id('img-upload-status');
      status.textContent = '⏳ Wird hochgeladen…';
      try {
        var b64 = await fileToBase64(file);
        var url = await apiUploadImage(file.name, b64);
        status.textContent = '✅ Hochgeladen';
        await loadGallery();
        // Auto-select the just uploaded image
        pickImg(url);
      } catch(e) {
        status.textContent = '❌ ' + e.message;
      }
    });
  }

  function fileToBase64(file) {
    return new Promise(function(resolve, reject) {
      var reader = new FileReader();
      reader.onload = function() {
        resolve(reader.result.split(',')[1]);
      };
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  /* ────────────────────────────────────────────────────────────
     MARKDOWN-BILD-EINFÜGEN (mit visueller Positionierung)
  ──────────────────────────────────────────────────────────── */
  var _mdImgSelected = null; // { url, name }
  // aktuelle Position: Größe, horizontale/vertikale Position, Textumfluss
  var _mdImgPos = { size: 'img-mittel', hpos: 'img-links', vpos: 'img-pos-oben', flow: 'img-flow' };

  function openMdImageModal() {
    if (!S.mde) { toast('❌ Editor nicht bereit', 'err'); return; }
    _mdImgSelected = null;
    var opts = id('mdimg-options');
    if (opts) opts.style.display = 'none';
    var insertBtn = id('mdimg-insert'); var insertBtnTop = id('mdimg-insert-top');
    if (insertBtn) insertBtn.disabled = true; if (insertBtnTop) insertBtnTop.disabled = true;
    var alt = id('mdimg-alt');
    if (alt) alt.value = '';
    // Reset auf Standard-Position (oben links, mittel, mit Textumfluss)
    _mdImgPos = { size: 'img-mittel', hpos: 'img-links', vpos: 'img-pos-oben', flow: 'img-flow' };
    mdImgSyncButtons();
    var preview = id('mdimg-preview-wrap');
    if (preview) preview.style.display = 'none';

    id('mdimg-modal').style.display = 'flex';
    loadMdImgGallery();
  }

  // Größen-Button geklickt
  window.mdImgSetSize = function(btn) {
    _mdImgPos.size = btn.getAttribute('data-size') || 'img-mittel';
    mdImgSyncButtons();
    updateMdImgPreview();
  };

  // Positions-Raster-Button geklickt (3x3-Raster: horizontale + vertikale Position)
  window.mdImgSetGrid = function(btn) {
    _mdImgPos.hpos = btn.getAttribute('data-h') || 'img-links';
    _mdImgPos.vpos = btn.getAttribute('data-v') || 'img-pos-oben';
    // Zentrierte Bilder können nicht vom Text umflossen werden
    if (_mdImgPos.hpos === 'img-zentriert' && _mdImgPos.flow === 'img-flow') {
      _mdImgPos.flow = 'img-block';
    }
    mdImgSyncButtons();
    updateMdImgPreview();
  };

  // Textumfluss-Button geklickt
  window.mdImgSetFlow = function(btn) {
    var flow = btn.getAttribute('data-flow') || 'img-block';
    if (flow === 'img-flow' && _mdImgPos.hpos === 'img-zentriert') return; // nicht möglich
    _mdImgPos.flow = flow;
    mdImgSyncButtons();
    updateMdImgPreview();
  };

  // Sprechende Beschriftung für die aktuelle Raster-Position
  function mdImgPosLabel(hpos, vpos) {
    if (hpos === 'img-zentriert' && vpos === 'img-pos-mitte') return 'Zentriert';
    var h = hpos === 'img-links' ? 'links' : (hpos === 'img-rechts' ? 'rechts' : 'Mitte');
    var v = vpos === 'img-pos-oben' ? 'Oben' : (vpos === 'img-pos-unten' ? 'Unten' : 'Mitte');
    return v + ' ' + h;
  }

  // Alle Buttons/Beschriftungen im Dialog mit dem aktuellen Zustand abgleichen
  function mdImgSyncButtons() {
    document.querySelectorAll('#mdimg-size-options [data-size]').forEach(function(b) {
      b.classList.toggle('mdimg-size-btn--active', b.getAttribute('data-size') === _mdImgPos.size);
    });
    document.querySelectorAll('#mdimg-grid-options [data-h]').forEach(function(b) {
      b.classList.toggle('mdimg-grid-btn--active',
        b.getAttribute('data-h') === _mdImgPos.hpos && b.getAttribute('data-v') === _mdImgPos.vpos);
    });
    var label = id('mdimg-grid-label');
    if (label) label.textContent = 'Position: ' + mdImgPosLabel(_mdImgPos.hpos, _mdImgPos.vpos);

    var isCenter = _mdImgPos.hpos === 'img-zentriert';
    document.querySelectorAll('#mdimg-flow-options [data-flow]').forEach(function(b) {
      var flow = b.getAttribute('data-flow');
      b.classList.toggle('mdimg-flow-btn--active', flow === _mdImgPos.flow);
      b.classList.toggle('mdimg-flow-btn--disabled', flow === 'img-flow' && isCenter);
    });
    var hint = id('mdimg-flow-hint');
    if (hint) hint.style.display = isCenter ? '' : 'none';
  }

  function updateMdImgPreview() {
    var wrap = id('mdimg-preview-wrap');
    var prev = id('mdimg-preview');
    if (!prev) return;
    if (!_mdImgSelected) { if (wrap) wrap.style.display = 'none'; return; }
    if (wrap) wrap.style.display = '';

    var size = _mdImgPos.size, hpos = _mdImgPos.hpos, vpos = _mdImgPos.vpos, flow = _mdImgPos.flow;

    // Vorschau-Stile ableiten (kompakter als auf der echten Seite, max. 120px hoch)
    var imgStyle = 'border-radius:4px;height:auto;max-height:120px;';
    var marginTop = vpos === 'img-pos-mitte' ? '18px' : (vpos === 'img-pos-unten' ? '36px' : '0');

    if (flow === 'img-flow' && hpos !== 'img-zentriert') {
      imgStyle += 'margin-top:' + marginTop + ';';
      if (hpos === 'img-links') imgStyle += 'float:left;margin-right:12px;margin-bottom:6px;';
      else                       imgStyle += 'float:right;margin-left:12px;margin-bottom:6px;';
    } else {
      imgStyle += 'display:block;margin-top:' + marginTop + ';margin-bottom:6px;';
      if (hpos === 'img-links')       imgStyle += 'margin-right:auto;';
      else if (hpos === 'img-rechts') imgStyle += 'margin-left:auto;';
      else                              imgStyle += 'margin-left:auto;margin-right:auto;';
    }

    if (size === 'img-voll') {
      imgStyle += 'width:100%;max-height:none;';
    } else if (size === 'img-gross') {
      imgStyle += 'max-width:75%;';
    } else if (size === 'img-klein') {
      imgStyle += 'max-width:25%;';
    } else {
      imgStyle += 'max-width:50%;';
    }

    prev.innerHTML =
      '<div style="overflow:hidden;">' +
        '<img src="' + _mdImgSelected.url + '" style="' + imgStyle + '" alt="Vorschau">' +
        '<div style="font-size:.78rem;color:#aaa;line-height:1.5;padding-top:2px;">' +
          'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' +
          'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ' +
          'Ut enim ad minim veniam, quis nostrud exercitation ullamco.' +
        '</div>' +
        '<div style="clear:both"></div>' +
      '</div>';
  }

  function closeMdImageModal() {
    id('mdimg-modal').style.display = 'none';
    id('mdimg-modal').classList.remove('mdimg-swap-mode');
    _mdLive.swapIndex = -1;
    S._tiptapImageField = null;
  }

  async function loadMdImgGallery() {
    var gallery = id('mdimg-gallery');
    if (!gallery) return;
    gallery.innerHTML = '<div class="gallery-loading">Bilder werden geladen…</div>';
    try {
      var files = await apiGetDir('images');
      var imgs = files.filter(function(f) {
        return f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
      });
      if (imgs.length === 0) {
        gallery.innerHTML = '<div class="gallery-loading">Noch keine Bilder vorhanden. Laden Sie eines hoch.</div>';
        return;
      }
      gallery.innerHTML = imgs.map(function(f) {
        var url = '/images/' + f.name;
        return '<div class="gallery-img-wrap">' +
          '<img class="gallery-img" id="mdimg-thumb-' + escAttr(url) + '" src="' + escAttr(url) + '" alt="' + escAttr(f.name) + '" ' +
            'onclick="mdImgPick(\'' + escAttr(url) + '\',\'' + escAttr(f.name) + '\')" loading="lazy">' +
          '<div class="gallery-img-name">' + escHtml(f.name) + '</div>' +
        '</div>';
      }).join('');
    } catch(e) {
      gallery.innerHTML = '<div class="gallery-loading">Fehler: ' + escHtml(e.message) + '</div>';
    }
  }

  window.mdImgPick = function(url, name) {
    if (_mdLive.swapIndex !== -1) {
      var swapIdx = _mdLive.swapIndex;
      _mdLive.swapIndex = -1;
      applyMdImgChange(swapIdx, { url: url });
      var modal = id('mdimg-modal');
      modal.style.display = 'none';
      modal.classList.remove('mdimg-swap-mode');
      toast('✅ Bild ausgetauscht', 'ok');
      return;
    }
    _mdImgSelected = { url: url, name: name };
    // Highlight selection
    document.querySelectorAll('#mdimg-gallery .gallery-img').forEach(function(img) {
      img.classList.toggle('gallery-img--selected', img.getAttribute('src') === url);
    });
    var opts = id('mdimg-options');
    if (opts) opts.style.display = '';
    var insertBtn = id('mdimg-insert'); var insertBtnTop = id('mdimg-insert-top');
    if (insertBtn) insertBtn.disabled = false; if (insertBtnTop) insertBtnTop.disabled = false;
    // Prefill alt text with filename (without extension) if empty
    var alt = id('mdimg-alt');
    if (alt && !alt.value && name) {
      alt.value = name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
    }
    // Live-Vorschau aktualisieren
    updateMdImgPreview();
  };

  function initMdImgUpload() {
    var input = id('mdimg-upload-input');
    if (!input) return;
    input.addEventListener('change', async function() {
      var file = input.files[0];
      if (!file) return;
      var status = id('mdimg-upload-status');
      if (status) status.textContent = '⏳ Wird hochgeladen…';
      try {
        var b64 = await fileToBase64(file);
        var url = await apiUploadImage(file.name, b64);
        if (status) status.textContent = '✅ Hochgeladen';
        await loadMdImgGallery();
        mdImgPick(url, file.name);
      } catch(e) {
        if (status) status.textContent = '❌ ' + e.message;
      }
      input.value = '';
    });
  }

  function insertMdImage() {
    if (!_mdImgSelected) return;
    var sizeCls = _mdImgPos.size || 'img-mittel';
    var hposCls = _mdImgPos.hpos || 'img-links';
    var vposCls = _mdImgPos.vpos || 'img-pos-oben';
    var flowCls = _mdImgPos.flow || 'img-flow';
    var altInput = id('mdimg-alt');
    var alt = altInput ? altInput.value.trim() : '';
    if (!alt) alt = _mdImgSelected.name ? _mdImgSelected.name.replace(/\.[^.]+$/, '') : '';

    var classes = [sizeCls, hposCls];
    if (vposCls !== 'img-pos-oben') classes.push(vposCls);
    classes.push(flowCls);

    // TipTap-Bild einfügen (form:'tiptap', z.B. Testseite/Infomobil).
    // Größe/Position des Modals werden auf das neue, schlanke Klassenschema
    // gemappt (img-25/50/75/100 + img-links/img-rechts/img-zentriert).
    // Feinjustierung danach per Klick-Menü direkt am Bild.
    if (S._tiptapImageField) {
      var activeEditor = S.tiptapEditors[S._tiptapImageField];
      if (activeEditor) {
        var TT_SIZE_MAP = { 'img-klein':'img-25', 'img-mittel':'img-50', 'img-gross':'img-75', 'img-voll':'img-100' };
        var ttSize = TT_SIZE_MAP[sizeCls] || 'img-50';
        var ttPos  = (hposCls === 'img-rechts' || hposCls === 'img-zentriert') ? hposCls : 'img-links';

        // Ein Float-Bild darf nie INNERHALB einer Liste sitzen, sonst
        // zerschießt es das Listen-Layout. Steht der Cursor in einer Liste,
        // den Cursor ans Ende der gesamten (äußersten) Liste verschieben,
        // damit das Bild als eigener Block NACH der Liste landet und der
        // nachfolgende Fließtext es sauber umfließt.
        var $from = activeEditor.state.selection.$from;
        var listDepth = -1;
        for (var d = 1; d <= $from.depth; d++) {
          var nm = $from.node(d).type.name;
          if (nm === 'bulletList' || nm === 'orderedList') { listDepth = d; break; }
        }
        if (listDepth !== -1) {
          activeEditor.chain().focus().setTextSelection($from.after(listDepth)).run();
        }

        activeEditor.chain().focus().setImage({
          src: _mdImgSelected.url,
          alt: alt,
          class: ttSize + ' ' + ttPos
        }).run();
      }
      closeMdImageModal();
      toast('✅ Bild eingefügt', 'ok');
      return;
    }

    // EasyMDE-Bild einfügen (alle anderen Editoren)
    if (!S.mde) return;
    var markdown = '![' + alt + '](' + _mdImgSelected.url + '){.' + classes.join(' .') + '}';
    var cm = S.mde.codemirror;
    cm.replaceSelection(markdown);
    cm.focus();
    closeMdImageModal();
    toast('✅ Bild eingefügt', 'ok');
  }

  /* ────────────────────────────────────────────────────────────
     LIVE-BILDVORSCHAU IM MARKDOWN-EDITOR
     Ersetzt ![alt](pfad){.klassen} im Editor durch eine echte
     Bild-Vorschau mit Bearbeitungs-Panel (Größe / Position /
     Austauschen / Entfernen). Gilt für alle Felder, die initMDE()
     verwenden.
  ──────────────────────────────────────────────────────────── */
  var MDIMG_SIZE_CLASSES   = ['img-klein', 'img-mittel', 'img-gross', 'img-voll'];
  var MDIMG_HALIGN_CLASSES = ['img-links', 'img-zentriert', 'img-rechts'];
  var MDIMG_VALIGN_CLASSES = ['img-pos-oben', 'img-pos-mitte', 'img-pos-unten'];
  var MDIMG_FLOW_CLASSES   = ['img-flow', 'img-block'];
  var MDIMG_RE = /!\[([^\]]*)\]\(([^()\s]+)\)\{([^}]*)\}/g;

  var _mdLive = {
    marks: [],        // [{ mark, el, alt, url, size, hpos, vpos, flow }]
    panel: null,
    panelIndex: -1,
    rescanTimer: null,
    swapIndex: -1
  };

  function mdImgParseClasses(raw) {
    var size = 'img-mittel', hpos = 'img-links', vpos = null, flow = null;
    (raw || '').trim().split(/\s+/).forEach(function(tok) {
      var c = tok.replace(/^\./, '');
      if (MDIMG_SIZE_CLASSES.indexOf(c)   !== -1) size = c;
      if (MDIMG_HALIGN_CLASSES.indexOf(c) !== -1) hpos = c;
      if (MDIMG_VALIGN_CLASSES.indexOf(c) !== -1) vpos = c;
      if (MDIMG_FLOW_CLASSES.indexOf(c)   !== -1) flow = c;
    });
    if (!vpos) vpos = 'img-pos-oben';
    // Alte Bilder ohne Umfluss-Klasse: bisheriges Verhalten nachbilden
    if (!flow) flow = (hpos === 'img-zentriert') ? 'img-block' : 'img-flow';
    return { size: size, hpos: hpos, vpos: vpos, flow: flow };
  }

  function mdImgStyleFor(size, hpos, vpos, flow) {
    var style = 'border-radius:4px;height:auto;';
    var marginTop = vpos === 'img-pos-mitte' ? '1.5rem' : (vpos === 'img-pos-unten' ? '3rem' : '0');

    if (flow === 'img-flow' && hpos !== 'img-zentriert') {
      style += 'display:block;margin-top:' + marginTop + ';';
      if (hpos === 'img-links') style += 'float:left;margin-right:1rem;margin-bottom:.6rem;';
      else                       style += 'float:right;margin-left:1rem;margin-bottom:.6rem;';
    } else {
      style += 'display:block;clear:both;margin-top:' + marginTop + ';margin-bottom:1rem;';
      if (hpos === 'img-links')       style += 'margin-right:auto;margin-left:0;';
      else if (hpos === 'img-rechts') style += 'margin-left:auto;margin-right:0;';
      else                              style += 'margin-left:auto;margin-right:auto;';
    }

    if (size === 'img-voll')        style += 'width:100%;max-width:100%;';
    else if (size === 'img-gross')  style += 'max-width:75%;';
    else if (size === 'img-klein')  style += 'max-width:25%;';
    else                              style += 'max-width:50%;';
    return style;
  }

  function buildMdImgWidget(entry, index, cm) {
    var wrap = document.createElement('div');
    wrap.className = 'mdimg-live';

    var img = document.createElement('img');
    img.className = 'mdimg-live-img';
    img.src = entry.url;
    img.alt = entry.alt || '';
    img.setAttribute('style', mdImgStyleFor(entry.size, entry.hpos, entry.vpos, entry.flow));
    img.onload  = function() { cm.refresh(); };
    img.onerror = function() { wrap.classList.add('mdimg-live--error'); };

    var badge = document.createElement('button');
    badge.type = 'button';
    badge.className = 'mdimg-live-edit';
    badge.title = 'Bild bearbeiten';
    badge.textContent = '✎';

    wrap.appendChild(img);
    wrap.appendChild(badge);

    function open(e) {
      e.preventDefault();
      e.stopPropagation();
      openMdImgPanel(index, wrap);
    }
    img.addEventListener('mousedown', open);
    badge.addEventListener('mousedown', open);

    return wrap;
  }

  function rescanMdImages(cm) {
    closeMdImgPanel();
    _mdLive.marks.forEach(function(e) { try { e.mark.clear(); } catch(err) {} });
    _mdLive.marks = [];

    var text = cm.getValue();
    var entries = [];
    var m;
    MDIMG_RE.lastIndex = 0;
    while ((m = MDIMG_RE.exec(text))) {
      var cls = mdImgParseClasses(m[3]);
      entries.push({
        alt: m[1], url: m[2], size: cls.size, hpos: cls.hpos, vpos: cls.vpos, flow: cls.flow,
        from: cm.posFromIndex(m.index),
        to:   cm.posFromIndex(m.index + m[0].length)
      });
    }
    entries.forEach(function(entry, i) {
      var el = buildMdImgWidget(entry, i, cm);
      var mark = cm.markText(entry.from, entry.to, {
        replacedWith: el, atomic: true, inclusiveLeft: false, inclusiveRight: false
      });
      _mdLive.marks.push({
        mark: mark, el: el, alt: entry.alt, url: entry.url,
        size: entry.size, hpos: entry.hpos, vpos: entry.vpos, flow: entry.flow
      });
    });
  }

  function scheduleMdImgRescan(cm) {
    if (_mdLive.rescanTimer) clearTimeout(_mdLive.rescanTimer);
    _mdLive.rescanTimer = setTimeout(function() { rescanMdImages(cm); }, 300);
  }

  function setupLiveImagePreview(cm) {
    rescanMdImages(cm);
    cm.on('change', function() { scheduleMdImgRescan(cm); });
    cm.on('scroll', closeMdImgPanel);
  }

  function destroyLiveImagePreview() {
    if (_mdLive.rescanTimer) { clearTimeout(_mdLive.rescanTimer); _mdLive.rescanTimer = null; }
    closeMdImgPanel();
    _mdLive.marks.forEach(function(e) { try { e.mark.clear(); } catch(err) {} });
    _mdLive.marks = [];
    _mdLive.swapIndex = -1;
  }

  function ensureMdImgPanel() {
    if (_mdLive.panel) return _mdLive.panel;
    var p = document.createElement('div');
    p.className = 'mdimg-live-panel';
    p.innerHTML =
      '<div class="mdimg-live-panel__title">🖼️ Bild bearbeiten</div>' +
      '<div class="mdimg-live-row">' +
        '<span class="mdimg-live-label">Größe</span>' +
        '<button type="button" class="mdimg-live-btn" data-size="img-klein">25%</button>' +
        '<button type="button" class="mdimg-live-btn" data-size="img-mittel">50%</button>' +
        '<button type="button" class="mdimg-live-btn" data-size="img-gross">75%</button>' +
        '<button type="button" class="mdimg-live-btn" data-size="img-voll">100%</button>' +
      '</div>' +
      '<div class="mdimg-live-row">' +
        '<span class="mdimg-live-label">Position</span>' +
        '<button type="button" class="mdimg-live-btn" data-h="img-links">Links</button>' +
        '<button type="button" class="mdimg-live-btn" data-h="img-zentriert">Zentriert</button>' +
        '<button type="button" class="mdimg-live-btn" data-h="img-rechts">Rechts</button>' +
      '</div>' +
      '<div class="mdimg-live-row mdimg-live-row--actions">' +
        '<button type="button" class="btn btn-outline btn-sm" id="mdimg-live-swap">🔄 Bild austauschen</button>' +
        '<button type="button" class="btn btn-outline btn-sm mdimg-live-danger" id="mdimg-live-remove">🗑️ Entfernen</button>' +
      '</div>';
    document.body.appendChild(p);

    p.addEventListener('mousedown', function(e) { e.stopPropagation(); });
    p.addEventListener('click', function(e) {
      var sizeBtn = e.target.closest('[data-size]');
      var posBtn  = e.target.closest('[data-h]');
      if (sizeBtn) {
        applyMdImgChange(_mdLive.panelIndex, { size: sizeBtn.getAttribute('data-size') });
      } else if (posBtn) {
        var hpos = posBtn.getAttribute('data-h');
        // Vertikale Position & Textumfluss werden nicht mehr separat abgefragt –
        // wie auf der Testseite automatisch aus der horizontalen Position abgeleitet.
        applyMdImgChange(_mdLive.panelIndex, {
          hpos: hpos,
          vpos: 'img-pos-oben',
          flow: hpos === 'img-zentriert' ? 'img-block' : 'img-flow'
        });
      } else if (e.target.id === 'mdimg-live-swap')   openMdImgSwapModal(_mdLive.panelIndex);
      else if (e.target.id === 'mdimg-live-remove') removeMdImg(_mdLive.panelIndex);
    });

    _mdLive.panel = p;
    return p;
  }

  function openMdImgPanel(index, anchorEl) {
    var entry = _mdLive.marks[index];
    if (!entry) return;
    var p = ensureMdImgPanel();
    _mdLive.panelIndex = index;

    p.querySelectorAll('[data-size]').forEach(function(b) {
      b.classList.toggle('mdimg-live-btn--active', b.getAttribute('data-size') === entry.size);
    });
    p.querySelectorAll('[data-h]').forEach(function(b) {
      b.classList.toggle('mdimg-live-btn--active', b.getAttribute('data-h') === entry.hpos);
    });

    p.style.display = 'flex';
    var rect  = anchorEl.getBoundingClientRect();
    var pRect = p.getBoundingClientRect();
    var top  = window.scrollY + rect.bottom + 6;
    var left = window.scrollX + rect.left;
    var maxLeft = window.scrollX + document.documentElement.clientWidth - pRect.width - 8;
    if (left > maxLeft) left = Math.max(8, maxLeft);
    p.style.top  = top + 'px';
    p.style.left = left + 'px';

    setTimeout(function() {
      document.addEventListener('mousedown', mdImgPanelOutsideClick);
    }, 0);
  }

  function mdImgPanelOutsideClick(e) {
    if (_mdLive.panel && _mdLive.panel.style.display !== 'none' && !_mdLive.panel.contains(e.target)) {
      closeMdImgPanel();
    }
  }

  function closeMdImgPanel() {
    if (_mdLive.panel) _mdLive.panel.style.display = 'none';
    _mdLive.panelIndex = -1;
    document.removeEventListener('mousedown', mdImgPanelOutsideClick);
  }

  function applyMdImgChange(index, patch) {
    var entry = _mdLive.marks[index];
    if (!entry || !S.mde) return;
    var cm = S.mde.codemirror;
    var range = entry.mark.find();
    if (!range) return;

    var alt  = patch.alt  !== undefined ? patch.alt  : entry.alt;
    var url  = patch.url  !== undefined ? patch.url  : entry.url;
    var size = patch.size || entry.size;
    var hpos = patch.hpos || entry.hpos;
    var vpos = patch.vpos || entry.vpos;
    var flow = patch.flow || entry.flow;
    if (hpos === 'img-zentriert' && flow === 'img-flow') flow = 'img-block';

    var classes = [size, hpos];
    if (vpos !== 'img-pos-oben') classes.push(vpos);
    classes.push(flow);

    var md = '![' + alt + '](' + url + '){.' + classes.join(' .') + '}';

    cm.replaceRange(md, range.from, range.to);
    if (_mdLive.rescanTimer) { clearTimeout(_mdLive.rescanTimer); _mdLive.rescanTimer = null; }
    rescanMdImages(cm);

    if (_mdLive.marks[index]) openMdImgPanel(index, _mdLive.marks[index].el);
  }

  function openMdImgSwapModal(index) {
    _mdLive.swapIndex = index;
    var modal = id('mdimg-modal');
    modal.classList.add('mdimg-swap-mode');
    id('mdimg-options').style.display = 'none';
    modal.style.display = 'flex';
    loadMdImgGallery();
    closeMdImgPanel();
  }

  function removeMdImg(index) {
    var entry = _mdLive.marks[index];
    if (!entry || !S.mde) return;
    var cm = S.mde.codemirror;
    showConfirm('Bild entfernen', 'Soll dieses Bild aus dem Text entfernt werden?', function() {
      var range = entry.mark.find();
      if (!range) return;
      var lineText = cm.getLine(range.from.line);
      if (range.from.line === range.to.line && lineText.trim() === lineText.slice(range.from.ch, range.to.ch)) {
        // Bild steht allein in seiner Zeile → ganze Zeile (+ ggf. folgende Leerzeile) entfernen
        var startLine = range.from.line;
        var endLine = startLine + 1;
        if (endLine < cm.lineCount() && cm.getLine(endLine).trim() === '') endLine++;
        endLine = Math.min(endLine, cm.lineCount());
        cm.replaceRange('', { line: startLine, ch: 0 }, { line: endLine, ch: 0 });
      } else {
        cm.replaceRange('', range.from, range.to);
      }
      if (_mdLive.rescanTimer) { clearTimeout(_mdLive.rescanTimer); _mdLive.rescanTimer = null; }
      rescanMdImages(cm);
      closeMdImgPanel();
      toast('🗑️ Bild entfernt', 'ok');
    });
  }

  /* ────────────────────────────────────────────────────────────
     PDF-DOKUMENT UPLOAD & EINFÜGEN
  ──────────────────────────────────────────────────────────── */
  async function apiUploadPdf(filename, base64Data) {
    var tok = await getToken();
    var safeName = Date.now() + '-' + filename.replace(/[^a-zA-Z0-9._-]/g, '-');
    var body = { message: '📄 PDF hochgeladen: ' + safeName, content: base64Data, branch: BRANCH };
    var r = await fetch(GIT + '/downloads/' + safeName, {
      method: 'PUT',
      headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error('PDF-Upload fehlgeschlagen');
    return '/downloads/' + safeName;
  }

  // Modus des PDF-Modals:
  //  - 'insert'    : Klick auf eine PDF fügt einen Markdown-Link an der
  //                   Cursor-Position in den Text ein (Original-Verhalten)
  //  - 'downloads' : Klick auf eine PDF fügt das Dokument zur "Dokumente &
  //                   Downloads"-Liste der Seite hinzu (kein Markdown-Link)
  var _pdfModalMode = 'insert';

  function openPdfModal(mode) {
    _pdfModalMode = (mode === 'downloads') ? 'downloads' : 'insert';
    if (_pdfModalMode === 'insert' && !S.mde) {
      toast('❌ Kein Markdown-Editor aktiv – bitte zuerst eine Seite öffnen', 'err');
      return;
    }
    var titleEl = document.querySelector('#pdf-modal .modal-head h3');
    if (titleEl) {
      titleEl.textContent = _pdfModalMode === 'downloads'
        ? '📄 Dokument zu „Dokumente & Downloads" hinzufügen'
        : '📄 PDF hochladen & einfügen';
    }
    id('pdf-modal').style.display = 'flex';
    id('pdf-upload-status').textContent = '';
    loadPdfGallery();
  }
  // Wird über inline onclick="openPdfModal()" / onclick="openPdfModal('downloads')"
  // aufgerufen (renderStandard etc.) – inline onclick läuft im globalen Scope,
  // daher muss die Funktion explizit auf window verfügbar gemacht werden.
  window.openPdfModal = openPdfModal;

  function closePdfModal() {
    id('pdf-modal').style.display = 'none';
  }

  async function loadPdfGallery() {
    var gallery = id('pdf-gallery');
    if (!gallery) return;
    gallery.innerHTML = '<div class="gallery-loading">PDFs werden geladen…</div>';
    try {
      var files = await apiGetDir('downloads');
      var pdfs = files.filter(function(f) {
        return f.type === 'file' && /\.pdf$/i.test(f.name);
      });
      if (!pdfs.length) {
        gallery.innerHTML = '<div class="gallery-loading">Noch keine PDFs vorhanden. Laden Sie eines hoch.</div>';
        return;
      }
      gallery.innerHTML = pdfs.map(function(f) {
        var url = '/downloads/' + f.name;
        // Anzeigename: Timestamp-Prefix entfernen für bessere Lesbarkeit
        var displayName = f.name.replace(/^\d+-/, '').replace(/-/g, ' ').replace(/\.pdf$/i, '');
        return '<div class="pdf-item" onclick="pdfItemClick(\'' + escAttr(url) + '\',\'' + escAttr(f.name) + '\')" title="Klicken zum Auswählen">' +
          '<span class="pdf-icon">📄</span>' +
          '<span class="pdf-name">' + escHtml(displayName) + '</span>' +
          '<span class="pdf-filename">' + escHtml(f.name) + '</span>' +
        '</div>';
      }).join('');
    } catch(e) {
      gallery.innerHTML = '<div class="gallery-loading">Fehler beim Laden: ' + escHtml(e.message) + '</div>';
    }
  }

  window.insertPdfLink = function(url, filename) {
    if (!S.mde) return;
    // Anzeigename: Timestamp und Bindestriche entfernen
    var displayName = filename.replace(/^\d+-/, '').replace(/-/g, ' ').replace(/\.pdf$/i, '').trim() || filename;
    var markdown = '[' + displayName + '](' + url + ')';
    var cm = S.mde.codemirror;
    cm.replaceSelection(markdown);
    cm.focus();
    closePdfModal();
    toast('✅ PDF-Link eingefügt: ' + displayName, 'ok');
  };

  // Klick auf eine PDF in der Galerie – Verhalten hängt vom Modal-Modus ab
  // (siehe openPdfModal): entweder Markdown-Link einfügen, oder das Dokument
  // zur "Dokumente & Downloads"-Liste der aktuellen Seite hinzufügen.
  window.pdfItemClick = function(url, filename) {
    if (_pdfModalMode === 'downloads') {
      addDownloadRow(url, filename);
      closePdfModal();
    } else {
      insertPdfLink(url, filename);
    }
  };

  function initPdfUpload() {
    var input = id('pdf-upload-input');
    if (!input) return;
    input.addEventListener('change', async function() {
      var file = this.files[0];
      if (!file) return;
      var status = id('pdf-upload-status');
      status.textContent = '⏳ Wird hochgeladen…';
      try {
        var b64 = await fileToBase64(file);
        var url = await apiUploadPdf(file.name, b64);
        status.textContent = '✅ Hochgeladen!';
        await loadPdfGallery();
        // Direkt verwenden: je nach Modus einfügen oder zur Downloads-Liste hinzufügen
        if (_pdfModalMode === 'downloads') {
          addDownloadRow(url, file.name);
          closePdfModal();
        } else {
          insertPdfLink(url, file.name);
        }
      } catch(e) {
        status.textContent = '❌ ' + e.message;
      }
      input.value = '';
    });
  }

  /* ────────────────────────────────────────────────────────────
     CONFIRM DIALOG
  ──────────────────────────────────────────────────────────── */
  var _confirmCallback = null;

  function showConfirm(title, msg, cb) {
    _confirmCallback = cb;
    id('confirm-title').textContent = title;
    id('confirm-msg').textContent   = msg;
    id('confirm-modal').style.display = 'flex';
  }

  /* ────────────────────────────────────────────────────────────
     MARKDOWN EDITOR
  ──────────────────────────────────────────────────────────── */
  function initMDE(fieldId) {
    var el = id('f-' + fieldId);
    if (!el || !window.EasyMDE) return;
    destroyMDE();
    S.mde = new EasyMDE({
      element: el,
      spellChecker: false,
      autosave: { enabled: false },
      toolbar: ['bold','italic','heading','|','unordered-list','ordered-list','|','link',
        {
          name: 'insert-image-sized',
          action: function() { openMdImageModal(); },
          className: 'mdimg-toolbar-btn',
          title: 'Bild einfügen (mit Position)'
        },
        {
          name: 'insert-pdf',
          action: function() { openPdfModal(); },
          className: 'pdf-toolbar-btn',
          title: 'PDF / Dokument einfügen'
        },
        '|','preview','guide'],
      status: false,
      minHeight: '180px',
      previewRender: function(plainText) {
        // Standard-Rendering von EasyMDE, danach unsere Bild-Größen-/
        // Ausrichtungs-Syntax ![alt](pfad){.img-mittel .img-rechts} in echte
        // CSS-Klassen umwandeln – sonst zeigt die Vorschau nur "{.img-mittel
        // .img-rechts}" als Text hinter dem Bild an (sieht "kaputt" aus,
        // obwohl die Live-Website das Bild korrekt mit Klassen rendert).
        var html = this.markdown(plainText);
        return html.replace(/(<img\b[^>]*>)\s*\{([^}]*)\}/g, function(m, imgTag, cls) {
          var classes = cls.trim().split(/\s+/)
            .map(function(c) { return c.replace(/^\./, ''); })
            .filter(Boolean).join(' ');
          if (!classes) return imgTag;
          if (/\sclass="/.test(imgTag)) {
            return imgTag.replace(/\sclass="([^"]*)"/, function(mm, existing) {
              return ' class="' + existing + ' ' + classes + '"';
            });
          }
          return imgTag.replace(/\/?>$/, ' class="' + classes + '">');
        });
      },
    });
    setupLiveImagePreview(S.mde.codemirror);
  }

  function destroyMDE() {
    destroyLiveImagePreview();
    if (S.mde) {
      try { S.mde.toTextArea(); } catch(e) {}
      S.mde = null;
    }
    // Reset table editor state
    S._tableMode  = false;
    S._segments   = null;
    S._tableField = null;
    S._mdeWrap    = null;
  }

  /* ── Markdown-Tabellen → echtes <table>-HTML ───────────────────
     Migrierte Markdown-Seiten enthalten Tabellen als reine Pipe-Syntax
     (| A | B | + Trennzeile | --- | --- |). TipTap erkennt das nicht und
     zeigt es als Strich-Text. Diese Funktion findet solche Tabellen-Blöcke
     im Inhalt und wandelt NUR sie in <table>-HTML um – umliegender Text/HTML
     bleibt unangetastet. Bevorzugt marked.js, mit manuellem Fallback. */
  function ttIsMdTableSeparator(line) {
    // z.B. "| --- | --- |", "|:--|--:|" – nur Pipes/Striche/Doppelpunkte/
    // Leerzeichen und mindestens ein Strich.
    return /\|/.test(line) && /-/.test(line) && /^[\s|:\-]+$/.test(line);
  }
  function ttMdTableBlockToHtml(md) {
    if (window.marked && typeof window.marked.parse === 'function') {
      var h = window.marked.parse(md);
      if (/<table/i.test(h)) return h;
    }
    // Manueller Fallback (falls marked fehlt oder keine Tabelle erzeugt)
    var rows = md.split('\n').filter(function(l) { return l.trim() !== ''; });
    if (rows.length < 2) return md;
    function cells(line) {
      return line.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|')
        .map(function(c) { return c.trim(); });
    }
    var headers = cells(rows[0]);
    var body = rows.slice(2).map(function(r) { return cells(r); });
    var out = '<table><thead><tr>' +
      headers.map(function(x) { return '<th>' + escHtml(x) + '</th>'; }).join('') +
      '</tr></thead><tbody>';
    body.forEach(function(r) {
      out += '<tr>' + headers.map(function(_, c) { return '<td>' + escHtml(r[c] || '') + '</td>'; }).join('') + '</tr>';
    });
    return out + '</tbody></table>';
  }
  function convertMarkdownTablesToHtml(input) {
    if (!input || input.indexOf('|') === -1) return input;
    var lines = input.split('\n');
    var out = [];
    var i = 0;
    while (i < lines.length) {
      var header = lines[i];
      var sep = lines[i + 1];
      if (header && /\|/.test(header) && sep !== undefined && ttIsMdTableSeparator(sep)) {
        var block = [header, sep];
        var j = i + 2;
        while (j < lines.length && /\|/.test(lines[j]) && lines[j].trim() !== '') {
          block.push(lines[j]);
          j++;
        }
        out.push(ttMdTableBlockToHtml(block.join('\n')));
        i = j;
      } else {
        out.push(header);
        i++;
      }
    }
    return out.join('\n');
  }

  /* ── Markdown-Marker erkennen ──────────────────────────────────
     Diese Zeichen gibt TipTap als sauberes HTML NIE als rohen Text aus
     (echte Überschrift = <h2>, echtes Fett = <strong>, echte Liste = <ul>).
     Tauchen sie im Inhalt auf, ist es (HTML-umhüllter) Markdown. */
  function hasMarkdownMarkers(s) {
    if (!s) return false;
    return /(^|\n)\s{0,3}#{1,6}\s/.test(s)               // ## / ### Überschrift
        || /\*\*[^*\n]+\*\*/.test(s)                      // **fett**
        || /(^|\n)\s{0,3}[*\-+]\s+\S/.test(s)             // * / - / + Listenpunkt
        || /(^|\n)\s{0,3}\d+\.\s+\S/.test(s)              // 1. nummerierte Liste
        || /(^|\n)\s*\|.*\|\s*\n\s*\|?[\s:|-]*-[\s:|-]*\|/.test(s); // | --- | Tabelle
  }

  /* ── Markdown → echtes HTML (komplett) ─────────────────────────
     Wandelt Überschriften/Fett/Kursiv/Listen/Tabellen via marked.js. Erkennt
     dabei, ob der Inhalt Markdown oder schon sauberes TipTap-HTML ist:
       (A) keine HTML-Tags        → reiner Markdown → marked.parse(ganz)
       (B) HTML + Markdown-Marker → HTML-umhüllter Markdown (z.B. <p>## …</p>):
           pro Top-Level-Knoten – Markdown-Absätze via marked wandeln, echtes
           HTML (Tabellen/Bilder/Listen) unverändert behalten
       (C) HTML ohne Marker       → sauberes TipTap-HTML → unverändert lassen
     So wird nie doppelt gewandelt. */
  function convertMarkdownToHtml(input) {
    if (!input) return input;
    var canMarked = !!(window.marked && typeof window.marked.parse === 'function');

    // (A) Kein einziges HTML-Tag → reiner Markdown → komplett via marked.
    if (!/<[a-z][\s\S]*?>/i.test(input)) {
      return canMarked ? window.marked.parse(input) : convertMarkdownTablesToHtml(input);
    }

    // (B) HTML vorhanden, aber mit rohen Markdown-Markern (naiv eingefügter
    //     Markdown landet als <p>## …</p>). Top-Level-Knoten einzeln behandeln.
    if (canMarked && hasMarkdownMarkers(input) && typeof document !== 'undefined') {
      var tmp = document.createElement('div');
      tmp.innerHTML = input;
      var out = '';
      Array.prototype.forEach.call(tmp.childNodes, function(node) {
        if (node.nodeType === 3) {                       // Textknoten
          var t = node.textContent;
          if (hasMarkdownMarkers(t)) out += window.marked.parse(t);
          else if (t && t.trim()) out += '<p>' + t + '</p>';
        } else if (node.nodeType === 1) {                // Element
          if (node.tagName === 'P' && hasMarkdownMarkers(node.textContent)) {
            out += window.marked.parse(node.textContent); // Markdown-Absatz wandeln
          } else {
            out += node.outerHTML;                        // echtes HTML behalten
          }
        }
      });
      return out || input;
    }

    // (C) Sauberes TipTap-HTML → unverändert (nur evtl. eingebettete MD-Tabellen).
    return convertMarkdownTablesToHtml(input);
  }

  /* ── TipTap-Modul zuverlässig laden (aktiver Retry + Fallback-CDN) ──
     window.TipTap gilt nur als bereit, wenn ALLE benötigten Extensions da
     sind. Das Modul wird via dynamischem import() geladen; schlägt ein Versuch
     fehl (kalter CDN-Start, Netzwerk-Hänger), wird AKTIV erneut geladen – statt
     nur auf einen Wert zu pollen, der nie kommt. So bleibt der Editor nicht
     dauerhaft bei „wird geladen…" hängen. */
  function tiptapReady() {
    var T = window.TipTap;
    return !!(T && T.Editor && T.StarterKit && T.Underline && T.Image &&
              T.Table && T.TableRow && T.TableCell && T.TableHeader);
  }
  var _tiptapPromise = null;
  function ensureTiptap() {
    if (tiptapReady()) return Promise.resolve();
    if (_tiptapPromise) return _tiptapPromise;
    var PKGS = ['core', 'starter-kit', 'extension-underline', 'extension-image',
                'extension-table', 'extension-table-row', 'extension-table-cell', 'extension-table-header'];
    // Mehrere CDNs – bei Ausfall des einen wird das andere versucht.
    var CDN = [
      function(p) { return 'https://esm.sh/@tiptap/' + p + '@2'; },
      function(p) { return 'https://cdn.jsdelivr.net/npm/@tiptap/' + p + '@2/+esm'; }
    ];
    var MAX = 6;
    function attempt(n) {
      if (tiptapReady()) return Promise.resolve(); // anderer Lauf war schneller
      var url = CDN[(n - 1) % CDN.length];
      return Promise.all(PKGS.map(function(p) { return import(url(p)); }))
        .then(function(m) {
          var T = {
            Editor:      m[0].Editor || m[0].default,
            StarterKit:  m[1].default,
            Underline:   m[2].default,
            Image:       m[3].default,
            Table:       m[4].default,
            TableRow:    m[5].default,
            TableCell:   m[6].default,
            TableHeader: m[7].default
          };
          if (!(T.Editor && T.StarterKit && T.Underline && T.Image &&
                T.Table && T.TableRow && T.TableCell && T.TableHeader)) {
            throw new Error('TipTap: Extensions unvollständig geladen');
          }
          window.TipTap = T; // atomar, erst wenn alles da ist
        })
        .catch(function(err) {
          if (n < MAX) {
            var wait = Math.min(400 * n, 2500); // 0.4s,0.8s,1.2s,1.6s,2.0s
            return new Promise(function(res) { setTimeout(res, wait); })
              .then(function() { return attempt(n + 1); });
          }
          throw err; // alle Versuche erschöpft
        });
    }
    _tiptapPromise = attempt(1).catch(function(err) {
      _tiptapPromise = null; // erlaubt manuellen Neuversuch (Button)
      console.error('TipTap konnte nicht geladen werden:', err);
      throw err;
    });
    return _tiptapPromise;
  }

  // Manueller Neuversuch über den „Erneut versuchen"-Button bei CDN-Ausfall.
  window.retryTiptapLoad = function() {
    _tiptapPromise = null;
    if (S.section && S.section.form === 'tiptap') renderInfomobil(S.section, S.data);
  };

  // Eager: TipTap-Modul schon beim Laden des Admin starten (nicht erst bei
  // Bedarf), damit es bereit ist, bevor der Nutzer eine TipTap-Seite öffnet.
  try { ensureTiptap().catch(function() {}); } catch (e) {}

  /* ── TipTap: init / get / destroy ─────────────────────────── */
  function initTiptap(fieldId, rawContent) {
    var container = id('tt-' + fieldId);
    if (!container) return;
    if (!tiptapReady()) {
      // Modul (ESM/CDN) noch nicht da → Platzhalter zeigen und AKTIV laden
      // (mit Retry/Fallback). Erst danach den Editor mit Inhalt initialisieren.
      // Niemals einen leeren Editor erzeugen.
      container.innerHTML = '<p style="color:var(--text-muted);padding:.75rem;">Editor wird geladen…</p>';
      ensureTiptap().then(function() {
        if (id('tt-' + fieldId)) initTiptap(fieldId, rawContent);
      }).catch(function() {
        var el = id('tt-' + fieldId);
        if (el) el.innerHTML =
          '<div style="padding:.75rem;color:var(--danger);font-size:.85rem;line-height:1.5;">' +
            '⚠️ Editor konnte nicht geladen werden (CDN/Netzwerk nicht erreichbar).' +
            '<br><button type="button" class="btn btn-sm btn-outline" style="margin-top:.5rem;" ' +
              'onclick="retryTiptapLoad()">🔄 Erneut versuchen</button>' +
          '</div>';
      });
      return;
    }
    var TT = window.TipTap;

    // Kompletten Markdown-Inhalt (Überschriften, Fett/Kursiv, Listen, Tabellen)
    // in echtes HTML umwandeln, damit TipTap ihn als Rich-Text statt als rohe
    // Markdown-Zeichen darstellt. Sauberes TipTap-HTML bleibt unverändert
    // (siehe convertMarkdownToHtml – erkennt Markdown vs. HTML, kein Doppel-Wandeln).
    var html = convertMarkdownToHtml(rawContent || '');
    // TipTap-interne Selektions-Klasse entfernen, die früher versehentlich
    // in gespeicherte Bilder geraten ist. Nur dieses eine Token wird gelöscht
    // (mit evtl. führendem Leerzeichen) – der restliche Inhalt bleibt intakt.
    html = html.replace(/\s*ProseMirror-selectednode/g, '');

    // Image-Extension um ein CSS-Klassen-Attribut erweitern. Das Bild wird
    // als schlichtes <img class="..."> gerendert; Größe (img-25/50/75/100)
    // und Position (img-links/img-rechts/img-zentriert) steuert allein das
    // CSS – kein Wrapper-Div, kein Inline-Style, kein Clearfix nötig.
    var ImageWithClass = TT.Image.extend({
      addAttributes: function() {
        var parent = this.parent ? this.parent() : {};
        return Object.assign({}, parent, {
          class: {
            default: null,
            parseHTML:  function(el) { return el.getAttribute('class'); },
            renderHTML: function(attrs) { return attrs.class ? { class: attrs.class } : {}; }
          }
        });
      }
    });

    var tableExts = (TT.Table && TT.TableRow && TT.TableCell && TT.TableHeader) ? [
      TT.Table.configure({ resizable: false }),
      TT.TableRow,
      TT.TableHeader,
      TT.TableCell
    ] : [];
    // Falls (z.B. durch einen Retry-Timer) bereits ein Editor für dieses Feld
    // existiert, vorher sauber entfernen → kein Doppel-Mount. Danach den
    // „Editor wird geladen…"-Platzhalter entfernen, bevor neu gemountet wird.
    if (S.tiptapEditors[fieldId]) { try { S.tiptapEditors[fieldId].destroy(); } catch(e) {} }
    container.innerHTML = '';
    var editor = new TT.Editor({
      element: container,
      extensions: [
        TT.StarterKit.configure({ heading: { levels: [2, 3] } }),
        TT.Underline,
        ImageWithClass
      ].concat(tableExts),
      content: html,
      editorProps: {
        // Wird Markdown-Text eingefügt (Überschriften/Fett/Listen/Tabellen),
        // in echtes HTML wandeln und einfügen. Liefert die Zwischenablage echtes
        // HTML oder ist es kein Markdown → normales Einfügen zulassen.
        handlePaste: function(view, event) {
          try {
            var cd = event.clipboardData || window.clipboardData;
            var text = cd && cd.getData('text/plain');
            var htmlClip = cd && cd.getData && cd.getData('text/html');
            if (text && !htmlClip && hasMarkdownMarkers(text)) {
              var converted = convertMarkdownToHtml(text);
              if (converted && converted !== text) {
                editor.chain().focus().insertContent(converted).run();
                event.preventDefault();
                return true;
              }
            }
          } catch (e) {}
          return false;
        }
      },
      onUpdate:         function() { updateTiptapToolbar(fieldId); },
      onSelectionUpdate: function() { updateTiptapToolbar(fieldId); }
    });
    S.tiptapEditors[fieldId] = editor;
    updateTiptapToolbar(fieldId);

    // Klick auf Bild → Kontextmenü anzeigen
    container.addEventListener('click', function(e) {
      if (e.target.tagName === 'IMG') {
        _ttClickedImg    = e.target;
        _ttClickedEditor = editor;
        showTtImgMenu(e.target);
      } else {
        hideTtImgMenu();
      }
    });
  }

  // Liefert den aktuellen Editor-Inhalt – ABER nur, wenn der Editor wirklich
  // bereit ist. So wird verhindert, dass beim Speichern guter Inhalt durch ''
  // überschrieben wird (z.B. wenn das TipTap-Modul beim Öffnen noch nicht
  // geladen war = Race-Condition).
  //   oldValue: der bisher gespeicherte Wert (Rückfall-Anker)
  //   label:    Klartext-Feldname für die Rückfrage
  function getTiptapValue(fieldId, oldValue, label) {
    oldValue = oldValue || '';
    var editor = S.tiptapEditors[fieldId];
    // (1) Editor nicht initialisiert → NICHT leeren, alten Wert behalten.
    if (!editor) return oldValue;
    // TipTap-interne Selektions-Klasse niemals mitspeichern.
    var html = editor.getHTML().replace(/\s*ProseMirror-selectednode/g, '');
    if (html === '<p></p>') html = '';
    // (2) Inhalt würde von „vorhanden" auf „leer" wechseln → nur löschen, wenn
    //     der Nutzer es ausdrücklich bestätigt; sonst alten Wert behalten.
    if (!html && oldValue) {
      var ok = window.confirm(
        'Das Feld „' + (label || fieldId) + '" war nicht leer und ist jetzt leer.\n\n' +
        'Inhalt wirklich löschen?\n\n' +
        'OK = löschen     ·     Abbrechen = bisherigen Inhalt behalten'
      );
      if (!ok) return oldValue;
    }
    return html;
  }

  function destroyAllTiptaps() {
    Object.keys(S.tiptapEditors).forEach(function(k) {
      try { S.tiptapEditors[k].destroy(); } catch(e) {}
    });
    S.tiptapEditors = {};
    S._tiptapImageField = null;
  }

  /* ── TipTap: Bild-Kontextmenü ─────────────────────────────── */
  var _ttClickedImg    = null;
  var _ttClickedEditor = null;
  var SIZE_CLS = ['img-25','img-50','img-75','img-100'];
  var POS_CLS  = ['img-links','img-zentriert','img-rechts'];

  function showTtImgMenu(imgEl) {
    var menu = id('tt-img-menu');
    if (!menu) return;
    var cls = imgEl.className || '';
    menu.querySelectorAll('[data-size]').forEach(function(b) {
      b.classList.toggle('tt-imgmenu-btn--active', cls.indexOf(b.getAttribute('data-size')) !== -1);
    });
    menu.querySelectorAll('[data-pos]').forEach(function(b) {
      b.classList.toggle('tt-imgmenu-btn--active', cls.indexOf(b.getAttribute('data-pos')) !== -1);
    });
    // Measure first (visibility:hidden), dann mit getBoundingClientRect positionieren
    menu.style.position   = 'fixed';
    menu.style.zIndex     = '9999';
    menu.style.visibility = 'hidden';
    menu.style.display    = 'block';
    var r = imgEl.getBoundingClientRect();
    menu.style.top  = (r.top + window.scrollY - menu.offsetHeight - 8) + 'px';
    menu.style.left = r.left + 'px';
    menu.style.visibility = '';
  }

  function hideTtImgMenu() {
    var menu = id('tt-img-menu');
    if (menu) menu.style.display = 'none';
    _ttClickedImg    = null;
    _ttClickedEditor = null;
  }

  function applyTtImgClass(newClass) {
    if (!_ttClickedImg || !_ttClickedEditor) return;
    try {
      var pos  = _ttClickedEditor.view.posAtDOM(_ttClickedImg, 0);
      var node = _ttClickedEditor.state.doc.nodeAt(pos);
      if (node && node.type.name === 'image') {
        var tr = _ttClickedEditor.state.tr.setNodeMarkup(
          pos, null, Object.assign({}, node.attrs, { class: newClass })
        );
        _ttClickedEditor.view.dispatch(tr);
        _ttClickedImg.className = newClass;
      }
    } catch(e) { console.warn('tt-img update:', e); }
    showTtImgMenu(_ttClickedImg);
  }

  window.ttImgSize = function(sizeClass) {
    if (!_ttClickedImg) return;
    var cur = (_ttClickedImg.className || '').split(' ').filter(function(c) {
      return SIZE_CLS.indexOf(c) === -1;
    });
    cur.push(sizeClass);
    applyTtImgClass(cur.join(' '));
  };

  window.ttImgPos = function(posClass) {
    if (!_ttClickedImg) return;
    var cur = (_ttClickedImg.className || '').split(' ').filter(function(c) {
      return POS_CLS.indexOf(c) === -1;
    });
    cur.push(posClass);
    applyTtImgClass(cur.join(' '));
  };

  window.ttImgDelete = function() {
    if (!_ttClickedImg || !_ttClickedEditor) return;
    try {
      var pos = _ttClickedEditor.view.posAtDOM(_ttClickedImg, 0);
      var tr  = _ttClickedEditor.state.tr.delete(pos, pos + 1);
      _ttClickedEditor.view.dispatch(tr);
    } catch(e) {}
    hideTtImgMenu();
  };

  // Menü ausblenden wenn außerhalb geklickt
  document.addEventListener('mousedown', function(e) {
    var menu = id('tt-img-menu');
    if (!menu || menu.style.display === 'none') return;
    if (!menu.contains(e.target) && e.target.tagName !== 'IMG') {
      hideTtImgMenu();
    }
  });

  // Toolbar-Buttons aktiv/inaktiv setzen je nach Cursor-Position
  function updateTiptapToolbar(fieldId) {
    var editor = S.tiptapEditors[fieldId];
    var bar    = id('ttbar-' + fieldId);
    if (!editor || !bar) return;
    var state = {
      bold:        editor.isActive('bold'),
      italic:      editor.isActive('italic'),
      underline:   editor.isActive('underline'),
      h2:          editor.isActive('heading', { level: 2 }),
      h3:          editor.isActive('heading', { level: 3 }),
      bulletList:  editor.isActive('bulletList'),
      orderedList: editor.isActive('orderedList')
    };
    bar.querySelectorAll('.tt-btn[data-cmd]').forEach(function(btn) {
      var cmd = btn.getAttribute('data-cmd');
      btn.classList.toggle('tt-btn--active', !!(cmd && state[cmd]));
    });
  }

  // Toolbar-Button-Kommandos (onclick im HTML)
  window.ttCmd = function(fieldId, cmd) {
    var editor = S.tiptapEditors[fieldId];
    if (!editor) return;
    var c = editor.chain().focus();
    switch (cmd) {
      case 'bold':        c.toggleBold().run();                   break;
      case 'italic':      c.toggleItalic().run();                 break;
      case 'underline':   c.toggleUnderline().run();              break;
      case 'h2':          c.toggleHeading({ level: 2 }).run();    break;
      case 'h3':          c.toggleHeading({ level: 3 }).run();    break;
      case 'bulletList':  c.toggleBulletList().run();             break;
      case 'orderedList': c.toggleOrderedList().run();            break;
    }
    updateTiptapToolbar(fieldId);
  };

  // Tabellen-Dropdown: öffnen/schließen unterhalb des "Tabelle"-Buttons
  var _ttTableField = null;
  window.ttToggleTableMenu = function(evt, fieldId) {
    var menu = id('tt-table-menu');
    if (!menu) return;
    if (menu.style.display !== 'none' && _ttTableField === fieldId) {
      menu.style.display = 'none';
      _ttTableField = null;
      return;
    }
    _ttTableField = fieldId;
    var btn = evt.currentTarget;
    menu.style.visibility = 'hidden';
    menu.style.display    = 'block';
    var r = btn.getBoundingClientRect();
    menu.style.top  = (r.bottom + 6) + 'px';
    menu.style.left = r.left + 'px';
    menu.style.visibility = '';
  };

  window.ttTableCmd = function(cmd) {
    var fieldId = _ttTableField;
    var editor  = fieldId && S.tiptapEditors[fieldId];
    if (editor) {
      var c = editor.chain().focus();
      switch (cmd) {
        case 'insertTable':    c.insertTable({ rows: 2, cols: 2, withHeaderRow: true }).run(); break;
        case 'addRowAfter':    c.addRowAfter().run();    break;
        case 'addColumnAfter': c.addColumnAfter().run(); break;
        case 'deleteRow':      c.deleteRow().run();      break;
        case 'deleteColumn':   c.deleteColumn().run();   break;
      }
    }
    var menu = id('tt-table-menu');
    if (menu) menu.style.display = 'none';
    _ttTableField = null;
  };

  // Tabellen-Dropdown ausblenden wenn außerhalb geklickt
  document.addEventListener('mousedown', function(e) {
    var menu = id('tt-table-menu');
    if (!menu || menu.style.display === 'none') return;
    if (!menu.contains(e.target) && !e.target.closest('.tt-btn')) {
      menu.style.display = 'none';
      _ttTableField = null;
    }
  });

  // Bild-Modal für TipTap öffnen (analog openMdImageModal, aber ohne S.mde-Check)
  window.openTiptapImageModal = function(fieldId) {
    S._tiptapImageField = fieldId;
    _mdImgSelected = null;
    var opts = id('mdimg-options');
    if (opts) opts.style.display = 'none';
    var insertBtn = id('mdimg-insert'); var insertBtnTop = id('mdimg-insert-top');
    if (insertBtn) insertBtn.disabled = true; if (insertBtnTop) insertBtnTop.disabled = true;
    var alt = id('mdimg-alt');
    if (alt) alt.value = '';
    _mdImgPos = { size: 'img-mittel', hpos: 'img-links', vpos: 'img-pos-oben', flow: 'img-flow' };
    mdImgSyncButtons();
    var preview = id('mdimg-preview-wrap');
    if (preview) preview.style.display = 'none';
    id('mdimg-modal').style.display = 'flex';
    loadMdImgGallery();
  };

  function getMDE() {
    // If table view is active, sync segments → markdown first
    if (S._tableMode && S._segments && S._tableField) {
      syncTableSegmentsFromDOM(S._tableField);
      return segmentsToMd(S._segments);
    }
    if (S.mde) return S.mde.value();
    var el = document.querySelector('.EasyMDEContainer + textarea');
    return el ? el.value : '';
  }

  /* ────────────────────────────────────────────────────────────
     TABLE EDITOR
  ──────────────────────────────────────────────────────────── */

  function mdHasTables(md) {
    return /^\|.+\|/m.test(md);
  }

  function parseCells(line) {
    return line.replace(/^\s*\|/, '').replace(/\|\s*$/, '')
      .split('|').map(function(c) { return c.trim(); });
  }

  function parseTableLines(lines) {
    if (lines.length < 2) return null;
    if (!/^\s*\|[\s\-:|]+\|/.test(lines[1])) return null;
    var headers = parseCells(lines[0]);
    var rows = [];
    for (var i = 2; i < lines.length; i++) {
      if (/^\s*\|/.test(lines[i])) rows.push(parseCells(lines[i]));
    }
    return { type: 'table', headers: headers, rows: rows };
  }

  function mdToSegments(md) {
    var lines = md.split('\n');
    var segments = [];
    var i = 0;
    while (i < lines.length) {
      if (/^\s*\|/.test(lines[i])) {
        var tableLines = [];
        while (i < lines.length && /^\s*\|/.test(lines[i])) {
          tableLines.push(lines[i]); i++;
        }
        var seg = parseTableLines(tableLines);
        if (seg) {
          segments.push(seg);
        } else {
          var last = segments[segments.length - 1];
          if (last && last.type === 'text') last.content += '\n' + tableLines.join('\n');
          else segments.push({ type: 'text', content: tableLines.join('\n') });
        }
      } else {
        var last = segments[segments.length - 1];
        if (last && last.type === 'text') last.content += '\n' + lines[i];
        else segments.push({ type: 'text', content: lines[i] });
        i++;
      }
    }
    segments.forEach(function(s) { if (s.type === 'text') s.content = s.content.trim(); });
    segments = segments.filter(function(s) { return !(s.type === 'text' && s.content === ''); });
    if (!segments.length) segments.push({ type: 'text', content: md });
    return segments;
  }

  function tableSegToMd(seg) {
    var cols = seg.headers.length;
    var header = '| ' + seg.headers.join(' | ') + ' |';
    var sep    = '| ' + seg.headers.map(function() { return '---'; }).join(' | ') + ' |';
    var rows = seg.rows.map(function(row) {
      var cells = [];
      for (var c = 0; c < cols; c++) cells.push(row[c] !== undefined ? row[c] : '');
      return '| ' + cells.join(' | ') + ' |';
    });
    return [header, sep].concat(rows).join('\n');
  }

  function segmentsToMd(segments) {
    return segments.map(function(seg) {
      return seg.type === 'table' ? tableSegToMd(seg) : (seg.content || '');
    }).filter(function(s) { return s !== ''; }).join('\n\n');
  }

  function initTableEditor(fieldId, markdown) {
    var barEl = id('te-bar-' + fieldId);
    if (!barEl) return;
    barEl.innerHTML =
      '<button type="button" class="te-btn" onclick="switchToTableView(\'' + fieldId + '\')" id="te-btn-table-' + fieldId + '">' +
        '🗂️ Tabellenansicht' +
      '</button>' +
      '<button type="button" class="te-btn te-btn-active" id="te-btn-md-' + fieldId + '" disabled onclick="switchToMarkdownView(\'' + fieldId + '\')">' +
        '✏️ Markdown' +
      '</button>' +
      '<button type="button" class="te-btn te-btn-new" onclick="insertNewTable(\'' + fieldId + '\')" id="te-btn-new-' + fieldId + '">' +
        '➕ Neue Tabelle' +
      '</button>';
  }

  function getMdeWrap(fieldId) {
    // Find the EasyMDE container that's a sibling of the textarea for this field
    var ta = id('f-' + fieldId);
    if (!ta) return document.querySelector('.EasyMDEContainer');
    // EasyMDE inserts its container right after the textarea
    var next = ta.nextElementSibling;
    while (next) {
      if (next.classList && next.classList.contains('EasyMDEContainer')) return next;
      next = next.nextElementSibling;
    }
    return document.querySelector('.EasyMDEContainer');
  }

  window.switchToTableView = function(fieldId) {
    var md = S.mde ? S.mde.value() : '';
    // Hide MDE
    var mdeWrap = getMdeWrap(fieldId);
    if (mdeWrap) mdeWrap.style.display = 'none';
    var contentEl = id('te-content-' + fieldId);
    if (contentEl) contentEl.style.display = '';
    S._tableMode  = true;
    S._tableField = fieldId;
    S._mdeWrap    = mdeWrap; // remember for restore
    S._segments   = mdToSegments(md);
    renderAllTableGrids(fieldId);
    var btnTable = id('te-btn-table-' + fieldId);
    var btnMd    = id('te-btn-md-'    + fieldId);
    if (btnTable) { btnTable.classList.add('te-btn-active');    btnTable.disabled = true;  }
    if (btnMd)    { btnMd.classList.remove('te-btn-active');    btnMd.disabled    = false; }
  };

  window.switchToMarkdownView = function(fieldId) {
    if (S._tableMode && S._segments) {
      syncTableSegmentsFromDOM(fieldId);
      var md = segmentsToMd(S._segments);
      var mdeWrap = S._mdeWrap || getMdeWrap(fieldId);
      if (mdeWrap) mdeWrap.style.display = '';
      var contentEl = id('te-content-' + fieldId);
      if (contentEl) contentEl.style.display = 'none';
      if (S.mde) S.mde.value(md);
      S._tableMode  = false;
      S._segments   = null;
      S._tableField = null;
      S._mdeWrap    = null;
    }
    var btnTable = id('te-btn-table-' + fieldId);
    var btnMd    = id('te-btn-md-'    + fieldId);
    if (btnTable) { btnTable.classList.remove('te-btn-active'); btnTable.disabled = false; }
    if (btnMd)    { btnMd.classList.add('te-btn-active');       btnMd.disabled    = true;  }
  };

  function syncTableSegmentsFromDOM(fieldId) {
    if (!S._segments) return;
    S._segments.forEach(function(seg, si) {
      if (seg.type !== 'table') return;
      var cols = seg.headers.length;
      for (var c = 0; c < cols; c++) {
        var el = id('te-h-' + fieldId + '-' + si + '-' + c);
        if (el) seg.headers[c] = el.value;
      }
      seg.rows.forEach(function(row, ri) {
        for (var c = 0; c < cols; c++) {
          var el = id('te-c-' + fieldId + '-' + si + '-' + ri + '-' + c);
          if (el) row[c] = el.value;
        }
      });
    });
  }

  function renderAllTableGrids(fieldId) {
    var contentEl = id('te-content-' + fieldId);
    if (!contentEl || !S._segments) return;
    var html = '';
    var tIdx = 0;
    var hasTable = false;
    S._segments.forEach(function(seg, si) {
      if (seg.type === 'text') {
        if (seg.content) {
          html += '<div class="te-text-preview">' +
            '<div class="te-text-label">📝 Textblock</div>' +
            '<div class="te-text-content">' + escHtml(seg.content.substring(0, 300)) + (seg.content.length > 300 ? '…' : '') + '</div>' +
          '</div>';
        }
      } else if (seg.type === 'table') {
        hasTable = true;
        html += renderTableGrid(fieldId, tIdx, seg, si);
        tIdx++;
      }
    });
    if (!hasTable) {
      html += '<div class="te-empty">📊 Dieser Inhalt enthält keine Tabellen.<br>Klicken Sie auf <strong>➕ Neue Tabelle</strong> um eine hinzuzufügen.</div>';
    }
    contentEl.innerHTML = html;
    // Scroll so user sees the result
    setTimeout(function() { contentEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
  }

  function renderTableGrid(fieldId, tIdx, seg, si) {
    var cols = seg.headers.length;
    var canUp   = si > 0;
    var canDown = si < S._segments.length - 1;
    var html = '<div class="te-table-card" id="te-card-' + fieldId + '-' + si + '">' +
      '<div class="te-table-toolbar">' +
        '<span class="te-table-title">Tabelle ' + (tIdx + 1) + '</span>' +
        '<div class="te-table-actions">' +
          (canUp   ? '<button type="button" class="btn btn-xs btn-ghost" onclick="teMoveUp(\'' + fieldId + '\',' + si + ')" title="Nach oben">↑</button>' : '') +
          (canDown ? '<button type="button" class="btn btn-xs btn-ghost" onclick="teMoveDown(\'' + fieldId + '\',' + si + ')" title="Nach unten">↓</button>' : '') +
          '<button type="button" class="btn btn-xs btn-danger-outline" onclick="teDelTable(\'' + fieldId + '\',' + si + ')" title="Tabelle löschen">🗑️ Löschen</button>' +
        '</div>' +
      '</div>' +
      '<div class="te-table-wrap"><table class="te-table"><thead><tr>';

    for (var c = 0; c < cols; c++) {
      html += '<th class="te-th">' +
        '<input class="te-header-input" type="text" id="te-h-' + fieldId + '-' + si + '-' + c + '" value="' + escAttr(seg.headers[c] || '') + '" placeholder="Spalte ' + (c + 1) + '">' +
        (cols > 1 ? '<button type="button" class="te-col-del-btn" onclick="teDelCol(\'' + fieldId + '\',' + si + ',' + c + ')" title="Spalte löschen">✕</button>' : '') +
      '</th>';
    }
    html += '<th class="te-col-add-th"><button type="button" class="te-col-add-btn" onclick="teAddCol(\'' + fieldId + '\',' + si + ')" title="Spalte hinzufügen">+</button></th>';
    html += '</tr></thead><tbody>';

    seg.rows.forEach(function(row, ri) {
      html += '<tr>';
      for (var c = 0; c < cols; c++) {
        html += '<td class="te-cell"><input class="te-cell-input" type="text" id="te-c-' + fieldId + '-' + si + '-' + ri + '-' + c + '" value="' + escAttr(row[c] !== undefined ? row[c] : '') + '"></td>';
      }
      html += '<td class="te-row-del-td"><button type="button" class="te-row-del-btn" onclick="teDelRow(\'' + fieldId + '\',' + si + ',' + ri + ')" title="Zeile löschen">✕</button></td></tr>';
    });

    html += '</tbody></table></div>' +
      '<button type="button" class="te-add-row-btn" onclick="teAddRow(\'' + fieldId + '\',' + si + ')">+ Zeile hinzufügen</button>' +
    '</div>';
    return html;
  }

  window.teAddRow = function(fieldId, si) {
    syncTableSegmentsFromDOM(fieldId);
    var seg = S._segments[si];
    if (!seg || seg.type !== 'table') return;
    var row = [];
    for (var c = 0; c < seg.headers.length; c++) row.push('');
    seg.rows.push(row);
    renderAllTableGrids(fieldId);
  };

  window.teDelRow = function(fieldId, si, ri) {
    syncTableSegmentsFromDOM(fieldId);
    var seg = S._segments[si];
    if (!seg || seg.type !== 'table') return;
    seg.rows.splice(ri, 1);
    renderAllTableGrids(fieldId);
  };

  window.teAddCol = function(fieldId, si) {
    syncTableSegmentsFromDOM(fieldId);
    var seg = S._segments[si];
    if (!seg || seg.type !== 'table') return;
    seg.headers.push('Spalte ' + (seg.headers.length + 1));
    seg.rows.forEach(function(row) { row.push(''); });
    renderAllTableGrids(fieldId);
  };

  window.teDelCol = function(fieldId, si, ci) {
    syncTableSegmentsFromDOM(fieldId);
    var seg = S._segments[si];
    if (!seg || seg.type !== 'table' || seg.headers.length <= 1) return;
    seg.headers.splice(ci, 1);
    seg.rows.forEach(function(row) { row.splice(ci, 1); });
    renderAllTableGrids(fieldId);
  };

  window.teDelTable = function(fieldId, si) {
    syncTableSegmentsFromDOM(fieldId);
    S._segments.splice(si, 1);
    renderAllTableGrids(fieldId);
  };

  window.teMoveUp = function(fieldId, si) {
    syncTableSegmentsFromDOM(fieldId);
    if (si <= 0) return;
    var tmp = S._segments[si - 1]; S._segments[si - 1] = S._segments[si]; S._segments[si] = tmp;
    renderAllTableGrids(fieldId);
  };

  window.teMoveDown = function(fieldId, si) {
    syncTableSegmentsFromDOM(fieldId);
    if (si >= S._segments.length - 1) return;
    var tmp = S._segments[si + 1]; S._segments[si + 1] = S._segments[si]; S._segments[si] = tmp;
    renderAllTableGrids(fieldId);
  };

  window.insertNewTable = function(fieldId) {
    var colsStr = prompt('Wie viele Spalten soll die neue Tabelle haben?', '3');
    if (!colsStr) return;
    var cols = parseInt(colsStr, 10);
    if (isNaN(cols) || cols < 1 || cols > 20) return;
    if (!S._tableMode) {
      window.switchToTableView(fieldId);
    } else {
      syncTableSegmentsFromDOM(fieldId);
    }
    var headers = [];
    for (var c = 0; c < cols; c++) headers.push('Spalte ' + (c + 1));
    var row = [];
    for (var c = 0; c < cols; c++) row.push('');
    S._segments.push({ type: 'table', headers: headers, rows: [row] });
    renderAllTableGrids(fieldId);
  };

  /* ────────────────────────────────────────────────────────────
     TOGGLE BUTTON
  ──────────────────────────────────────────────────────────── */
  window.toggleBtn = function(btn) {
    var on = btn.getAttribute('data-val') === '1';
    var newOn = !on;
    btn.setAttribute('data-val', newOn ? '1' : '0');
    btn.classList.toggle('on', newOn);
    btn.setAttribute('aria-pressed', newOn ? 'true' : 'false');
    var labelEl = id('tl-' + btn.id.replace('f-', ''));
    if (labelEl) labelEl.textContent = newOn ? 'Ja' : 'Nein';
  };

  function toggleVal(fieldId) {
    var el = id('f-' + fieldId);
    return el ? el.getAttribute('data-val') === '1' : false;
  }

  /* ────────────────────────────────────────────────────────────
     UI HELPERS
  ──────────────────────────────────────────────────────────── */
  function panelHeader(title, extraBtns) {
    return '<div class="panel-header">' +
      '<h2>' + escHtml(title) + '</h2>' +
      '<div class="panel-header-actions">' +
        (extraBtns || '') +
        '<button class="btn btn-primary" data-save>💾 Speichern</button>' +
      '</div></div>';
  }

  function saveBar() {
    return '<div class="save-bar">' +
      '<span class="save-status" id="save-status"></span>' +
      '<button class="btn btn-primary" data-save>💾 Speichern</button>' +
    '</div>';
  }

  var _toastTimer = null;
  function toast(msg, type) {
    var el = id('toast');
    el.textContent = msg;
    el.className = 'toast ' + (type || 'info');
    el.style.display = 'block';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(function() { el.style.display = 'none'; }, 3500);
  }

  /* ────────────────────────────────────────────────────────────
     DOM & VALUE HELPERS
  ──────────────────────────────────────────────────────────── */
  function id(s)  { return document.getElementById(s); }
  function gv(fId){ return val('f-' + fId); }
  function val(elId) { var e = id(elId); return e ? e.value : ''; }

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function escAttr(s) {
    return String(s || '').replace(/"/g,'&quot;').replace(/'/g,'&#x27;');
  }

  function makeSlug(str) {
    return str.toLowerCase()
      .replace(/[äÄ]/g,'ae').replace(/[öÖ]/g,'oe').replace(/[üÜ]/g,'ue').replace(/ß/g,'ss')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').substring(0,60);
  }

  function datumToIso(datum) {
    // DD.MM.YYYY → YYYY-MM-DD
    var m = datum && datum.match(/^(\d{1,2})\.(\d{2})\.(\d{4})$/);
    if (m) return m[3] + '-' + m[2].padStart(2,'0') + '-' + m[1].padStart(2,'0');
    // Try ISO already
    if (/^\d{4}-\d{2}-\d{2}/.test(datum)) return datum.substring(0,10);
    return '';
  }

  function isoToDatum(iso) {
    // YYYY-MM-DD → DD.MM.YYYY
    var m = iso && iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return parseInt(m[3],10) + '.' + m[2] + '.' + m[1];
    return iso || '';
  }

  function findByKey(arr, key) {
    for (var i = 0; i < arr.length; i++) {
      var item = arr[i];
      if (item.key === key) return item;
      if (item.children) {
        var found = findByKey(item.children, key);
        if (found) return found;
      }
    }
    return null;
  }

  /* ────────────────────────────────────────────────────────────
     SUCHE
  ──────────────────────────────────────────────────────────── */
  var _searchIndex  = [];
  var _activeResult = -1;

  function initSearch() {
    id('search-btn').addEventListener('click', openSearch);
    id('search-close-btn').addEventListener('click', closeSearch);
    id('search-backdrop').addEventListener('click', closeSearch);
    id('search-input').addEventListener('input', onSearchInput);
    id('search-input').addEventListener('keydown', onSearchKeyDown);
    buildSearchIndex();
  }

  function openSearch() {
    id('search-overlay').style.display = 'flex';
    id('search-input').value = '';
    id('search-results').innerHTML = '';
    id('search-results').style.display = 'none';
    _activeResult = -1;
    setTimeout(function() { id('search-input').focus(); }, 50);
  }

  function closeSearch() {
    id('search-overlay').style.display = 'none';
    id('search-results').innerHTML = '';
    _activeResult = -1;
  }

  function onSearchInput() {
    var q = id('search-input').value.trim();
    _activeResult = -1;
    if (q.length < 2) {
      id('search-results').style.display = 'none';
      id('search-results').innerHTML = '';
      return;
    }
    var results = doSearch(q);
    renderSearchResults(results, q);
  }

  function onSearchKeyDown(e) {
    var items = id('search-results').querySelectorAll('.search-result-item');
    if (e.key === 'Escape') { closeSearch(); return; }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      _activeResult = Math.min(_activeResult + 1, items.length - 1);
      updateActiveResult(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      _activeResult = Math.max(_activeResult - 1, 0);
      updateActiveResult(items);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (_activeResult >= 0 && items[_activeResult]) items[_activeResult].click();
      else if (items.length === 1) items[0].click();
    }
  }

  function updateActiveResult(items) {
    items.forEach(function(el, i) {
      el.classList.toggle('active', i === _activeResult);
      if (i === _activeResult) el.scrollIntoView({ block: 'nearest' });
    });
  }

  function doSearch(q) {
    var terms = q.toLowerCase().split(/\s+/).filter(Boolean);
    var results = [];
    _searchIndex.forEach(function(entry) {
      var score = 0;
      terms.forEach(function(t) {
        if (entry.match.indexOf(t) !== -1) score++;
        if ((entry.label || '').toLowerCase().indexOf(t) !== -1) score += 2; // title boost
      });
      if (score > 0) results.push({ entry: entry, score: score });
    });
    results.sort(function(a, b) { return b.score - a.score; });
    return results.slice(0, 10).map(function(r) { return r.entry; });
  }

  function highlight(text, q) {
    var terms = q.toLowerCase().split(/\s+/).filter(Boolean);
    var escaped = escHtml(text);
    terms.forEach(function(t) {
      var re = new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
      escaped = escaped.replace(re, '<mark>$1</mark>');
    });
    return escaped;
  }

  function renderSearchResults(results, q) {
    var el = id('search-results');
    if (!results.length) {
      el.innerHTML = '<div class="search-no-results">Keine Ergebnisse für „' + escHtml(q) + '"</div>';
      el.style.display = 'block';
      return;
    }
    el.innerHTML = results.map(function(entry, i) {
      return '<div class="search-result-item" data-idx="' + i + '" tabindex="-1">' +
        '<div class="search-result-icon">' + (entry.icon || '📄') + '</div>' +
        '<div class="search-result-body">' +
          '<div class="search-result-label">' + highlight(entry.label, q) + '</div>' +
          (entry.sub ? '<div class="search-result-sub">' + escHtml(entry.sub) + '</div>' : '') +
        '</div>' +
      '</div>';
    }).join('');
    el.style.display = 'block';
    el.querySelectorAll('.search-result-item').forEach(function(item, i) {
      item.addEventListener('click', function() {
        closeSearch();
        results[i].action();
      });
    });
  }

  // ── Index aufbauen ──────────────────────────────────────────
  function buildSearchIndex() {
    _searchIndex = [];

    // 1. Alle statischen NAV-Items traversieren
    function traverseNav(items, path) {
      items.forEach(function(item) {
        if (item.isAdd) return;
        var label = (item.label || '').replace(/^[🏠🦌🌿📅📰❓⚙️📥🖼️➕]\s*/u, '');
        if (item.group || item.dynamicChildren) {
          if (item.children) traverseNav(item.children, path);
          return;
        }
        if (!item.file && item.form !== 'medien') return;
        var bereich = path || label;
        _searchIndex.push({
          icon: item.label.match(/^./u)?.[0] || '📄',
          label: label,
          sub: 'Bereich: ' + bereich,
          match: [label, item.key || '', item.file || ''].join(' ').toLowerCase(),
          action: function() { selectSection(item); }
        });
      });
    }
    traverseNav(NAV, '');

    // 2. Aktuelles-Beiträge
    var aktDef = findByKey(NAV, 'aktuelles');
    fetch('/content/aktuelles.json').then(function(r){return r.json();}).then(function(d){
      (d.beitraege || []).forEach(function(b, i) {
        _searchIndex.push({
          icon: '📰',
          label: b.titel || '(Kein Titel)',
          sub: 'Aktuelles' + (b.datum ? ' · ' + b.datum : '') + (b.kategorie ? ' · ' + b.kategorie : ''),
          match: [b.titel, b.kategorie, b.text].filter(Boolean).join(' ').toLowerCase(),
          action: function() {
            selectSection(aktDef).then(function() { window.aktuellesEdit(i); });
          }
        });
      });
    }).catch(function(){});

    // 3. FAQ
    var faqDef = findByKey(NAV, 'faq');
    fetch('/content/faq.json').then(function(r){return r.json();}).then(function(d){
      (d.fragen || []).forEach(function(f, i) {
        _searchIndex.push({
          icon: '❓',
          label: f.frage || '(Keine Frage)',
          sub: 'FAQ',
          match: [f.frage, f.antwort].filter(Boolean).join(' ').toLowerCase(),
          action: function() { selectSection(faqDef); }
        });
      });
    }).catch(function(){});

    // 4. Termine
    var termDef = findByKey(NAV, 'termine');
    fetch('/content/termine.json').then(function(r){return r.json();}).then(function(d){
      (d.termine || []).forEach(function(t, i) {
        _searchIndex.push({
          icon: '📅',
          label: t.veranstaltung || '(Kein Titel)',
          sub: 'Termine' + (t.datum ? ' · ' + t.datum : '') + (t.ort ? ' · ' + t.ort : ''),
          match: [t.veranstaltung, t.ort, t.kategorie].filter(Boolean).join(' ').toLowerCase(),
          action: function() {
            selectSection(termDef).then(function() { window.termineEdit(i); });
          }
        });
      });
    }).catch(function(){});

    // 5. Dynamische Seiten (Manifeste)
    var manifeste = [
      { url: '/content/seiten-kjs.json',        icon: '🦌', bereich: 'Jäger / KJS',          dir: 'content/seiten-kjs',         form: 'standard' },
      { url: '/content/seiten-aufgaben.json',    icon: '🦌', bereich: 'Jäger / Aufgaben',      dir: 'content/seiten-aufgaben',    form: 'standard' },
      { url: '/content/seiten-weitere.json',     icon: '🦌', bereich: 'Jäger / Weitere Themen',dir: 'content/seiten-weitere',     form: 'standard' },
      { url: '/content/seiten-verbraucher.json', icon: '🌿', bereich: 'Verbraucher',            dir: 'content/seiten-verbraucher', form: 'standard' },
      { url: '/content/aufgaben/hundeausbildung-seiten.json', icon: '🐕', bereich: 'Aufgaben / Jagdhundeschule', dir: 'content/aufgaben/hundeausbildung', form: 'standard' },
    ];
    manifeste.forEach(function(m) {
      fetch(m.url).then(function(r){return r.json();}).then(function(d){
        (d.seiten || []).filter(function(s){ return s.veroeffentlicht !== false; }).forEach(function(s) {
          var def = { key: 'dyn-' + s.slug, label: s.nav_label || s.slug, file: m.dir + '/' + s.slug + '.json', form: m.form, isDynamic: true, navFile: m.url.replace('/content/','content/'), navKey: 'seiten', slug: s.slug, dir: m.dir };
          _searchIndex.push({
            icon: m.icon,
            label: s.nav_label || s.slug,
            sub: m.bereich + ' (Seite)',
            match: [s.nav_label, s.slug].filter(Boolean).join(' ').toLowerCase(),
            action: function() { selectSection(def); }
          });
        });
      }).catch(function(){});
    });
  }

  /* ────────────────────────────────────────────────────────────
     INIT
  ──────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    initAuth();

    // Modal close handlers
    id('img-backdrop').addEventListener('click', function() { id('img-modal').style.display = 'none'; });
    id('img-close').addEventListener('click',    function() { id('img-modal').style.display = 'none'; });

    // Markdown-Bild-Einfügen Modal
    id('mdimg-backdrop').addEventListener('click', closeMdImageModal);
    id('mdimg-close').addEventListener('click',      closeMdImageModal);
    id('mdimg-cancel').addEventListener('click',     closeMdImageModal);
    id('mdimg-insert').addEventListener('click',     insertMdImage);
    id('mdimg-insert-top').addEventListener('click', insertMdImage);
    initMdImgUpload();

    id('confirm-backdrop').addEventListener('click', function() { id('confirm-modal').style.display = 'none'; });
    id('confirm-cancel').addEventListener('click',   function() { id('confirm-modal').style.display = 'none'; });
    id('confirm-ok').addEventListener('click', function() {
      id('confirm-modal').style.display = 'none';
      if (_confirmCallback) { _confirmCallback(); _confirmCallback = null; }
    });

    // PDF Modal
    id('pdf-backdrop').addEventListener('click', closePdfModal);
    id('pdf-close').addEventListener('click',    closePdfModal);
    id('pdf-cancel').addEventListener('click',   closePdfModal);
    initPdfUpload();

    initImageUpload();
  });

  // Expose to window for onclick handlers
  window.S = S;
  window.renderAktuelles = renderAktuelles;
  window.renderTermine   = renderTermine;
  window.renderPersonen  = renderPersonen;
  window.renderHegeringe = renderHegeringe;

})();
