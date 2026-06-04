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
  var BRANCH = 'main';

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
  };

  /* ────────────────────────────────────────────────────────────
     NAVIGATION TREE
  ──────────────────────────────────────────────────────────── */
  var NAV = [
    { key:'startseite',  label:'🏠 Startseite',           file:'content/startseite.json',               form:'startseite' },
    { key:'jaeger', label:'🦌 Jäger', group:true, open:true, children:[
      { key:'kjs', label:'KJS Bad Segeberg', group:true, open:true, children:[
        { key:'kjs-uebersicht',   label:'Übersicht',         file:'content/jaeger/uebersicht.json',       form:'standard' },
        { key:'vorstand',         label:'Vorstand',           file:'content/vorstand.json',                form:'personen', dataKey:'mitglieder', fields:['rolle','name','email','telefon','bild'] },
        { key:'obleute',          label:'Obleute',            file:'content/obleute.json',                 form:'personen', dataKey:'obleute',   fields:['rolle','name','email','telefon','bild'] },
        { key:'hegeringe',        label:'Hegeringe',          file:'content/hegeringe.json',               form:'hegeringe' },
        { key:'mitglied-werden',  label:'Mitglied werden',    file:'content/jaeger/mitglied-werden.json',  form:'standard' },
        { key:'jaeger-werden',    label:'Jäger/in werden',    file:'content/jaeger/jaeger-werden.json',    form:'standard' },
        { key:'new-kjs', label:'➕ Neue KJS-Unterseite',  form:'neueSeite', isAdd:true,
          navFile:'content/seiten-kjs.json', navKey:'seiten', dir:'content/seiten-kjs' },
      ]},
      { key:'kjm', label:'Kreisjägermeister', file:'content/kreisjjaegermeister.json', form:'kjm' },
      { key:'aufgaben', label:'Aufgaben der KJS', group:true, open:false, children:[
        { key:'auf-schiessen',  label:'Schießwesen',          file:'content/aufgaben/schiessen.json',      form:'standard' },
        { key:'auf-hunde', label:'Hundeausbildung', group:true, open:false, children:[
          { key:'auf-hunde-uebersicht', label:'Übersichtsseite', file:'content/aufgaben/hundeausbildung.json', form:'standard' },
          { key:'jagdhundeschule-gruppe', label:'🐕 Jagdhundeschule (21 Seiten)', group:true, open:false, children:[
            { key:'new-jagdhundeschule', label:'➕ Neue Seite', form:'neueSeite', isAdd:true,
              navFile:'content/aufgaben/hundeausbildung-seiten.json', navKey:'seiten', dir:'content/aufgaben/hundeausbildung' },
          ]},
        ]},
        { key:'auf-schweiss',   label:'Schweißhundeführer',    file:'content/aufgaben/schweisshunde.json',  form:'standard' },
        { key:'auf-jugend',     label:'Jugendarbeit',          file:'content/aufgaben/jugend.json',         form:'standard' },
        { key:'auf-jagdhorn',   label:'Jagdhornblasen',        file:'content/aufgaben/jagdhorn.json',       form:'standard' },
        { key:'auf-natur',      label:'Naturschutz',           file:'content/aufgaben/naturschutz.json',    form:'standard' },
        { key:'auf-jungwild',   label:'Jungwildrettung',       file:'content/aufgaben/jungwildrettung.json',form:'standard' },
        { key:'new-aufgaben', label:'➕ Neue Aufgaben-Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-aufgaben.json', navKey:'seiten', dir:'content/seiten-aufgaben' },
      ]},
      { key:'infomobil', label:'Infomobil', file:'content/jaeger/infomobil.json', form:'standard' },
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
    ]},
    { key:'downloads', label:'📥 Downloads', file:'content/downloads.json', form:'downloads' },
    { key:'medien',    label:'🖼️ Medien & Bilder', form:'medien' },
  ];

  /* ────────────────────────────────────────────────────────────
     API LAYER (git-gateway)
  ──────────────────────────────────────────────────────────── */
  async function getToken() {
    var user = netlifyIdentity.currentUser();
    if (!user) throw new Error('Nicht angemeldet');
    return user.jwt ? await user.jwt() : (user.token && user.token.access_token);
  }

  async function apiGet(path) {
    var tok = await getToken();
    var r = await fetch(GIT + '/' + path + '?ref=' + BRANCH, {
      headers: { 'Authorization': 'Bearer ' + tok }
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
    var r = await fetch(GIT + '/' + path + '?ref=' + BRANCH, {
      headers: { 'Authorization': 'Bearer ' + tok }
    });
    if (!r.ok) return [];
    var data = await r.json();
    return Array.isArray(data) ? data : [];
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
     SIDEBAR
  ──────────────────────────────────────────────────────────── */
  function initApp() {
    renderSidebar(NAV);
    loadDynamicChildren();       // "Weitere Themen" (dynamicChildren:true)
    loadAllManifestItems();      // KJS / Aufgaben / Verbraucher custom pages
    initSearch();                // Suchfunktion initialisieren
    id('home-btn').addEventListener('click', showWelcome);
  }

  /* ────────────────────────────────────────────────────────────
     WELCOME / DASHBOARD
  ──────────────────────────────────────────────────────────── */
  function showWelcome() {
    destroyMDE();
    S.section = null;
    S.dirty = false;
    setActiveNav('');
    id('admin-main').innerHTML =
      '<div class="welcome-screen">' +
        '<div class="welcome-icon">🦌</div>' +
        '<h2>Willkommen im Admin-Bereich</h2>' +
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

        header.addEventListener('click', function() {
          var isOpen = childWrap.style.display !== 'none';
          childWrap.style.display = isOpen ? 'none' : '';
          if (chevron) chevron.classList.toggle('open', !isOpen);
        });
        el.appendChild(header);
        renderSidebar(item.children, childWrap);
        el.appendChild(childWrap);
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
    div.innerHTML = escHtml(item.label);
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
    switch(def.form) {
      case 'standard':     renderStandard(def, data);     break;
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
      case 'navExtra':     renderNavExtra(def, data);      break;
      default:             renderStandard(def, data);
    }
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
    var html = panelHeader(def.label, extraBtns) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Seiteninhalt</div>' +
          fText('titel', 'Seitentitel', data.titel) +
          fText('untertitel', 'Untertitel', data.untertitel) +
          fTextarea('intro', 'Einleitungstext', data.intro, 2) +
          '<div class="field-row">' +
            '<label class="field-label">Textinhalt (Markdown)</label>' +
            '<div id="te-bar-inhalt" class="te-bar"></div>' +
            '<textarea class="field-textarea" id="f-inhalt" rows="8">' + escHtml(data.inhalt || '') + '</textarea>' +
            '<div id="te-content-inhalt" style="display:none"></div>' +
          '</div>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Bilder</div>' +
          fImage('hero_bild', 'Hero-Hintergrundbild', data.hero_bild) +
          fImage('bild', 'Inhaltsbild', data.bild) +
          fText('bild_alt', 'Bild-Beschreibung', data.bild_alt) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Kontakt (optional)</div>' +
          fText('kontakt_name', 'Kontaktname', data.kontakt_name) +
          fText('kontakt_email', 'Kontakt E-Mail', data.kontakt_email) +
        '</div>' +
      '</div>' +
      saveBar();
    id('admin-main').innerHTML = html;
    initMDE('inhalt');
    initTableEditor('inhalt', data.inhalt || '');
    bindSaveBtn();
  }

  function collectStandard(data) {
    data.titel         = gv('titel');
    data.untertitel    = gv('untertitel');
    data.intro         = gv('intro');
    data.inhalt        = getMDE();
    data.hero_bild     = gv('hero_bild');
    data.bild          = gv('bild');
    data.bild_alt      = gv('bild_alt');
    data.kontakt_name  = gv('kontakt_name');
    data.kontakt_email = gv('kontakt_email');
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     STARTSEITE FORM
  ──────────────────────────────────────────────────────────── */
  function renderStartseite(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Hero-Bereich (Startseiten-Banner)</div>' +
          fText('hero_titel', 'Titel Zeile 1', data.hero_titel) +
          fText('hero_titel_zeile2', 'Titel Zeile 2', data.hero_titel_zeile2) +
          fTextarea('hero_untertitel', 'Untertitel-Text', data.hero_untertitel, 2) +
          fText('hero_button_text', 'Button-Text', data.hero_button_text) +
          fImage('hero_bild', 'Hero-Hintergrundbild', data.hero_bild) +
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
        '<div class="form-card">' +
          '<div class="form-card-title">Über uns</div>' +
          fText('ueber_titel', 'Überschrift', data.ueber_titel) +
          fTextarea('ueber_text', 'Text', data.ueber_text, 3) +
        '</div>' +
      '</div>' +
      saveBar();
    id('admin-main').innerHTML = html;
    bindSaveBtn();
  }

  function collectStartseite(data) {
    ['hero_titel','hero_titel_zeile2','hero_untertitel','hero_button_text',
     'statistik_1_zahl','statistik_1_label','statistik_2_zahl','statistik_2_label',
     'statistik_3_zahl','statistik_3_label','ueber_titel','ueber_text'].forEach(function(k) {
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
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="aktuellesNeu()">➕ Neuer Beitrag</button>') +
      '<div class="panel-body">' +
        '<p class="text-muted" style="margin-bottom:1rem;">' + beitraege.length + ' Beiträge. Klicken zum Bearbeiten.</p>';

    beitraege.forEach(function(b, i) {
      html += '<div class="item-card" onclick="aktuellesEdit(' + i + ')">' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(b.titel || '(Kein Titel)') + '</div>' +
          '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
            (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div class="item-actions">' +
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
    var newB = { titel:'', datum:'', kategorie:'Allgemein', bild:'', text:'', link:'' };
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
    await doSave(S.section.file, S.data, '📰 Aktuelles: Beitrag gespeichert');
    toast('✅ Beitrag gespeichert!', 'ok');
    renderAktuelles(S.section, S.data);
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
      '</div>' + saveBar();
    id('admin-main').innerHTML = html;
    initMDE('kjm-aufgaben');
    bindSaveBtn();
  }

  function collectKJM(data) {
    data.name     = gv('kjm-name');
    data.bild     = gv('kjm-bild');
    data.email    = gv('kjm-email');
    data.telefon  = gv('kjm-telefon');
    data.aufgaben = getMDE();
    data.grußwort = gv('kjm-grußwort');
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
          fText('ei-telefon', 'Telefon', data.telefon) +
          fText('ei-email', 'E-Mail', data.email) +
          fTextarea('ei-adresse', 'Adresse (jede Zeile = eine Zeile)', data.adresse, 3) +
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
    data.email   = gv('ei-email');
    data.adresse = gv('ei-adresse');
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
        return '<div class="gallery-img-wrap">' +
          '<img class="gallery-img" src="' + escAttr(url) + '" alt="' + escAttr(f.name) + '" loading="lazy">' +
          '<div class="gallery-img-name">' + escHtml(f.name) + '</div>' +
          '<div style="text-align:center;margin-top:.25rem">' +
            '<button class="btn btn-sm btn-outline" onclick="medienCopyUrl(\'' + escAttr(url) + '\')">📋 URL kopieren</button>' +
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
      manifestData[def.navKey].push({ slug: slug, nav_label: navLabel, in_navigation: true, veroeffentlicht: true });
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
      case 'standard':      data = collectStandard(data); break;
      case 'startseite':    data = collectStartseite(data); break;
      case 'kjm':           data = collectKJM(data); break;
      case 'faq':           data = collectFAQ(data); break;
      case 'einstellungen': data = collectEinstellungen(data); break;
      case 'footer':        data = collectFooter(data); break;
      case 'design':        data = collectDesign(data); break;
      case 'impressum':     data = collectImpressum(data); break;
      case 'downloads':     data = collectDownloads(data); break;
      case 'navExtra':      data = collectNavExtra(data); break;
    }

    setSaving(true);
    try {
      var result = await doSave(def.file, data, '💾 ' + def.label + ' gespeichert');
      S.data = data;
      setSaving(false);
      toast('✅ Gespeichert!', 'ok');
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
      toolbar: ['bold','italic','heading','|','unordered-list','ordered-list','|','link','image','|','preview','guide'],
      status: false,
      minHeight: '180px',
    });
  }

  function destroyMDE() {
    if (S.mde) {
      try { S.mde.toTextArea(); } catch(e) {}
      S.mde = null;
    }
    // Reset table editor state
    S._tableMode  = false;
    S._segments   = null;
    S._tableField = null;
  }

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

  window.switchToTableView = function(fieldId) {
    var md = S.mde ? S.mde.value() : '';
    // Hide MDE
    var mdeWrap = document.querySelector('.EasyMDEContainer');
    if (mdeWrap) mdeWrap.style.display = 'none';
    var contentEl = id('te-content-' + fieldId);
    if (contentEl) contentEl.style.display = '';
    S._tableMode  = true;
    S._tableField = fieldId;
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
      var mdeWrap = document.querySelector('.EasyMDEContainer');
      if (mdeWrap) mdeWrap.style.display = '';
      var contentEl = id('te-content-' + fieldId);
      if (contentEl) contentEl.style.display = 'none';
      if (S.mde) S.mde.value(md);
      S._tableMode  = false;
      S._segments   = null;
      S._tableField = null;
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
    S._segments.forEach(function(seg, si) {
      if (seg.type === 'text') {
        if (seg.content) {
          html += '<div class="te-text-preview">' +
            '<div class="te-text-label">📝 Textblock</div>' +
            '<div class="te-text-content">' + escHtml(seg.content.substring(0, 300)) + (seg.content.length > 300 ? '…' : '') + '</div>' +
          '</div>';
        }
      } else if (seg.type === 'table') {
        html += renderTableGrid(fieldId, tIdx, seg, si);
        tIdx++;
      }
    });
    if (!html) {
      html = '<div class="te-empty">Noch kein Inhalt. Klicken Sie auf „➕ Neue Tabelle" um zu beginnen.</div>';
    }
    contentEl.innerHTML = html;
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
        '<button class="btn btn-primary" data-save onclick="saveCurrentSection()">💾 Speichern</button>' +
      '</div></div>';
  }

  function saveBar() {
    return '<div class="save-bar">' +
      '<span class="save-status" id="save-status"></span>' +
      '<button class="btn btn-primary" data-save onclick="saveCurrentSection()">💾 Speichern</button>' +
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
    id('confirm-backdrop').addEventListener('click', function() { id('confirm-modal').style.display = 'none'; });
    id('confirm-cancel').addEventListener('click',   function() { id('confirm-modal').style.display = 'none'; });
    id('confirm-ok').addEventListener('click', function() {
      id('confirm-modal').style.display = 'none';
      if (_confirmCallback) { _confirmCallback(); _confirmCallback = null; }
    });

    initImageUpload();
  });

  // Expose to window for onclick handlers
  window.S = S;
  window.renderAktuelles = renderAktuelles;
  window.renderTermine   = renderTermine;
  window.renderPersonen  = renderPersonen;
  window.renderHegeringe = renderHegeringe;

})();
