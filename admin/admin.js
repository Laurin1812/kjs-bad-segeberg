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
    imgTarget: null, // field id receiving chosen image
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
        { key:'auf-hunde',      label:'Hundeausbildung',       file:'content/aufgaben/hundeausbildung.json',form:'standard' },
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
      { key:'verbraucher-wild',  label:'Wildfleisch',           file:'content/verbraucher/wildfleisch.json',          form:'standard' },
      { key:'verbraucher-lernort', label:'Lernort Natur',       file:'content/verbraucher/lernort-natur.json',        form:'standard' },
      { key:'verbraucher-gruen', label:'Grünes Klassenzimmer',  file:'content/verbraucher/gruenes-klassenzimmer.json',form:'standard' },
      { key:'new-verbraucher', label:'➕ Neue Verbraucher-Seite', form:'neueSeite', isAdd:true,
        navFile:'content/seiten-verbraucher.json', navKey:'seiten', dir:'content/seiten-verbraucher' },
    ]},
    { key:'termine',    label:'📅 Termine',   file:'content/termine.json',   form:'termine' },
    { key:'aktuelles',  label:'📰 Aktuelles', file:'content/aktuelles.json', form:'aktuelles' },
    { key:'faq',        label:'❓ FAQ',        file:'content/faq.json',       form:'faq' },
    { key:'einstellungen', label:'⚙️ Einstellungen', group:true, open:false, children:[
      { key:'kontakt',   label:'Kontakt & Öffnungszeiten', file:'content/einstellungen.json', form:'einstellungen' },
      { key:'footer',    label:'Fußzeile',                  file:'content/footer.json',       form:'footer' },
      { key:'design',    label:'Design & Farben',           file:'content/design.json',       form:'design' },
      { key:'impressum', label:'Impressum',                  file:'content/impressum.json',    form:'impressum' },
    ]},
    { key:'downloads', label:'📥 Downloads', file:'content/downloads.json', form:'downloads' },
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
  }

  // Inserts custom pages created via "Neue Unterseite" into the sidebar
  async function loadAllManifestItems() {
    var sections = [
      { insertBeforeKey: 'new-kjs',         file: 'content/seiten-kjs.json',         dir: 'content/seiten-kjs',         keyPrefix: 'kjs-dyn',         level: 2 },
      { insertBeforeKey: 'new-aufgaben',    file: 'content/seiten-aufgaben.json',    dir: 'content/seiten-aufgaben',    keyPrefix: 'aufgaben-dyn',    level: 2 },
      { insertBeforeKey: 'new-verbraucher', file: 'content/seiten-verbraucher.json', dir: 'content/seiten-verbraucher', keyPrefix: 'verbraucher-dyn', level: 2 },
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
            key:   dynKey,
            label: s.nav_label || s.slug,
            file:  sec.dir + '/' + s.slug + '.json',
            form:  'standard',
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
    id('admin-main').innerHTML =
      '<div class="panel-body"><div class="form-card">' +
      '<p style="color:var(--danger)">⚠️ Fehler: ' + escHtml(msg) + '</p>' +
      '<p class="mt-1"><button class="btn btn-outline" onclick="location.reload()">Seite neu laden</button></p>' +
      '</div></div>';
  }

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
    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Seiteninhalt</div>' +
          fText('titel', 'Seitentitel', data.titel) +
          fText('untertitel', 'Untertitel', data.untertitel) +
          fTextarea('intro', 'Einleitungstext', data.intro, 2) +
          fMarkdown('inhalt', 'Textinhalt (Markdown)', data.inhalt) +
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
          fSelect('b-kategorie', 'Kategorie', b.kategorie, KAT_NEWS) +
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
        fSelect('t-kategorie', 'Kategorie', t.kategorie, KAT_TERMINE) +
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
          fMarkdown('ns-inhalt', 'Textinhalt (Markdown)', '') +
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
    // Always fetch fresh SHA before saving to avoid conflicts
    var current = await apiGet(filePath).catch(function() { return null; });
    var sha = current ? current.sha : (S.sha || null);
    var result = await apiPut(filePath, data, sha, message);
    // Update stored SHA
    if (result && result.content && result.content.sha) {
      S.sha = result.content.sha;
    }
    return result;
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
  }

  function getMDE() {
    if (S.mde) return S.mde.value();
    var el = document.querySelector('.EasyMDEContainer + textarea');
    return el ? el.value : '';
  }

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
