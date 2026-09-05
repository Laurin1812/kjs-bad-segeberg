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
  var KAT_SERVICE = ['Umweltschutz','Förderung','Merkblätter','Formulare','Allgemein'];

  // Aktuelles-Kategorien erweiterbar (Frank-Wunsch Punkt 3): die Kategorie-
  // Liste ist jetzt eine echte, dauerhaft in content/aktuelles.json unter
  // einstellungen.kategorien gespeicherte Liste (nicht mehr nur aus
  // vorhandenen Beiträgen abgeleitet) – Frank legt neue Kategorien über den
  // "+ Neu"-Button neben dem Kategorie-Dropdown an (siehe
  // window.aktuellesKategorieAdd), die dann dauerhaft zur Auswahl stehen,
  // auch bevor ein Beitrag sie benutzt. KAT_NEWS ist nur der Startwert, falls
  // noch keine eigene Liste gespeichert ist. Alte Beiträge mit einer
  // Kategorie, die (noch) nicht in der Liste steht, werden zur Sicherheit
  // ergänzt. Dieselbe Funktion liefert auch die Basis für den Kategorie-
  // Filter auf der öffentlichen Seite (Punkt 6).
  function alleAktuellesKategorien() {
    var kats = (S.data && S.data.einstellungen && S.data.einstellungen.kategorien) || KAT_NEWS;
    var used = ((S.data && S.data.beitraege) || []).map(function(b) {
      return (b.kategorie || '').trim();
    }).filter(Boolean);
    var seen = {};
    var out = [];
    kats.concat(used).forEach(function(k) {
      if (!seen[k]) { seen[k] = true; out.push(k); }
    });
    return out;
  }

  // "+ Neu"-Button neben dem Kategorie-Dropdown bei Aktuelles-Beiträgen:
  // fragt einen Namen ab, speichert ihn dauerhaft in
  // S.data.einstellungen.kategorien (sofortiges Speichern, unabhängig vom
  // aktuellen Beitrag) und wählt ihn direkt im offenen Formular aus.
  window.aktuellesKategorieAdd = async function() {
    var neu = await showPrompt('Neue Kategorie', 'Name der neuen Kategorie:');
    if (!neu) return;
    neu = neu.trim();
    if (!neu) return;
    S.data.einstellungen = S.data.einstellungen || {};
    var kats = (S.data.einstellungen.kategorien || KAT_NEWS).slice();
    if (kats.indexOf(neu) === -1) kats.push(neu);
    S.data.einstellungen.kategorien = kats;
    try {
      await doSave(S.section.file, S.data, '🏷️ Aktuelles: Kategorie "' + neu + '" hinzugefügt');
      toast('✅ Kategorie „' + neu + '" hinzugefügt', 'ok');
    } catch (e) {
      await handleSaveError(e);
      return;
    }
    var sel = id('f-b-kategorie');
    if (sel) {
      if (!sel.querySelector('option[value="' + neu.replace(/"/g, '\\"') + '"]')) {
        var opt = document.createElement('option');
        opt.value = neu;
        opt.textContent = neu;
        sel.appendChild(opt);
      }
      sel.value = neu;
      markDirty(); // Programmatische Auswahl im offenen Formular - kein natives change-Event
    }
  };

  // "🗑"-Button neben dem Kategorie-Dropdown: löscht die aktuell ausgewählte
  // Kategorie dauerhaft aus S.data.einstellungen.kategorien (Frank-Wunsch:
  // versehentlich angelegte Kategorien wie "test22" sollen wieder entfernbar
  // sein). Ist die Kategorie noch bei mindestens einem Beitrag eingetragen,
  // wird die Löschung verweigert (sonst taucht sie über alleAktuellesKategorien()
  // sofort wieder in der Liste auf, da "verwendete" Kategorien immer ergänzt
  // werden) – stattdessen Hinweis, erst die betroffenen Beiträge umzustellen.
  window.aktuellesKategorieDelete = async function() {
    var sel = id('f-b-kategorie');
    var val = sel ? sel.value : '';
    if (!val) return;
    var usedBy = ((S.data && S.data.beitraege) || []).filter(function(b) {
      return (b.kategorie || '').trim() === val;
    });
    if (usedBy.length) {
      await showAlert('Kann nicht gelöscht werden',
        '„' + val + '" wird noch von ' + usedBy.length +
        ' Beitrag' + (usedBy.length === 1 ? '' : 'en') + ' verwendet:\n\n' +
        usedBy.map(function(b) { return '• ' + (b.titel || '(ohne Titel)'); }).join('\n') +
        '\n\nBitte dort erst eine andere Kategorie wählen.');
      return;
    }
    showConfirm('Kategorie löschen', 'Kategorie „' + val + '" wirklich löschen?', async function() {
      S.data.einstellungen = S.data.einstellungen || {};
      var kats = (S.data.einstellungen.kategorien || KAT_NEWS).slice();
      var idx = kats.indexOf(val);
      if (idx !== -1) kats.splice(idx, 1);
      S.data.einstellungen.kategorien = kats;
      try {
        await doSave(S.section.file, S.data, '🏷️ Aktuelles: Kategorie "' + val + '" gelöscht');
        toast('✅ Kategorie „' + val + '" gelöscht', 'ok');
      } catch (e) {
        await handleSaveError(e);
        return;
      }
      if (sel) {
        var opt = sel.querySelector('option[value="' + val.replace(/"/g, '\\"') + '"]');
        if (opt) opt.remove();
        if (sel.options.length) { sel.value = sel.options[0].value; markDirty(); }
      }
    });
  };

  // Termine-Kategorien erweiterbar (Frank-Wunsch, wie schon bei Aktuelles):
  // gleiches Prinzip wie alleAktuellesKategorien/aktuellesKategorieAdd/-Delete
  // oben, nur auf content/termine.json (Feld einstellungen.kategorien,
  // Fallback KAT_TERMINE) und S.data.termine[].kategorie bezogen.
  function alleTermineKategorien() {
    var kats = (S.data && S.data.einstellungen && S.data.einstellungen.kategorien) || KAT_TERMINE;
    var used = ((S.data && S.data.termine) || []).map(function(t) {
      return (t.kategorie || '').trim();
    }).filter(Boolean);
    var seen = {};
    var out = [];
    kats.concat(used).forEach(function(k) {
      if (!seen[k]) { seen[k] = true; out.push(k); }
    });
    return out;
  }

  window.termineKategorieAdd = async function() {
    var neu = await showPrompt('Neue Kategorie', 'Name der neuen Kategorie:');
    if (!neu) return;
    neu = neu.trim();
    if (!neu) return;
    S.data.einstellungen = S.data.einstellungen || {};
    var kats = (S.data.einstellungen.kategorien || KAT_TERMINE).slice();
    if (kats.indexOf(neu) === -1) kats.push(neu);
    S.data.einstellungen.kategorien = kats;
    try {
      await doSave(S.section.file, S.data, '🏷️ Termine: Kategorie "' + neu + '" hinzugefügt');
      toast('✅ Kategorie „' + neu + '" hinzugefügt', 'ok');
    } catch (e) {
      await handleSaveError(e);
      return;
    }
    var sel = id('f-t-kategorie');
    if (sel) {
      if (!sel.querySelector('option[value="' + neu.replace(/"/g, '\\"') + '"]')) {
        var opt = document.createElement('option');
        opt.value = neu;
        opt.textContent = neu;
        sel.appendChild(opt);
      }
      sel.value = neu;
      markDirty(); // Programmatische Auswahl im offenen Formular - kein natives change-Event
    }
  };

  window.termineKategorieDelete = async function() {
    var sel = id('f-t-kategorie');
    var val = sel ? sel.value : '';
    if (!val) return;
    var usedBy = ((S.data && S.data.termine) || []).filter(function(t) {
      return (t.kategorie || '').trim() === val;
    });
    if (usedBy.length) {
      await showAlert('Kann nicht gelöscht werden',
        '„' + val + '" wird noch von ' + usedBy.length +
        ' Termin' + (usedBy.length === 1 ? '' : 'en') + ' verwendet:\n\n' +
        usedBy.map(function(t) { return '• ' + (t.veranstaltung || '(ohne Titel)'); }).join('\n') +
        '\n\nBitte dort erst eine andere Kategorie wählen.');
      return;
    }
    showConfirm('Kategorie löschen', 'Kategorie „' + val + '" wirklich löschen?', async function() {
      S.data.einstellungen = S.data.einstellungen || {};
      var kats = (S.data.einstellungen.kategorien || KAT_TERMINE).slice();
      var idx = kats.indexOf(val);
      if (idx !== -1) kats.splice(idx, 1);
      S.data.einstellungen.kategorien = kats;
      try {
        await doSave(S.section.file, S.data, '🏷️ Termine: Kategorie "' + val + '" gelöscht');
        toast('✅ Kategorie „' + val + '" gelöscht', 'ok');
      } catch (e) {
        await handleSaveError(e);
        return;
      }
      if (sel) {
        var opt = sel.querySelector('option[value="' + val.replace(/"/g, '\\"') + '"]');
        if (opt) opt.remove();
        if (sel.options.length) { sel.value = sel.options[0].value; markDirty(); }
      }
    });
  };

  // Service-Kategorien erweiterbar (Frank-Wunsch 21.08.2026: Admin-Aufbau an
  // Aktuelles angleichen) – gleiches Prinzip wie alleAktuellesKategorien/
  // aktuellesKategorieAdd/-Delete oben, nur auf content/service.json (Feld
  // einstellungen.kategorien, Fallback KAT_SERVICE) und S.data.beitraege[].kategorie
  // bezogen. Ein Service-Beitrag hat wie ein Aktuelles-Beitrag genau eine
  // Kategorie (kein Kategorie-Dokumente-Container mehr, siehe renderService).
  function alleServiceKategorien() {
    var kats = (S.data && S.data.einstellungen && S.data.einstellungen.kategorien) || KAT_SERVICE;
    var used = ((S.data && S.data.beitraege) || []).map(function(b) {
      return (b.kategorie || '').trim();
    }).filter(Boolean);
    var seen = {};
    var out = [];
    kats.concat(used).forEach(function(k) {
      if (!seen[k]) { seen[k] = true; out.push(k); }
    });
    return out;
  }

  window.serviceKategorieAdd = async function() {
    var neu = await showPrompt('Neue Kategorie', 'Name der neuen Kategorie:');
    if (!neu) return;
    neu = neu.trim();
    if (!neu) return;
    S.data.einstellungen = S.data.einstellungen || {};
    var kats = (S.data.einstellungen.kategorien || KAT_SERVICE).slice();
    if (kats.indexOf(neu) === -1) kats.push(neu);
    S.data.einstellungen.kategorien = kats;
    try {
      await doSave(S.section.file, S.data, '🏷️ Service: Kategorie "' + neu + '" hinzugefügt');
      toast('✅ Kategorie „' + neu + '" hinzugefügt', 'ok');
    } catch (e) {
      await handleSaveError(e);
      return;
    }
    var sel = id('f-svb-kategorie');
    if (sel) {
      if (!sel.querySelector('option[value="' + neu.replace(/"/g, '\\"') + '"]')) {
        var opt = document.createElement('option');
        opt.value = neu;
        opt.textContent = neu;
        sel.appendChild(opt);
      }
      sel.value = neu;
      markDirty(); // Programmatische Auswahl im offenen Formular - kein natives change-Event
    }
  };

  window.serviceKategorieDelete = async function() {
    var sel = id('f-svb-kategorie');
    var val = sel ? sel.value : '';
    if (!val) return;
    var usedBy = ((S.data && S.data.beitraege) || []).filter(function(b) {
      return (b.kategorie || '').trim() === val;
    });
    if (usedBy.length) {
      await showAlert('Kann nicht gelöscht werden',
        '„' + val + '" wird noch von ' + usedBy.length +
        ' Beitrag' + (usedBy.length === 1 ? '' : 'en') + ' verwendet:\n\n' +
        usedBy.map(function(b) { return '• ' + (b.titel || '(ohne Titel)'); }).join('\n') +
        '\n\nBitte dort erst eine andere Kategorie wählen.');
      return;
    }
    showConfirm('Kategorie löschen', 'Kategorie „' + val + '" wirklich löschen?', async function() {
      S.data.einstellungen = S.data.einstellungen || {};
      var kats = (S.data.einstellungen.kategorien || KAT_SERVICE).slice();
      var idx = kats.indexOf(val);
      if (idx !== -1) kats.splice(idx, 1);
      S.data.einstellungen.kategorien = kats;
      try {
        await doSave(S.section.file, S.data, '🏷️ Service: Kategorie "' + val + '" gelöscht');
        toast('✅ Kategorie „' + val + '" gelöscht', 'ok');
      } catch (e) {
        await handleSaveError(e);
        return;
      }
      if (sel) {
        var opt = sel.querySelector('option[value="' + val.replace(/"/g, '\\"') + '"]');
        if (opt) opt.remove();
        if (sel.options.length) { sel.value = sel.options[0].value; markDirty(); }
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     STATE
  ──────────────────────────────────────────────────────────── */
  var S = {
    section: null,   // current nav def object
    data:    null,   // loaded JSON
    sha:     null,   // current SHA (des aktuell offenen S.section.file, siehe trackSha())
    shaMap:  {},     // filePath → zuletzt bekannte SHA "zum Zeitpunkt des Ladens" für JEDE
                      // Datei, die in dieser Sitzung geladen/gespeichert wurde (nicht nur
                      // die gerade offene Sektion) – Grundlage der Konflikterkennung in
                      // doSave(). Siehe trackSha() weiter unten.
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
     UNGESPEICHERTE ÄNDERUNGEN – zentrale Erkennung & Navigationsschutz
     (Phase 5B.4). Vorher setzte NUR eine Handvoll Einzelstellen S.dirty
     (Downloads-/Galerie-/Hero-Slide-Zeile hinzufügen, Navigation &
     Reihenfolge per Drag&Drop) und NUR selectSection() prüfte es (mit
     einem nativen confirm()) - normales Tippen in einem Textfeld, TipTap-
     Änderungen, EasyMDE-Änderungen und alle 9 internen "← Zurück"-Buttons
     blieben komplett ungeprüft: ein Wechsel/Zurück konnte unbemerkt
     Eingaben verwerfen.
     Prinzip statt Einzellösung pro Seite: EIN delegierter 'input'/'change'-
     Listener auf #admin-main fängt praktisch jedes native Formularfeld
     (Text/Zahl/Datum/Textarea/Select/Checkbox/Color) automatisch ab, ohne
     dass jede Seite eigenen Code braucht. Werte, die beim Rendern über
     HTML-Attribute (value="…") gesetzt werden, lösen NIE ein input/change-
     Event aus - daher entsteht beim bloßen Öffnen eines Formulars keine
     Fehlwarnung. Nur wenige Bedienelemente feuern kein natives Event und
     brauchen daher einen expliziten markDirty()-Aufruf an ihrer jeweiligen
     Stelle: der eigene Ja/Nein-Umschalter (toggleBtn), die Bildauswahl
     (pickImg), die Bildgrößen-Buttons (bildGroesseSet), Zeile-hinzufügen/
     -entfernen-Buttons bei Downloads/Galerie/Hero-Slides/Testimonials/
     Linkliste, TipTap (eigenes onUpdate, feuert nachweislich nicht beim
     initialen Laden - siehe initTiptap) und EasyMDE/CodeMirror (eigenes
     'change'-Event, siehe initMDE).
  ──────────────────────────────────────────────────────────── */

  // Zentrale Markierung "es gibt etwas Ungespeichertes im aktuell offenen
  // Formular". Auch als window.markDirty, damit inline onclick-Attribute
  // im gerenderten HTML sie direkt aufrufen können (z.B. Zeile-entfernen-
  // Buttons, die nur als String zusammengebaut werden).
  function markDirty() { S.dirty = true; }
  window.markDirty = markDirty;

  // Ersetzt id('admin-main').innerHTML = html im gesamten Datei: jede
  // vollständig neu gerenderte Ansicht ist per Definition "frisch geladen"
  // und enthält noch keine ungespeicherten Änderungen - EIN Ort für diesen
  // Reset statt einer eigenen Zeile in jeder einzelnen render*()-Funktion
  // (weniger Fehlerrisiko, siehe Anforderung "zentrale Dirty-State-Logik,
  // nicht pro Seite").
  function renderMain(html) {
    id('admin-main').innerHTML = html;
    S.dirty = false;
  }

  // Zentrale Navigationssperre: wird VOR jeder Aktion aufgerufen, die die
  // aktuelle Ansicht verlässt (die 9 "← Zurück"-Buttons, Sidebar-/Bereichs-
  // wechsel über selectSection(), sowie - wo eine Liste selbst editierbare
  // Felder wie "Seiteneinstellungen"/"Hero-Bild" trägt, siehe Service/
  // Termine/Hundebörse - Bearbeiten/Neu/Archiv-Ansicht/Schnellaktionen).
  // Ohne ungespeicherte Änderungen läuft fn() sofort wie bisher; sonst
  // Warnung mit "Zurück und verwerfen"/"Hier bleiben" - "Hier bleiben"
  // bzw. Klick daneben ändert nichts (kein eigener Callback nötig, die
  // bestehenden showConfirm-Cancel-Pfade tun ohnehin nichts).
  function confirmNav(fn) {
    if (!S.dirty) { fn(); return; }
    showConfirm(
      'Ungespeicherte Änderungen',
      'Du hast ungespeicherte Änderungen. Wenn du fortfährst, gehen diese verloren.',
      function() { S.dirty = false; fn(); },
      'Verwerfen und fortfahren',
      'btn-danger',
      'Hier bleiben'
    );
  }
  window.confirmNav = confirmNav;

  // Delegierter Listener statt Einzelverkabelung pro Feld/Seite: fängt
  // JEDES native Formularfeld innerhalb von #admin-main ab, unabhängig
  // davon, welche render*()-Funktion es gerade erzeugt hat. 'input' deckt
  // Text/Zahl/Textarea/Color während des Tippens ab, 'change' zusätzlich
  // Checkbox/Select/Date, die teils kein 'input' feuern.
  (function() {
    var main = id('admin-main');
    if (main) {
      main.addEventListener('input',  markDirty);
      main.addEventListener('change', markDirty);
    }
  })();

  // beforeunload: Tab schließen, Seite neu laden oder die Adresse ändern
  // mit ungespeicherten Änderungen löst die native Browser-Warnung aus
  // (ein eigener Text ist aus Sicherheitsgründen in keinem aktuellen
  // Browser mehr möglich - preventDefault()/return '' reicht, damit der
  // Dialog überhaupt erscheint). Ohne ungespeicherte Änderungen passiert
  // nichts. Da der Admin keine eigene Seiten-Historie führt (siehe
  // Abschlussbericht Punkt 6), ist ein Browser-Zurück aus dem Admin
  // technisch ebenfalls ein voller Seitenwechsel und damit über denselben
  // Weg abgesichert.
  window.addEventListener('beforeunload', function(e) {
    if (!S.dirty) return;
    e.preventDefault();
    e.returnValue = '';
    return '';
  });

  /* ────────────────────────────────────────────────────────────
     NAVIGATION TREE
  ──────────────────────────────────────────────────────────── */
  var NAV = [
    { key:'startseite',  label:'🏠 Startseite',           file:'content/startseite.json',               form:'startseite' },
    { key:'jaeger', label:'🦌 Jäger', group:true, open:true, children:[
      { key:'jaeger-ueber-uns', label:'Über uns', file:'content/jaeger/ueber-uns.json', form:'standard', group:true, open:false, children:[
        { key:'new-sub-ueber-uns', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-ueber-uns.json', navKey:'seiten', dir:'content/seiten-sub-ueber-uns',
          parentSlug:'ueber-uns' },
      ]},
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
        { key:'mitglied-werden', label:'Mitglied werden', file:'content/jaeger/mitglied-werden.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-mitglied-werden', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-mitglied-werden.json', navKey:'seiten', dir:'content/seiten-sub-mitglied-werden',
            parentSlug:'mitglied-werden' },
        ]},
        { key:'jaeger-werden', label:'Jäger/in werden', file:'content/jaeger/jaeger-werden.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-jaeger-werden', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-jaeger-werden.json', navKey:'seiten', dir:'content/seiten-sub-jaeger-werden',
            parentSlug:'jaeger-werden' },
        ]},
        { key:'niederwild', label:'Niederwild', file:'content/jaeger/niederwild.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-niederwild', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-niederwild.json', navKey:'seiten', dir:'content/seiten-sub-niederwild',
            parentSlug:'niederwild' },
        ]},
        { key:'hochwild', label:'Hochwild', file:'content/jaeger/hochwild.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-hochwild', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-hochwild.json', navKey:'seiten', dir:'content/seiten-sub-hochwild',
            parentSlug:'hochwild' },
        ]},
        { key:'schiessobleute', label:'Schießobleute', file:'content/jaeger/schiessobleute.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-schiessobleute', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-schiessobleute.json', navKey:'seiten', dir:'content/seiten-sub-schiessobleute',
            parentSlug:'schiessobleute' },
        ]},
        { key:'satzung', label:'Satzung', file:'content/jaeger/satzung.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-satzung', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-satzung.json', navKey:'seiten', dir:'content/seiten-sub-satzung',
            parentSlug:'satzung' },
        ]},
        { key:'landesjagdverband', label:'Landesjagdverband', file:'content/jaeger/landesjagdverband.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-landesjagdverband', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-landesjagdverband.json', navKey:'seiten', dir:'content/seiten-sub-landesjagdverband',
            parentSlug:'landesjagdverband' },
        ]},
      ]},
      { key:'kjm', label:'Kreisjägermeister', file:'content/kreisjjaegermeister.json', form:'kjm' },
      { key:'aufgaben', label:'Aufgaben der KJS', group:true, open:false, children:[
        { key:'auf-schiessen',  label:'Schießwesen',          file:'content/aufgaben/schiessen.json',      form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-schiessen', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-schiessen.json', navKey:'seiten', dir:'content/seiten-sub-schiessen',
            parentSlug:'schiessen' },
        ]},
        { key:'auf-hunde', label:'Hundeausbildung', group:true, open:false, drag:true, children:[
          { key:'auf-hunde-uebersicht', label:'Übersichtsseite', file:'content/aufgaben/hundeausbildung.json', form:'standard' },
          { key:'jagdhundeschule-gruppe', label:'🐕 Jagdhundeschule (21 Seiten)', group:true, open:false, children:[
            { key:'new-jagdhundeschule', label:'➕ Neue Seite', form:'neueSeite', isAdd:true,
              navFile:'content/aufgaben/hundeausbildung-seiten.json', navKey:'seiten', dir:'content/aufgaben/hundeausbildung' },
          ]},
        ]},
        { key:'auf-schweiss', label:'Schweißhundeführer', file:'content/aufgaben/schweisshunde.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-schweisshunde', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-schweisshunde.json', navKey:'seiten', dir:'content/seiten-sub-schweisshunde',
            parentSlug:'schweisshunde' },
        ]},
        { key:'auf-jugend', label:'Jugendarbeit', file:'content/aufgaben/jugend.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-jugend', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-jugend.json', navKey:'seiten', dir:'content/seiten-sub-jugend',
            parentSlug:'jugend' },
        ]},
        { key:'auf-jagdhorn', label:'Jagdhornblasen', file:'content/aufgaben/jagdhorn.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-jagdhorn', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-jagdhorn.json', navKey:'seiten', dir:'content/seiten-sub-jagdhorn',
            parentSlug:'jagdhorn' },
        ]},
        { key:'auf-natur', label:'Naturschutz', file:'content/aufgaben/naturschutz.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-naturschutz', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-naturschutz.json', navKey:'seiten', dir:'content/seiten-sub-naturschutz',
            parentSlug:'naturschutz' },
        ]},
        { key:'auf-jungwild', label:'Jungwildrettung', file:'content/aufgaben/jungwildrettung.json', form:'standard', drag:true, group:true, open:false, children:[
          { key:'new-sub-jungwildrettung', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
            navFile:'content/seiten-sub-jungwildrettung.json', navKey:'seiten', dir:'content/seiten-sub-jungwildrettung',
            parentSlug:'jungwildrettung' },
        ]},
        { key:'new-aufgaben', label:'➕ Neue Aufgaben-Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-aufgaben.json', navKey:'seiten', dir:'content/seiten-aufgaben' },
      ]},
      { key:'infomobil', label:'Infomobil', file:'content/jaeger/infomobil.json', form:'standard' },
      // Partner (03.09.2026): an derselben Stelle einsortiert wie in der
      // öffentlichen Navigation (content/navigation.json: jaeger_dropdown
      // direkt nach "infomobil") - Laurin-Wunsch, kein neuer Hauptmenüpunkt.
      { key:'partner', label:'🤝 Partner', file:'content/partner.json', form:'partner', dataKey:'partner', drag:true },
      // "Weitere Themen" (dynamicChildren, content/seiten-weitere.json) am
      // 22.08.2026 auf Laurin-Wunsch entfernt ("brauchen wir eigentlich
      // nicht, verwirrt nur") - loadDynamicChildren() hat einen Guard
      // (if (!weitereNode) return;) und braucht daher keine eigene Anpassung.
      // Die öffentliche Anzeige (js/main.js, Flyout im Jäger-Menü) wurde
      // ebenfalls deaktiviert, siehe dortigen Kommentar.
    ]},
    { key:'verbraucher', label:'🌿 Verbraucher', group:true, open:false, children:[
      // 22.08.2026 (Laurin-Feedback): Wildfleisch/Lernort Natur/Grünes
      // Klassenzimmer tragen jetzt selbst file+form (statt eines separaten
      // "Seiteninhalt"-Unterpunkts) - ein Klick auf den Gruppennamen öffnet
      // direkt den Seiteninhalt UND klappt die Unterseiten auf, statt erst
      // eine Ebene tiefer auf "Seiteninhalt" klicken zu müssen. Siehe
      // renderSidebar()/navItemEl() für die dafür nötige Sonderbehandlung
      // von Gruppen mit eigenem file (anders als reine Ordner-Gruppen wie
      // "Verbraucher" selbst, "Jäger", "Einstellungen" etc.).
      { key:'verbraucher-wild', label:'Wildfleisch', file:'content/verbraucher/wildfleisch.json', form:'standard', group:true, open:false, children:[
        { key:'new-sub-wild', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-wildfleisch.json', navKey:'seiten', dir:'content/seiten-sub-wildfleisch',
          parentSlug:'wildfleisch' },
      ]},
      { key:'verbraucher-lernort', label:'Lernort Natur', file:'content/verbraucher/lernort-natur.json', form:'standard', group:true, open:false, children:[
        { key:'new-sub-lernort', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-lernort-natur.json', navKey:'seiten', dir:'content/seiten-sub-lernort-natur',
          parentSlug:'lernort-natur' },
      ]},
      { key:'verbraucher-gruen', label:'Grünes Klassenzimmer', file:'content/verbraucher/gruenes-klassenzimmer.json', form:'standard', group:true, open:false, children:[
        { key:'new-sub-gruen', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-gruenes-klassenzimmer.json', navKey:'seiten', dir:'content/seiten-sub-gruenes-klassenzimmer',
          parentSlug:'gruenes-klassenzimmer' },
      ]},
      // 23.08.2026 (Laurin-Wunsch): Waidmannssprache war bisher Unterseite
      // von Wildfleisch, ist jetzt eine eigenständige Hauptseite im
      // Verbraucher-Dropdown wie Wildfleisch/Lernort Natur/Grünes
      // Klassenzimmer - inkl. eigenem Unterseiten-System (gleicher Stil
      // wie die anderen drei, Laurin-Feedback 23.08.2026: 'überall gleich').
      { key:'verbraucher-waidmannssprache', label:'Waidmannssprache', file:'content/verbraucher/waidmannssprache.json', form:'standard', group:true, open:false, children:[
        { key:'new-sub-waidmannssprache', label:'➕ Neue Unterseite', form:'neueSeite', isAdd:true,
          navFile:'content/seiten-sub-waidmannssprache.json', navKey:'seiten', dir:'content/seiten-sub-waidmannssprache',
          parentSlug:'waidmannssprache' },
      ]},
      // Der frühere generische Button "➕ Neue Verbraucher-Seite" (schrieb nach
      // content/seiten-verbraucher/) wurde am 22.08.2026 entfernt (Frank/Laurin-
      // Feedback: zwei parallele "Neue Seite"-Systeme unter Verbraucher waren
      // verwirrend, Seiten landeten im falschen Topf und tauchten in keiner
      // Sidebar auf). Neue Unterseiten jetzt IMMER über den "➕ Neue Unterseite"-
      // Button beim jeweiligen Thema (Wildfleisch/Lernort Natur/Grünes
      // Klassenzimmer) anlegen - siehe new-sub-wild/new-sub-lernort/new-sub-gruen
      // oben. content/seiten-verbraucher/ bleibt als Alt-Ordner im Repo, wird
      // aber nicht mehr beschrieben (Bestandsseiten wurden migriert, siehe
      // content/seiten-sub-wildfleisch/).
    ]},
    { key:'termine',    label:'📅 Termine',   file:'content/termine.json',   form:'termine' },
    { key:'aktuelles',  label:'📰 Aktuelles', file:'content/aktuelles.json', form:'aktuelles' },
    { key:'service',    label:'🧰 Service',    file:'content/service.json',   form:'service' },
    { key:'hundeboerse', label:'🐕 Hundebörse', file:'content/hundeboerse.json', form:'hundeboerse' },
    // Waffenbörse (Phase 1 Prototyp, 02.09.2026): eigenständiges Modul,
    // eigene JSON-Datei (content/waffenboerse.json) - bewusst NICHT in
    // hundeboerse.json integriert (andere Fachlogik/Felder). Noch kein
    // Eintrag in der öffentlichen Hauptnavigation (siehe js/components.js /
    // js/main.js) - Seiten sind bewusst nur über direkte URL erreichbar,
    // bis nach Sichtprüfung entschieden ist, ob/wie ein Nav-Punkt ergänzt wird.
    { key:'waffenboerse', label:'🔫 Waffenbörse', file:'content/waffenboerse.json', form:'waffenboerse' },
    // "Kontakt & Stammdaten" und FAQ bewusst in "Einstellungen" verschoben
    // (nicht mehr zwischen Service und Verbraucher-Themen als eigene
    // Top-Level-Punkte) - beides sind Rahmendaten/Konfiguration, keine
    // Inhaltsseiten wie Aktuelles/Termine/Service (Laurin-Feedback
    // 22.08.2026, Carsten-Hinweis "zentraler Punkt für zentrale, mehrfach
    // verwendete Daten"; FAQ auf Laurin-Wunsch 22.08.2026 ebenfalls dorthin
    // verschoben).
    { key:'einstellungen', label:'⚙️ Einstellungen', group:true, open:false, children:[
      // "Kontakt & Stammdaten" ersetzt die früheren getrennten Bereiche
      // "📞 Kontaktseite" (eigener Top-Level-Punkt) und "Telefonzentrale &
      // Kalender" (hier drin) - beide schrieben schon dieselbe Datei
      // (content/einstellungen.json), waren aber an zwei verschiedenen
      // Stellen im Menü mit unterschiedlichen Namen zu finden. Jetzt EIN
      // Bereich mit drei klar benannten Blöcken (Kopfzeile/Kontaktbox/
      // Kontaktseite), siehe renderKontaktStammdaten().
      { key:'kontakt-stammdaten', label:'📞 Kontakt & Stammdaten', file:'content/einstellungen.json', form:'kontaktStammdaten' },
      { key:'faq',        label:'❓ FAQ',        file:'content/faq.json',       form:'faq' },
      { key:'footer',    label:'Fußzeile',                  file:'content/footer.json',           form:'footer' },
      { key:'design',    label:'Design & Farben',           file:'content/design.json',           form:'design' },
      { key:'impressum', label:'Impressum',                  file:'content/impressum.json',        form:'impressum' },
      { key:'nav-extra', label:'🧭 Hauptnavigation erweitern', file:'content/navigation-extra.json', form:'navExtra' },
      { key:'nav-reihenfolge', label:'🔀 Navigation & Reihenfolge', file:'content/navigation.json', form:'navReihenfolge' },
      { key:'benutzer', label:'👥 Benutzerverwaltung', form:'benutzer' },
    ]},
    { key:'downloads', label:'📥 Downloads', file:'content/downloads.json', form:'downloads' },
    { key:'medien',    label:'🖼️ Medien & Bilder', form:'medien' },
    // "🧪 Testseite" (content/test/testseite.json) 22.08.2026 aus dem Menü
    // entfernt (Laurin-Wunsch, Aufräumen) - war ursprünglich das Sandbox-
    // Fundament, auf dem das TipTap-Formular entwickelt wurde. Phase 5B.5
    // (Admin-Vereinheitlichung normaler Inhaltsseiten): "Infomobil" lief bis
    // dahin noch über das separate, historische form:'tiptap'/renderInfomobil
    // (inhaltlich fast identisch zu form:'standard'/renderStandard, nur ohne
    // die Zusatzfelder, die Infomobils NAV-Eintrag ohnehin nie gesetzt hat -
    // nav_label/Unterseiten-System/mitglied-werden/Hundebörse-CTA/Jagdhund-
    // schule-Felder sind alle an def-Eigenschaften geknüpft, die Infomobil
    // nicht besitzt). Jetzt auf form:'standard' umgestellt, renderInfomobil/
    // collectInfomobil entfernt - ein einziger Editor-Pfad für alle normalen
    // Inhaltsseiten. content/test/testseite.json und /test/testseite.html
    // bleiben als inertes, nicht mehr verlinktes Altlast-Duo bestehen (siehe
    // Abschlussbericht Phase 5B.5) - keine Navigation/kein Code hängt mehr
    // daran.
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
    if (!r.ok) throw new Error(await apiUploadErrorMessage(r));
    return '/images/' + safeName;
  }

  // Liest die eigentliche Fehlermeldung aus der GitHub-API-Antwort aus (statt
  // nur "Upload fehlgeschlagen" anzuzeigen) – z.B. "Content is too large" bei
  // Dateien über dem ~1MB-Limit der Contents-API, oder ein 409/422 bei
  // Namenskollisionen. Ohne dieses Detail war ein fehlgeschlagener Upload
  // bisher nicht ohne DevTools-Netzwerktab diagnostizierbar.
  async function apiUploadErrorMessage(r) {
    var detail = '';
    try {
      var err = await r.json();
      detail = err && err.message ? err.message : '';
    } catch (e) { /* Antwort war kein JSON */ }
    if (!detail && r.status === 413) detail = 'Datei zu groß';
    if (!detail && r.status === 422) detail = 'Ungültige Anfrage (evtl. Datei zu groß oder Namenskonflikt)';
    return 'Upload fehlgeschlagen (' + r.status + (detail ? ': ' + detail : '') + ')';
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
    S.section = null; S.data = null; S.sha = null; S.dirty = false;
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
      var svNameResult = await apiPut('content/navigation.json', data, fresh.sha, '✏️ Sektionsname: ' + newName);
      trackSha('content/navigation.json', svNameResult && svNameResult.content && svNameResult.content.sha);
      toast('✅ Name gespeichert');
    } catch(e) {
      toast('❌ Fehler: ' + e.message, 'err');
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
      { insertBeforeKey: 'new-aufgaben',    file: 'content/seiten-aufgaben.json',    navKey:'seiten', dir: 'content/seiten-aufgaben',    keyPrefix: 'aufgaben-dyn',    level: 2 },
      // Der generische "kjs-dyn"-Eintrag (content/seiten-kjs.json, Button
      // "new-kjs") wurde am 27.08.2026 zusammen mit dem NAV-Button entfernt -
      // der Pool war komplett leer (keine echten Seiten drin) und alle 8
      // KJS-Segeberg-Seiten haben jetzt ihr eigenes Unterseiten-System
      // (siehe sub-*-dyn-Einträge unten, gleiches Muster wie Verbraucher).
      // Der generische "verbraucher-dyn"-Eintrag (content/seiten-verbraucher.json)
      // wurde am 22.08.2026 zusammen mit dem zugehörigen "Neue Verbraucher-
      // Seite"-Button entfernt (siehe Kommentar bei new-sub-wild weiter unten
      // in NAV) - Bestandsseiten migriert nach seiten-sub-wildfleisch.
      // Sub-pages under specific Verbraucher pages
      { insertBeforeKey: 'new-sub-wild',    file: 'content/seiten-sub-wildfleisch.json',            navKey:'seiten', dir: 'content/seiten-sub-wildfleisch',            keyPrefix: 'sub-wild-dyn',    level: 3 },
      { insertBeforeKey: 'new-sub-lernort', file: 'content/seiten-sub-lernort-natur.json',          navKey:'seiten', dir: 'content/seiten-sub-lernort-natur',          keyPrefix: 'sub-lernort-dyn', level: 3 },
      { insertBeforeKey: 'new-sub-gruen',   file: 'content/seiten-sub-gruenes-klassenzimmer.json',  navKey:'seiten', dir: 'content/seiten-sub-gruenes-klassenzimmer',  keyPrefix: 'sub-gruen-dyn',   level: 3 },
      // Jagdhundeschule sub-pages
      { insertBeforeKey: 'new-jagdhundeschule', file: 'content/aufgaben/hundeausbildung-seiten.json', navKey:'seiten', dir: 'content/aufgaben/hundeausbildung', keyPrefix: 'jagdhundeschule-dyn', level: 3 },
      // 27.08.2026: Sub-pages unter den 14 Jäger-/Aufgaben-Seiten, die auf
      // dasselbe Unterseiten-Muster umgestellt wurden wie Verbraucher/
      // Wildfleisch (Laurin-Wunsch: "einheitliche Seitenstruktur im
      // Admin-Bereich", siehe [[project_kjs...]] Memory).
      { insertBeforeKey: 'new-sub-ueber-uns',        file: 'content/seiten-sub-ueber-uns.json',        navKey:'seiten', dir: 'content/seiten-sub-ueber-uns',        keyPrefix: 'sub-ueber-uns-dyn',        level: 3 },
      { insertBeforeKey: 'new-sub-mitglied-werden',  file: 'content/seiten-sub-mitglied-werden.json',  navKey:'seiten', dir: 'content/seiten-sub-mitglied-werden',  keyPrefix: 'sub-mitglied-werden-dyn',  level: 3 },
      { insertBeforeKey: 'new-sub-jaeger-werden',    file: 'content/seiten-sub-jaeger-werden.json',    navKey:'seiten', dir: 'content/seiten-sub-jaeger-werden',    keyPrefix: 'sub-jaeger-werden-dyn',    level: 3 },
      { insertBeforeKey: 'new-sub-niederwild',       file: 'content/seiten-sub-niederwild.json',       navKey:'seiten', dir: 'content/seiten-sub-niederwild',       keyPrefix: 'sub-niederwild-dyn',       level: 3 },
      { insertBeforeKey: 'new-sub-hochwild',         file: 'content/seiten-sub-hochwild.json',         navKey:'seiten', dir: 'content/seiten-sub-hochwild',         keyPrefix: 'sub-hochwild-dyn',         level: 3 },
      { insertBeforeKey: 'new-sub-schiessobleute',   file: 'content/seiten-sub-schiessobleute.json',   navKey:'seiten', dir: 'content/seiten-sub-schiessobleute',   keyPrefix: 'sub-schiessobleute-dyn',   level: 3 },
      { insertBeforeKey: 'new-sub-satzung',          file: 'content/seiten-sub-satzung.json',          navKey:'seiten', dir: 'content/seiten-sub-satzung',          keyPrefix: 'sub-satzung-dyn',          level: 3 },
      { insertBeforeKey: 'new-sub-landesjagdverband',file: 'content/seiten-sub-landesjagdverband.json',navKey:'seiten', dir: 'content/seiten-sub-landesjagdverband',keyPrefix: 'sub-landesjagdverband-dyn',level: 3 },
      { insertBeforeKey: 'new-sub-schiessen',        file: 'content/seiten-sub-schiessen.json',        navKey:'seiten', dir: 'content/seiten-sub-schiessen',        keyPrefix: 'sub-schiessen-dyn',        level: 3 },
      { insertBeforeKey: 'new-sub-schweisshunde',    file: 'content/seiten-sub-schweisshunde.json',    navKey:'seiten', dir: 'content/seiten-sub-schweisshunde',    keyPrefix: 'sub-schweisshunde-dyn',    level: 3 },
      { insertBeforeKey: 'new-sub-jugend',           file: 'content/seiten-sub-jugend.json',           navKey:'seiten', dir: 'content/seiten-sub-jugend',           keyPrefix: 'sub-jugend-dyn',           level: 3 },
      { insertBeforeKey: 'new-sub-jagdhorn',         file: 'content/seiten-sub-jagdhorn.json',         navKey:'seiten', dir: 'content/seiten-sub-jagdhorn',         keyPrefix: 'sub-jagdhorn-dyn',         level: 3 },
      { insertBeforeKey: 'new-sub-naturschutz',      file: 'content/seiten-sub-naturschutz.json',      navKey:'seiten', dir: 'content/seiten-sub-naturschutz',      keyPrefix: 'sub-naturschutz-dyn',      level: 3 },
      { insertBeforeKey: 'new-sub-jungwildrettung',  file: 'content/seiten-sub-jungwildrettung.json',  navKey:'seiten', dir: 'content/seiten-sub-jungwildrettung',  keyPrefix: 'sub-jungwildrettung-dyn',  level: 3 },
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
        // Gruppen mit eigenem "file" (z.B. Wildfleisch/Lernort Natur/Grünes
        // Klassenzimmer, 22.08.2026-Umbau): Header ist klickbar wie eine
        // normale Seite UND klappt zusätzlich die Unterseiten-Kinder auf -
        // sonst (reine Ordner-Gruppen wie "Verbraucher", "Jäger") bleibt
        // der Header ein reiner Auf/Zu-Klapper wie bisher.
        var hatEigeneSeite = !!item.file;
        var header = navItemEl(item, level, hatEigeneSeite);
        header.setAttribute('data-key', item.key);
        var chevron = header.querySelector('.nav-chevron');
        var childWrap = document.createElement('div');
        childWrap.className = 'nav-children';
        childWrap.id = 'nc-' + item.key;
        if (!item.open) childWrap.style.display = 'none';
        else if (chevron) chevron.classList.add('open');

        header.addEventListener('click', function(e) {
          if (e.target.closest && e.target.closest('.nav-drag-handle')) return;
          if (hatEigeneSeite && !(e.target.closest && e.target.closest('.nav-chevron'))) {
            selectSection(item);
            if (childWrap.style.display === 'none') {
              childWrap.style.display = '';
              if (chevron) chevron.classList.add('open');
            }
            return;
          }
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
          trackSha('content/navigation.json', resp.sha);
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
          trackSha(opts.dynamicNavFile, resp.sha);
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
      if (e && e.isSaveConflict) {
        await handleSaveError(e);
      } else {
        toast('❌ Fehler beim Speichern der Reihenfolge: ' + e.message, 'err');
      }
    }
  }

  /* ────────────────────────────────────────────────────────────
     SECTION LOADING
  ──────────────────────────────────────────────────────────── */
  // selectSection ist der Wrapper: prüft zentral über confirmNav auf
  // ungespeicherte Änderungen (Phase 5B.4) und delegiert dann an
  // selectSectionImpl. Als Promise implementiert, damit bestehende
  // Aufrufer weiterhin `selectSection(def).then(...)` nutzen können -
  // löst bei "Hier bleiben" die Promise bewusst nicht auf, da niemand
  // aktuell .catch() daran hängt und ein Then-Callback in diesem Fall
  // ohnehin nicht laufen soll.
  function selectSection(def) {
    if (S.dirty && S.section) {
      return new Promise(function(resolve) {
        confirmNav(function() { selectSectionImpl(def).then(resolve); });
      });
    }
    return selectSectionImpl(def);
  }

  async function selectSectionImpl(def) {
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
      trackSha(def.file, resp.sha); // merkt sich den Lade-Stand als Basis für die Konflikterkennung beim Speichern
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
      case 'startseite':   renderStartseite(def, data);   break;
      case 'aktuelles':    renderAktuelles(def, data);     break;
      case 'termine':      renderTermine(def, data);       break;
      case 'personen':     renderPersonen(def, data);      break;
      case 'hegeringe':    renderHegeringe(def, data);     break;
      case 'kjm':          renderKJM(def, data);           break;
      case 'faq':          renderFAQ(def, data);           break;
      case 'kontaktStammdaten': renderKontaktStammdaten(def, data); break;
      case 'footer':       renderFooter(def, data);        break;
      case 'design':       renderDesign(def, data);        break;
      case 'impressum':    renderImpressum(def, data);     break;
      case 'downloads':    renderDownloads(def, data);     break;
      case 'navExtra':        renderNavExtra(def, data);         break;
      case 'navReihenfolge':  renderNavReihenfolge(def, data);   break;
      case 'benutzer':        renderBenutzer();                  break;
      case 'service':          renderService(def, data);          break;
      case 'hundeboerse':      renderHundeboerse(def, data);       break;
      case 'waffenboerse':     renderWaffenboerse(def, data);      break;
      case 'partner':          renderPartner(def, data);           break;
      default:                renderStandard(def, data);
    }
    // Universeller "Dokumente & Downloads"-Bereich am Ende jeder Inhaltsseite
    // (für Einstellungs-/Verwaltungsseiten ohne öffentliche Entsprechung
    // nicht sinnvoll und daher ausgenommen). "aktuelles"/"termine"/"service"
    // rendern ihre eigene "#downloads-list" bereits selbst pro Beitrag (siehe
    // aktuellesEdit/serviceEdit) – injectDownloadsCard() erkennt das (early
    // return bei bereits vorhandener Liste) und hängt dort nichts doppelt an,
    // müssen also hier nicht extra ausgenommen werden.
    // "personen"/"hegeringe" (Vorstand, Obleute, Hegeringe) sind reine
    // Listenseiten ohne eigenes "downloads"-Feld im Datenschema und ohne
    // zentrale Speicherfunktion für diese Ansicht (jede Aktion – Bearbeiten,
    // Löschen, Sortieren – speichert einzeln direkt per doSave()). Eine hier
    // injizierte Downloads-Karte hätte daher gar keinen Weg, tatsächlich
    // gespeichert zu werden (Nebenfund aus Phase 5B.5, bereinigt).
    var NO_DOWNLOADS_FORMS = ['kontaktStammdaten','footer','design','impressum','downloads','navExtra','navReihenfolge','benutzer','hundeboerse','personen','hegeringe','waffenboerse','partner'];
    if (NO_DOWNLOADS_FORMS.indexOf(def.form) === -1) {
      injectDownloadsCard(data);
    }
    // Bildergalerie: gleiche Seiten wie Downloads (Personen-Listen sind seit
    // der Bereinigung oben bereits in NO_DOWNLOADS_FORMS enthalten und damit
    // hier automatisch mit ausgenommen), zusätzlich ohne "service": Service-
    // Beiträge sind rein dokumentenorientiert, eine Bildergalerie pro Beitrag
    // ist (Stand 21.08.2026) nicht gewünscht.
    var NO_GALERIE_FORMS = NO_DOWNLOADS_FORMS.concat(['service']);
    if (NO_GALERIE_FORMS.indexOf(def.form) === -1) {
      injectGalerieCard(data);
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
  // Kategorie-Dropdown bei Aktuelles-Beiträgen (Frank-Wunsch Punkt 3, nach
  // Rückmeldung von Laurin überarbeitet): echtes <select> statt freiem
  // Textfeld, daneben ein "+ Neu"-Button (window.aktuellesKategorieAdd), der
  // eine neue Kategorie dauerhaft in content/aktuelles.json speichert.
  function fKategorieDropdown(val) {
    var options = alleAktuellesKategorien();
    if (val && options.indexOf(val) === -1) options = options.concat([val]);
    var opts = options.map(function(o) {
      return '<option value="' + escAttr(o) + '"' + (o === val ? ' selected' : '') + '>' + escHtml(o) + '</option>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-b-kategorie">Kategorie</label>' +
      '<div style="display:flex;gap:.5rem;align-items:center;">' +
        '<select class="field-input" id="f-b-kategorie" style="flex:1;">' + opts + '</select>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="aktuellesKategorieAdd()" style="white-space:nowrap;">+ Neu</button>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="aktuellesKategorieDelete()" title="Ausgewählte Kategorie löschen" style="white-space:nowrap;">🗑</button>' +
      '</div>' +
      '<p class="field-hint">Neue Kategorie über „+ Neu" anlegen, ausgewählte über 🗑 löschen (nur möglich, wenn kein Beitrag sie mehr verwendet).</p>' +
    '</div>';
  }

  // Gleiches Kategorie-Dropdown wie fKategorieDropdown oben, nur für Termine
  // (window.termineKategorieAdd/-Delete, Feld-ID f-t-kategorie).
  function fTermineKategorieDropdown(val) {
    var options = alleTermineKategorien();
    if (val && options.indexOf(val) === -1) options = options.concat([val]);
    var opts = options.map(function(o) {
      return '<option value="' + escAttr(o) + '"' + (o === val ? ' selected' : '') + '>' + escHtml(o) + '</option>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-t-kategorie">Kategorie</label>' +
      '<div style="display:flex;gap:.5rem;align-items:center;">' +
        '<select class="field-input" id="f-t-kategorie" style="flex:1;">' + opts + '</select>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="termineKategorieAdd()" style="white-space:nowrap;">+ Neu</button>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="termineKategorieDelete()" title="Ausgewählte Kategorie löschen" style="white-space:nowrap;">🗑</button>' +
      '</div>' +
      '<p class="field-hint">Neue Kategorie über „+ Neu" anlegen, ausgewählte über 🗑 löschen (nur möglich, wenn kein Termin sie mehr verwendet).</p>' +
    '</div>';
  }

  // Gleiches Kategorie-Dropdown wie fKategorieDropdown/fTermineKategorieDropdown
  // oben, nur für Service-Beiträge (window.serviceKategorieAdd/-Delete,
  // Feld-ID f-svb-kategorie). Frank-Wunsch 21.08.2026: Service-Admin soll sich
  // genau wie Aktuelles bedienen lassen.
  function fServiceKategorieDropdown(val) {
    var options = alleServiceKategorien();
    if (val && options.indexOf(val) === -1) options = options.concat([val]);
    var opts = options.map(function(o) {
      return '<option value="' + escAttr(o) + '"' + (o === val ? ' selected' : '') + '>' + escHtml(o) + '</option>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-svb-kategorie">Kategorie</label>' +
      '<div style="display:flex;gap:.5rem;align-items:center;">' +
        '<select class="field-input" id="f-svb-kategorie" style="flex:1;">' + opts + '</select>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="serviceKategorieAdd()" style="white-space:nowrap;">+ Neu</button>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="serviceKategorieDelete()" title="Ausgewählte Kategorie löschen" style="white-space:nowrap;">🗑</button>' +
      '</div>' +
      '<p class="field-hint">Neue Kategorie über „+ Neu" anlegen, ausgewählte über 🗑 löschen (nur möglich, wenn kein Beitrag sie mehr verwendet).</p>' +
    '</div>';
  }

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

  // 23.08.2026 (Laurin-Wunsch "immer im gleichen Stil"): Unterseiten-Kasten-
  // Titel + Link-Liste sollen bei JEDER Seite erscheinen, die selbst ein
  // eigenes "➕ Neue Unterseite"-System hat - nicht nur bei den vier
  // Verbraucher-Hauptseiten (wo das zuerst gebaut wurde), sondern genauso
  // bei den 14 Jäger-/Aufgaben-Seiten, die am 23.08.2026 auf dasselbe
  // Muster umgestellt wurden. Strukturelle Prüfung statt Namens-Präfix:
  // "hat diese Seite ein Kind-Element, das der Unterseiten-Anlege-Button
  // ist?" - dadurch automatisch korrekt für jede künftige Seite mit
  // eigenem Unterseiten-System, ohne dass hier jedes Mal ein neuer Key
  // ergänzt werden muss.
  function hatUnterseitenSystem(def) {
    return !!(def && Array.isArray(def.children) && def.children.some(function(c) {
      return c && c.isAdd && c.form === 'neueSeite';
    }));
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
          // Menü-Bezeichnung: nur bei dynamisch angelegten Unterseiten (isDynamic)
          // editierbar - bei den fest verdrahteten Hauptseiten gibt es kein Menü-
          // Manifest, das dieses Feld lesen würde. Ohne dieses Feld war ein
          // Tippfehler in der Menü-Bezeichnung/URL nur durch Löschen+Neuanlegen
          // korrigierbar (Frank-Beispiel Waidmannssprache, 22.08.2026) - jetzt
          // direkt im Formular korrigierbar, wird beim Speichern zusätzlich ins
          // Manifest zurückgeschrieben (siehe saveCurrentSection).
          (def.isDynamic
            ? fH(fText('nav_label', 'Menü-Bezeichnung', data.nav_label),
                'Wie die Seite im Menü, in der Seitenleiste "Weitere Seiten" und in der Brotkrumen-Navigation heißt. Meist wie der Seitentitel, kann aber abweichen.')
            : '') +
          fH(fTipTap('untertitel', 'Untertitel', false),
            'Kurzer Text direkt unter dem Titel, als Einstieg in die Seite. Optional.') +
          fH(fTipTap('intro', 'Einleitungstext', true),
            'Der erste Textblock der Seite, oberhalb des Hauptinhalts. Formatierung, Listen und Bilder sind möglich.') +
          fH(fTipTap('inhalt', 'Textinhalt', true),
            'Der Haupttext der Seite. Hier kommt der eigentliche Inhalt rein – mit Formatierung, Listen, Tabellen und Bildern (frei verschiebbar, wie bei Untertitel/Einleitungstext).') +
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
          // "Ohne Rahmen": nur für Jagdhundeschule-Seiten, da aktuell nur dort
          // (aufgaben/jagdhundeschule.html) die Frontend-Vorlage dieses Feld
          // auch tatsächlich auswertet. Für Logos/Grafiken mit eigenem weißen
          // Hintergrund (z.B. Landesjagdverband-Logo), wo der sonst überall
          // sinnvolle Schatten+Rundung wie ein Kasten gegen die weiße Seite
          // wirkt (Frank-Feedback 19.08.2026).
          (def.key && def.key.indexOf('jagdhundeschule') !== -1
            ? fH(fToggle('bild_flat', 'Inhaltsbild ohne Rahmen/Schatten', !!data.bild_flat),
              'Für Logos oder Grafiken mit eigenem weißen Hintergrund aktivieren, damit kein sichtbarer Kasten gegen die weiße Seite entsteht.')
            : '') +
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
        // Unterseiten-Kasten-Titel + Link-Liste (Seitenleiste): auf allen vier
        // Verbraucher-Hauptseiten gleich (Laurin-Wunsch 23.08.2026: "überall
        // im gleichen Stil arbeiten" - vorher nur bei Wildfleisch vorhanden,
        // was inkonsistent war).
        (hatUnterseitenSystem(def)
          ? '<div class="form-card">' +
              '<div class="form-card-title">📄 Unterseiten-Kasten (Seitenleiste)</div>' +
              fText('unterseiten_titel', 'Überschrift des Kastens', data.unterseiten_titel, 'Unterseiten zu ' + (data.titel || def.label)) +
            '</div>'
          : '') +
        (hatUnterseitenSystem(def) ? renderLinklisteCard(data) : '') +
        renderDownloadsCard(data) +
        (def.key === 'mitglied-werden' ?
          '<div class="form-card">' +
            '<div class="form-card-title">Mitgliedsantrag</div>' +
            fText('antrag_url', 'Mitgliedsantrag-URL', data.antrag_url, 'https://...') +
          '</div>'
        : '') +
        // Hundebörse-Verweis (Seitenleiste): nur auf der Hundevermittlung-Seite
        // (Frank behält seinen eigenen Redaktionstext auf dieser Seite - die
        // Hundebörse ersetzt sie nicht, sondern wird nur als grüner CTA-Kasten
        // in der Seitenleiste verlinkt, Laurin-Wunsch 29.08.2026). Titel/Text/
        // Button-Beschriftung sind hier admin-editierbar über das ganz normale
        // Seiten-Formular ("keine neue parallele Content-Verwaltung") - das
        // Linkziel selbst ist bewusst fest verdrahtet auf die Hundebörse-
        // Übersicht (siehe seiten/index.html renderHundeboerseCtaSidebar()).
        // Leer lassen = Kasten wird nicht angezeigt.
        (def.slug === 'hundevermittlung' ?
          '<div class="form-card">' +
            '<div class="form-card-title">🐕 Hundebörse-Verweis (Seitenleiste)</div>' +
            fH(fText('hundeboerse_cta_titel', 'Überschrift', data.hundeboerse_cta_titel, 'Hundebörse'),
              'Überschrift des grünen Hinweiskastens in der Seitenleiste.') +
            fH(fText('hundeboerse_cta_text', 'Text', data.hundeboerse_cta_text, 'Aktuelle Jagdhunde und Würfe entdecken oder selbst eine Anzeige aufgeben.'),
              'Kurzer erklärender Text unter der Überschrift.') +
            fH(fText('hundeboerse_cta_button', 'Button-Beschriftung', data.hundeboerse_cta_button, 'ZUR HUNDEBÖRSE →'),
              'Beschriftung des Buttons, der zur Hundebörse-Übersicht führt. Leer lassen, um den Kasten auszublenden.') +
          '</div>'
        : '') +
      '</div>' +
      saveBar();
    renderMain(html);
    initTiptap('untertitel', data.untertitel || '');
    initTiptap('intro',      data.intro      || '');
    initTiptap('inhalt',     data.inhalt     || '');
    initDownloadsSortable();
    initLinklisteSortable();
    bindSaveBtn();
  }

  function collectStandard(data) {
    data.titel         = gv('titel');
    data.untertitel    = getTiptapValue('untertitel', data.untertitel, 'Untertitel');
    data.intro         = getTiptapValue('intro',      data.intro,      'Einleitungstext');
    data.inhalt        = getTiptapValue('inhalt',     data.inhalt,     'Textinhalt');
    data.hero_bild     = gv('hero_bild');
    data.bild          = gv('bild');
    data.bild_groesse  = gv('bild_groesse');
    data.bild_alt      = gv('bild_alt');
    data.kontakt_name  = gv('kontakt_name');
    data.kontakt_email = gv('kontakt_email');
    if (S.section && S.section.isDynamic) {
      data.nav_label = gv('nav_label').trim() || data.titel;
    }
    if (S.section && S.section.key === 'mitglied-werden') {
      data.antrag_url = gv('antrag_url');
    }
    if (S.section && S.section.slug === 'hundevermittlung') {
      data.hundeboerse_cta_titel  = gv('hundeboerse_cta_titel');
      data.hundeboerse_cta_text   = gv('hundeboerse_cta_text');
      data.hundeboerse_cta_button = gv('hundeboerse_cta_button');
    }
    // Jagdhundeschule-spezifische Felder (Kachel-Vorschau + Bild ohne Rahmen)
    if (S.section && S.section.key && S.section.key.indexOf('jagdhundeschule') !== -1) {
      data.vorschaubild     = gv('vorschaubild');
      data.kurzbeschreibung = gv('kurzbeschreibung');
      data.bild_flat        = toggleVal('bild_flat');
    }
    if (id('f-unterseiten_titel')) {
      data.unterseiten_titel = gv('unterseiten_titel');
    }
    data.downloads = collectDownloadsList();
    data.galerie = collectGalerieList();
    data.galerie_titel = collectGalerieTitel();
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     SERVICE – Beiträge mit Kategorie (Frank-Wunsch 21.08.2026: Admin-
     Aufbau an Aktuelles angleichen, damit das CMS überall gleich zu
     bedienen ist, auch wenn ein Nachfolger es übernehmen muss). Löst
     das frühere zweistufige Schema "Kategorie enthält mehrere
     Dokumente" ab durch eine flache Liste data.beitraege = [{ titel,
     datum, kategorie, text, video, downloads:[...], archiviert }] –
     ein Beitrag hat wie bei Aktuelles GENAU EINE Kategorie. Dieser
     Block ist bewusst eine Eins-zu-eins-Kopie des renderAktuelles/
     aktuellesEdit-Aufbaus weiter oben. Kategorien werden separat in
     data.einstellungen.kategorien verwaltet (siehe
     alleServiceKategorien/serviceKategorieAdd/-Delete oben). Downloads
     pro Beitrag laufen über dieselbe renderDownloadsCard()/
     collectDownloadsList()-Komponente wie bei Aktuelles.
  ──────────────────────────────────────────────────────────── */

  /* ────────────────────────────────────────────────────────────
     SERVICE – Admin-UX 22.08.2026 auf das Aktuelles-Muster angeglichen
     (Frank-Wunsch: "gleichbleibendes System" über alle CMS-Bereiche):
       - Jahr-/Kategorie-Filter oben wie bei Aktuelles (renderAktuelles).
       - Erscheinungsjahr-Feld pro Beitrag wie bei Aktuelles.
       - Archivierung NICHT mehr als Badge in derselben Liste (das alte
         Muster), sondern als eigene Archiv-Unterseite/"Unterordner" wie
         bei Medien & Bilder (medienArchivSeiteOeffnen) - archivierte
         Beiträge verschwinden aus der normalen Liste und tauchen nur noch
         auf der Archiv-Unterseite auf.
     S.svcAnsicht ('alle'|'archiv') merkt sich, welche der beiden Ansichten
     gerade offen ist, damit z.B. der "← Zurück"-Button beim Bearbeiten
     eines archivierten Beitrags wieder zur Archiv-Unterseite zurückführt
     und nicht immer zur normalen Liste (analog zu
     medienAktuelleAnsichtNeuRendern für Medien & Bilder).
  ──────────────────────────────────────────────────────────── */
  function serviceAktuelleAnsichtRendern() {
    if (S.svcAnsicht === 'archiv') serviceArchivSeiteOeffnen();
    else renderService(S.section, S.data);
  }

  function renderService(def, data) {
    S.svcAnsicht = 'alle';
    var beitraege = data.beitraege || [];
    S.svcFilterJahr = S.svcFilterJahr || '';
    S.svcFilterKat  = S.svcFilterKat  || '';

    var indexed = beitraege.map(function(b, i) {
      return { b: b, i: i, jahr: b.jahr || jahrAusDatum(b.datum), iso: datumToIsoFlexible(b.datum) };
    });
    var aktive = indexed.filter(function(e) { return !e.b.archiviert; });
    var archivAnzahl = indexed.length - aktive.length;

    var jahre = [];
    aktive.forEach(function(e) { if (e.jahr && jahre.indexOf(e.jahr) === -1) jahre.push(e.jahr); });
    jahre.sort(function(a, b) { return b - a; });

    var gefiltert = aktive.filter(function(e) {
      if (S.svcFilterJahr && e.jahr !== S.svcFilterJahr) return false;
      if (S.svcFilterKat && (e.b.kategorie || '') !== S.svcFilterKat) return false;
      return true;
    });
    gefiltert.sort(function(a, b) {
      if (a.iso && b.iso) return b.iso.localeCompare(a.iso);
      if (a.iso) return -1;
      if (b.iso) return 1;
      return a.i - b.i;
    });

    var jahrOptions = '<option value="">Alle Jahre</option>' + jahre.map(function(j) {
      return '<option value="' + escAttr(j) + '"' + (j === S.svcFilterJahr ? ' selected' : '') + '>' + escHtml(j) + '</option>';
    }).join('');
    var katOptions = '<option value="">Alle Kategorien</option>' + alleServiceKategorien().map(function(k) {
      return '<option value="' + escAttr(k) + '"' + (k === S.svcFilterKat ? ' selected' : '') + '>' + escHtml(k) + '</option>';
    }).join('');

    var html = panelHeader(def.label,
      '<button type="button" class="btn btn-outline btn-sm" onclick="confirmNav(serviceArchivSeiteOeffnen)">📁 Archivierte Beiträge (' + archivAnzahl + ')</button>' +
      '<button class="btn btn-primary" onclick="confirmNav(serviceNeu)">➕ Neuer Beitrag</button>') +
      '<div class="panel-body">' +

      // ── Seiteneinstellungen (Titel/Hero-Bild/Kontakt) ───────
      '<div class="form-card">' +
        '<div class="form-card-title">⚙️ Seiteneinstellungen</div>' +
        fText('sv-titel', 'Seitentitel', data.titel) +
        fImage('sv-hero_bild', 'Hero-Hintergrundbild', data.hero_bild) +
        fText('sv-kontakt_name', 'Kontaktname (optional)', data.kontakt_name) +
        fText('sv-kontakt_email', 'Kontakt E-Mail (optional)', data.kontakt_email) +
        '<button class="btn btn-sm btn-outline" onclick="serviceEinstSave()">Speichern</button>' +
      '</div>' +

      // ── Jahr-/Kategorie-Filter (wie bei Aktuelles) ──────────
      '<div class="form-card">' +
        '<div class="form-card-title">🔍 Filtern &amp; Sortieren</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;font-size:.85rem;">Die Liste ist immer nach Datum sortiert (neueste zuerst). Optional zusätzlich nach Jahr und/oder Kategorie filtern.</p>' +
        '<div class="field-row" style="align-items:center;gap:1rem;flex-direction:row;flex-wrap:wrap;">' +
          '<select class="field-input" id="svc-filter-jahr" style="max-width:180px;" onchange="serviceFilterChange()">' + jahrOptions + '</select>' +
          '<select class="field-input" id="svc-filter-kat" style="max-width:220px;" onchange="serviceFilterChange()">' + katOptions + '</select>' +
          (S.svcFilterJahr || S.svcFilterKat
            ? '<button class="btn btn-sm btn-ghost" onclick="serviceFilterReset()">✕ Filter zurücksetzen</button>'
            : '') +
        '</div>' +
      '</div>' +

      '<p class="text-muted" style="margin-bottom:1rem;">' + gefiltert.length + ' von ' + aktive.length + ' Beiträgen. Klicken zum Bearbeiten.</p>';

    gefiltert.forEach(function(entry) {
      var b = entry.b, i = entry.i;
      html += '<div class="item-card" onclick="confirmNav(function(){serviceEdit(' + i + ')})">' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(b.titel || '(Kein Titel)') + '</div>' +
          '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
            (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-ghost" title="Ins Archiv verschieben" onclick="event.stopPropagation();confirmNav(function(){serviceArchivToggle(' + i + ')})">📦 Archivieren</button>' +
          '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmNav(function(){serviceEdit(' + i + ')})">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();confirmNav(function(){serviceDelete(' + i + ')})">Löschen</button>' +
        '</div>' +
      '</div>';
    });

    if (!gefiltert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine Beiträge in dieser Filteransicht.</p></div>';
    }

    html += '</div>';
    renderMain(html);
  }

  window.serviceFilterChange = function() {
    S.svcFilterJahr = val('svc-filter-jahr');
    S.svcFilterKat  = val('svc-filter-kat');
    renderService(S.section, S.data);
  };
  window.serviceFilterReset = function() {
    S.svcFilterJahr = '';
    S.svcFilterKat  = '';
    renderService(S.section, S.data);
  };

  // Eigene "Unterordner"-Ansicht statt Inline-Badge - ersetzt admin-main
  // komplett, mit "← Zurück"-Button zur normalen Service-Übersicht
  // (renderService). Gleiches Muster wie medienArchivSeiteOeffnen().
  window.serviceArchivSeiteOeffnen = function() {
    S.svcAnsicht = 'archiv';
    var beitraege = S.data.beitraege || [];
    var indexed = beitraege.map(function(b, i) { return { b: b, i: i, iso: datumToIsoFlexible(b.datum) }; });
    var archiviert = indexed.filter(function(e) { return e.b.archiviert; });
    archiviert.sort(function(a, b) {
      if (a.iso && b.iso) return b.iso.localeCompare(a.iso);
      if (a.iso) return -1;
      if (b.iso) return 1;
      return a.i - b.i;
    });

    var html = '<div class="panel-header"><h2>📦 Archivierte Beiträge</h2></div>' +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<button type="button" class="btn btn-outline btn-sm" style="margin-bottom:1rem;" onclick="confirmNav(function(){renderService(S.section,S.data)})">← Zurück zu Service</button>' +
          '<p class="text-muted" style="margin-bottom:0;font-size:.85rem;">Archivierte Beiträge erscheinen nicht mehr auf der Service-Seite. Über „↩️ Wiederherstellen" lassen sie sich jederzeit zurückholen.</p>' +
        '</div>';

    if (!archiviert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine archivierten Beiträge.</p></div>';
    } else {
      archiviert.forEach(function(entry) {
        var b = entry.b, i = entry.i;
        html += '<div class="item-card" onclick="confirmNav(function(){serviceEdit(' + i + ')})">' +
          '<div class="item-body">' +
            '<div class="item-title">' + escHtml(b.titel || '(Kein Titel)') + '</div>' +
            '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
              (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
            '</div>' +
          '</div>' +
          '<div class="item-actions">' +
            '<button class="btn btn-sm btn-outline" title="Aus Archiv zurückholen" onclick="event.stopPropagation();confirmNav(function(){serviceArchivToggle(' + i + ')})">↩️ Wiederherstellen</button>' +
            '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmNav(function(){serviceEdit(' + i + ')})">Bearbeiten</button>' +
            '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();confirmNav(function(){serviceDelete(' + i + ')})">Löschen</button>' +
          '</div>' +
        '</div>';
      });
    }
    html += '</div>';
    renderMain(html);
  };

  window.serviceEinstSave = async function() {
    S.data.titel          = gv('sv-titel');
    S.data.hero_bild      = gv('sv-hero_bild');
    S.data.kontakt_name   = gv('sv-kontakt_name');
    S.data.kontakt_email  = gv('sv-kontakt_email');
    try {
      await doSave(S.section.file, S.data, '⚙️ Service: Seiteneinstellungen gespeichert');
      toast('✅ Einstellungen gespeichert!', 'ok');
      S.dirty = false;
    } catch (e) { await handleSaveError(e); }
  };

  window.serviceNeu = function() {
    var data = S.data;
    data.beitraege = data.beitraege || [];
    var newB = { titel:'', datum:'', jahr: String(new Date().getFullYear()), kategorie:(alleServiceKategorien()[0] || 'Allgemein'), text:'', video:'', downloads:[], archiviert:false };
    data.beitraege.unshift(newB);
    serviceEdit(0);
  };

  window.serviceEdit = function(idx) {
    destroyMDE();
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    var html = panelHeader('🧰 Beitrag bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(serviceAktuelleAnsichtRendern)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="serviceSave(' + idx + ')">💾 Speichern</button>',
        true) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="field-row">' +
            '<label class="field-label" for="svb-jahr">Erscheinungsjahr</label>' +
            '<input class="field-input" type="number" id="svb-jahr" value="' + escAttr(b.jahr || jahrAusDatum(b.datum)) + '" placeholder="' + escAttr(jahrAusDatum(b.datum) || String(new Date().getFullYear())) + '" style="max-width:140px">' +
            '<p class="field-hint">Bestimmt, in welchem Archiv-Jahr der Beitrag einsortiert wird (unabhängig vom Datum unten). Normalerweise gleich dem Jahr des Datums.</p>' +
          '</div>' +
          fDate('svb-datum', 'Datum', b.datum) +
          fText('svb-titel', 'Titel', b.titel) +
          fServiceKategorieDropdown(b.kategorie) +
          fMarkdown('svb-text', 'Einleitungstext (Markdown)', b.text) +
          fText('svb-video', 'YouTube-Video-Link (optional)', b.video) +
          '<div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">' +
            '<label class="field-label" style="min-width:160px;margin:0">Ins Archiv verschieben</label>' +
            '<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">' +
              '<input type="checkbox" id="svb-archiviert"' + (b.archiviert ? ' checked' : '') + ' style="width:18px;height:18px;cursor:pointer;">' +
              '<span style="font-size:.85rem;color:var(--text-muted);">Erscheint nicht mehr auf der Service-Seite, bleibt im Archiv sichtbar</span>' +
            '</label>' +
          '</div>' +
        '</div>' +
        renderDownloadsCard(b) +
      '</div>';
    renderMain(html);
    initMDE('svb-text');
    initDownloadsSortable();
  };

  window.serviceSave = async function(idx) {
    var b = S.data.beitraege[idx];
    b.titel      = gv('svb-titel');
    b.datum      = isoToDatum(gv('svb-datum'));
    b.jahr       = gv('svb-jahr') || jahrAusDatum(b.datum);
    b.kategorie  = gv('svb-kategorie');
    b.text       = getMDE();
    b.video      = gv('svb-video');
    var archCheck = id('svb-archiviert');
    b.archiviert = archCheck ? archCheck.checked : (b.archiviert || false);
    b.downloads  = collectDownloadsList();
    try {
      await doSave(S.section.file, S.data, '🧰 Service: Beitrag gespeichert');
      toast('✅ Beitrag gespeichert!', 'ok');
      S.dirty = false;
      serviceAktuelleAnsichtRendern();
    } catch (e) { await handleSaveError(e); }
  };

  window.serviceArchivToggle = async function(idx) {
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    b.archiviert = !b.archiviert;
    try {
      await doSave(S.section.file, S.data, '🧰 Service: Archivstatus geändert');
      toast(b.archiviert ? '📦 Ins Archiv verschoben' : '↩️ Aus Archiv zurückgeholt', 'ok');
      serviceAktuelleAnsichtRendern();
    } catch (e) {
      b.archiviert = !b.archiviert; // lokale, optimistische Änderung zurücknehmen - nicht gespeichert
      await handleSaveError(e);
    }
  };

  window.serviceDelete = function(idx) {
    showConfirm('Beitrag löschen', 'Diesen Beitrag wirklich löschen?', async function() {
      var entfernt = S.data.beitraege.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '🧰 Service: Beitrag gelöscht');
        toast('🗑️ Beitrag gelöscht', 'info');
        serviceAktuelleAnsichtRendern();
      } catch (e) {
        if (entfernt.length) S.data.beitraege.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     HUNDEBÖRSE – Phase 1 (Admin-Verwaltung)
     28.08.2026: Neues Modul nach Frank-Briefing, technisch 1:1 nach dem
     Service-Muster gebaut (ein JSON mit data.anzeigen = [...], Status-
     Filterleiste statt Jahr/Kategorie, eigene Bearbeiten-Vollansicht,
     bestehende Feld-/Galerie-Komponenten wiederverwendet). Die eigentliche
     öffentliche Einreichung/Speicherung ist bewusst NICHT Teil dieser
     Phase – die Server-/Datenbank-Architektur wird separat mit Carsten
     geklärt (siehe HUNDEBOERSE-KONZEPT.md). Diese Admin-Verwaltung dient
     zunächst zum Anlegen/Testen von Anzeigen auf Staging.
     Bilder laufen bewusst über dieselbe Bildergalerie-Komponente wie bei
     Aktuelles/Service (data.galerie / data.galerie_titel), keine eigene
     Kopie – erstes Bild in der Liste gilt als Hauptbild.
  ──────────────────────────────────────────────────────────── */
  var HB_STATUS = [
    { value:'pending',   label:'Wartet auf Freigabe' },
    { value:'published', label:'Veröffentlicht' },
    { value:'rejected',  label:'Abgelehnt' },
    { value:'archived',  label:'Archiviert' }
  ];
  function hbStatusLabel(s) {
    var m = HB_STATUS.filter(function(x) { return x.value === s; })[0];
    return m ? m.label : (s || 'Entwurf');
  }
  function hbTypLabel(t) { return t === 'litter' ? 'Wurf' : 'Einzelhund'; }
  function hbPreisText(a) {
    if (a.priceType === 'fixed' || a.priceType === 'negotiable') {
      var txt = a.price ? (a.price + ' €') : '';
      return a.priceType === 'negotiable' ? (txt ? txt + ' VB' : 'VB') : txt;
    }
    if (a.priceType === 'on_request') return 'Auf Anfrage';
    return '';
  }
  function hbDatumAnzeige(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
  }

  function renderHundeboerse(def, data) {
    var anzeigen = data.anzeigen || [];
    S.hbFilterStatus = S.hbFilterStatus || '';

    var indexed = anzeigen.map(function(a, i) { return { a: a, i: i }; });
    var counts = { alle: indexed.length, pending: 0, published: 0, rejected: 0, archived: 0 };
    indexed.forEach(function(e) {
      if (counts[e.a.status] !== undefined) counts[e.a.status]++;
    });

    var gefiltert = S.hbFilterStatus
      ? indexed.filter(function(e) { return e.a.status === S.hbFilterStatus; })
      : indexed;
    gefiltert.sort(function(x, y) {
      var dx = x.a.createdAt || '', dy = y.a.createdAt || '';
      if (dx && dy) return dy.localeCompare(dx);
      if (dx) return -1;
      if (dy) return 1;
      return y.i - x.i;
    });

    function tab(value, label, count) {
      var active = S.hbFilterStatus === value;
      return '<button type="button" class="btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline') +
        '" onclick="hundeboerseFilter(\'' + value + '\')" style="margin:0 .4rem .4rem 0;">' +
        escHtml(label) + ' (' + count + ')</button>';
    }

    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="confirmNav(hundeboerseNeu)">➕ Neue Anzeige</button>') +
      '<div class="panel-body">' +
      '<div class="form-card">' +
        '<div class="form-card-title">🖼️ Hero-Bild (Hundebörse-Übersicht &amp; Detailseiten)</div>' +
        '<p class="field-hint">Wird als Kopfbild oben auf der öffentlichen Hundebörse-Übersicht und den Anzeigen-Detailseiten verwendet. Ohne eigenes Bild wird das bisherige Standard-Hintergrundbild genutzt.</p>' +
        fImage('hb-hero_bild', 'Hero-Hintergrundbild', data.hero_bild) +
        '<button class="btn btn-outline btn-sm" onclick="hundeboerseHeroSave()">💾 Hero-Bild speichern</button>' +
      '</div>' +
      '<div class="form-card">' +
        '<div class="form-card-title">🔍 Status</div>' +
        '<div style="display:flex;flex-wrap:wrap;">' +
          tab('', 'Alle', counts.alle) +
          tab('pending', 'Wartet auf Freigabe', counts.pending) +
          tab('published', 'Veröffentlicht', counts.published) +
          tab('rejected', 'Abgelehnt', counts.rejected) +
          tab('archived', 'Archiviert', counts.archived) +
        '</div>' +
      '</div>' +
      '<p class="text-muted" style="margin-bottom:1rem;">' + gefiltert.length + ' von ' + counts.alle + ' Anzeigen. Klicken zum Bearbeiten.</p>';

    gefiltert.forEach(function(entry) {
      var a = entry.a, i = entry.i;
      var bild = (a.galerie && a.galerie[0] && a.galerie[0].bild) || '';
      var thumb = bild
        ? '<img src="' + escAttr(bild) + '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0;">'
        : '<div style="width:48px;height:48px;border-radius:8px;flex-shrink:0;background:var(--bg);"></div>';
      var metaParts = [];
      if (a.breed) metaParts.push(escHtml(a.breed));
      if (a.city || a.postalCode) metaParts.push(escHtml([a.postalCode, a.city].filter(Boolean).join(' ')));
      if (a.createdAt) metaParts.push('📅 ' + escHtml(hbDatumAnzeige(a.createdAt)));

      html += '<div class="item-card" onclick="confirmNav(function(){hundeboerseEdit(' + i + ')})">' +
        thumb +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(a.title || '(Kein Titel)') +
            '<span class="item-badge">' + hbTypLabel(a.type) + '</span>' +
            '<span class="item-badge">' + hbStatusLabel(a.status) + '</span>' +
          '</div>' +
          '<div class="item-meta">' + metaParts.join(' · ') + '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-ghost" onclick="event.stopPropagation();hundeboerseVorschau(' + i + ')">Vorschau</button>' +
          '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmNav(function(){hundeboerseEdit(' + i + ')})">Bearbeiten</button>' +
          (a.status === 'pending'
            ? '<button class="btn btn-sm btn-primary" onclick="event.stopPropagation();confirmNav(function(){hundeboerseFreigeben(' + i + ')})">Freigeben</button>'
            : '') +
          (a.status === 'published' || a.status === 'rejected'
            ? '<button class="btn btn-sm btn-ghost" onclick="event.stopPropagation();confirmNav(function(){hundeboerseArchivieren(' + i + ')})">Archivieren</button>'
            : '') +
          '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();confirmNav(function(){hundeboerseDelete(' + i + ')})">Löschen</button>' +
        '</div>' +
      '</div>';
    });

    if (!gefiltert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine Anzeigen in dieser Ansicht.</p></div>';
    }
    html += '</div>';
    renderMain(html);
  }

  window.hundeboerseFilter = function(status) {
    S.hbFilterStatus = status;
    renderHundeboerse(S.section, S.data);
  };

  // Hero-Bild ist ein Feld auf oberster Ebene von content/hundeboerse.json
  // (neben "anzeigen"), unabhängig von einzelnen Anzeigen - eigener kleiner
  // Speichern-Button statt über die generische saveCurrentSection()-Logik,
  // da renderHundeboerse() komplett eigenständig ist (kein saveBar()).
  // Nutzt den bestehenden fImage()/openImgPicker()-Baustein, kein neues
  // Mediensystem (Laurin-Vorgabe, Phase 3, 28.08.2026).
  window.hundeboerseHeroSave = async function() {
    S.data.hero_bild = gv('hb-hero_bild');
    try {
      await doSave(S.section.file, S.data, '🐕 Hundebörse: Hero-Bild aktualisiert');
      renderHundeboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.hundeboerseNeu = function() {
    var data = S.data;
    data.anzeigen = data.anzeigen || [];
    var now = new Date().toISOString();
    var neu = {
      id: 'hb-' + Date.now(),
      status: 'pending',
      createdAt: now, updatedAt: now,
      type: 'single', title: '', breed: '', color: '', coat: '',
      priceType: 'on_request', price: '',
      postalCode: '', city: '', description: '',
      father: '', fatherTests: '', mother: '', motherTests: '',
      hasZuchtverband: false, zuchtverband: '',
      huntingTests: '', trainingLevel: '',
      providerName: '', contactPerson: '', email: '', phone: '', contactNotes: '',
      dogName: '', birthDate: '', gender: '',
      litterDate: '', maleCount: '', femaleCount: '',
      galerie: [], galerie_titel: 'Bilder'
    };
    data.anzeigen.unshift(neu);
    hundeboerseEdit(0);
  };

  window.hundeboerseEdit = function(idx) {
    destroyMDE();
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    var isLitter = a.type === 'litter';
    var titel = a.title ? ('Anzeige bearbeiten: ' + a.title) : 'Neue Anzeige';

    var html = panelHeader(titel,
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderHundeboerse(S.section,S.data)})">← Zurück zur Hundebörse</button>' +
        '<button class="btn btn-outline" onclick="hundeboerseVorschau(' + idx + ')">Vorschau</button>' +
        '<button class="btn btn-outline" onclick="hundeboerseSave(' + idx + ')">💾 Speichern</button>' +
        '<button class="btn btn-primary" onclick="hundeboerseFreigebenAusEdit(' + idx + ')">Speichern &amp; Freigeben</button>',
        true) +
      '<div class="panel-body">' +

      '<div class="form-card">' +
        '<div class="form-card-title">📌 Status</div>' +
        fSelect('hb-status', 'Aktueller Status', a.status || 'pending', HB_STATUS) +
        '<p class="field-hint">Eingegangen am ' + escHtml(hbDatumAnzeige(a.createdAt)) +
          (a.updatedAt ? (' · zuletzt geändert am ' + escHtml(hbDatumAnzeige(a.updatedAt))) : '') + '</p>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">🐕 Grunddaten</div>' +
        '<div class="field-row">' +
          '<label class="field-label">Art der Anzeige</label>' +
          '<div class="mdimg-size-row" id="hb-typ-group">' +
            '<button type="button" class="mdimg-size-btn' + (!isLitter ? ' mdimg-size-btn--active' : '') + '" data-val="single" onclick="hundeboerseTypSet(\'single\')">Einzelhund</button>' +
            '<button type="button" class="mdimg-size-btn' + (isLitter ? ' mdimg-size-btn--active' : '') + '" data-val="litter" onclick="hundeboerseTypSet(\'litter\')">Wurf</button>' +
          '</div>' +
          '<input type="hidden" id="f-hb-type" value="' + escAttr(a.type || 'single') + '">' +
        '</div>' +
        fText('hb-title', 'Titel', a.title) +
        fText('hb-breed', 'Rasse', a.breed) +
        '<div id="hb-block-single" style="display:' + (isLitter ? 'none' : 'block') + '">' +
          fText('hb-dogName', 'Name des Hundes', a.dogName) +
          fDate('hb-birthDate', 'Geburtsdatum', a.birthDate) +
          fSelect('hb-gender', 'Geschlecht', a.gender || '', [{value:'',label:'Bitte wählen …'},{value:'male',label:'Rüde'},{value:'female',label:'Hündin'}]) +
        '</div>' +
        '<div id="hb-block-litter" style="display:' + (isLitter ? 'block' : 'none') + '">' +
          fDate('hb-litterDate', 'Wurfdatum', a.litterDate) +
          fText('hb-maleCount', 'Anzahl Rüden', a.maleCount) +
          fText('hb-femaleCount', 'Anzahl Hündinnen', a.femaleCount) +
        '</div>' +
        fText('hb-color', 'Farbe', a.color) +
        fText('hb-coat', 'Haarart', a.coat) +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">💶 Preis &amp; Standort</div>' +
        fSelect('hb-priceType', 'Preisart', a.priceType || 'on_request', [
          {value:'fixed',label:'Festpreis'}, {value:'negotiable',label:'Verhandlungsbasis (VB)'},
          {value:'on_request',label:'Auf Anfrage'}, {value:'none',label:'Keine Angabe / kostenlose Abgabe'}
        ]) +
        '<div id="hb-price-wrap" style="display:' + ((a.priceType === 'fixed' || a.priceType === 'negotiable') ? 'block' : 'none') + '">' +
          fText('hb-price', 'Preis (€)', a.price) +
        '</div>' +
        '<div class="field-row-2">' +
          fText('hb-postalCode', 'PLZ', a.postalCode) +
          fText('hb-city', 'Ort', a.city) +
        '</div>' +
        '<p class="field-hint">Es wird später nur der grobe Standort (PLZ/Ort) öffentlich angezeigt, keine genaue Adresse.</p>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">🎯 Jagdliche Informationen</div>' +
        fTextarea('hb-huntingTests', 'Prüfungen', a.huntingTests, 3) +
        fTextarea('hb-trainingLevel', 'Ausbildungsstand / weitere Angaben', a.trainingLevel, 3) +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">🧬 Abstammung</div>' +
        '<div class="field-row-2">' +
          fText('hb-father', 'Vater', a.father) +
          fText('hb-mother', 'Mutter', a.mother) +
        '</div>' +
        '<div class="field-row-2">' +
          fText('hb-fatherTests', 'Prüfungen Vater', a.fatherTests) +
          fText('hb-motherTests', 'Prüfungen Mutter', a.motherTests) +
        '</div>' +
        fToggle('hb-hasZuchtverband', 'Zuchtverband vorhanden?', a.hasZuchtverband === true) +
        '<div id="hb-zuchtverband-wrap" style="display:' + (a.hasZuchtverband === true ? 'block' : 'none') + '">' +
          fCombobox('hb-zuchtverband', 'Zuchtverband', a.zuchtverband, S.data.zuchtverbaende || []) +
        '</div>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">📝 Beschreibung</div>' +
        fMarkdown('hb-description', 'Freitext-Beschreibung', a.description) +
      '</div>' +

      renderHundeboerseGalerieCard(a) +

      '<div class="form-card">' +
        '<div class="form-card-title">📞 Anbieter / Kontaktdaten</div>' +
        fText('hb-providerName', 'Name des Anbieters', a.providerName) +
        fText('hb-contactPerson', 'Ansprechpartner', a.contactPerson) +
        fText('hb-email', 'E-Mail', a.email) +
        fText('hb-phone', 'Telefon/Mobil', a.phone) +
        fTextarea('hb-contactNotes', 'Weitere Hinweise (optional)', a.contactNotes, 2) +
      '</div>' +

      '<div class="form-card" style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:flex-end;">' +
        '<button class="btn btn-danger-outline" onclick="hundeboerseAblehnen(' + idx + ')">Anzeige ablehnen</button>' +
        '<button class="btn btn-outline" onclick="hundeboerseVorschau(' + idx + ')">Vorschau der Anzeige</button>' +
        '<button class="btn btn-primary" onclick="hundeboerseFreigebenAusEdit(' + idx + ')">Anzeige freigeben</button>' +
      '</div>' +
      '</div>';

    renderMain(html);
    initMDE('hb-description');
    initGalerieSortable();

    var ptEl = id('f-hb-priceType');
    if (ptEl) ptEl.addEventListener('change', function() {
      var wrap = id('hb-price-wrap');
      if (wrap) wrap.style.display = (this.value === 'fixed' || this.value === 'negotiable') ? 'block' : 'none';
    });

    // Zuchtverband-Feld nur einblenden, wenn "Ja" gewählt ist. Läuft NACH
    // dem inline onclick="toggleBtn(this)" des fToggle-Buttons (weil dieser
    // Listener erst nach dem Rendern angehängt wird), liest also bereits den
    // neuen data-val-Zustand.
    var zvBtn = id('f-hb-hasZuchtverband');
    if (zvBtn) zvBtn.addEventListener('click', function() {
      var wrap = id('hb-zuchtverband-wrap');
      if (wrap) wrap.style.display = (this.getAttribute('data-val') === '1') ? 'block' : 'none';
    });
  };

  window.hundeboerseTypSet = function(typ) {
    var typEl = id('f-hb-type');
    if (typEl) typEl.value = typ;
    document.querySelectorAll('#hb-typ-group .mdimg-size-btn').forEach(function(btn) {
      btn.classList.toggle('mdimg-size-btn--active', btn.getAttribute('data-val') === typ);
    });
    var single = id('hb-block-single'), litter = id('hb-block-litter');
    if (single) single.style.display = (typ === 'litter') ? 'none' : 'block';
    if (litter) litter.style.display = (typ === 'litter') ? 'block' : 'none';
  };

  // Wartbare Vorschlagsliste für "Zuchtverband" (28.08.2026, Laurin/Frank-
  // Wunsch nach gemeinsamer Prüfung): keine neue Datenbank/Library - die
  // bekannten Verbandsnamen leben einfach als Array "zuchtverbaende" auf
  // oberster Ebene von content/hundeboerse.json (neben "anzeigen" und
  // "hero_bild") und wachsen automatisch, sobald jemand einen noch nicht
  // bekannten Namen einträgt. fCombobox() zeigt sie als <datalist>-
  // Vorschläge, das Feld bleibt aber immer ein freies Textfeld.
  function hbZuchtverbandMerken(wert) {
    wert = (wert || '').trim();
    if (!wert) return;
    S.data.zuchtverbaende = S.data.zuchtverbaende || [];
    var vorhanden = S.data.zuchtverbaende.some(function(v) {
      return v.toLowerCase() === wert.toLowerCase();
    });
    if (!vorhanden) {
      S.data.zuchtverbaende.push(wert);
      S.data.zuchtverbaende.sort(function(a, b) { return a.localeCompare(b, 'de'); });
    }
  }

  // Geokodierung für die Kartenansicht auf der öffentlichen Detailseite
  // (31.08.2026, Laurin-Wunsch "interaktive Karte"). Bewusst NICHT bei jedem
  // Seitenaufruf der Detailseite (das wäre ein Live-Request pro Website-
  // Besucher an einen fremden Dienst und würde Nominatims Nutzungsrichtlinien
  // widersprechen), sondern einmalig hier beim Admin-Speichern: PLZ+Ort
  // werden über den öffentlichen OpenStreetMap-Dienst Nominatim in
  // Koordinaten umgerechnet und zusammen mit der Anzeige abgelegt. Die
  // Detailseite lädt dann nur noch Kartenkacheln, keinen Geokodierungs-
  // Request. Datenschutz: es wird ausschließlich PLZ+Ort übertragen, nie
  // eine genaue Adresse (die gibt es in den Anzeigedaten auch gar nicht).
  // Schlägt die Geokodierung fehl (kein Treffer, Timeout, Netzwerkfehler),
  // wird einfach nicht blockierend gespeichert wie bisher - nur eben ohne
  // Koordinaten; die Detailseite zeigt dann den bisherigen Text ohne Karte.
  async function hbGeocode(postalCode, city) {
    postalCode = (postalCode || '').trim();
    city = (city || '').trim();
    if (!postalCode && !city) return null;
    try {
      var params = new URLSearchParams({ format: 'json', limit: '1', country: 'Deutschland' });
      if (postalCode) params.set('postalcode', postalCode);
      if (city) params.set('city', city);
      var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      var timer = ctrl ? setTimeout(function() { ctrl.abort(); }, 8000) : null;
      var res = await fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), {
        signal: ctrl ? ctrl.signal : undefined
      });
      if (timer) clearTimeout(timer);
      if (!res.ok) return null;
      var arr = await res.json();
      if (!arr || !arr.length) return null;
      var lat = parseFloat(arr[0].lat);
      var lng = parseFloat(arr[0].lon);
      if (!isFinite(lat) || !isFinite(lng)) return null;
      return { lat: lat, lng: lng };
    } catch (e) {
      return null;
    }
  }

  async function hundeboerseCollect(idx) {
    var a = S.data.anzeigen[idx];
    var vorherigePLZ  = a.postalCode;
    var vorherigerOrt = a.city;
    a.status        = gv('hb-status');
    a.type          = val('f-hb-type') || 'single';
    a.title         = gv('hb-title');
    a.breed         = gv('hb-breed');
    a.color         = gv('hb-color');
    a.coat          = gv('hb-coat');
    a.dogName       = gv('hb-dogName');
    a.birthDate     = isoToDatum(gv('hb-birthDate'));
    a.gender        = gv('hb-gender');
    a.litterDate    = isoToDatum(gv('hb-litterDate'));
    a.maleCount     = gv('hb-maleCount');
    a.femaleCount   = gv('hb-femaleCount');
    a.priceType     = gv('hb-priceType');
    a.price         = gv('hb-price');
    a.postalCode    = gv('hb-postalCode');
    a.city          = gv('hb-city');
    a.huntingTests  = gv('hb-huntingTests');
    a.trainingLevel = gv('hb-trainingLevel');
    a.father        = gv('hb-father');
    a.fatherTests   = gv('hb-fatherTests');
    a.mother        = gv('hb-mother');
    a.motherTests   = gv('hb-motherTests');
    a.hasZuchtverband = toggleVal('hb-hasZuchtverband');
    a.zuchtverband  = a.hasZuchtverband ? gv('hb-zuchtverband') : '';
    hbZuchtverbandMerken(a.zuchtverband);
    a.description   = getMDE();
    a.providerName  = gv('hb-providerName');
    a.contactPerson = gv('hb-contactPerson');
    a.email         = gv('hb-email');
    a.phone         = gv('hb-phone');
    a.contactNotes  = gv('hb-contactNotes');
    a.galerie       = collectGalerieList();

    // Koordinaten nur neu ermitteln, wenn nötig - nicht bei jedem Speichern
    // erneut anfragen (siehe hbGeocode oben).
    var hatOrt      = !!(a.postalCode || a.city);
    var ortGeaendert = (a.postalCode !== vorherigePLZ) || (a.city !== vorherigerOrt);
    if (!hatOrt) {
      delete a.lat;
      delete a.lng;
    } else if (ortGeaendert || typeof a.lat !== 'number' || typeof a.lng !== 'number') {
      var koord = await hbGeocode(a.postalCode, a.city);
      if (koord) {
        a.lat = koord.lat;
        a.lng = koord.lng;
      } else {
        delete a.lat;
        delete a.lng;
      }
    }

    a.updatedAt = new Date().toISOString();
    return a;
  }

  window.hundeboerseSave = async function(idx) {
    await hundeboerseCollect(idx);
    try {
      await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige gespeichert');
      toast('✅ Anzeige gespeichert!', 'ok');
      S.dirty = false;
      renderHundeboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.hundeboerseFreigebenAusEdit = async function(idx) {
    await hundeboerseCollect(idx);
    showConfirm('Anzeige freigeben', 'Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Hundebörse erscheinen.', async function() {
      var a = S.data.anzeigen[idx];
      a.status = 'published';
      a.updatedAt = new Date().toISOString();
      try {
        await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige freigegeben');
        toast('✅ Anzeige freigegeben!', 'ok');
        S.dirty = false;
        renderHundeboerse(S.section, S.data);
      } catch (e) { await handleSaveError(e); }
    }, 'Freigeben', 'btn-primary');
  };

  window.hundeboerseFreigeben = function(idx) {
    showConfirm('Anzeige freigeben', 'Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Hundebörse erscheinen.', async function() {
      var a = (S.data.anzeigen || [])[idx];
      if (!a) return;
      a.status = 'published';
      a.updatedAt = new Date().toISOString();
      try {
        await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige freigegeben');
        toast('✅ Anzeige freigegeben!', 'ok');
        renderHundeboerse(S.section, S.data);
      } catch (e) { await handleSaveError(e); }
    }, 'Freigeben', 'btn-primary');
  };

  window.hundeboerseAblehnen = async function(idx) {
    await hundeboerseCollect(idx);
    var a = S.data.anzeigen[idx];
    a.status = 'rejected';
    a.updatedAt = new Date().toISOString();
    try {
      await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige abgelehnt');
      toast('🚫 Anzeige abgelehnt', 'info');
      S.dirty = false;
      renderHundeboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.hundeboerseArchivieren = async function(idx) {
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    a.status = 'archived';
    a.updatedAt = new Date().toISOString();
    try {
      await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige archiviert');
      toast('📦 Anzeige archiviert', 'ok');
      renderHundeboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.hundeboerseDelete = function(idx) {
    showConfirm('Anzeige löschen', 'Diese Anzeige wirklich unwiderruflich löschen?', async function() {
      var entfernt = S.data.anzeigen.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '🐕 Hundebörse: Anzeige gelöscht');
        toast('🗑️ Anzeige gelöscht', 'info');
        renderHundeboerse(S.section, S.data);
      } catch (e) {
        if (entfernt.length) S.data.anzeigen.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  window.hundeboerseVorschau = function(idx) {
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    var bilder = a.galerie || [];
    var haupt = bilder[0] ? bilder[0].bild : '';
    var thumbs = bilder.slice(1).map(function(g) {
      return '<img src="' + escAttr(g.bild) + '" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;">';
    }).join('');

    var eckdaten = [];
    if (a.breed) eckdaten.push('<strong>Rasse:</strong> ' + escHtml(a.breed));
    if (a.type === 'litter') {
      if (a.litterDate) eckdaten.push('<strong>Wurfdatum:</strong> ' + escHtml(a.litterDate));
      var counts = [];
      if (a.maleCount) counts.push(a.maleCount + ' Rüden');
      if (a.femaleCount) counts.push(a.femaleCount + ' Hündinnen');
      if (counts.length) eckdaten.push('<strong>Welpen:</strong> ' + escHtml(counts.join(', ')));
    } else {
      if (a.gender) eckdaten.push('<strong>Geschlecht:</strong> ' + (a.gender === 'male' ? 'Rüde' : 'Hündin'));
      if (a.birthDate) eckdaten.push('<strong>Geburtsdatum:</strong> ' + escHtml(a.birthDate));
    }
    if (a.color) eckdaten.push('<strong>Farbe:</strong> ' + escHtml(a.color));
    if (a.coat) eckdaten.push('<strong>Haarart:</strong> ' + escHtml(a.coat));
    var preisText = hbPreisText(a);
    if (preisText) eckdaten.push('<strong>Preis:</strong> ' + escHtml(preisText));
    if (a.city || a.postalCode) eckdaten.push('<strong>Standort:</strong> ' + escHtml([a.postalCode, a.city].filter(Boolean).join(' ')));

    var beschreibung = a.description
      ? (window.marked && typeof window.marked.parse === 'function' ? window.marked.parse(a.description) : '<p>' + escHtml(a.description) + '</p>')
      : '';

    var abstammung = [];
    if (a.father) abstammung.push('<strong>Vater:</strong> ' + escHtml(a.father) + (a.fatherTests ? ' (' + escHtml(a.fatherTests) + ')' : ''));
    if (a.mother) abstammung.push('<strong>Mutter:</strong> ' + escHtml(a.mother) + (a.motherTests ? ' (' + escHtml(a.motherTests) + ')' : ''));
    if (a.hasZuchtverband === true && a.zuchtverband) abstammung.push('<strong>Zuchtverband:</strong> ' + escHtml(a.zuchtverband));
    else if (a.hasZuchtverband === false) abstammung.push('<strong>Zuchtverband:</strong> Kein Zuchtverband');

    var jagdlich = [];
    if (a.huntingTests) jagdlich.push('<strong>Prüfungen:</strong> ' + escHtml(a.huntingTests));
    if (a.trainingLevel) jagdlich.push('<strong>Ausbildungsstand:</strong> ' + escHtml(a.trainingLevel));

    var kontakt = [];
    if (a.providerName) kontakt.push(escHtml(a.providerName));
    if (a.contactPerson) kontakt.push('Ansprechpartner: ' + escHtml(a.contactPerson));
    if (a.email) kontakt.push(escHtml(a.email));
    if (a.phone) kontakt.push(escHtml(a.phone));

    var body =
      '<p class="text-muted" style="font-size:.8rem;margin-top:-.5rem;">Interne Admin-Vorschau – so ist der aktuelle Datenstand, das endgültige öffentliche Layout entsteht erst in Phase 2.</p>' +
      (haupt ? '<img src="' + escAttr(haupt) + '" alt="" style="width:100%;max-height:280px;object-fit:cover;border-radius:10px;margin-bottom:.75rem;">' : '') +
      (thumbs ? '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">' + thumbs + '</div>' : '') +
      '<h3 style="margin-bottom:.25rem;">' + escHtml(a.title || '(Kein Titel)') +
        ' <span class="item-badge">' + hbTypLabel(a.type) + '</span>' +
        ' <span class="item-badge">' + hbStatusLabel(a.status) + '</span></h3>' +
      (eckdaten.length ? '<p style="line-height:1.8;">' + eckdaten.join('<br>') + '</p>' : '') +
      (beschreibung ? '<div style="margin:1rem 0;">' + beschreibung + '</div>' : '') +
      (abstammung.length ? '<p><strong>Abstammung</strong><br>' + abstammung.join('<br>') + '</p>' : '') +
      (jagdlich.length ? '<p><strong>Jagdliche Informationen</strong><br>' + jagdlich.join('<br>') + '</p>' : '') +
      (kontakt.length ? '<p style="margin-top:1rem;"><strong>Anbieter</strong><br>' + kontakt.join('<br>') + '</p>' : '');

    hbShowModal('Vorschau: ' + (a.title || 'Anzeige'), body);
  };

  function hbShowModal(title, bodyHtml) {
    var el = id('hb-preview-modal');
    if (!el) {
      el = document.createElement('div');
      el.id = 'hb-preview-modal';
      el.className = 'modal';
      el.style.display = 'none';
      el.innerHTML =
        '<div class="modal-backdrop" onclick="hbCloseModal()"></div>' +
        '<div class="modal-box" style="max-width:560px;">' +
          '<div class="modal-head"><h3 id="hb-preview-title"></h3>' +
            '<button class="modal-close-btn" onclick="hbCloseModal()" aria-label="Schließen">✕</button>' +
          '</div>' +
          '<div class="modal-body" id="hb-preview-body"></div>' +
        '</div>';
      document.body.appendChild(el);
    }
    id('hb-preview-title').textContent = title;
    id('hb-preview-body').innerHTML = bodyHtml;
    el.style.display = 'flex';
  }
  window.hbCloseModal = function() {
    var el = id('hb-preview-modal');
    if (el) el.style.display = 'none';
  };

  // Hundebörse-eigene Bildergalerie-Karte: technisch dieselbe Basis wie die
  // generische renderGalerieCard() (gleiche #galerie-list-Struktur, gleiche
  // renderGalerieRow()/collectGalerieList()), aber ohne das allgemeine
  // "Überschrift der Galerie"-Feld und mit Hundebörse-spezifischem Hinweistext
  // + Obergrenze von 10 Bildern (Frank-Wunsch 28.08.2026). Die generische
  // renderGalerieCard() bleibt für alle anderen Seiten unverändert.
  var HB_MAX_BILDER = 10;
  function renderHundeboerseGalerieCard(data) {
    var list = data.galerie || [];
    var rows = list.map(renderGalerieRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">🖼️ Bildergalerie</div>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:0 0 .75rem;">' +
        'Laden Sie bis zu 10 Bilder hoch. Das erste Bild wird als Hauptbild der Anzeige verwendet. ' +
        'Die Reihenfolge kann per Drag &amp; Drop geändert werden.' +
      '</p>' +
      '<div id="galerie-list">' + rows + '</div>' +
      '<p class="text-muted" id="galerie-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Bilder hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="hundeboerseGalerieAdd()">🖼️ Bild hinzufügen</button>' +
    '</div>';
  }
  window.hundeboerseGalerieAdd = function() {
    var list = id('galerie-list');
    var count = list ? list.querySelectorAll('.galerie-row').length : 0;
    if (count >= HB_MAX_BILDER) {
      toast('Maximal ' + HB_MAX_BILDER + ' Bilder pro Anzeige.', 'info');
      return;
    }
    window.addGalerieRow();
  };

  /* ────────────────────────────────────────────────────────────
     WAFFENBÖRSE (Phase 1 Prototyp, 02.09.2026)
     Eigenständiges Modul, eigene Datei content/waffenboerse.json
     ({ kategorien:[...], anzeigen:[...] }) - bewusst NICHT in
     hundeboerse.json integriert (andere Fachlogik/Felder, siehe
     Laurin-Brief "Waffenbörse Phase 1"). Struktur/Bausteine sind
     größtenteils 1:1 vom Hundebörse-Modul übernommen (Status-Workflow,
     doSave/confirmNav/markDirty/renderMain, generische Bildergalerie
     #galerie-list), bewusst OHNE die hundeboerse-spezifische Einzelhund-
     /Wurf-Logik, Zuchtverband-Vorschlagsliste und Geokodierung - dafür
     gibt es bei Waffen keine fachliche Entsprechung.
     Kaliber ist im Datenmodell ein Array (Kombiwaffen können mehrere
     Kaliber haben), wird im Admin aber bewusst als ein einzelnes
     Textfeld mit Komma-Trennung bedient (kein neuer Zeilen-Editor nötig
     für Phase 1, siehe Laurin-Vorgabe "relativ schnell").
     Beschreibung nutzt fTipTap (nicht fMarkdown wie Hundebörse) - für
     Hundebörse war Markdown eine bewusste Bestandsschutz-Entscheidung
     aus Phase 5B.5, hier gibt es keine Altdaten, TipTap ist der
     aktuelle Standard-Editor der Seite.
  ──────────────────────────────────────────────────────────── */
  var WB_STATUS = [
    { value:'pending',   label:'Wartet auf Freigabe' },
    { value:'published', label:'Veröffentlicht' },
    { value:'rejected',  label:'Abgelehnt' },
    { value:'archived',  label:'Archiviert' }
  ];
  var WB_KATEGORIEN_DEFAULT = ['Büchsen', 'Flinten', 'Kombinierte Waffen', 'Kurzwaffen', 'Optik', 'Zubehör', 'Sonstiges'];
  var WB_ZUSTAND = [
    { value:'neu',       label:'Neu' },
    { value:'gebraucht', label:'Gebraucht' }
  ];
  var WB_PREIS_TYP = [
    { value:'',         label:'Keine Angabe' },
    { value:'festpreis', label:'Festpreis' },
    { value:'vb',        label:'Verhandlungsbasis (VB)' }
  ];
  function wbStatusLabel(s) {
    var m = WB_STATUS.filter(function(x) { return x.value === s; })[0];
    return m ? m.label : (s || 'Entwurf');
  }
  function wbZustandLabel(z) {
    var m = WB_ZUSTAND.filter(function(x) { return x.value === z; })[0];
    return m ? m.label : (z || '');
  }
  function wbPreisText(a) {
    var p = a.preis ? (('' + a.preis).trim() + ' €') : '';
    if (a.preis_typ === 'vb') return p ? (p + ' VB') : 'VB';
    return p;
  }
  function wbKaliberText(a) {
    return (a.kaliber || []).join(' · ');
  }

  // Kategorien erweiterbar (Punkt 3 des Laurin-Briefs: "Struktur so, dass
  // Kategorien im Admin später relativ leicht angepasst werden können") -
  // gleiches, bereits bewährtes Prinzip wie bei Aktuelles/Termine/Service
  // (fKategorieDropdown & Co., siehe oben), nur dass die Liste hier auf
  // oberster Ebene unter data.kategorien liegt (nicht unter
  // data.einstellungen.kategorien) - Waffenbörse hat kein "einstellungen"-
  // Objekt und braucht auch keines.
  function alleWaffenboerseKategorien() {
    var kats = (S.data && S.data.kategorien) || WB_KATEGORIEN_DEFAULT;
    var used = ((S.data && S.data.anzeigen) || []).map(function(a) {
      return (a.kategorie || '').trim();
    }).filter(Boolean);
    var seen = {};
    var out = [];
    kats.concat(used).forEach(function(k) {
      if (!seen[k]) { seen[k] = true; out.push(k); }
    });
    return out;
  }

  function fWaffenboerseKategorieDropdown(val) {
    var options = alleWaffenboerseKategorien();
    if (val && options.indexOf(val) === -1) options = options.concat([val]);
    var opts = options.map(function(o) {
      return '<option value="' + escAttr(o) + '"' + (o === val ? ' selected' : '') + '>' + escHtml(o) + '</option>';
    }).join('');
    return '<div class="field-row">' +
      '<label class="field-label" for="f-wb-kategorie">Kategorie</label>' +
      '<div style="display:flex;gap:.5rem;align-items:center;">' +
        '<select class="field-input" id="f-wb-kategorie" style="flex:1;">' + opts + '</select>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="waffenboerseKategorieAdd()" style="white-space:nowrap;">+ Neu</button>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="waffenboerseKategorieDelete()" title="Ausgewählte Kategorie löschen" style="white-space:nowrap;">🗑</button>' +
      '</div>' +
      '<p class="field-hint">Neue Kategorie über „+ Neu" anlegen, ausgewählte über 🗑 löschen (nur möglich, wenn keine Anzeige sie mehr verwendet).</p>' +
    '</div>';
  }

  window.waffenboerseKategorieAdd = async function() {
    var neu = await showPrompt('Neue Kategorie', 'Name der neuen Kategorie:');
    if (!neu) return;
    neu = neu.trim();
    if (!neu) return;
    var kats = (S.data.kategorien || WB_KATEGORIEN_DEFAULT).slice();
    if (kats.indexOf(neu) === -1) kats.push(neu);
    S.data.kategorien = kats;
    try {
      await doSave(S.section.file, S.data, '🔫 Waffenbörse: Kategorie "' + neu + '" hinzugefügt');
      toast('✅ Kategorie „' + neu + '" hinzugefügt', 'ok');
    } catch (e) {
      await handleSaveError(e);
      return;
    }
    var sel = id('f-wb-kategorie');
    if (sel) {
      if (!sel.querySelector('option[value="' + neu.replace(/"/g, '\\"') + '"]')) {
        var opt = document.createElement('option');
        opt.value = neu;
        opt.textContent = neu;
        sel.appendChild(opt);
      }
      sel.value = neu;
      markDirty();
    }
  };

  window.waffenboerseKategorieDelete = async function() {
    var sel = id('f-wb-kategorie');
    var wert = sel ? sel.value : '';
    if (!wert) return;
    var usedBy = ((S.data && S.data.anzeigen) || []).filter(function(a) {
      return (a.kategorie || '').trim() === wert;
    });
    if (usedBy.length) {
      await showAlert('Kann nicht gelöscht werden',
        '„' + wert + '" wird noch von ' + usedBy.length +
        ' Anzeige' + (usedBy.length === 1 ? '' : 'n') + ' verwendet:\n\n' +
        usedBy.map(function(a) { return '• ' + (a.titel || '(ohne Titel)'); }).join('\n') +
        '\n\nBitte dort erst eine andere Kategorie wählen.');
      return;
    }
    showConfirm('Kategorie löschen', 'Kategorie „' + wert + '" wirklich löschen?', async function() {
      var kats = (S.data.kategorien || WB_KATEGORIEN_DEFAULT).slice();
      var idx = kats.indexOf(wert);
      if (idx !== -1) kats.splice(idx, 1);
      S.data.kategorien = kats;
      try {
        await doSave(S.section.file, S.data, '🔫 Waffenbörse: Kategorie "' + wert + '" gelöscht');
        toast('✅ Kategorie „' + wert + '" gelöscht', 'ok');
      } catch (e) {
        await handleSaveError(e);
        return;
      }
      if (sel) {
        var opt = sel.querySelector('option[value="' + wert.replace(/"/g, '\\"') + '"]');
        if (opt) opt.remove();
        if (sel.options.length) { sel.value = sel.options[0].value; markDirty(); }
      }
    });
  };

  function renderWaffenboerse(def, data) {
    var anzeigen = data.anzeigen || [];
    S.wbFilterStatus = S.wbFilterStatus || '';

    var indexed = anzeigen.map(function(a, i) { return { a: a, i: i }; });
    var counts = { alle: indexed.length, pending: 0, published: 0, rejected: 0, archived: 0 };
    indexed.forEach(function(e) {
      if (counts[e.a.status] !== undefined) counts[e.a.status]++;
    });

    var gefiltert = S.wbFilterStatus
      ? indexed.filter(function(e) { return e.a.status === S.wbFilterStatus; })
      : indexed;
    gefiltert.sort(function(x, y) {
      var dx = x.a.erstellt_am || '', dy = y.a.erstellt_am || '';
      if (dx && dy) return dy.localeCompare(dx);
      if (dx) return -1;
      if (dy) return 1;
      return y.i - x.i;
    });

    function tab(value, label, count) {
      var active = S.wbFilterStatus === value;
      return '<button type="button" class="btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline') +
        '" onclick="waffenboerseFilter(\'' + value + '\')" style="margin:0 .4rem .4rem 0;">' +
        escHtml(label) + ' (' + count + ')</button>';
    }

    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="confirmNav(waffenboerseNeu)">➕ Neue Anzeige</button>') +
      '<div class="panel-body">' +
      '<div class="form-card">' +
        '<div class="form-card-title">🔍 Status</div>' +
        '<div style="display:flex;flex-wrap:wrap;">' +
          tab('', 'Alle', counts.alle) +
          tab('pending', 'Wartet auf Freigabe', counts.pending) +
          tab('published', 'Veröffentlicht', counts.published) +
          tab('rejected', 'Abgelehnt', counts.rejected) +
          tab('archived', 'Archiviert', counts.archived) +
        '</div>' +
      '</div>' +
      '<p class="text-muted" style="margin-bottom:1rem;">' + gefiltert.length + ' von ' + counts.alle + ' Anzeigen. Klicken zum Bearbeiten.</p>';

    gefiltert.forEach(function(entry) {
      var a = entry.a, i = entry.i;
      var bild = (a.bilder && a.bilder[0] && a.bilder[0].bild) || '';
      var thumb = bild
        ? '<img src="' + escAttr(bild) + '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0;">'
        : '<div style="width:48px;height:48px;border-radius:8px;flex-shrink:0;background:var(--bg);"></div>';
      var metaParts = [];
      if (a.kategorie) metaParts.push(escHtml(a.kategorie));
      if (a.hersteller || a.modell) metaParts.push(escHtml([a.hersteller, a.modell].filter(Boolean).join(' ')));
      if (wbKaliberText(a)) metaParts.push(escHtml(wbKaliberText(a)));
      if (wbPreisText(a)) metaParts.push(escHtml(wbPreisText(a)));
      if (a.ort || a.plz) metaParts.push('📍 ' + escHtml([a.plz, a.ort].filter(Boolean).join(' ')));

      html += '<div class="item-card" onclick="confirmNav(function(){waffenboerseEdit(' + i + ')})">' +
        thumb +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(a.titel || '(Kein Titel)') +
            '<span class="item-badge">' + wbStatusLabel(a.status) + '</span>' +
            (a.erwerbsberechtigung_erforderlich ? '<span class="item-badge">Erwerbsberechtigung erforderlich</span>' : '') +
          '</div>' +
          '<div class="item-meta">' + metaParts.join(' · ') + '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-ghost" onclick="event.stopPropagation();waffenboerseVorschau(' + i + ')">Vorschau</button>' +
          '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmNav(function(){waffenboerseEdit(' + i + ')})">Bearbeiten</button>' +
          (a.status === 'pending'
            ? '<button class="btn btn-sm btn-primary" onclick="event.stopPropagation();confirmNav(function(){waffenboerseFreigeben(' + i + ')})">Freigeben</button>'
            : '') +
          (a.status === 'published' || a.status === 'rejected'
            ? '<button class="btn btn-sm btn-ghost" onclick="event.stopPropagation();confirmNav(function(){waffenboerseArchivieren(' + i + ')})">Archivieren</button>'
            : '') +
          '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();confirmNav(function(){waffenboerseDelete(' + i + ')})">Löschen</button>' +
        '</div>' +
      '</div>';
    });

    if (!gefiltert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine Anzeigen in dieser Ansicht.</p></div>';
    }
    html += '</div>';
    renderMain(html);
  }

  window.waffenboerseFilter = function(status) {
    S.wbFilterStatus = status;
    renderWaffenboerse(S.section, S.data);
  };

  window.waffenboerseNeu = function() {
    var data = S.data;
    data.anzeigen = data.anzeigen || [];
    var now = new Date().toISOString();
    var neu = {
      id: 'wb-' + Date.now(),
      status: 'pending',
      erstellt_am: now, aktualisiert_am: now,
      titel: '', kategorie: '', hersteller: '', modell: '', kaliber: [],
      zustand: 'gebraucht', preis: '', preis_typ: '',
      erwerbsberechtigung_erforderlich: false,
      beschreibung: '', bilder: [],
      plz: '', ort: '', versand_moeglich: false, versandkosten: '',
      anbieter_name: '', anbieter_email: '', anbieter_telefon: ''
    };
    data.anzeigen.unshift(neu);
    waffenboerseEdit(0);
  };

  window.waffenboerseEdit = function(idx) {
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    var titel = a.titel ? ('Anzeige bearbeiten: ' + a.titel) : 'Neue Anzeige';

    var html = panelHeader(titel,
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderWaffenboerse(S.section,S.data)})">← Zurück zur Waffenbörse</button>' +
        '<button class="btn btn-outline" onclick="waffenboerseVorschau(' + idx + ')">Vorschau</button>' +
        '<button class="btn btn-outline" onclick="waffenboerseSave(' + idx + ')">💾 Speichern</button>' +
        '<button class="btn btn-primary" onclick="waffenboerseFreigebenAusEdit(' + idx + ')">Speichern &amp; Freigeben</button>',
        true) +
      '<div class="panel-body">' +

      '<div class="form-card">' +
        '<div class="form-card-title">📌 Status</div>' +
        fSelect('wb-status', 'Aktueller Status', a.status || 'pending', WB_STATUS) +
        '<p class="field-hint">Eingegangen am ' + escHtml(hbDatumAnzeige(a.erstellt_am)) +
          (a.aktualisiert_am ? (' · zuletzt geändert am ' + escHtml(hbDatumAnzeige(a.aktualisiert_am))) : '') + '</p>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">🔫 Grunddaten</div>' +
        fText('wb-titel', 'Titel', a.titel) +
        fWaffenboerseKategorieDropdown(a.kategorie) +
        '<div class="field-row-2">' +
          fText('wb-hersteller', 'Hersteller', a.hersteller) +
          fText('wb-modell', 'Modell', a.modell) +
        '</div>' +
        // Eine Eingabezeile pro Kaliber statt Komma-Trennfeld (Phase 2.1,
        // Punkt 2, 03.09.2026): Komma war als Trennzeichen ungeeignet, weil
        // Kaliber selbst Kommas enthalten können (z.B. "5,6x52R") - wurde
        // dadurch fälschlich in zwei Einträge zerlegt. Zeilen werden nach
        // renderMain() unten über renderWaffenboerseKaliberRows(a.kaliber)
        // befüllt (auch bestehende, evtl. schon "verunglückte" Kaliber-
        // Arrays aus der alten Eingabe werden dabei unverändert als je eine
        // Zeile angezeigt - keine automatische Korrektur bestehender Daten).
        '<div class="field-row">' +
          '<label class="field-label">Kaliber</label>' +
          '<div class="wb-kaliber-list" id="wb-kaliber-list"></div>' +
          '<button type="button" class="btn btn-outline btn-sm" onclick="waffenboerseKaliberAdd()">+ Weiteres Kaliber</button>' +
        '</div>' +
        fSelect('wb-zustand', 'Zustand', a.zustand || 'gebraucht', WB_ZUSTAND) +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">💶 Preis &amp; Versand</div>' +
        fSelect('wb-preis_typ', 'Preisart', a.preis_typ || '', WB_PREIS_TYP) +
        fText('wb-preis', 'Preis (€)', a.preis) +
        fToggle('wb-versand_moeglich', 'Versand möglich?', a.versand_moeglich === true) +
        '<div id="wb-versandkosten-wrap" style="display:' + (a.versand_moeglich === true ? 'block' : 'none') + '">' +
          fText('wb-versandkosten', 'Versandkosten', a.versandkosten) +
        '</div>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">📍 Standort</div>' +
        '<div class="field-row-2">' +
          fText('wb-plz', 'PLZ', a.plz) +
          fText('wb-ort', 'Ort', a.ort) +
        '</div>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">⚠️ Erwerbsberechtigung</div>' +
        fToggle('wb-erwerb', 'Erwerbsberechtigung erforderlich?', a.erwerbsberechtigung_erforderlich === true) +
        '<p class="field-hint">Wird bei „Ja" auf der öffentlichen Detailseite deutlich sichtbar angezeigt.</p>' +
      '</div>' +

      '<div class="form-card">' +
        '<div class="form-card-title">📝 Beschreibung</div>' +
        fTipTap('wb-beschreibung', 'Freitext-Beschreibung', false) +
      '</div>' +

      renderWaffenboerseGalerieCard(a) +

      '<div class="form-card">' +
        '<div class="form-card-title">📞 Anbieter / Kontaktdaten</div>' +
        fText('wb-anbieter_name', 'Name des Anbieters', a.anbieter_name) +
        fText('wb-anbieter_email', 'E-Mail', a.anbieter_email) +
        fText('wb-anbieter_telefon', 'Telefon/Mobil (optional)', a.anbieter_telefon) +
      '</div>' +

      '<div class="form-card" style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:flex-end;">' +
        '<button class="btn btn-danger-outline" onclick="waffenboerseAblehnen(' + idx + ')">Anzeige ablehnen</button>' +
        '<button class="btn btn-outline" onclick="waffenboerseVorschau(' + idx + ')">Vorschau der Anzeige</button>' +
        '<button class="btn btn-primary" onclick="waffenboerseFreigebenAusEdit(' + idx + ')">Anzeige freigeben</button>' +
      '</div>' +
      '</div>';

    renderMain(html);
    initTiptap('wb-beschreibung', a.beschreibung || '');
    initGalerieSortable();
    renderWaffenboerseKaliberRows(a.kaliber || []);

    var vmBtn = id('f-wb-versand_moeglich');
    if (vmBtn) vmBtn.addEventListener('click', function() {
      var wrap = id('wb-versandkosten-wrap');
      if (wrap) wrap.style.display = (this.getAttribute('data-val') === '1') ? 'block' : 'none';
    });
  };

  // Kaliber-Zeilen (Phase 2.1, Punkt 2): analog zum öffentlichen Formular
  // (waffenboerse/anbieten.html) - eine Zeile pro Kaliber, kein Komma-Split
  // mehr. Bewusst eigenständig statt renderGalerieRow() o.ä. zu
  // verwenden, da hier reine Strings statt {bild,titel}-Objekte verwaltet
  // werden.
  function renderWaffenboerseKaliberRow(wert) {
    return '<div class="wb-kaliber-row">' +
      '<input class="field-input wb-kaliber-input" type="text" value="' + escAttr(wert || '') + '" placeholder="z.B. 7x65R">' +
      '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.wb-kaliber-row\').remove();markDirty()">🗑️</button>' +
    '</div>';
  }
  function renderWaffenboerseKaliberRows(kaliberArr) {
    var list = id('wb-kaliber-list');
    if (!list) return;
    list.innerHTML = (kaliberArr && kaliberArr.length ? kaliberArr : ['']).map(renderWaffenboerseKaliberRow).join('');
  }
  window.waffenboerseKaliberAdd = function() {
    var list = id('wb-kaliber-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderWaffenboerseKaliberRow('');
    list.appendChild(wrap.firstChild);
    markDirty();
  };
  function waffenboerseKaliberCollect() {
    var list = id('wb-kaliber-list');
    if (!list) return [];
    return Array.prototype.slice.call(list.querySelectorAll('.wb-kaliber-input'))
      .map(function(inp) { return inp.value.trim(); })
      .filter(Boolean);
  }

  async function waffenboerseCollect(idx) {
    var a = S.data.anzeigen[idx];
    a.status        = gv('wb-status');
    a.titel         = gv('wb-titel');
    a.kategorie     = gv('wb-kategorie');
    a.hersteller    = gv('wb-hersteller');
    a.modell        = gv('wb-modell');
    a.kaliber       = waffenboerseKaliberCollect();
    a.zustand       = gv('wb-zustand');
    a.preis_typ     = gv('wb-preis_typ');
    a.preis         = gv('wb-preis');
    a.versand_moeglich = toggleVal('wb-versand_moeglich');
    a.versandkosten = a.versand_moeglich ? gv('wb-versandkosten') : '';
    a.plz           = gv('wb-plz');
    a.ort           = gv('wb-ort');
    a.erwerbsberechtigung_erforderlich = toggleVal('wb-erwerb');
    a.beschreibung  = getTiptapValue('wb-beschreibung', a.beschreibung, 'Beschreibung');
    a.bilder        = collectGalerieList();
    a.anbieter_name  = gv('wb-anbieter_name');
    a.anbieter_email = gv('wb-anbieter_email');
    a.anbieter_telefon = gv('wb-anbieter_telefon');
    a.aktualisiert_am = new Date().toISOString();
    return a;
  }

  window.waffenboerseSave = async function(idx) {
    await waffenboerseCollect(idx);
    try {
      await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige gespeichert');
      toast('✅ Anzeige gespeichert!', 'ok');
      S.dirty = false;
      renderWaffenboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.waffenboerseFreigebenAusEdit = async function(idx) {
    await waffenboerseCollect(idx);
    showConfirm('Anzeige freigeben', 'Diese Anzeige jetzt freigeben? Sie erscheint dann auf der öffentlichen Waffenbörse.', async function() {
      var a = S.data.anzeigen[idx];
      a.status = 'published';
      a.aktualisiert_am = new Date().toISOString();
      try {
        await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige freigegeben');
        toast('✅ Anzeige freigegeben!', 'ok');
        S.dirty = false;
        renderWaffenboerse(S.section, S.data);
      } catch (e) { await handleSaveError(e); }
    }, 'Freigeben', 'btn-primary');
  };

  window.waffenboerseFreigeben = function(idx) {
    showConfirm('Anzeige freigeben', 'Diese Anzeige jetzt freigeben? Sie erscheint dann auf der öffentlichen Waffenbörse.', async function() {
      var a = (S.data.anzeigen || [])[idx];
      if (!a) return;
      a.status = 'published';
      a.aktualisiert_am = new Date().toISOString();
      try {
        await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige freigegeben');
        toast('✅ Anzeige freigegeben!', 'ok');
        renderWaffenboerse(S.section, S.data);
      } catch (e) { await handleSaveError(e); }
    }, 'Freigeben', 'btn-primary');
  };

  window.waffenboerseAblehnen = async function(idx) {
    await waffenboerseCollect(idx);
    var a = S.data.anzeigen[idx];
    a.status = 'rejected';
    a.aktualisiert_am = new Date().toISOString();
    try {
      await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige abgelehnt');
      toast('🚫 Anzeige abgelehnt', 'info');
      S.dirty = false;
      renderWaffenboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.waffenboerseArchivieren = async function(idx) {
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    a.status = 'archived';
    a.aktualisiert_am = new Date().toISOString();
    try {
      await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige archiviert');
      toast('📦 Anzeige archiviert', 'ok');
      renderWaffenboerse(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.waffenboerseDelete = function(idx) {
    showConfirm('Anzeige löschen', 'Diese Anzeige wirklich unwiderruflich löschen?', async function() {
      var entfernt = S.data.anzeigen.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '🔫 Waffenbörse: Anzeige gelöscht');
        toast('🗑️ Anzeige gelöscht', 'info');
        renderWaffenboerse(S.section, S.data);
      } catch (e) {
        if (entfernt.length) S.data.anzeigen.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  window.waffenboerseVorschau = function(idx) {
    var a = (S.data.anzeigen || [])[idx];
    if (!a) return;
    var bilder = a.bilder || [];
    var haupt = bilder[0] ? bilder[0].bild : '';
    var thumbs = bilder.slice(1).map(function(g) {
      return '<img src="' + escAttr(g.bild) + '" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;">';
    }).join('');

    var eckdaten = [];
    if (a.hersteller || a.modell) eckdaten.push('<strong>Hersteller/Modell:</strong> ' + escHtml([a.hersteller, a.modell].filter(Boolean).join(' ')));
    if (wbKaliberText(a)) eckdaten.push('<strong>Kaliber:</strong> ' + escHtml(wbKaliberText(a)));
    if (a.zustand) eckdaten.push('<strong>Zustand:</strong> ' + escHtml(wbZustandLabel(a.zustand)));
    var preisText = wbPreisText(a);
    if (preisText) eckdaten.push('<strong>Preis:</strong> ' + escHtml(preisText));
    if (a.ort || a.plz) eckdaten.push('<strong>Standort:</strong> ' + escHtml([a.plz, a.ort].filter(Boolean).join(' ')));
    eckdaten.push('<strong>Versand:</strong> ' + (a.versand_moeglich ? ('möglich' + (a.versandkosten ? (' (' + escHtml(a.versandkosten) + ')') : '')) : 'nur Abholung'));
    eckdaten.push('<strong>Erwerbsberechtigung:</strong> ' + (a.erwerbsberechtigung_erforderlich ? 'erforderlich' : 'nicht erforderlich'));

    var kontakt = [];
    if (a.anbieter_name) kontakt.push(escHtml(a.anbieter_name));
    if (a.anbieter_email) kontakt.push(escHtml(a.anbieter_email));
    if (a.anbieter_telefon) kontakt.push(escHtml(a.anbieter_telefon));

    var body =
      '<p class="text-muted" style="font-size:.8rem;margin-top:-.5rem;">Interne Admin-Vorschau – so ist der aktuelle Datenstand.</p>' +
      (haupt ? '<img src="' + escAttr(haupt) + '" alt="" style="width:100%;max-height:280px;object-fit:cover;border-radius:10px;margin-bottom:.75rem;">' : '') +
      (thumbs ? '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">' + thumbs + '</div>' : '') +
      '<h3 style="margin-bottom:.25rem;">' + escHtml(a.titel || '(Kein Titel)') +
        ' <span class="item-badge">' + wbStatusLabel(a.status) + '</span></h3>' +
      (a.kategorie ? '<p class="item-badge" style="display:inline-block;margin-bottom:.5rem;">' + escHtml(a.kategorie) + '</p>' : '') +
      (eckdaten.length ? '<p style="line-height:1.8;">' + eckdaten.join('<br>') + '</p>' : '') +
      (a.beschreibung ? '<div style="margin:1rem 0;">' + a.beschreibung + '</div>' : '') +
      (kontakt.length ? '<p style="margin-top:1rem;"><strong>Anbieter</strong><br>' + kontakt.join('<br>') + '</p>' : '');

    hbShowModal('Vorschau: ' + (a.titel || 'Anzeige'), body);
  };

  // Waffenbörse-eigene Bildergalerie-Karte: technisch dieselbe Basis wie die
  // generische renderGalerieCard() (gleiche #galerie-list-Struktur, gleiche
  // renderGalerieRow()/collectGalerieList()) - siehe auch
  // renderHundeboerseGalerieCard() oben, gleiches Prinzip. Ohne das
  // allgemeine "Überschrift der Galerie"-Feld, mit eigenem Hinweistext und
  // Obergrenze.
  var WB_MAX_BILDER = 10;

  // Nachbesserung 03.09.2026 (Laurin-Feedback): In der Waffenbörsen-
  // Bilderverwaltung gab es pro Bild zwei Löschwege - das "✕ Entfernen" aus
  // dem generischen fImage()-Baustein (leert nur das Bildfeld dieser Zeile,
  // die Zeile bleibt als leerer Slot stehen) UND den roten Papierkorb der
  // Zeile selbst (entfernt die ganze Zeile). Für eine Galerie-Zeile führte
  // das faktisch zum selben sichtbaren Ergebnis (Bild verschwindet aus der
  // Galerie) und war verwirrend doppelt. fImage()/renderGalerieRow() selbst
  // bleiben bewusst UNANGETASTET (beide sind gemeinsam mit Hundebörse und
  // allen anderen Bildfeldern im Admin genutzt - eine Änderung dort hätte
  // sitebreite Nebenwirkungen). Stattdessen nur für die Waffenbörse ein
  // eigener, dünner Zeilen-Renderer: identisch zu renderGalerieRow(), nur
  // dass der "✕ Entfernen"-Button aus der von fImage() gelieferten HTML
  // herausgeschnitten wird, bevor die Zeile zusammengebaut wird. Bild
  // wählen/Vorschau/Beschriftung/Drag & Drop/Reihenfolge/Hauptbild-Regel/
  // 10-Bilder-Obergrenze bleiben alle unverändert - übrig bleibt genau ein
  // Löschmechanismus pro Zeile, der rote Papierkorb (passt zum bestehenden
  // Muster bei Downloads/Linkliste/generischer Galerie).
  function renderWaffenboerseGalerieRow(g) {
    var imgId = 'gal-bild-' + (galRowSeq++);
    var bildFeld = fImage(imgId, 'Bild', g.bild)
      .replace(/<button type="button" class="btn btn-ghost btn-sm" onclick="clearImg\([^)]*\)">✕ Entfernen<\/button>/, '');
    return '<div class="item-card galerie-row" data-img-id="' + imgId + '">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        bildFeld +
        '<input class="field-input gal-titel" type="text" value="' + escAttr(g.titel || '') + '" placeholder="Beschriftung (z.B. Frontansicht)" style="margin-top:.5rem;">' +
      '</div>' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.galerie-row\').remove();markDirty()">🗑️</button>' +
      '</div>' +
    '</div>';
  }
  window.addWaffenboerseGalerieRow = function() {
    var list = id('galerie-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderWaffenboerseGalerieRow({});
    list.appendChild(wrap.firstChild);
    var empty = id('galerie-empty');
    if (empty) empty.style.display = 'none';
    initGalerieSortable();
    markDirty();
  };
  function renderWaffenboerseGalerieCard(data) {
    var list = data.bilder || [];
    var rows = list.map(renderWaffenboerseGalerieRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">🖼️ Bilder</div>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:0 0 .75rem;">' +
        'Laden Sie bis zu 10 Bilder hoch. Das erste Bild wird als Hauptbild der Anzeige verwendet. ' +
        'Die Reihenfolge kann per Drag &amp; Drop geändert werden.' +
      '</p>' +
      '<div id="galerie-list">' + rows + '</div>' +
      '<p class="text-muted" id="galerie-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Bilder hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="waffenboerseGalerieAdd()">🖼️ Bild hinzufügen</button>' +
    '</div>';
  }
  window.waffenboerseGalerieAdd = function() {
    var list = id('galerie-list');
    var count = list ? list.querySelectorAll('.galerie-row').length : 0;
    if (count >= WB_MAX_BILDER) {
      toast('Maximal ' + WB_MAX_BILDER + ' Bilder pro Anzeige.', 'info');
      return;
    }
    window.addWaffenboerseGalerieRow();
  };

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
          '<button type="button" class="tt-btn" data-cmd="strike"      onclick="ttCmd(\'' + fieldId + '\',\'strike\')"      title="Durchstreichen"><s>S</s></button>' +
          '<button type="button" class="tt-btn" data-cmd="link"        onclick="ttLink(event,\'' + fieldId + '\')"          title="Link einfügen/bearbeiten">🔗 Link</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" data-cmd="h2"          onclick="ttCmd(\'' + fieldId + '\',\'h2\')"          title="Überschrift H2">H2</button>' +
          '<button type="button" class="tt-btn" data-cmd="h3"          onclick="ttCmd(\'' + fieldId + '\',\'h3\')"          title="Überschrift H3">H3</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" data-cmd="bulletList"  onclick="ttCmd(\'' + fieldId + '\',\'bulletList\')"  title="Aufzählungsliste">&bull;&nbsp;Liste</button>' +
          '<button type="button" class="tt-btn" data-cmd="orderedList" onclick="ttCmd(\'' + fieldId + '\',\'orderedList\')" title="Nummerierte Liste">1.&nbsp;Liste</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" data-cmd="alignLeft"   onclick="ttCmd(\'' + fieldId + '\',\'alignLeft\')"   title="Linksbündig">&#8676;</button>' +
          '<button type="button" class="tt-btn" data-cmd="alignCenter" onclick="ttCmd(\'' + fieldId + '\',\'alignCenter\')" title="Zentriert">&#8596;</button>' +
          '<button type="button" class="tt-btn" data-cmd="alignRight"  onclick="ttCmd(\'' + fieldId + '\',\'alignRight\')"  title="Rechtsbündig">&#8677;</button>' +
          '<span class="tt-sep"></span>' +
          '<label class="tt-btn tt-color-btn" title="Textfarbe">' +
            'A<input type="color" class="tt-color-input" value="#1a1a1a" onchange="ttColor(\'' + fieldId + '\', this.value)">' +
          '</label>' +
          '<button type="button" class="tt-btn" data-cmd="highlight"   onclick="ttCmd(\'' + fieldId + '\',\'highlight\')"   title="Hervorheben (Textmarker)">&#9998;</button>' +
          '<span class="tt-sep"></span>' +
          '<span class="tt-imgmenu-label" style="align-self:center;font-size:.75rem;color:var(--text-muted);margin-right:.15rem;">Abstand:</span>' +
          '<button type="button" class="tt-btn" data-cmd="spacingEng"    onclick="ttCmd(\'' + fieldId + '\',\'spacingEng\')"    title="Eng">Eng</button>' +
          '<button type="button" class="tt-btn" data-cmd="spacingNormal" onclick="ttCmd(\'' + fieldId + '\',\'spacingNormal\')" title="Normal">Normal</button>' +
          '<button type="button" class="tt-btn" data-cmd="spacingWeit"   onclick="ttCmd(\'' + fieldId + '\',\'spacingWeit\')"   title="Weit">Weit</button>' +
          '<span class="tt-sep"></span>' +
          '<button type="button" class="tt-btn" onclick="ttToggleTableMenu(event,\'' + fieldId + '\')" title="Tabelle">&#9638;&nbsp;Tabelle</button>' +
          '<button type="button" class="tt-btn" onclick="ttVideo(\'' + fieldId + '\')" title="YouTube-Video einfügen (URL einfügen)">&#9654;&nbsp;Video</button>' +
          imgBtn +
        '</div>' +
        '<div class="tt-mount" id="tt-' + fieldId + '"></div>' +
      '</div>' +
    '</div>';
  }

  // Bildgröße-Auswahl als Button-Gruppe (gleiche Konvention wie Inline-Bilder:
  // img-25/50/75/100). Gespeichert wird der Klassenname im versteckten Feld
  // f-bild_groesse, das collectStandard über gv('bild_groesse') ausliest.
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
    markDirty(); // Setzt .value programmatisch - löst kein natives input/change-Event aus
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

  // renderInfomobil/collectInfomobil (das historische form:'tiptap', aus dem
  // renderStandard/collectStandard einst hervorgegangen sind) wurden in
  // Phase 5B.5 entfernt: Infomobil läuft jetzt über form:'standard' wie alle
  // anderen normalen Inhaltsseiten (siehe NAVIGATION TREE oben) - inhaltlich
  // deckungsgleich, da Infomobils NAV-Eintrag keine der Zusatzfelder von
  // renderStandard auslöst (kein isDynamic/children/slug/jagdhundeschule-Key).

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
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.download-row\').remove();markDirty()">🗑️</button>' +
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

  /* ────────────────────────────────────────────────────────────
     LINK-LISTE (pro Seite, optional, aktuell nur Wildfleisch)
     data.linkliste = [{ titel: "...", url: "..." }]
     Wird als eigener Sidebar-Kasten gerendert (siehe verbraucher/wildfleisch.html).
  ──────────────────────────────────────────────────────────── */
  function renderLinklisteRow(l) {
    return '<div class="item-card linkliste-row">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        '<input class="field-input ln-titel" type="text" value="' + escAttr(l.titel || '') + '" placeholder="Beschriftung (z.B. Rezepte)" style="margin-bottom:.35rem;">' +
        '<input class="field-input ln-url" type="text" value="' + escAttr(l.url || '') + '" placeholder="https://... oder /pfad">' +
      '</div>' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.linkliste-row\').remove();markDirty()">🗑️</button>' +
      '</div>' +
    '</div>';
  }

  function renderLinklisteCard(data) {
    var list = data.linkliste || [];
    var rows = list.map(renderLinklisteRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">🔗 Link-Liste (Seitenleiste)</div>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:0 0 .75rem;">' +
        'Frei benennbare Links, die als eigener Kasten in der Seitenleiste erscheinen (z.B. "Rezepte", "Wildfleisch bestellen"). Reihenfolge per Drag &amp; Drop änderbar.' +
      '</p>' +
      '<div class="field-row">' +
        '<label class="field-label" for="f-linkliste-titel">Überschrift des Kastens</label>' +
        '<input class="field-input" type="text" id="f-linkliste-titel" value="' + escAttr(data.linkliste_titel || '') + '" placeholder="Weiterführende Links">' +
      '</div>' +
      '<div id="linkliste-list">' + rows + '</div>' +
      '<p class="text-muted" id="linkliste-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Links hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="linklisteRowAdd()">➕ Link hinzufügen</button>' +
    '</div>';
  }

  function initLinklisteSortable() {
    var el = id('linkliste-list');
    if (el && window.Sortable && !el._sortableInit) {
      el._sortableInit = true;
      Sortable.create(el, { handle: '.item-drag', animation: 150 });
    }
  }

  window.linklisteRowAdd = function() {
    var list = id('linkliste-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderLinklisteRow({});
    list.appendChild(wrap.firstChild);
    var empty = id('linkliste-empty');
    if (empty) empty.style.display = 'none';
    markDirty();
  };

  function collectLinklisteList() {
    var result = [];
    document.querySelectorAll('#linkliste-list .linkliste-row').forEach(function(row) {
      var titelEl = row.querySelector('.ln-titel');
      var urlEl   = row.querySelector('.ln-url');
      var titel = titelEl ? titelEl.value.trim() : '';
      var url   = urlEl   ? urlEl.value.trim()   : '';
      if (titel && url) result.push({ titel: titel, url: url });
    });
    return result;
  }

  // Überschrift des Link-Liste-Kastens (Laurin-Wunsch 22.08.2026: frei
  // umbenennbar statt fest "Weiterführende Links"). Leer -> Standardwert.
  function collectLinklisteTitel() {
    var t = gv('linkliste-titel');
    return (t && t.trim()) || 'Weiterführende Links';
  }

  // Fügt ein Dokument (neu hochgeladen oder aus der Galerie ausgewählt) zur
  // "Dokumente & Downloads"-Liste der aktuell geöffneten Seite hinzu.
  // targetListId wird nur noch der Vollständigkeit halber durchgereicht
  // (generalisiertes PDF-Modal, siehe openPdfModal/_pdfModalTargetListId
  // weiter unten) – seit dem Umbau von Service auf das Aktuelles-Modell
  // (21.08.2026, ein Beitrag = eine eigene "#downloads-list" wie bei
  // Aktuelles, siehe renderService/serviceEdit) gibt es kein zweites,
  // abweichendes Ziel mehr, jeder Aufruf landet in "#downloads-list".
  function addDownloadRow(url, filename, targetListId) {
    var list = id(targetListId || 'downloads-list');
    if (!list) return;
    var displayName = filename.replace(/^\d+-/, '').replace(/-/g, ' ').replace(/\.pdf$/i, '').trim() || filename;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderDownloadRow({ titel: displayName, datei: url });
    list.appendChild(wrap.firstChild);
    var empty = id('downloads-empty');
    if (empty) empty.style.display = 'none';
    initDownloadsSortable();
    markDirty();
    toast('✅ Dokument hinzugefügt – Beschriftung prüfen & Seite speichern', 'ok');
  }

  /* ────────────────────────────────────────────────────────────
     BILDERGALERIE (Punkt 2, Frank-Wunsch) – gleiches Baukasten-Prinzip
     wie "Dokumente & Downloads": data.galerie = [{ bild, titel }].
     Wird generisch (ohne Änderungen an einzelnen Seiten) über
     renderForm()/injectGalerieCard() an jede Inhaltsseite angehängt.
  ──────────────────────────────────────────────────────────── */
  var galRowSeq = 0;

  function renderGalerieRow(g) {
    var imgId = 'gal-bild-' + (galRowSeq++);
    return '<div class="item-card galerie-row" data-img-id="' + imgId + '">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        fImage(imgId, 'Bild', g.bild) +
        '<input class="field-input gal-titel" type="text" value="' + escAttr(g.titel || '') + '" placeholder="Beschriftung (z.B. Hegeringtag Mai 2026)" style="margin-top:.5rem;">' +
      '</div>' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.galerie-row\').remove();markDirty()">🗑️</button>' +
      '</div>' +
    '</div>';
  }

  function renderGalerieCard(data) {
    var list = data.galerie || [];
    var rows = list.map(renderGalerieRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">🖼️ Bildergalerie</div>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:0 0 .75rem;">' +
        'Mehrere Bilder mit eigener Beschriftung (z.B. Fotos von einer Veranstaltung). ' +
        'Werden am Ende dieser Seite als Galerie angezeigt. Reihenfolge per Drag &amp; Drop änderbar.' +
      '</p>' +
      '<div class="field-row">' +
        '<label class="field-label" for="f-galerie-titel">Überschrift der Galerie</label>' +
        '<input class="field-input" type="text" id="f-galerie-titel" value="' + escAttr(data.galerie_titel || '') + '" placeholder="Bildergalerie">' +
      '</div>' +
      '<div id="galerie-list">' + rows + '</div>' +
      '<p class="text-muted" id="galerie-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Bilder hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="addGalerieRow()">🖼️ Bild hinzufügen</button>' +
    '</div>';
  }

  function initGalerieSortable() {
    var el = id('galerie-list');
    if (el && window.Sortable && !el._sortableInit) {
      el._sortableInit = true;
      Sortable.create(el, { handle: '.item-drag', animation: 150 });
    }
  }

  function collectGalerieList() {
    var result = [];
    document.querySelectorAll('#galerie-list .galerie-row').forEach(function(row) {
      var imgId = row.getAttribute('data-img-id');
      var bildEl = imgId ? id('f-' + imgId) : null;
      var titelEl = row.querySelector('.gal-titel');
      var bild = bildEl ? bildEl.value.trim() : '';
      var titel = titelEl ? titelEl.value.trim() : '';
      if (bild) result.push({ bild: bild, titel: titel });
    });
    return result;
  }

  // Überschrift der Bildergalerie (Frank-Wunsch: frei umbenennbar statt
  // fest "Bildergalerie"). Leer -> Standardwert.
  function collectGalerieTitel() {
    var t = gv('galerie-titel');
    return (t && t.trim()) || 'Bildergalerie';
  }

  // Fügt eine leere Bildzeile hinzu; der Admin wählt das Bild dann direkt
  // über den "Bild wählen"-Button in der neuen Zeile aus.
  window.addGalerieRow = function() {
    var list = id('galerie-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderGalerieRow({});
    list.appendChild(wrap.firstChild);
    var empty = id('galerie-empty');
    if (empty) empty.style.display = 'none';
    initGalerieSortable();
    markDirty();
  };

  // Hängt die Bildergalerie-Karte ans Ende des aktuellen Formulars an,
  // analog zu injectDownloadsCard().
  function injectGalerieCard(data) {
    if (id('galerie-list')) { initGalerieSortable(); return; }
    var body = document.querySelector('#admin-main .panel-body');
    if (!body) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderGalerieCard(data);
    body.appendChild(wrap.firstChild);
    initGalerieSortable();
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
          renderHeroSlidesBlock(data) +
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
        renderTestimonialsCard(data) +
      '</div>' +
      saveBar();
    renderMain(html);
    initTestimonialsSortable();
    initHeroSlidesSortable();
    bindSaveBtn();
  }

  /* ────────────────────────────────────────────────────────────
     HERO-DIASHOW (Startseiten-Banner) - ersetzt das alte, nie öffentlich
     verdrahtete Einzelbild-Feld "hero_bild". Ein Bild = festes
     Hintergrundbild wie bisher. Mehrere Bilder = automatischer Wechsel
     (Crossfade), jedes Bild mit eigener Anzeigedauer in Sekunden.
     data.hero_slides = [{ bild, dauer }]. Alle Bilder werden auf der
     öffentlichen Seite automatisch im gleichen Ausschnitt/Format
     zugeschnitten (background-size:cover), unabhängig von der
     ursprünglichen Bildgröße - kein manuelles Zuschneiden nötig.
  ──────────────────────────────────────────────────────────── */
  var heroSlideRowSeq = 0;

  function renderHeroSlideRow(sVal) {
    sVal = sVal || {};
    var imgId = 'hero-slide-bild-' + (heroSlideRowSeq++);
    return '<div class="item-card hero-slide-row" data-img-id="' + imgId + '">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        fImage(imgId, 'Bild', sVal.bild) +
        '<div class="field-row" style="margin-top:.5rem;max-width:220px;">' +
          '<label class="field-label">Anzeigedauer (Sekunden)</label>' +
          '<input class="field-input hs-dauer" type="number" min="1" step="1" value="' + escAttr(sVal.dauer || '') + '" placeholder="6">' +
        '</div>' +
      '</div>' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.hero-slide-row\').remove();markDirty()">🗑️</button>' +
      '</div>' +
    '</div>';
  }

  function renderHeroSlidesBlock(data) {
    // Migration: altes hero_bild (war nie live verdrahtet) als ersten Slide
    // übernehmen, falls noch keine hero_slides-Liste existiert.
    var list = (data.hero_slides && data.hero_slides.length) ? data.hero_slides :
      (data.hero_bild ? [{ bild: data.hero_bild, dauer: 6 }] : []);
    var rows = list.map(renderHeroSlideRow).join('');
    return '<div class="field-row">' +
      '<label class="field-label">Hero-Hintergrundbilder</label>' +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:.25rem 0 .75rem;">' +
        'Ein Bild = festes Hintergrundbild wie bisher. Mehrere Bilder = automatische Diashow: ' +
        'jedes Bild wird für die eingestellte Dauer gezeigt und dann sanft zum nächsten überblendet. ' +
        'Alle Bilder werden automatisch im gleichen Ausschnitt/Format zugeschnitten, unabhängig von ' +
        'der ursprünglichen Bildgröße.' +
      '</p>' +
      '<div id="hero-slides-list">' + rows + '</div>' +
      '<p class="text-muted" id="hero-slides-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch kein Bild hinterlegt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="heroSlideRowAdd()">🖼️ Bild hinzufügen</button>' +
    '</div>';
  }

  function initHeroSlidesSortable() {
    var el = id('hero-slides-list');
    if (el && window.Sortable && !el._sortableInit) {
      el._sortableInit = true;
      Sortable.create(el, { handle: '.item-drag', animation: 150 });
    }
  }

  window.heroSlideRowAdd = function() {
    var list = id('hero-slides-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderHeroSlideRow({});
    list.appendChild(wrap.firstChild);
    var empty = id('hero-slides-empty');
    if (empty) empty.style.display = 'none';
    initHeroSlidesSortable();
    markDirty();
  };

  function collectHeroSlidesList() {
    var result = [];
    document.querySelectorAll('#hero-slides-list .hero-slide-row').forEach(function(row) {
      var imgId = row.getAttribute('data-img-id');
      var bildEl = imgId ? id('f-' + imgId) : null;
      var dauerEl = row.querySelector('.hs-dauer');
      var bild = bildEl ? bildEl.value.trim() : '';
      if (!bild) return;
      var dauer = dauerEl ? parseInt(dauerEl.value, 10) : NaN;
      if (!dauer || dauer < 1) dauer = 6;
      result.push({ bild: bild, dauer: dauer });
    });
    return result;
  }

  /* ────────────────────────────────────────────────────────────
     STIMMEN / TESTIMONIALS (Startseite, "Was unsere Jäger und
     Mitglieder sagen") - war bisher komplett hart im HTML verdrahtet
     (3 feste Zitate mit Fantasienamen), 22.08.2026 auf Laurin-Wunsch
     editierbar gemacht. data.testimonials = [{ text, name, rolle, icon }]
  ──────────────────────────────────────────────────────────── */
  function renderTestimonialRow(t) {
    t = t || {};
    return '<div class="item-card testimonial-row">' +
      '<div class="item-drag">⠿</div>' +
      '<div class="item-body">' +
        '<textarea class="field-input ts-text" rows="2" placeholder="Zitat-Text">' + escHtml(t.text || '') + '</textarea>' +
        '<div class="field-row-2" style="margin-top:.5rem;">' +
          '<input class="field-input ts-name" type="text" value="' + escAttr(t.name || '') + '" placeholder="Name">' +
          '<input class="field-input ts-rolle" type="text" value="' + escAttr(t.rolle || '') + '" placeholder="Funktion/Rolle">' +
        '</div>' +
        '<input class="field-input ts-icon" type="text" value="' + escAttr(t.icon || '') + '" placeholder="Emoji (z.B. 🌿)" style="margin-top:.5rem;max-width:110px;">' +
      '</div>' +
      '<div class="item-actions">' +
        '<button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest(\'.testimonial-row\').remove();markDirty()">🗑️</button>' +
      '</div>' +
    '</div>';
  }

  function renderTestimonialsCard(data) {
    var list = data.testimonials || [];
    var rows = list.map(renderTestimonialRow).join('');
    return '<div class="form-card">' +
      '<div class="form-card-title">💬 Stimmen ("Was unsere Jäger und Mitglieder sagen")</div>' +
      fText('testimonials_titel', 'Überschrift', data.testimonials_titel, 'Was unsere Jäger und Mitglieder sagen') +
      fText('testimonials_untertitel', 'Unterüberschrift', data.testimonials_untertitel, 'Stimmen aus unserer Gemeinschaft') +
      '<p style="font-size:.84rem;color:var(--text-muted);margin:.5rem 0 .75rem;">' +
        'Zitate, die darunter erscheinen. Reihenfolge per Drag &amp; Drop änderbar.' +
      '</p>' +
      '<div id="testimonials-list">' + rows + '</div>' +
      '<p class="text-muted" id="testimonials-empty" style="font-size:.85rem;' + (rows ? 'display:none;' : '') + '">Noch keine Zitate hinzugefügt.</p>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="testimonialRowAdd()">➕ Zitat hinzufügen</button>' +
    '</div>';
  }

  function initTestimonialsSortable() {
    var el = id('testimonials-list');
    if (el && window.Sortable && !el._sortableInit) {
      el._sortableInit = true;
      Sortable.create(el, { handle: '.item-drag', animation: 150 });
    }
  }

  window.testimonialRowAdd = function() {
    var list = id('testimonials-list');
    if (!list) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = renderTestimonialRow({});
    list.appendChild(wrap.firstChild);
    var empty = id('testimonials-empty');
    if (empty) empty.style.display = 'none';
    markDirty();
  };

  function collectTestimonialsList() {
    var result = [];
    document.querySelectorAll('#testimonials-list .testimonial-row').forEach(function(row) {
      var textEl = row.querySelector('.ts-text');
      var nameEl = row.querySelector('.ts-name');
      var rolleEl = row.querySelector('.ts-rolle');
      var iconEl = row.querySelector('.ts-icon');
      var text = textEl ? textEl.value.trim() : '';
      var name = nameEl ? nameEl.value.trim() : '';
      if (text && name) {
        result.push({
          text: text,
          name: name,
          rolle: rolleEl ? rolleEl.value.trim() : '',
          icon: iconEl ? iconEl.value.trim() : ''
        });
      }
    });
    return result;
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
    data.hero_slides = collectHeroSlidesList();
    data.testimonials_titel = gv('testimonials_titel');
    data.testimonials_untertitel = gv('testimonials_untertitel');
    data.testimonials = collectTestimonialsList();
    return data;
  }

  /* ────────────────────────────────────────────────────────────
     AKTUELLES (list + item editor)
  ──────────────────────────────────────────────────────────── */
  // Liste war bisher unsortiert (rohe Speicher-Reihenfolge) und ohne Filter
  // - bei wachsender Beitragsanzahl fand Frank nichts mehr wieder (Jahre
  // sprangen wild durcheinander: 2025, 2026, 2022, ...). Jetzt: Liste wird
  // immer nach Datum absteigend (neueste zuerst) angezeigt, zusätzlich
  // Jahr- und Kategorie-Filter oben. Sortierung/Filter wirken nur auf die
  // ANZEIGE (S.data.beitraege bleibt unangetastet) - die echten Array-
  // Indizes für Bearbeiten/Löschen/Archivieren bleiben dadurch stabil.
  function datumToIsoFlexible(datum) {
    if (!datum) return '';
    var s = String(datum).trim();
    // Bereits ISO oder "D.MM.YYYY" (Standardformat aus dem Formular)
    var iso = datumToIso(s);
    if (iso) return iso;
    // "D. Monatsname YYYY" (deutsch ODER versehentlich englisch gespeichert)
    var m = s.match(/^(\d{1,2})\.?\s+([A-Za-zÄÖÜäöüß]+)\s+(\d{4})$/);
    if (m) {
      var MONTHS = {
        januar:1, jan:1, january:1,
        februar:2, feb:2, february:2,
        maerz:3, märz:3, mrz:3, march:3, mar:3,
        april:4, apr:4,
        mai:5, may:5,
        juni:6, jun:6, june:6,
        juli:7, jul:7, july:7,
        august:8, aug:8,
        september:9, sep:9, sept:9,
        oktober:10, okt:10, october:10, oct:10,
        november:11, nov:11,
        dezember:12, dez:12, december:12, dec:12
      };
      var mo = MONTHS[m[2].toLowerCase()];
      if (mo) return m[3] + '-' + String(mo).padStart(2,'0') + '-' + m[1].padStart(2,'0');
    }
    return '';
  }

  // Merkt sich, welche Ansicht (normale Liste vs. Archiv-Unterseite) gerade
  // offen ist, damit Zurück/Speichern/Löschen/Archivieren aus dem
  // Bearbeiten-Formular zur richtigen Ansicht zurückführen - gleiches
  // Muster wie S.svcAnsicht bei Service (siehe dort für Begründung).
  function aktuellesAktuelleAnsichtRendern() {
    if (S.aktAnsicht === 'archiv') aktuellesArchivSeiteOeffnen();
    else renderAktuelles(S.section, S.data);
  }

  function renderAktuelles(def, data) {
    S.aktAnsicht = 'alle';
    var beitraege = data.beitraege || [];
    S.aktFilterJahr = S.aktFilterJahr || '';
    S.aktFilterKat  = S.aktFilterKat  || '';

    // Anzeigeliste: Original-Index i für die Buttons behalten, dann
    // filtern und nach Datum absteigend sortieren (unparsbare Daten
    // fallen ans Ende, Reihenfolge untereinander stabil).
    var indexed = beitraege.map(function(b, i) {
      return { b: b, i: i, jahr: b.jahr || jahrAusDatum(b.datum), iso: datumToIsoFlexible(b.datum) };
    });
    // Archivierte Beiträge erscheinen jetzt NICHT mehr inline mit Badge in
    // dieser Liste, sondern nur noch auf der Archiv-Unterseite (22.08.2026,
    // wie bei Service/Medien & Bilder) - siehe aktuellesArchivSeiteOeffnen().
    var aktive = indexed.filter(function(e) { return !e.b.archiviert; });
    var archivAnzahl = indexed.length - aktive.length;

    var jahre = [];
    aktive.forEach(function(e) { if (e.jahr && jahre.indexOf(e.jahr) === -1) jahre.push(e.jahr); });
    jahre.sort(function(a, b) { return b - a; });

    var gefiltert = aktive.filter(function(e) {
      if (S.aktFilterJahr && e.jahr !== S.aktFilterJahr) return false;
      if (S.aktFilterKat && (e.b.kategorie || '') !== S.aktFilterKat) return false;
      return true;
    });
    gefiltert.sort(function(a, b) {
      if (a.iso && b.iso) return b.iso.localeCompare(a.iso);
      if (a.iso) return -1;
      if (b.iso) return 1;
      return a.i - b.i;
    });

    var jahrOptions = '<option value="">Alle Jahre</option>' + jahre.map(function(j) {
      return '<option value="' + escAttr(j) + '"' + (j === S.aktFilterJahr ? ' selected' : '') + '>' + escHtml(j) + '</option>';
    }).join('');
    var katOptions = '<option value="">Alle Kategorien</option>' + alleAktuellesKategorien().map(function(k) {
      return '<option value="' + escAttr(k) + '"' + (k === S.aktFilterKat ? ' selected' : '') + '>' + escHtml(k) + '</option>';
    }).join('');

    var html = panelHeader(def.label,
      '<button type="button" class="btn btn-outline btn-sm" onclick="aktuellesArchivSeiteOeffnen()">📁 Archivierte Beiträge (' + archivAnzahl + ')</button>' +
      '<button class="btn btn-primary" onclick="aktuellesNeu()">➕ Neuer Beitrag</button>') +
      '<div class="panel-body">' +

      // ── Jahr-/Kategorie-Filter (Frank-Wunsch 20.08.2026) ────
      // Die frühere "⚙️ Anzeigeeinstellungen"-Karte (Anzahl anzeigen /
      // hauptseite_anzahl) wurde am 22.08.2026 entfernt - überholt, seit
      // Besucher auf der öffentlichen Aktuelles-Seite selbst nach Jahr
      // filtern können (siehe jahresFilter() in aktuelles/index.html).
      '<div class="form-card">' +
        '<div class="form-card-title">🔍 Filtern &amp; Sortieren</div>' +
        '<p class="text-muted" style="margin-bottom:1rem;font-size:.85rem;">Die Liste ist immer nach Datum sortiert (neueste zuerst). Optional zusätzlich nach Jahr und/oder Kategorie filtern.</p>' +
        '<div class="field-row" style="align-items:center;gap:1rem;flex-direction:row;flex-wrap:wrap;">' +
          '<select class="field-input" id="akt-filter-jahr" style="max-width:180px;" onchange="aktuellesFilterChange()">' + jahrOptions + '</select>' +
          '<select class="field-input" id="akt-filter-kat" style="max-width:220px;" onchange="aktuellesFilterChange()">' + katOptions + '</select>' +
          (S.aktFilterJahr || S.aktFilterKat
            ? '<button class="btn btn-sm btn-ghost" onclick="aktuellesFilterReset()">✕ Filter zurücksetzen</button>'
            : '') +
        '</div>' +
      '</div>' +

      '<p class="text-muted" style="margin-bottom:1rem;">' + gefiltert.length + ' von ' + aktive.length + ' Beiträgen. Klicken zum Bearbeiten.</p>';

    gefiltert.forEach(function(entry) {
      var b = entry.b, i = entry.i;
      html += '<div class="item-card" onclick="aktuellesEdit(' + i + ')">' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(b.titel || '(Kein Titel)') + '</div>' +
          '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
            (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-ghost" title="Ins Archiv verschieben" onclick="event.stopPropagation();aktuellesArchivToggle(' + i + ')">📦 Archivieren</button>' +
          '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();aktuellesEdit(' + i + ')">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();aktuellesDelete(' + i + ')">Löschen</button>' +
        '</div>' +
      '</div>';
    });

    if (!gefiltert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine Beiträge in dieser Filteransicht.</p></div>';
    }

    html += '</div>';
    renderMain(html);
  }

  window.aktuellesFilterChange = function() {
    S.aktFilterJahr = val('akt-filter-jahr');
    S.aktFilterKat  = val('akt-filter-kat');
    renderAktuelles(S.section, S.data);
  };
  window.aktuellesFilterReset = function() {
    S.aktFilterJahr = '';
    S.aktFilterKat  = '';
    renderAktuelles(S.section, S.data);
  };

  // Eigene "Unterordner"-Ansicht statt Inline-Badge - ersetzt admin-main
  // komplett, mit "← Zurück"-Button zur normalen Aktuelles-Übersicht
  // (renderAktuelles). Gleiches Muster wie serviceArchivSeiteOeffnen() /
  // medienArchivSeiteOeffnen() (22.08.2026).
  window.aktuellesArchivSeiteOeffnen = function() {
    S.aktAnsicht = 'archiv';
    var beitraege = S.data.beitraege || [];
    var indexed = beitraege.map(function(b, i) { return { b: b, i: i, iso: datumToIsoFlexible(b.datum) }; });
    var archiviert = indexed.filter(function(e) { return e.b.archiviert; });
    archiviert.sort(function(a, b) {
      if (a.iso && b.iso) return b.iso.localeCompare(a.iso);
      if (a.iso) return -1;
      if (b.iso) return 1;
      return a.i - b.i;
    });

    var html = '<div class="panel-header"><h2>📦 Archivierte Beiträge</h2></div>' +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<button type="button" class="btn btn-outline btn-sm" style="margin-bottom:1rem;" onclick="confirmNav(function(){renderAktuelles(S.section,S.data)})">← Zurück zu Aktuelles</button>' +
          '<p class="text-muted" style="margin-bottom:0;font-size:.85rem;">Archivierte Beiträge erscheinen nicht mehr auf der Hauptseite. Über „↩️ Wiederherstellen" lassen sie sich jederzeit zurückholen.</p>' +
        '</div>';

    if (!archiviert.length) {
      html += '<div class="form-card"><p class="text-muted">Keine archivierten Beiträge.</p></div>';
    } else {
      archiviert.forEach(function(entry) {
        var b = entry.b, i = entry.i;
        html += '<div class="item-card" onclick="aktuellesEdit(' + i + ')">' +
          '<div class="item-body">' +
            '<div class="item-title">' + escHtml(b.titel || '(Kein Titel)') + '</div>' +
            '<div class="item-meta">📅 ' + escHtml(b.datum || '') +
              (b.kategorie ? ' <span class="item-badge">' + escHtml(b.kategorie) + '</span>' : '') +
            '</div>' +
          '</div>' +
          '<div class="item-actions">' +
            '<button class="btn btn-sm btn-outline" title="Aus Archiv zurückholen" onclick="event.stopPropagation();aktuellesArchivToggle(' + i + ')">↩️ Wiederherstellen</button>' +
            '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();aktuellesEdit(' + i + ')">Bearbeiten</button>' +
            '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();aktuellesDelete(' + i + ')">Löschen</button>' +
          '</div>' +
        '</div>';
      });
    }
    html += '</div>';
    renderMain(html);
  };

  window.aktuellesNeu = function() {
    var data = S.data;
    data.beitraege = data.beitraege || [];
    var newB = { titel:'', datum:'', jahr: String(new Date().getFullYear()), kategorie:'Allgemein', bild:'', text:'', link:'', archiviert: false };
    data.beitraege.unshift(newB);
    aktuellesEdit(0);
  };

  window.aktuellesEdit = function(idx) {
    destroyMDE();
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    var html = panelHeader('📰 Beitrag bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(aktuellesAktuelleAnsichtRendern)">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="aktuelleSave(' + idx + ')">💾 Speichern</button>',
        true) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="field-row">' +
            '<label class="field-label" for="f-b-jahr">Erscheinungsjahr</label>' +
            '<input class="field-input" type="number" id="f-b-jahr" value="' + escAttr(b.jahr || jahrAusDatum(b.datum)) + '" placeholder="' + escAttr(jahrAusDatum(b.datum) || String(new Date().getFullYear())) + '" style="max-width:140px">' +
            '<p class="field-hint">Bestimmt, in welchem Archiv-Jahr der Beitrag einsortiert wird (unabhängig vom Datum unten). Normalerweise gleich dem Jahr des Datums.</p>' +
          '</div>' +
          fDate('b-datum', 'Datum', b.datum) +
          fText('b-titel', 'Titel', b.titel) +
          fKategorieDropdown(b.kategorie) +
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
        // "Aktuelles" ist ein eigenständiges Mini-Formular (läuft nicht über
        // renderForm()), deshalb Bildergalerie UND Downloads hier direkt
        // eingebunden statt über injectGalerieCard()/injectDownloadsCard() –
        // siehe Frank-Wunsch Punkt 2 (Fotos) und Punkt 5 (Dokumente, pro
        // Beitrag statt seitenweit).
        renderGalerieCard(b) +
        renderDownloadsCard(b) +
      '</div>';
    renderMain(html);
    initMDE('b-text');
    initGalerieSortable();
    initDownloadsSortable();
  };

  window.aktuelleSave = async function(idx) {
    var b = S.data.beitraege[idx];
    b.titel     = gv('b-titel');
    b.datum     = isoToDatum(gv('b-datum'));
    b.jahr      = gv('b-jahr') || jahrAusDatum(b.datum);
    b.kategorie = gv('b-kategorie');
    b.bild      = gv('b-bild');
    b.text      = getMDE();
    b.link      = gv('b-link');
    var archCheck = id('b-archiviert');
    b.archiviert = archCheck ? archCheck.checked : (b.archiviert || false);
    b.galerie = collectGalerieList();
    b.galerie_titel = collectGalerieTitel();
    b.downloads = collectDownloadsList();
    try {
      await doSave(S.section.file, S.data, '📰 Aktuelles: Beitrag gespeichert');
      toast('✅ Beitrag gespeichert!', 'ok');
      S.dirty = false;
      aktuellesAktuelleAnsichtRendern();
    } catch (e) { await handleSaveError(e); }
  };

  window.aktuellesArchivToggle = async function(idx) {
    var b = (S.data.beitraege || [])[idx];
    if (!b) return;
    b.archiviert = !b.archiviert;
    try {
      await doSave(S.section.file, S.data, '📰 Aktuelles: Archivstatus geändert');
      toast(b.archiviert ? '📦 Ins Archiv verschoben' : '↩️ Aus Archiv zurückgeholt', 'ok');
      aktuellesAktuelleAnsichtRendern();
    } catch (e) {
      b.archiviert = !b.archiviert; // lokale, optimistische Änderung zurücknehmen - nicht gespeichert
      await handleSaveError(e);
    }
  };

  window.aktuellesDelete = function(idx) {
    showConfirm('Beitrag löschen', 'Diesen Beitrag wirklich löschen?', async function() {
      var entfernt = S.data.beitraege.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '📰 Aktuelles: Beitrag gelöscht');
        toast('🗑️ Beitrag gelöscht', 'info');
        aktuellesAktuelleAnsichtRendern();
      } catch (e) {
        if (entfernt.length) S.data.beitraege.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     TERMINE
     Frank-Wünsche 20.08.2026: Listenansicht wie bei Aktuelles
     (.item-card statt Tabelle) inkl. Archiv-Badge/Toggle, Kategorien
     dauerhaft verwaltbar wie bei Aktuelles, Ort zu Straße/PLZ/Ort +
     eigenem Revier/Hegering-Feld erweitert (Vorschlagsliste aus
     content/hegeringe.json, freie Eingabe weiterhin möglich).
  ──────────────────────────────────────────────────────────── */
  var _hegeringOptionenCache = null;
  function ladeHegeringOptionen() {
    if (_hegeringOptionenCache) return Promise.resolve(_hegeringOptionenCache);
    return fetch('/content/hegeringe.json').then(function(r) { return r.json(); }).then(function(d) {
      _hegeringOptionenCache = (d.hegeringe || []).map(function(h) {
        return (h.nummer || '') + (h.name ? ' – ' + h.name : '');
      }).filter(Boolean);
      return _hegeringOptionenCache;
    }).catch(function() { return []; });
  }

  function renderTermine(def, data) {
    var termine = data.termine || [];
    var einst = data.einstellungen || {};
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="confirmNav(termineNeu)">➕ Neuer Termin</button>') +
      '<div class="panel-body">' +

      // Überschrift & Einleitungstext der öffentlichen Termine-Seite waren bisher
      // fest im HTML eingefroren (u.a. mit Jahreszahl "2025") und nirgends
      // änderbar - jetzt hier editierbar, wie die "Anzeigeeinstellungen" bei
      // Aktuelles (Frank-Wunsch 20.08.2026).
      '<div class="form-card">' +
        '<div class="form-card-title">⚙️ Überschrift &amp; Einleitungstext (Live-Seite)</div>' +
        fText('t-ueberschrift', 'Überschrift', einst.ueberschrift || 'Veranstaltungskalender 2026') +
        '<div class="field-row">' +
          '<label class="field-label" for="f-t-einleitung">Einleitungstext</label>' +
          '<textarea class="field-input" id="f-t-einleitung" rows="3">' + escHtml(einst.einleitung || 'Hier finden Sie alle aktuellen Termine und Veranstaltungen der Kreisjägerschaft Segeberg e.V. sowie der angeschlossenen Hegeringe. Bitte beachten Sie, dass sich Termine kurzfristig ändern können.') + '</textarea>' +
        '</div>' +
        '<button class="btn btn-sm btn-outline" onclick="termineEinstSave()">Speichern</button>' +
      '</div>';

    if (termine.length === 0) {
      html += '<div class="form-card"><p class="text-muted">Noch keine Termine. Klicken Sie auf "Neuer Termin".</p></div>';
    } else {
      html += '<p class="text-muted" style="margin-bottom:1rem;">' + termine.length + ' Termine. Klicken zum Bearbeiten.</p>';
      termine.forEach(function(t, i) {
        var archivBadge = t.archiviert
          ? '<span class="item-badge" style="background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;">📦 Archiv</span> '
          : '';
        var ortMeta = [t.ort, t.revier].filter(Boolean).join(' · ');
        html += '<div class="item-card" onclick="confirmNav(function(){termineEdit(' + i + ')})">' +
          '<div class="item-body">' +
            '<div class="item-title">' + archivBadge + escHtml(t.veranstaltung || '(Kein Titel)') + '</div>' +
            '<div class="item-meta">📅 ' + escHtml(t.datum || '') + (t.uhrzeit ? ' · ' + escHtml(t.uhrzeit) : '') +
              (ortMeta ? ' · ' + escHtml(ortMeta) : '') +
              (t.kategorie ? ' <span class="item-badge">' + escHtml(t.kategorie) + '</span>' : '') +
            '</div>' +
          '</div>' +
          '<div class="item-actions">' +
            '<button class="btn btn-sm ' + (t.archiviert ? 'btn-outline' : 'btn-ghost') + '" ' +
              'title="' + (t.archiviert ? 'Aus Archiv zurückholen' : 'Ins Archiv verschieben') + '" ' +
              'onclick="event.stopPropagation();confirmNav(function(){termineArchivToggle(' + i + ')})">' +
              (t.archiviert ? '↩️ Wiederherstellen' : '📦 Archivieren') +
            '</button>' +
            '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmNav(function(){termineEdit(' + i + ')})">Bearbeiten</button>' +
            '<button class="btn btn-sm btn-danger-outline" onclick="event.stopPropagation();confirmNav(function(){termineDelete(' + i + ')})">Löschen</button>' +
          '</div>' +
        '</div>';
      });
    }
    html += '</div>';
    renderMain(html);
  }

  window.termineNeu = function() {
    S.data.termine = S.data.termine || [];
    S.data.termine.unshift({ datum:'', uhrzeit:'', veranstaltung:'', strasse:'', plz:'', ort:'', revier:'', kategorie:'Kreisveranstaltung', archiviert:false });
    termineEdit(0);
  };

  window.termineEdit = async function(idx) {
    var t = (S.data.termine || [])[idx];
    if (!t) return;
    var hegeringOptionen = await ladeHegeringOptionen();
    var html = panelHeader('📅 Termin bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderTermine(S.section,S.data)})">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="termineSave(' + idx + ')">💾 Speichern</button>',
        true) +
      '<div class="panel-body"><div class="form-card">' +
        fDate('t-datum', 'Datum', t.datum) +
        fText('t-uhrzeit', 'Uhrzeit', t.uhrzeit, 'z.B. 18:00 Uhr') +
        fText('t-veranstaltung', 'Veranstaltung', t.veranstaltung) +
        fText('t-strasse', 'Straße', t.strasse, 'optional') +
        '<div class="field-row field-row-2">' +
          '<div><label class="field-label" for="f-t-plz">PLZ</label>' +
            '<input class="field-input" type="text" id="f-t-plz" value="' + escAttr(t.plz || '') + '"></div>' +
          '<div><label class="field-label" for="f-t-ort">Ort</label>' +
            '<input class="field-input" type="text" id="f-t-ort" value="' + escAttr(t.ort || '') + '"></div>' +
        '</div>' +
        fCombobox('t-revier', 'Revier / Hegering', t.revier, hegeringOptionen) +
        fTermineKategorieDropdown(t.kategorie) +
        '<div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">' +
          '<label class="field-label" style="min-width:160px;margin:0">Ins Archiv verschieben</label>' +
          '<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">' +
            '<input type="checkbox" id="t-archiviert"' + (t.archiviert ? ' checked' : '') + ' style="width:18px;height:18px;cursor:pointer;">' +
            '<span style="font-size:.85rem;color:var(--text-muted);">Erscheint nicht mehr auf der Live-Seite (zusätzlich werden Termine automatisch 7 Tage nach ihrem Datum ausgeblendet)</span>' +
          '</label>' +
        '</div>' +
      '</div></div>';
    renderMain(html);
  };

  window.termineSave = async function(idx) {
    var t = S.data.termine[idx];
    t.datum        = isoToDatum(gv('t-datum'));
    t.uhrzeit      = gv('t-uhrzeit');
    t.veranstaltung= gv('t-veranstaltung');
    t.strasse      = gv('t-strasse');
    t.plz          = gv('t-plz');
    t.ort          = gv('t-ort');
    t.revier       = gv('t-revier');
    t.kategorie    = gv('t-kategorie');
    var archCheck  = id('t-archiviert');
    t.archiviert   = archCheck ? archCheck.checked : (t.archiviert || false);
    // Sort by date
    S.data.termine.sort(function(a, b) {
      return datumToIso(a.datum).localeCompare(datumToIso(b.datum));
    });
    try {
      await doSave(S.section.file, S.data, '📅 Termin gespeichert');
      toast('✅ Termin gespeichert!', 'ok');
      S.dirty = false;
      renderTermine(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.termineArchivToggle = async function(idx) {
    var t = (S.data.termine || [])[idx];
    if (!t) return;
    t.archiviert = !t.archiviert;
    try {
      await doSave(S.section.file, S.data, '📅 Termine: Archivstatus geändert');
      toast(t.archiviert ? '📦 Ins Archiv verschoben' : '↩️ Aus Archiv zurückgeholt', 'ok');
      renderTermine(S.section, S.data);
    } catch (e) {
      t.archiviert = !t.archiviert; // lokale, optimistische Änderung zurücknehmen - nicht gespeichert
      await handleSaveError(e);
    }
  };

  window.termineEinstSave = async function() {
    S.data.einstellungen = S.data.einstellungen || {};
    S.data.einstellungen.ueberschrift = gv('t-ueberschrift');
    var einlEl = id('f-t-einleitung');
    S.data.einstellungen.einleitung = einlEl ? einlEl.value : '';
    try {
      await doSave(S.section.file, S.data, '📅 Termine: Überschrift/Einleitung gespeichert');
      toast('✅ Gespeichert!', 'ok');
      S.dirty = false;
    } catch (e) { await handleSaveError(e); }
  };

  window.termineDelete = function(idx) {
    showConfirm('Termin löschen', 'Diesen Termin wirklich löschen?', async function() {
      var entfernt = S.data.termine.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '📅 Termin gelöscht');
        toast('🗑️ Termin gelöscht', 'info');
        renderTermine(S.section, S.data);
      } catch (e) {
        if (entfernt.length) S.data.termine.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     PERSONEN (Vorstand, Obleute)
  ──────────────────────────────────────────────────────────── */
  function renderPersonen(def, data) {
    var liste = data[def.dataKey] || [];
    // hideDefaultSave=true: diese Listenansicht hat kein eigenes Speichern -
    // jede Aktion (Bearbeiten, Löschen, Sortieren) speichert einzeln direkt
    // per doSave(). Der generische "💾 Speichern"-Button aus panelHeader()
    // wäre hier nie mit einer Funktion verbunden gewesen (Nebenfund aus
    // Phase 5B.5, bereinigt statt einer neuen Speicherfunktion nur für ihn).
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="personAdd()">➕ Person hinzufügen</button>',
      true) +
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
    renderMain(html);

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
          try {
            await doSave(def.file, data, '👤 Reihenfolge geändert');
            toast('✅ Reihenfolge gespeichert', 'ok');
          } catch (e) {
            arr.splice(evt.newIndex, 1); // Verschiebung zurücknehmen - nicht gespeichert
            arr.splice(evt.oldIndex, 0, moved);
            renderPersonen(def, data);
            await handleSaveError(e);
          }
        }
      });
    }
  }

  window.personEdit = function(idx) {
    var def = S.section;
    var p = (S.data[def.dataKey] || [])[idx];
    if (!p) return;
    var html = panelHeader('👤 Person bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderPersonen(S.section,S.data)})">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="personSave(' + idx + ')">💾 Speichern</button>',
        true) +
      '<div class="panel-body"><div class="form-card">' +
        fText('p-rolle', 'Funktion / Rolle', p.rolle) +
        fText('p-name', 'Name', p.name) +
        fText('p-email', 'E-Mail', p.email) +
        fText('p-telefon', 'Telefon', p.telefon) +
        fImage('p-bild', 'Foto', p.bild) +
      '</div></div>';
    renderMain(html);
  };

  window.personSave = async function(idx) {
    var def = S.section;
    var p = S.data[def.dataKey][idx];
    p.rolle   = gv('p-rolle');
    p.name    = gv('p-name');
    p.email   = gv('p-email');
    p.telefon = gv('p-telefon');
    p.bild    = gv('p-bild');
    try {
      await doSave(def.file, S.data, '👤 Person gespeichert');
      toast('✅ Gespeichert!', 'ok');
      S.dirty = false;
      renderPersonen(def, S.data);
    } catch (e) { await handleSaveError(e); }
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
      var entfernt = S.data[def.dataKey].splice(idx, 1);
      try {
        await doSave(def.file, S.data, '👤 Person gelöscht');
        toast('🗑️ Gelöscht', 'info');
        renderPersonen(def, S.data);
      } catch (e) {
        if (entfernt.length) S.data[def.dataKey].splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     PARTNER (03.09.2026 - Partnerbereich neu aufgebaut, Laurin-Auftrag)
     Bewusst nach exakt demselben Muster wie PERSONEN oben gebaut (Liste +
     Drag&Drop + Bearbeiten + Löschen, jede Aktion speichert einzeln direkt
     per doSave() statt über einen zentralen Speichern-Button) - kein neues
     paralleles Admin-System, nur ein weiteres Modul nach bestehender
     Konvention. Einziger Unterschied zu Personen: "aktiv"-Umschalter statt
     nur Löschen (Auftrags-Punkt 6 "Partner löschen/deaktivieren") - ein
     deaktivierter Partner bleibt im Admin bearbeitbar, verschwindet aber
     aus der öffentlichen Übersicht (siehe partner/index.html: Filter
     "aktiv !== false"). Reihenfolge bewusst NICHT über ein eigenes
     "sortierung"-Feld, sondern wie bei Personen/Hegeringe über die reine
     Array-Position (persistiert beim Drag&Drop) - im Projekt gibt es an
     keiner Stelle ein separates sortierung-Feld, ein neues nur für Partner
     wäre eine zweite, inkonsistente Sortier-Logik gewesen.
  ──────────────────────────────────────────────────────────── */
  function renderPartner(def, data) {
    var liste = data[def.dataKey] || [];
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="partnerAdd()">➕ Partner hinzufügen</button>',
      true) +
      '<div class="panel-body">' +
        '<p class="text-muted" style="margin-bottom:1rem;">Klicken zum Bearbeiten. Reihenfolge per Drag &amp; Drop.</p>' +
        '<div id="partner-list">';

    liste.forEach(function(p, i) {
      html += '<div class="item-card" data-idx="' + i + '">' +
        '<div class="item-drag">⠿</div>' +
        '<div class="item-body">' +
          '<div class="item-title">' + escHtml(p.name || '(Kein Name)') + (p.aktiv === false ? ' <span class="item-badge">Inaktiv</span>' : '') + '</div>' +
          '<div class="item-meta">' + escHtml(p.website || '') + '</div>' +
        '</div>' +
        '<div class="item-actions">' +
          '<button class="btn btn-sm btn-outline" onclick="partnerEdit(' + i + ')">Bearbeiten</button>' +
          '<button class="btn btn-sm btn-danger-outline" onclick="partnerDelete(' + i + ')">Löschen</button>' +
        '</div></div>';
    });

    html += '</div></div>';
    renderMain(html);

    var listEl = id('partner-list');
    if (listEl && window.Sortable) {
      Sortable.create(listEl, {
        handle: '.item-drag',
        animation: 150,
        onEnd: async function(evt) {
          var arr = data[def.dataKey];
          var moved = arr.splice(evt.oldIndex, 1)[0];
          arr.splice(evt.newIndex, 0, moved);
          try {
            await doSave(def.file, data, '🤝 Partner-Reihenfolge geändert');
            toast('✅ Reihenfolge gespeichert', 'ok');
          } catch (e) {
            arr.splice(evt.newIndex, 1);
            arr.splice(evt.oldIndex, 0, moved);
            renderPartner(def, data);
            await handleSaveError(e);
          }
        }
      });
    }
  }

  window.partnerEdit = function(idx) {
    var def = S.section;
    var p = (S.data[def.dataKey] || [])[idx];
    if (!p) return;
    var html = panelHeader('🤝 Partner bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderPartner(S.section,S.data)})">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="partnerSave(' + idx + ')">💾 Speichern</button>',
        true) +
      '<div class="panel-body"><div class="form-card">' +
        fText('pn-name', 'Name', p.name) +
        fImage('pn-logo', 'Logo', p.logo) +
        fToggle('pn-aktiv', 'Aktiv (in der öffentlichen Übersicht sichtbar)', p.aktiv !== false) +
        fText('pn-kurzbeschreibung', 'Kurzbeschreibung / Kategorie', p.kurzbeschreibung, 'z.B. "Optik & Zubehör" (erscheint auf der Kachel)') +
        fTextarea('pn-beschreibung', 'Ausführliche Beschreibung', p.beschreibung, 6) +
      '</div><div class="form-card">' +
        '<div class="form-card-title">📞 Kontakt (optional)</div>' +
        fText('pn-ansprechpartner', 'Ansprechpartner', p.ansprechpartner) +
        fText('pn-telefon', 'Telefonnummer', p.telefon) +
        fText('pn-email', 'E-Mail', p.email) +
        fText('pn-website', 'Website', p.website, 'https://...') +
      '</div><div class="form-card">' +
        '<div class="form-card-title">📄 Weitere Angaben (optional)</div>' +
        fTextarea('pn-rahmenvertrag', 'Rahmenvertrag', p.rahmenvertrag, 3) +
        fTextarea('pn-vorteile', 'Vorteile / Leistungen', p.vorteile, 3) +
        fTextarea('pn-weitere_infos', 'Weitere Hinweise', p.weitere_infos, 3) +
      '</div></div>';
    renderMain(html);
  };

  window.partnerSave = async function(idx) {
    var def = S.section;
    var p = S.data[def.dataKey][idx];
    p.name             = gv('pn-name');
    p.logo             = gv('pn-logo');
    p.aktiv            = toggleVal('pn-aktiv');
    p.kurzbeschreibung = gv('pn-kurzbeschreibung');
    p.beschreibung     = gv('pn-beschreibung');
    p.ansprechpartner  = gv('pn-ansprechpartner');
    p.telefon          = gv('pn-telefon');
    p.email            = gv('pn-email');
    p.website          = gv('pn-website');
    p.rahmenvertrag    = gv('pn-rahmenvertrag');
    p.vorteile         = gv('pn-vorteile');
    p.weitere_infos    = gv('pn-weitere_infos');
    try {
      await doSave(def.file, S.data, '🤝 Partner gespeichert');
      toast('✅ Gespeichert!', 'ok');
      S.dirty = false;
      renderPartner(def, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.partnerAdd = function() {
    var def = S.section;
    S.data[def.dataKey] = S.data[def.dataKey] || [];
    S.data[def.dataKey].push({
      id: 'pn-' + Date.now(), name: '', logo: '', kurzbeschreibung: '', beschreibung: '',
      ansprechpartner: '', telefon: '', email: '', website: '',
      rahmenvertrag: '', vorteile: '', weitere_infos: '', aktiv: true
    });
    partnerEdit(S.data[def.dataKey].length - 1);
  };

  window.partnerDelete = function(idx) {
    var def = S.section;
    showConfirm('Partner löschen', 'Diesen Partner wirklich löschen? Alternativ kann er über "Bearbeiten" auch nur deaktiviert werden.', async function() {
      var entfernt = S.data[def.dataKey].splice(idx, 1);
      try {
        await doSave(def.file, S.data, '🤝 Partner gelöscht');
        toast('🗑️ Gelöscht', 'info');
        renderPartner(def, S.data);
      } catch (e) {
        if (entfernt.length) S.data[def.dataKey].splice(idx, 0, entfernt[0]);
        await handleSaveError(e);
      }
    });
  };

  /* ────────────────────────────────────────────────────────────
     HEGERINGE
  ──────────────────────────────────────────────────────────── */
  function renderHegeringe(def, data) {
    var liste = data.hegeringe || [];
    // hideDefaultSave=true: siehe renderPersonen oben - gleiche Begründung,
    // gleicher Nebenfund aus Phase 5B.5.
    var html = panelHeader(def.label,
      '<button class="btn btn-primary" onclick="hegeringAdd()">➕ Hegering hinzufügen</button>',
      true) +
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
    renderMain(html);

    if (window.Sortable) {
      Sortable.create(id('hegering-list'), {
        handle: '.item-drag', animation: 150,
        onEnd: async function(e) {
          var a = S.data.hegeringe;
          var m = a.splice(e.oldIndex,1)[0]; a.splice(e.newIndex,0,m);
          try {
            await doSave(def.file, S.data, '🗺️ Hegering-Reihenfolge geändert');
            toast('✅ Reihenfolge gespeichert', 'ok');
          } catch (err) {
            a.splice(e.newIndex, 1); // Verschiebung zurücknehmen - nicht gespeichert
            a.splice(e.oldIndex, 0, m);
            renderHegeringe(def, S.data);
            await handleSaveError(err);
          }
        }
      });
    }
  }

  window.hegeringEdit = function(idx) {
    var h = (S.data.hegeringe || [])[idx];
    if (!h) return;
    var html = panelHeader('🗺️ Hegering bearbeiten',
        '<button class="btn btn-outline" onclick="confirmNav(function(){renderHegeringe(S.section,S.data)})">← Zurück</button>' +
        '<button class="btn btn-primary" onclick="hegeringSave(' + idx + ')">💾 Speichern</button>',
        true) +
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
    renderMain(html);
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
    try {
      await doSave(S.section.file, S.data, '🗺️ Hegering gespeichert');
      toast('✅ Gespeichert!', 'ok');
      S.dirty = false;
      renderHegeringe(S.section, S.data);
    } catch (e) { await handleSaveError(e); }
  };

  window.hegeringAdd = function() {
    S.data.hegeringe = S.data.hegeringe || [];
    S.data.hegeringe.push({ nummer:'', name:'', obmann:'', geschlecht:'Hegeringsleiter/in', gemeinden:'', email:'', telefon:'' });
    hegeringEdit(S.data.hegeringe.length - 1);
  };

  window.hegeringDelete = function(idx) {
    showConfirm('Hegering löschen', 'Diesen Hegering wirklich löschen?', async function() {
      var entfernt = S.data.hegeringe.splice(idx, 1);
      try {
        await doSave(S.section.file, S.data, '🗺️ Hegering gelöscht');
        toast('🗑️ Gelöscht', 'info');
        renderHegeringe(S.section, S.data);
      } catch (e) {
        if (entfernt.length) S.data.hegeringe.splice(idx, 0, entfernt[0]); // Löschung zurücknehmen - nicht gespeichert
        await handleSaveError(e);
      }
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
          '<div class="form-card-title">Aufgaben & Zuständigkeiten</div>' +
          fTipTap('kjm-aufgaben', 'Aufgaben', true) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">Grußwort</div>' +
          fTipTap('kjm-grußwort', 'Grußwort', true) +
        '</div>' +
        renderDownloadsCard(data) +
      '</div>' + saveBar();
    renderMain(html);
    initTiptap('kjm-aufgaben', data.aufgaben || '');
    // Grußwort war bis Phase 5B.5 ein einfaches Textfeld (fTextarea). Umgestellt
    // auf denselben TipTap-Editor wie das Aufgaben-Feld direkt daneben, damit
    // beide Felder auf dieser Seite einheitlich bedienbar sind. Bestehender
    // Alttext (reiner Text ohne HTML) wird von initTiptap/convertMarkdownToHtml
    // automatisch und verlustfrei in Absätze umgewandelt - siehe dazu auch die
    // an das Aufgaben-Feld angelehnte HTML-Erkennung in
    // kreisjjaegermeister/index.html, die verhindert, dass die öffentliche
    // Seite frisch gespeichertes HTML fälschlich nochmal in <p>-Tags verpackt.
    initTiptap('kjm-grußwort', data.grußwort || '');
    initDownloadsSortable();
    bindSaveBtn();
  }

  function collectKJM(data) {
    data.name     = gv('kjm-name');
    data.bild     = gv('kjm-bild');
    data.email    = gv('kjm-email');
    data.telefon  = gv('kjm-telefon');
    data.aufgaben = getTiptapValue('kjm-aufgaben', data.aufgaben, 'Aufgaben');
    data.grußwort = getTiptapValue('kjm-grußwort', data.grußwort, 'Grußwort');
    data.downloads = collectDownloadsList();
    data.galerie = collectGalerieList();
    data.galerie_titel = collectGalerieTitel();
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
    renderMain(html);
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
     KONTAKT & STAMMDATEN (zusammengelegt 22.08.2026)

     Vorher gab es hierfür ZWEI getrennte Admin-Bereiche an zwei
     verschiedenen Stellen im Menü ("📞 Kontaktseite" oben, "Telefonzentrale
     & Kalender" unter Einstellungen), die aber schon dieselbe Datei
     (content/einstellungen.json) schrieben - das führte laut Laurin/Carsten
     genau zu der Verwirrung, die Carsten ansprach ("zentrale Daten müssen an
     EINEM Ort liegen"). Jetzt EIN Bereich mit klar benannten Blöcken, jeweils
     danach benannt, WO auf der Website die Daten erscheinen:
       - Kopfzeile:     nur telefon_header (E-Mail dort ist dieselbe wie im
                         Kontaktbox-Block unten, wird hier nur informativ
                         schreibgeschützt angezeigt, damit der Zusammenhang
                         sofort klar ist - kein Doppel-Eingabefeld für
                         dieselbe Datenquelle, das würde bei Konflikten beim
                         Speichern zu Verwirrung führen).
       - Kontaktbox:     telefon/email/adresse/postadresse/oeffnungszeiten -
                         erscheint automatisch auf der Kontaktseite, in der
                         grünen Box auf allen Unterseiten UND im Impressum.
       - Kontaktseite:   nur die Überschrift/Einleitungstext, die
                         ausschließlich auf kontakt/index.html stehen.
       - Google Kalender: eigenständig, gehört zur Termine-Seite, thematisch
                         kein Kontakt-Feld, aber da es dieselbe Datei nutzt
                         bleibt es als eigene, klar abgegrenzte Karte hier.
  ──────────────────────────────────────────────────────────── */
  function renderKontaktStammdaten(def, data) {
    var oz = data.oeffnungszeiten || [];
    var ozHtml = oz.map(function(o, i) {
      return '<div style="display:flex;gap:.5rem;margin-bottom:.5rem;" data-koz="' + i + '">' +
        '<input class="field-input" style="flex:1" value="' + escAttr(o.tage) + '" placeholder="Tag(e), z.B. Montag – Freitag" id="koz-tage-' + i + '">' +
        '<input class="field-input" style="flex:1" value="' + escAttr(o.zeiten) + '" placeholder="Uhrzeit, z.B. 10:00 – 12:00 Uhr" id="koz-zeiten-' + i + '">' +
        '<button class="btn btn-sm btn-ghost" onclick="kontaktOzDelete(' + i + ')">✕</button>' +
      '</div>';
    }).join('');

    var html = panelHeader(def.label) +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">📱 Kopfzeile</div>' +
          '<p style="margin:-.4rem 0 .85rem;color:var(--text-muted);font-size:.82rem;">' +
            'Wird ganz oben auf jeder Seite angezeigt.' +
          '</p>' +
          fText('ei-telefon-header', 'Telefonnummer in der Kopfzeile', data.telefon_header) +
          '<div class="field-row">' +
            '<label class="field-label">E-Mail in der Kopfzeile</label>' +
            '<input class="field-input" type="text" value="' + escAttr(data.email || '') + '" disabled style="background:var(--bg);color:var(--text-muted);">' +
          '</div>' +
          '<p style="margin:-.4rem 0 0;color:var(--text-muted);font-size:.82rem;">' +
            'Das ist dieselbe E-Mail-Adresse wie unten bei „Kontaktbox" – dort ändern, hier zieht sie automatisch nach.' +
          '</p>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">🟩 Kontaktbox (auf allen Seiten)</div>' +
          '<p style="margin:-.4rem 0 .85rem;color:var(--text-muted);font-size:.82rem;">' +
            'Diese Angaben erscheinen automatisch auf der Kontaktseite, in der grünen „Adresse KJS"-Kontaktbox auf allen Unterseiten UND im Impressum – einmal hier ändern, überall aktuell.' +
          '</p>' +
          fText('ko-telefon', 'Telefon Geschäftsstelle', data.telefon) +
          fText('ko-email', 'E-Mail Geschäftsstelle', data.email) +
          fTextarea('ko-adresse', 'Adresse Geschäftsstelle (jede Zeile einzeln)', data.adresse, 3) +
          fTextarea('ko-postadresse', 'Postadresse (nur Name + Anschrift, jede Zeile einzeln – Telefon/E-Mail bitte in die beiden Felder darunter, damit sie als eigene klickbare Zeilen angezeigt werden)', data.postadresse, 4) +
          fText('ko-postadresse-telefon', 'Telefon (zur Postadresse)', data.postadresse_telefon) +
          fText('ko-postadresse-email', 'E-Mail (zur Postadresse)', data.postadresse_email) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">🟩 Kontaktbox: Sprechzeiten</div>' +
          '<div id="koz-list">' + ozHtml + '</div>' +
          '<button class="list-add-btn" onclick="kontaktOzAdd()" style="margin-top:.5rem">+ Zeile hinzufügen</button>' +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">📄 Kontaktseite (nur /kontakt)</div>' +
          '<p style="margin:-.4rem 0 .85rem;color:var(--text-muted);font-size:.82rem;">' +
            'Nur auf der Kontaktseite selbst sichtbar, oberhalb des Formulars.' +
          '</p>' +
          fText('ko-ueberschrift', 'Überschrift', data.kontakt_ueberschrift, 'So erreichen Sie uns') +
          fTextarea('ko-text', 'Einleitungstext', data.kontakt_text, 3) +
        '</div>' +
        '<div class="form-card">' +
          '<div class="form-card-title">📅 Google Kalender (optional, Termine-Seite)</div>' +
          fText('ei-kal-url', 'Google Kalender URL', data.google_kalender_url, 'Einbettungs-URL aus Google Kalender') +
          fText('ei-kal-titel', 'Kalender-Überschrift', data.google_kalender_titel, 'z.B. Terminbuchung') +
        '</div>' +
      '</div>' + saveBar();
    renderMain(html);
    bindSaveBtn();
  }

  window.kontaktOzDelete = function(i) {
    S.data.oeffnungszeiten.splice(i, 1);
    renderKontaktStammdaten(S.section, S.data);
  };
  window.kontaktOzAdd = function() {
    S.data.oeffnungszeiten = S.data.oeffnungszeiten || [];
    S.data.oeffnungszeiten.push({ tage:'', zeiten:'' });
    renderKontaktStammdaten(S.section, S.data);
  };

  function collectKontaktStammdaten(data) {
    data.telefon_header = gv('ei-telefon-header');
    data.google_kalender_url   = gv('ei-kal-url');
    data.google_kalender_titel = gv('ei-kal-titel');
    data.kontakt_ueberschrift = gv('ko-ueberschrift');
    data.kontakt_text  = gv('ko-text');
    data.telefon = gv('ko-telefon');
    data.email   = gv('ko-email');
    data.adresse = gv('ko-adresse');
    data.postadresse = gv('ko-postadresse');
    data.postadresse_telefon = gv('ko-postadresse-telefon');
    data.postadresse_email   = gv('ko-postadresse-email');
    var oz = [];
    document.querySelectorAll('[data-koz]').forEach(function(row) {
      var i = row.getAttribute('data-koz');
      oz.push({ tage: val('koz-tage-' + i), zeiten: val('koz-zeiten-' + i) });
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
    renderMain(html);
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
          fColorField('farbe_gruen', 'Hauptfarbe Grün', data.farbe_gruen, '#2e6b30') +
          fColorField('farbe_dunkelgruen', 'Dunkelgrün (Header/Footer)', data.farbe_dunkelgruen, '#1a4a1c') +
          fColorField('farbe_akzent', 'Akzentfarbe (Gold)', data.farbe_akzent, '#b8860b') +
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
    renderMain(html);
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
     FARBFELD: Color-Picker + Hex-Feld + "RAL wählen" (Arbeitsblock 5,
     05.09.2026, Frank-Wunsch)

     Reiner Komfort-Ausbau der bestehenden freien Farbauswahl in
     renderDesign - KEINE neue Speicherlogik. Das native <input type="color">
     mit der ID f-<key> bleibt exakt wie vorher die einzige Quelle, aus der
     collectDesign() per gv(key) liest; alles hier Neue (Hex-Textfeld,
     RAL-Auswahl-Button) schreibt nur in genau dieses vorhandene Feld.

     - Color-Picker und Hex-Feld sind bidirektional synchronisiert
       (ralSyncHexFromColor / ralSyncColorFromHex), beide bleiben frei manuell
       bedienbar (freie Farbauswahl bleibt erhalten, RAL ist nur zusätzlich).
     - Ein natives 'change'/'input' auf einem der beiden sichtbaren Felder
       läuft über die bestehende Delegation auf #admin-main (siehe main.
       addEventListener('input'/'change', markDirty) weiter unten) und setzt
       Dirty-State automatisch - hier ist kein zusätzlicher markDirty()-Aufruf
       nötig.
     - Nur die RAL-Auswahl aus der Liste (ralPickerSelect) setzt Werte rein
       programmatisch und braucht deshalb einen expliziten markDirty()-Aufruf,
       genau wie an den anderen Stellen im Admin, die Feldwerte per Klick statt
       per nativem Input setzen (vgl. bildGroesseSet, ttColor).
  ──────────────────────────────────────────────────────────── */
  function fColorField(key, label, value, fallback) {
    var hex = escAttr(value || fallback);
    return '<div class="field-row">' +
      '<label class="field-label" for="f-' + key + '-hex">' + escHtml(label) + '</label>' +
      '<div class="color-field-group">' +
        '<input type="color" id="f-' + key + '" value="' + hex + '" class="color-swatch-input" ' +
          'aria-label="' + escAttr(label) + ' – Farbwähler" oninput="ralSyncHexFromColor(\'' + key + '\')">' +
        '<input type="text" id="f-' + key + '-hex" class="field-input color-hex-input" value="' + hex + '" ' +
          'maxlength="7" placeholder="#000000" aria-label="' + escAttr(label) + ' – Hex-Wert" ' +
          'onchange="ralSyncColorFromHex(\'' + key + '\')">' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="ralPickerOpen(\'' + key + '\', \'' + escAttr(label) + '\')">' +
          '🎨 RAL wählen</button>' +
      '</div>' +
    '</div>';
  }

  // Hex-Textfeld -> Color-Picker. Manuelle Eingabe bleibt vollständig frei;
  // nur bei gültigem #RRGGBB wird der Picker (und die Groß/Kleinschreibung
  // der Anzeige) übernommen. Ungültige/unfertige Eingabe (z.B. während des
  // Tippens) wird nur optisch markiert, nicht überschrieben oder verworfen.
  window.ralSyncColorFromHex = function(key) {
    var hexEl = id('f-' + key + '-hex');
    var colorEl = id('f-' + key);
    if (!hexEl || !colorEl) return;
    var v = (hexEl.value || '').trim();
    if (v && v.charAt(0) !== '#') v = '#' + v;
    if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
      hexEl.value = v.toUpperCase();
      colorEl.value = v;
      hexEl.classList.remove('field-input--invalid');
    } else {
      hexEl.classList.add('field-input--invalid');
    }
  };

  // Color-Picker (nativer Farbwähler) -> Hex-Textfeld.
  window.ralSyncHexFromColor = function(key) {
    var hexEl = id('f-' + key + '-hex');
    var colorEl = id('f-' + key);
    if (!hexEl || !colorEl) return;
    hexEl.value = colorEl.value.toUpperCase();
    hexEl.classList.remove('field-input--invalid');
  };

  /* ────────────────────────────────────────────────────────────
     RAL-AUSWAHL-DIALOG

     RAL-Daten kommen ausschließlich aus window.RAL_COLORS (admin/ral-
     colors.js, per <script> vor admin.js geladen) - keine hier erfundenen
     Hex-Werte. Quelle und Bildschirm-Näherungs-Hinweis stehen dort und
     zusätzlich direkt im Dialog (s.u.).
  ──────────────────────────────────────────────────────────── */
  var _ralPickerKey = null;

  window.ralPickerOpen = function(key, label) {
    _ralPickerKey = key;
    var el = id('ral-picker-modal');
    if (!el) {
      el = document.createElement('div');
      el.id = 'ral-picker-modal';
      el.className = 'modal';
      el.style.display = 'none';
      el.innerHTML =
        '<div class="modal-backdrop" onclick="ralPickerClose()"></div>' +
        '<div class="modal-box modal-box--sm" style="max-width:420px;">' +
          '<div class="modal-head"><h3 id="ral-picker-title">RAL-Farbe wählen</h3>' +
            '<button class="modal-close-btn" onclick="ralPickerClose()" aria-label="Schließen">✕</button>' +
          '</div>' +
          '<div class="modal-body">' +
            '<label class="field-label" for="ral-picker-search">Suche</label>' +
            '<input type="text" id="ral-picker-search" class="field-input" ' +
              'placeholder="RAL-Nummer oder Name, z. B. 6005 oder Moos…" autocomplete="off" ' +
              'style="margin-bottom:.6rem;">' +
            '<p class="field-hint" style="margin:0 0 .75rem;">' +
              'Bildschirmdarstellung nur annähernd – verbindlich ist der jeweilige RAL-Farbstandard.' +
            '</p>' +
            '<div id="ral-picker-list" class="ral-picker-list" role="listbox" aria-label="RAL-Farben"></div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(el);
      id('ral-picker-search').addEventListener('input', ralPickerRenderList);
      id('ral-picker-search').addEventListener('keydown', function(e) {
        if (e.key === 'Escape') ralPickerClose();
      });
    }
    id('ral-picker-title').textContent = label ? ('RAL-Farbe wählen – ' + label) : 'RAL-Farbe wählen';
    id('ral-picker-search').value = '';
    ralPickerRenderList();
    el.style.display = 'flex';
    setTimeout(function() { var s = id('ral-picker-search'); if (s) s.focus(); }, 0);
  };

  window.ralPickerClose = function() {
    var el = id('ral-picker-modal');
    if (el) el.style.display = 'none';
    _ralPickerKey = null;
  };

  function ralPickerRenderList() {
    var listEl = id('ral-picker-list');
    if (!listEl) return;
    var all = window.RAL_COLORS || [];
    var q = (id('ral-picker-search').value || '').trim().toLowerCase();
    var qDigits = q.replace(/^ral\s*/, '').replace(/\D/g, '');
    var matches = !q ? all : all.filter(function(c) {
      if (c.name.toLowerCase().indexOf(q) !== -1) return true;
      if (c.ral.toLowerCase().indexOf(q) !== -1) return true;
      if (qDigits && c.ral.replace(/\D/g, '').indexOf(qDigits) !== -1) return true;
      return false;
    });
    if (!all.length) {
      listEl.innerHTML = '<p style="padding:.75rem;color:var(--text-muted);font-size:.85rem;margin:0;">RAL-Farbliste nicht verfügbar.</p>';
      return;
    }
    if (!matches.length) {
      listEl.innerHTML = '<p style="padding:.75rem;color:var(--text-muted);font-size:.85rem;margin:0;">Keine RAL-Farbe gefunden.</p>';
      return;
    }
    listEl.innerHTML = matches.map(function(c) {
      return '<button type="button" class="ral-picker-row" role="option" ' +
        'onclick="ralPickerSelect(\'' + c.hex + '\')" ' +
        'aria-label="' + escAttr(c.ral + ' ' + c.name + ', Hex ' + c.hex) + '">' +
        '<span class="ral-picker-swatch" style="background:' + c.hex + '" aria-hidden="true"></span>' +
        '<span class="ral-picker-code">' + escHtml(c.ral) + '</span>' +
        '<span class="ral-picker-name">' + escHtml(c.name) + '</span>' +
      '</button>';
    }).join('');
  }

  window.ralPickerSelect = function(hex) {
    var key = _ralPickerKey;
    if (!key) return;
    var colorEl = id('f-' + key);
    var hexEl = id('f-' + key + '-hex');
    if (colorEl) colorEl.value = hex;
    if (hexEl) { hexEl.value = hex.toUpperCase(); hexEl.classList.remove('field-input--invalid'); }
    markDirty(); // Setzt Werte programmatisch - löst kein natives input/change-Event aus
    ralPickerClose();
  };

  /* ────────────────────────────────────────────────────────────
     IMPRESSUM
  ──────────────────────────────────────────────────────────── */
  function renderImpressum(def, data) {
    var html = panelHeader(def.label) +
      '<div class="panel-body"><div class="form-card">' +
        '<p style="margin:0 0 1rem;color:var(--text-muted);font-size:.82rem;">' +
          'Adresse, Postadresse, Telefon und E-Mail werden automatisch von „⚙️ Einstellungen → 📞 Kontakt & Stammdaten" übernommen – dort ändern, nicht hier.' +
        '</p>' +
        fText('imp-verein', 'Vereinsname', data.verein) +
        fText('imp-vertreten', 'Vertreten durch', data.vertreten_durch) +
        fText('imp-registergericht', 'Registergericht', data.registergericht) +
        fText('imp-registernummer', 'Registernummer', data.registernummer) +
        fTextarea('imp-verantwortlich', 'Verantwortlich (§18)', data.verantwortlich, 2) +
      '</div></div>' + saveBar();
    renderMain(html);
    bindSaveBtn();
  }

  function collectImpressum(data) {
    data.verein           = gv('imp-verein');
    data.vertreten_durch  = gv('imp-vertreten');
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
    renderMain(html);
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

     Archivierfunktion (21.08.2026, Frank-Wunsch): "Archivieren" darf die
     Bilddatei NIEMALS im Repo verschieben/umbenennen, da Bilder site-weit
     per festem Pfad /images/<name> referenziert werden (Aktuelles/Termine/
     Service-Beiträge, Markdown-Inhalte, Downloads-Vorschaubilder) - eine
     Verschiebung würde all diese Referenzen live brechen. Stattdessen wird
     nur der Dateiname in einer separaten Metadaten-Liste geführt
     (content/medien-archiv.json). Die Datei selbst bleibt unverändert am
     gleichen Ort liegen -> auf der Live-Seite ändert sich nichts.

     UX (22.08.2026, nach Rücksprache mit Frank): archivierte Bilder klappen
     NICHT unter der normalen Galerie auf (fühlte sich an wie "ist eh alles
     sichtbar"), sondern wie ein echter Unterordner/eine zweite Seite - Klick
     auf "📁 Archivierte Bilder" tauscht die komplette Ansicht aus
     (medienArchivSeiteOeffnen), mit "← Zurück"-Button zur normalen Ansicht.
     Außerdem WICHTIG (Frank, 22.08.2026): archivierte Bilder dürfen in
     KEINER Bildauswahl im Admin mehr auftauchen - weder hier in "Alle
     Bilder", noch im "Bild auswählen"-Dialog für Hero-/Vorschaubilder
     (loadGallery), noch im Markdown-Bild-Einfügen-Dialog (loadMdImgGallery,
     siehe weiter unten) - sonst könnte man versehentlich ein archiviertes
     Bild neu verwenden, was dem Zweck des Archivierens widerspricht.
  ──────────────────────────────────────────────────────────── */
  var medienFiles = null;        // zuletzt geladene Verzeichnisliste von images/
  var medienArchivListe = [];    // Dateinamen, die als "archiviert" markiert sind
  var medienArchivSha = null;    // sha von content/medien-archiv.json (falls vorhanden)

  function renderMedian() {
    var html = '<div class="panel-header"><h2>🖼️ Medien & Bilder</h2></div>' +
      '<div class="panel-body panel-body--wide">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Bild hochladen</div>' +
          '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">' +
            '<div class="upload-row" style="margin-bottom:0;">' +
              '<label class="btn btn-primary" for="medien-upload-input">📤 Bild hochladen</label>' +
              '<input type="file" id="medien-upload-input" accept="image/*" style="display:none">' +
              '<span id="medien-upload-status" style="margin-left:.75rem;color:var(--text-muted);font-size:.85rem;"></span>' +
            '</div>' +
            '<button type="button" class="btn btn-outline btn-sm" id="medien-archiv-toggle" onclick="medienArchivSeiteOeffnen()">📁 Archivierte Bilder</button>' +
          '</div>' +
          '<div class="form-card-title">Alle Bilder</div>' +
          '<div class="img-gallery" id="medien-gallery"><div class="gallery-loading">Wird geladen…</div></div>' +
        '</div>' +
      '</div>';
    renderMain(html);
    loadMedianGallery();

    id('medien-upload-input').addEventListener('change', async function() {
      var file = this.files[0];
      if (!file) return;
      var status = id('medien-upload-status');
      status.textContent = '⏳ Wird hochgeladen…';
      try {
        var prepared = await prepareImageForUpload(file);
        await apiUploadImage(prepared.filename, prepared.base64);
        status.textContent = '✅ Hochgeladen!';
        loadMedianGallery();
      } catch(e) {
        status.textContent = '❌ ' + e.message;
      }
    });
  }

  // Eigene "Unterordner"-Ansicht statt Akkordeon: ersetzt admin-main komplett,
  // mit "← Zurück"-Button zur normalen Medien-Übersicht (renderMedian).
  window.medienArchivSeiteOeffnen = async function() {
    var html = '<div class="panel-header"><h2>📦 Archivierte Bilder</h2></div>' +
      '<div class="panel-body panel-body--wide">' +
        '<div class="form-card">' +
          '<button type="button" class="btn btn-outline btn-sm" style="margin-bottom:1rem;" onclick="confirmNav(renderMedian)">← Zurück zu Medien &amp; Bilder</button>' +
          '<p class="text-muted" style="margin-bottom:1rem;font-size:.85rem;">Archivierte Bilder bleiben auf der Website ganz normal bestehen und tauchen nur hier nicht mehr in der normalen Übersicht oder bei der Bildauswahl auf. Über „♻️ Wiederherstellen" lassen sie sich jederzeit zurückholen.</p>' +
          '<div class="img-gallery" id="medien-archiv-gallery"><div class="gallery-loading">Wird geladen…</div></div>' +
        '</div>' +
      '</div>';
    renderMain(html);
    try {
      if (!medienFiles) medienFiles = await apiGetDir('images');
      await loadMedienArchivListe();
      renderMedienArchivAnsicht();
    } catch(e) {
      var g = id('medien-archiv-gallery');
      if (g) g.innerHTML = '<div class="gallery-loading">Fehler: ' + escHtml(e.message) + '</div>';
    }
  };

  async function loadMedienArchivListe() {
    try {
      var resp = await apiGet('content/medien-archiv.json');
      medienArchivSha = resp.sha;
      trackSha('content/medien-archiv.json', resp.sha);
      var data = JSON.parse(fromBase64(resp.content));
      medienArchivListe = Array.isArray(data.archiviert) ? data.archiviert : [];
    } catch(e) {
      // Datei existiert vermutlich noch nicht (erster Aufruf) - leere Liste,
      // wird beim ersten Archivieren automatisch neu angelegt (doSave/apiPut
      // ohne sha = neue Datei).
      medienArchivListe = [];
      medienArchivSha = null;
      if (S.shaMap) delete S.shaMap['content/medien-archiv.json'];
    }
  }

  async function loadMedianGallery() {
    var gallery = id('medien-gallery');
    if (!gallery) return;
    gallery.innerHTML = '<div class="gallery-loading">Bilder werden geladen…</div>';
    try {
      medienFiles = await apiGetDir('images');
      await loadMedienArchivListe();
      renderMedienAlleBilderAnsicht();
    } catch(e) {
      gallery.innerHTML = '<div class="gallery-loading">Fehler: ' + escHtml(e.message) + '</div>';
    }
  }

  function medienBilderListe() {
    return (medienFiles || []).filter(function(f) {
      return f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
    });
  }

  function medienGalleryItemHtml(f, istArchiviert) {
    var url = '/images/' + f.name;
    var archivBtn = istArchiviert
      ? '<button class="btn btn-sm btn-outline" onclick="medienArchivToggle(\'' + escAttr(f.name) + '\')">♻️ Wiederherstellen</button>'
      : '<button class="btn btn-sm btn-outline" onclick="medienArchivToggle(\'' + escAttr(f.name) + '\')">📦 Archivieren</button>';
    return '<div class="gallery-img-wrap" data-path="' + escAttr(f.path) + '">' +
      '<img class="gallery-img" src="' + escAttr(url) + '" alt="' + escAttr(f.name) + '" loading="lazy">' +
      '<div class="gallery-img-name">' + escHtml(f.name) + '</div>' +
      '<div style="text-align:center;margin-top:.25rem;display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap;">' +
        archivBtn +
        '<button class="btn btn-sm btn-outline" style="color:#c0392b;border-color:#c0392b;" onclick="medienDeleteImage(\'' + escAttr(f.path) + '\',\'' + escAttr(f.sha) + '\',\'' + escAttr(f.name) + '\')">🗑️ Löschen</button>' +
      '</div>' +
    '</div>';
  }

  function renderMedienAlleBilderAnsicht() {
    var gallery = id('medien-gallery');
    var toggleBtn = id('medien-archiv-toggle');
    if (!gallery) return;
    var imgs = medienBilderListe();
    var aktive = imgs.filter(function(f) { return medienArchivListe.indexOf(f.name) === -1; });
    var archiviert = imgs.filter(function(f) { return medienArchivListe.indexOf(f.name) !== -1; });
    if (toggleBtn) toggleBtn.textContent = '📁 Archivierte Bilder (' + archiviert.length + ')';
    gallery.innerHTML = aktive.length
      ? aktive.map(function(f) { return medienGalleryItemHtml(f, false); }).join('')
      : '<div class="gallery-loading">Noch keine Bilder vorhanden.</div>';
  }

  function renderMedienArchivAnsicht() {
    var archivGallery = id('medien-archiv-gallery');
    if (!archivGallery) return;
    var archiviert = medienBilderListe().filter(function(f) { return medienArchivListe.indexOf(f.name) !== -1; });
    archivGallery.innerHTML = archiviert.length
      ? archiviert.map(function(f) { return medienGalleryItemHtml(f, true); }).join('')
      : '<div class="gallery-loading">Keine archivierten Bilder.</div>';
  }

  // Rendert die gerade sichtbare Ansicht neu (Alle Bilder ODER Archiv-Unterseite -
  // die beiden existieren nie gleichzeitig im DOM, da medienArchivSeiteOeffnen()
  // admin-main komplett ersetzt statt aufzuklappen).
  function medienAktuelleAnsichtNeuRendern() {
    if (id('medien-archiv-gallery')) renderMedienArchivAnsicht();
    else if (id('medien-gallery')) renderMedienAlleBilderAnsicht();
  }

  window.medienArchivToggle = async function(name) {
    var idx = medienArchivListe.indexOf(name);
    var wirdArchiviert = idx === -1;
    if (wirdArchiviert) medienArchivListe.push(name); else medienArchivListe.splice(idx, 1);
    medienAktuelleAnsichtNeuRendern(); // optimistisches UI-Update, Datei bleibt unangetastet
    try {
      var result = await doSave('content/medien-archiv.json', { archiviert: medienArchivListe },
        wirdArchiviert ? ('📦 Bild archiviert: ' + name) : ('♻️ Bild wiederhergestellt: ' + name));
      if (result && result.content && result.content.sha) medienArchivSha = result.content.sha;
      toast(wirdArchiviert ? '✅ Bild archiviert.' : '✅ Bild wiederhergestellt.', 'ok');
    } catch(e) {
      // Rückgängig machen, wenn das Speichern fehlschlägt
      var idx2 = medienArchivListe.indexOf(name);
      if (wirdArchiviert) { if (idx2 !== -1) medienArchivListe.splice(idx2, 1); }
      else medienArchivListe.push(name);
      medienAktuelleAnsichtNeuRendern();
      await handleSaveError(e);
    }
  };

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
          if (medienFiles) medienFiles = medienFiles.filter(function(f) { return f.path !== path; });
          // aus der Archiv-Liste ebenfalls entfernen (falls dort vorhanden), damit
          // medien-archiv.json nicht auf eine gelöschte Datei verweist
          var idx = medienArchivListe.indexOf(name);
          if (idx !== -1) {
            medienArchivListe.splice(idx, 1);
            // Bild ist bereits unwiderruflich gelöscht - dieser Speichervorgang räumt
            // nur die Archiv-Liste auf. Ein Konflikt hier ist nicht kritisch, soll
            // aber nicht mehr wie bisher lautlos verschluckt werden (Phase 5B.1).
            doSave('content/medien-archiv.json', { archiviert: medienArchivListe }, '📦 Archiv-Eintrag bereinigt (Bild gelöscht): ' + name)
              .catch(function(cleanupErr) {
                console.warn('Archiv-Liste konnte nach Bild-Löschung nicht bereinigt werden:', cleanupErr);
                toast('⚠️ Bild gelöscht, aber Archiv-Liste konnte nicht aktualisiert werden. Bitte Seite neu laden.', 'err');
              });
          }
          medienAktuelleAnsichtNeuRendern();
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
    renderMain(html);
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
    var hm  = data.hauptmenu    || ['startseite','jaeger','verbraucher','aktuelles','termine','faq','service','kontakt'];
    var jd  = data.jaeger_dropdown || ['ueber-uns','kreisjjaegermeister','kjs-segeberg','aufgaben','infomobil','hundeboerse','waffenboerse','partner','weitere-themen'];
    var kjs = data.kjs          || [];
    var auf = data.aufgaben     || [];
    var vbr = data.verbraucher  || [];

    var HM_LABELS = { startseite:'Startseite', jaeger:'Jäger', verbraucher:'Verbraucher', aktuelles:'Aktuelles',
                      termine:'Termine', faq:'FAQ', service:'Service', kontakt:'Kontakt' };
    // Hundebörse/Waffenbörse (04.09.2026, Frank-Wunsch): jetzt Leaf-Einträge
    // im Jäger-Dropdown statt eigener Hauptmenü-Punkte, siehe navigation.json.
    var JD_LABELS = {
      'ueber-uns':           'Über uns',
      'kreisjjaegermeister': 'Kreisjägermeister',
      'kjs-segeberg':        'KJS Segeberg (Untermenü →)',
      'aufgaben':            'Aufgaben der Kreisjägerschaft (Untermenü →)',
      'infomobil':           'Infomobil',
      'hundeboerse':         'Hundebörse',
      'waffenboerse':        'Waffenbörse',
      'partner':             'Partner',
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

    renderMain(html);
    bindSaveBtn();

    // Initialize Sortable.js on each list
    var lists = ['navreo-hauptmenu','navreo-jaegerdropdown','navreo-kjs','navreo-aufgaben','navreo-verbraucher'];
    lists.forEach(function(listId) {
      var el = id(listId);
      if (el && window.Sortable) {
        Sortable.create(el, {
          handle: '.navreo-handle',
          animation: 150,
          onEnd: function() { markDirty(); }
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
     Läuft seit 05.09.2026 vollständig über die Netlify Function
     "/.netlify/functions/admin-users" statt über einen direkten
     Browser-Aufruf der rohen Identity-Admin-API (siehe ausführlicher
     Kommentar in netlify/functions/admin-users.js: Grund für den
     vorherigen dauerhaften 401 war, dass diese API grundsätzlich kein
     normales Benutzer-Token akzeptiert, unabhängig von dessen Rollen).
     Die Function prüft Login + Rolle "admin" serverseitig und führt die
     eigentliche Identity-Admin-Aktion mit dem site-eigenen Service-Token
     aus, das den Browser nie erreicht.
  ──────────────────────────────────────────────────────────── */
  var BU_ENDPOINT = '/.netlify/functions/admin-users';
  var BU_ROLES = ['admin']; // aktuell einzige existierende Rolle, s. Abschlussbericht für "redakteur" als möglichen nächsten Schritt

  function renderBenutzer() {
    var main = id('admin-main');
    main.innerHTML =
      panelHeader('👥 Benutzerverwaltung') +
      '<div class="panel-body">' +
        '<div class="form-card">' +
          '<div class="form-card-title">Neuen Benutzer einladen</div>' +
          '<p class="text-muted" style="margin-bottom:1rem;">Der Benutzer erhält eine reguläre Einladungs-E-Mail von Netlify Identity und vergibt sein Passwort selbst beim ersten Anmelden.</p>' +
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
          '<p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.75rem;">Rollenänderungen wirken beim betroffenen Benutzer erst nach dessen nächster Anmeldung (neues Zugriffstoken).</p>' +
          '<div id="bu-list"><div class="gallery-loading">Wird geladen…</div></div>' +
        '</div>' +
      '</div>';
    benutzerLoad();
  }

  // Liefert dem Benutzer eine verständliche, nicht-technische Meldung;
  // vollständige technische Details (Status, Rohantwort) nur in der Konsole.
  function buFehlerText(status, body) {
    if (status === 401) return 'Sitzung abgelaufen. Bitte erneut anmelden.';
    if (status === 403) return 'Keine Adminrechte.';
    if (body && body.message) return body.message;
    return 'Serverfehler – bitte später erneut versuchen.';
  }

  async function buFetch(method, pathSuffix, bodyObj) {
    var tok = await getToken(true);
    var opts = { method: method, headers: { 'Authorization': 'Bearer ' + tok } };
    if (bodyObj !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(bodyObj);
    }
    var r = await fetch(BU_ENDPOINT + (pathSuffix || ''), opts);
    var data = await r.json().catch(function() { return {}; });
    if (!r.ok) {
      console.error('[Benutzerverwaltung] ' + method + ' ' + BU_ENDPOINT + (pathSuffix || '') + ' → HTTP ' + r.status, data);
      throw new Error(buFehlerText(r.status, data));
    }
    return data;
  }

  window.benutzerLoad = benutzerLoad;
  async function benutzerLoad() {
    var list = id('bu-list');
    if (!list) return;
    try {
      var data = await buFetch('GET');
      var users = data.users || [];
      if (!users.length) {
        list.innerHTML = '<p style="color:var(--text-muted);">Keine Benutzer gefunden.</p>';
        return;
      }
      list.innerHTML = users.map(function(u) {
        var isAdmin = u.roles.indexOf('admin') !== -1;
        var statusLabel = u.status === 'bestaetigt' ? 'Bestätigt' : 'Einladung ausstehend';
        var roleOptions = '<option value=""' + (!isAdmin ? ' selected' : '') + '>— keine besondere Rolle —</option>' +
          BU_ROLES.map(function(rl) {
            return '<option value="' + escAttr(rl) + '"' + (u.roles.indexOf(rl) !== -1 ? ' selected' : '') + '>' + escHtml(rl) + '</option>';
          }).join('');
        return '<div class="bu-user-row">' +
          '<div class="bu-user-info">' +
            '<strong>' + escHtml(u.email) + '</strong>' +
            (u.full_name ? ' <span class="bu-user-name">(' + escHtml(u.full_name) + ')</span>' : '') +
            ' <span class="bu-badge">' + escHtml(statusLabel) + '</span>' +
          '</div>' +
          '<select class="field-input" style="width:auto;" onchange="benutzerSetRole(\'' + escAttr(u.id) + '\', this.value)">' + roleOptions + '</select>' +
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
      await buFetch('POST', '', { email: email });
      toast('✅ Einladung gesendet an ' + email);
      emailEl.value = '';
      benutzerLoad();
    } catch(e) {
      console.error('[Benutzerverwaltung] Fehler beim Einladen:', e);
      toast('❌ ' + e.message, true);
    }
  };

  window.benutzerSetRole = async function(uid, role) {
    try {
      await buFetch('PATCH', '', { id: uid, roles: role ? [role] : [] });
      toast('✅ Rolle aktualisiert');
      benutzerLoad();
    } catch(e) {
      console.error('[Benutzerverwaltung] Fehler beim Ändern der Rolle:', e);
      toast('❌ ' + e.message, true);
      benutzerLoad(); // UI (Dropdown) auf tatsächlichen Stand zurücksetzen
    }
  };

  window.benutzerRemove = async function(uid, email) {
    if (!confirm('Benutzer ' + email + ' wirklich entfernen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
    try {
      await buFetch('DELETE', '?id=' + encodeURIComponent(uid));
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
          fTipTap('ns-inhalt', 'Textinhalt', true) +
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
    renderMain(html);
    initTiptap('ns-inhalt', '');

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
      inhalt:         getTiptapValue('ns-inhalt', '', 'Textinhalt'),
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
      case 'startseite':    data = collectStartseite(data); break;
      case 'kjm':           data = collectKJM(data); break;
      case 'faq':           data = collectFAQ(data); break;
      case 'kontaktStammdaten': data = collectKontaktStammdaten(data); break;
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
    // Universelle Bildergalerie: gleiches Prinzip wie Downloads oben.
    if (id('galerie-list')) {
      data.galerie = collectGalerieList();
      data.galerie_titel = collectGalerieTitel();
    }
    // Universelle Link-Liste (aktuell nur Wildfleisch, siehe renderStandard).
    if (id('linkliste-list')) {
      data.linkliste = collectLinklisteList();
      data.linkliste_titel = collectLinklisteTitel();
    }

    setSaving(true);
    try {
      var result = await doSave(def.file, data, '💾 ' + def.label + ' gespeichert');
      S.data = data;

      // Dynamisch angelegte Unterseiten (isDynamic): manche Felder werden auf
      // der öffentlichen Seite NICHT aus der Unterseite selbst gelesen, sondern
      // aus dem übergeordneten Manifest (z.B. content/seiten-sub-wildfleisch.json
      // für die "Weitere Seiten"-Sidebar, oder hundeausbildung-seiten.json für
      // die Kachel-Vorschau). Ohne diesen Sync bleibt das Manifest beim
      // Bearbeiten einer bestehenden Seite auf dem alten Stand, obwohl das
      // Formular einen neuen Wert zeigt - war z.B. der Grund, warum eine
      // falsch geschriebene Menü-Bezeichnung bisher nur durch Löschen+
      // Neuanlegen korrigierbar war (22.08.2026, Waidmannssprache-Fall).
      if (def.isDynamic && def.navFile && def.slug) {
        try {
          var manifestResp = await apiGet(def.navFile);
          var manifestData = JSON.parse(fromBase64(manifestResp.content));
          var liste = manifestData[def.navKey] || [];
          var eintrag = liste.filter(function(s) { return s.slug === def.slug; })[0];
          if (eintrag) {
            var manifestChanged = false;
            if (eintrag.nav_label !== data.nav_label) {
              eintrag.nav_label = data.nav_label || def.slug;
              manifestChanged = true;
            }
            if (def.navFile.indexOf('hundeausbildung-seiten') !== -1) {
              eintrag.vorschaubild     = data.vorschaubild     || '';
              eintrag.kurzbeschreibung = data.kurzbeschreibung || '';
              manifestChanged = true;
            }
            if (manifestChanged) {
              await apiPut(def.navFile, manifestData, manifestResp.sha, '🔀 Unterseite aktualisiert: ' + (data.nav_label || def.slug));
            }
          }
        } catch(syncErr) {
          // War früher ein reines console.warn (für den Redakteur unsichtbar).
          // Der eigentliche Seiteninhalt wurde bereits erfolgreich gespeichert
          // (siehe oben) - nur dieser sekundäre Menü-Abgleich ist fehlgeschlagen,
          // daher kein Speicherkonflikt-Dialog, aber ein sichtbarer Hinweis,
          // damit das nicht unbemerkt bleibt.
          console.warn('Manifest-Sync fehlgeschlagen:', syncErr);
          toast('⚠️ Seite gespeichert, aber Menü-Abgleich fehlgeschlagen: ' + syncErr.message, 'err');
        }
      }

      setSaving(false);
      toast('✅ Gespeichert! Änderungen erscheinen auf der Website in max. 5 Minuten.', 'ok');
      setStatus('✅ Gespeichert');
      S.dirty = false;
    } catch(e) {
      await handleSaveError(e);
    }
  };

  // Merkt sich für eine Datei die zuletzt bekannte SHA – sowohl beim Laden
  // (Basis der Bearbeitung) als auch nach erfolgreichem Speichern (neue
  // Basis für die nächste Speicherung derselben Sitzung). Wird NUR anhand
  // des Dateipfads indiziert, nicht global auf S.sha gemappt – so kann z.B.
  // ein Speichern von content/medien-archiv.json nicht versehentlich die
  // SHA-Basis einer ganz anderen, gerade im Admin geöffneten Sektion
  // verfälschen (das war vor dieser Absicherung ein latenter Fehler: doSave
  // schrieb früher bedingungslos in S.sha, unabhängig davon, welche Datei
  // gerade tatsächlich gespeichert wurde).
  function trackSha(filePath, sha) {
    if (!sha) return;
    if (!S.shaMap) S.shaMap = {};
    S.shaMap[filePath] = sha;
    if (S.section && S.section.file === filePath) S.sha = sha;
  }

  function saveConflictError(filePath) {
    var err = new Error(
      'Diese Inhalte wurden zwischenzeitlich an anderer Stelle geändert (z. B. in einem ' +
      'anderen Browser-Tab oder von einer anderen Person). Deine Änderungen wurden NICHT ' +
      'überschrieben und NICHT gespeichert.'
    );
    err.isSaveConflict = true;
    err.filePath = filePath;
    return err;
  }

  // Zentraler, gegen veraltete Zwischenstände abgesicherter Speicherweg. ALLE
  // Admin-Speicherfunktionen (normale Inhaltsseiten, Aktuelles, Termine,
  // Service, Hundebörse, Personen, Hegeringe, Medien-Archiv, Reihenfolgen
  // usw.) laufen über diese eine Funktion.
  //
  // Funktionsweise der Konflikterkennung (Phase 5B.1):
  // Vorher wurde hier zwar auch schon eine "frische" SHA direkt vor dem
  // Schreiben nachgeladen (fetchFreshSha) – aber ausschließlich verwendet, um
  // damit zu schreiben, NIE verglichen mit der SHA, auf deren Basis die
  // Bearbeitung im Browser überhaupt begonnen hatte. Dadurch wurde jede
  // Speicherung technisch "erfolgreich", auch wenn zwischenzeitlich jemand
  // anderes dieselbe Datei bereits verändert hatte – die neuere fremde
  // Änderung wurde dabei stillschweigend überschrieben (klassisches
  // "Last-Write-Wins" ohne Warnung).
  //
  // Jetzt wird vor jedem Schreiben verglichen:
  //   erwarteteSha (S.shaMap[filePath], gesetzt beim Laden dieser Datei)
  //   vs.
  //   freshSha (der tatsächlich aktuelle Stand im Repository, gerade eben geladen)
  // Sind beide bekannt und unterscheiden sie sich, hat sich die Datei seit dem
  // Laden verändert → doSave bricht ab, OHNE zu schreiben, und wirft einen
  // Fehler mit e.isSaveConflict = true. Der Aufrufer zeigt daraufhin eine
  // Konfliktmeldung (siehe handleSaveError) statt die Änderung zu verlieren.
  // Ist keine Basis-SHA bekannt (z.B. eine bisher nie geladene/neue Datei),
  // wird - wie bisher - ohne Konfliktprüfung gespeichert; das entspricht dem
  // bestehenden Verhalten beim Neuanlegen von Dateien.
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

    var expectedSha = (S.shaMap && S.shaMap[filePath]) || null;
    var freshSha = await fetchFreshSha().catch(function() { return null; });

    // Echte Konflikterkennung: Stand, auf dessen Basis bearbeitet wurde, vs.
    // tatsächlicher aktueller Stand im Repository.
    if (expectedSha && freshSha && freshSha !== expectedSha) {
      throw saveConflictError(filePath);
    }

    var sha = freshSha || expectedSha || null;
    try {
      var result = await apiPut(filePath, data, sha, message);
      trackSha(filePath, result && result.content && result.content.sha);
      return result;
    } catch(e) {
      // 409 = GitHub selbst lehnt die SHA ab. Das kann entweder eine durch
      // Git-Gateway-Cache-Verzögerung noch veraltete "frische" SHA sein
      // (transient, kein echter Konflikt) oder eine echte Änderung zwischen
      // unserem Fresh-Fetch oben und dem Schreiben gerade eben. Deshalb: SHA
      // einmal erneut frisch laden UND erneut gegen die erwartete Basis
      // prüfen, bevor überhaupt erneut versucht wird zu schreiben.
      if (e.message && e.message.indexOf('409') !== -1) {
        await new Promise(function(res) { setTimeout(res, 600); });
        var retrySha = await fetchFreshSha().catch(function() { return null; });
        if (expectedSha && retrySha && retrySha !== expectedSha) {
          throw saveConflictError(filePath);
        }
        if (!retrySha) throw e;
        var result2 = await apiPut(filePath, data, retrySha, message);
        trackSha(filePath, result2 && result2.content && result2.content.sha);
        return result2;
      }
      throw e;
    }
  }

  // Einheitliche Fehlerbehandlung für alle Speicherwege: unterscheidet einen
  // erkannten Speicherkonflikt (doSave hat NICHT geschrieben, Eingaben bleiben
  // im Formular erhalten) von einem sonstigen Fehler (Netzwerk, Server,
  // Berechtigung usw.) und macht in beiden Fällen sichtbar, dass NICHT
  // gespeichert wurde. Ersetzt die bisherigen, uneinheitlichen (teils
  // fehlenden) catch-Blöcke der einzelnen Speicherfunktionen.
  async function handleSaveError(e) {
    setSaving(false);
    if (e && e.isSaveConflict) {
      setStatus('⛔ Nicht gespeichert – Konflikt mit einer neueren Änderung');
      await showAlert('Speichern nicht möglich – Konflikt',
        'Diese Inhalte wurden zwischenzeitlich an anderer Stelle geändert – zum Beispiel in ' +
        'einem anderen Browser-Tab oder von einer anderen Person.\n\n' +
        'Deine Änderungen wurden NICHT gespeichert und NICHT überschrieben. Sie stehen ' +
        'weiterhin hier im Formular – bitte notiere oder kopiere sie dir sicherheitshalber.\n\n' +
        'Bitte lade diesen Bereich anschließend neu (links im Menü erneut anklicken) und ' +
        'übertrage deine Änderungen dann auf den aktuellen Stand.');
    } else {
      var msg = (e && e.message) ? e.message : 'Unbekannter Fehler beim Speichern.';
      toast('❌ ' + msg, 'err');
      setStatus('❌ Fehler: ' + msg);
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
  var imgPickerZeigtArchiv = false; // Bild-Picker-Modal: normale oder Archiv-Ansicht

  window.openImgPicker = function(fieldId) {
    S.imgTarget = fieldId;
    imgPickerZeigtArchiv = false; // jede neue Öffnung startet in der normalen Ansicht
    id('img-modal').style.display = 'flex';
    loadGallery();
  };

  window.imgPickerArchivToggle = function() {
    imgPickerZeigtArchiv = !imgPickerZeigtArchiv;
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
    var toggleBtn = id('img-archiv-toggle');
    if (toggleBtn) toggleBtn.textContent = imgPickerZeigtArchiv ? '📷 Zurück zu allen Bildern' : '📁 Archivierte Bilder';
    try {
      var files = await apiGetDir('images');
      await loadMedienArchivListe();
      var imgs = files.filter(function(f) {
        var istBild = f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
        var istArchiviert = medienArchivListe.indexOf(f.name) !== -1;
        return istBild && (imgPickerZeigtArchiv ? istArchiviert : !istArchiviert);
      });
      if (imgs.length === 0) {
        gallery.innerHTML = imgPickerZeigtArchiv
          ? '<div class="gallery-loading">Keine archivierten Bilder.</div>'
          : '<div class="gallery-loading">Noch keine Bilder vorhanden. Laden Sie ein Bild hoch.</div>';
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
    if (el) markDirty(); // Setzt .value programmatisch - löst kein natives input/change-Event aus
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
        var prepared = await prepareImageForUpload(file);
        var url = await apiUploadImage(prepared.filename, prepared.base64);
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
     ZENTRALE BILD-OPTIMIERUNG VOR UPLOAD (Arbeitsblock 6, 05.09.2026,
     Frank-Wunsch: Uploads sollen kleiner/schneller werden, OHNE dass
     irgendwo automatisch zugeschnitten wird)

     Wird von allen drei Bild-Upload-Stellen im Admin verwendet (Medien &
     Bilder, der generische Bild-Picker "img-upload-input" - darüber läuft
     JEDES fImage()-Feld, also Hundebörse/Waffenbörse-Galerien, Partner-Logo,
     Personen-Fotos, Hero-/Vorschaubilder etc. - sowie das TipTap/Markdown-
     Bild-Einfügen "mdimg-upload-input"), jeweils direkt vor apiUploadImage().
     PDFs (apiUploadPdf/fileToBase64) sind davon NICHT betroffen - fileToBase64
     bleibt dafür unverändert; prepareImageForUpload() ruft es nur als
     Fallback für unveränderte Fälle auf (s.u.).

     Prinzipien:
     - Seitenverhältnis bleibt IMMER erhalten - kein Crop, kein Stretch.
       drawImage() zeichnet das KOMPLETTE Quellbild in ein proportional
       verkleinertes Canvas (Breite und Höhe werden mit demselben Faktor
       skaliert) - es wird nichts ausgeschnitten oder verzerrt.
     - Nur verkleinert, wenn die längere Kante IMG_MAX_DIMENSION überschreitet;
       kleine Bilder werden nie hochskaliert.
     - JPEG/JPG -> neu komprimiert (Qualität IMG_JPEG_QUALITY) und bei Bedarf
       verkleinert.
     - PNG -> nur dann als PNG belassen, wenn das Bild tatsächlich
       transparente Pixel enthält (Alpha-Kanal-Check auf dem Canvas, z.B.
       Partner-Logo mit transparentem Hintergrund); andernfalls als JPEG
       gespeichert, weil verlustfreies PNG bei Fotos ohne Transparenz kaum
       kleiner wird als das Original.
     - GIF und SVG werden NICHT verarbeitet (Animation bzw. Vektorgrafik
       würden durch die Rasterung auf dem Canvas kaputtgehen bzw. unnötig
       verschlechtert) - Original wie bisher unverändert hochladen.
     - Sicherheitsnetz: Wird das Ergebnis nicht kleiner als das Original
       (z.B. bei bereits kleinen/optimierten Bildern), wird das Original
       unverändert verwendet - nie eine schlechtere/größere Datei hochladen
       als vorher, und nie den Upload wegen eines Verarbeitungsfehlers
       blockieren (jeder Fehlerfall fällt auf das Original zurück).
     - WebP wurde geprüft, aber bewusst NICHT als Standard-Zielformat gewählt
       (siehe Abschlussbericht) - JPEG/PNG bleiben universell kompatibel und
       vorhersagbar. Die zentrale Funktion lässt sich bei Bedarf später leicht
       um WebP erweitern, ohne die Aufrufstellen anzufassen.
  ──────────────────────────────────────────────────────────── */
  var IMG_MAX_DIMENSION = 1920; // längere Kante in px - reicht für jede Darstellung inkl. Vollbild/Lightbox
  var IMG_JPEG_QUALITY = 0.85;  // konservativ: deutlich kleiner, ohne sichtbare Artefakte
  var IMG_SKIP_TYPES = /^image\/(gif|svg\+xml)$/i;

  function prepareImageForUpload(file) {
    // Fallback-Helfer: Original unverändert als Base64 zurückgeben, in genau
    // der Form, die alle Aufrufstellen erwarten ({base64, filename}).
    function original() {
      return fileToBase64(file).then(function(b64) {
        return { base64: b64, filename: file.name };
      });
    }

    if (!file || !/^image\//i.test(file.type) || IMG_SKIP_TYPES.test(file.type)) {
      return original();
    }

    return new Promise(function(resolve, reject) {
      var objectUrl = URL.createObjectURL(file);
      var img = new Image();

      img.onload = function() {
        try {
          var w = img.naturalWidth, h = img.naturalHeight;
          var scale = Math.min(1, IMG_MAX_DIMENSION / Math.max(w, h));
          var tw = Math.max(1, Math.round(w * scale));
          var th = Math.max(1, Math.round(h * scale));

          var canvas = document.createElement('canvas');
          canvas.width = tw; canvas.height = th;
          var ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, tw, th); // komplettes Bild, proportional skaliert - kein Ausschnitt
          URL.revokeObjectURL(objectUrl);

          var isPng = /image\/png/i.test(file.type);
          var hasAlpha = false;
          if (isPng) {
            try {
              var data = ctx.getImageData(0, 0, tw, th).data;
              for (var i = 3; i < data.length; i += 4) {
                if (data[i] < 255) { hasAlpha = true; break; }
              }
            } catch (e) {
              hasAlpha = true; // im Zweifel PNG/Transparenz behalten
            }
          }

          var outType = (isPng && hasAlpha) ? 'image/png' : 'image/jpeg';
          var outExt  = (outType === 'image/png') ? '.png' : '.jpg';
          var quality = (outType === 'image/jpeg') ? IMG_JPEG_QUALITY : undefined;

          canvas.toBlob(function(blob) {
            if (!blob || blob.size >= file.size) {
              // Kein Gewinn (oder Canvas-Export fehlgeschlagen) -> Original behalten
              original().then(resolve, reject);
              return;
            }
            var newName = file.name.replace(/\.[^.]+$/, '') + outExt;
            var reader = new FileReader();
            reader.onload = function() {
              resolve({ base64: reader.result.split(',')[1], filename: newName });
            };
            reader.onerror = function() { original().then(resolve, reject); };
            reader.readAsDataURL(blob);
          }, outType, quality);
        } catch (e) {
          URL.revokeObjectURL(objectUrl);
          original().then(resolve, reject); // nie den Upload wegen eines Verarbeitungsfehlers blockieren
        }
      };
      img.onerror = function() {
        URL.revokeObjectURL(objectUrl);
        original().then(resolve, reject); // Datei nicht als Bild ladbar -> unverändert hochladen
      };
      img.src = objectUrl;
    });
  }

  /* ────────────────────────────────────────────────────────────
     MARKDOWN-BILD-EINFÜGEN (mit visueller Positionierung)
  ──────────────────────────────────────────────────────────── */
  var _mdImgSelected = null; // { url, name }
  // aktuelle Position: Größe, horizontale/vertikale Position, Textumfluss
  var _mdImgPos = { size: 'img-mittel', hpos: 'img-links', vpos: 'img-pos-oben', flow: 'img-flow' };

  var mdImgZeigtArchiv = false; // "Bild einfügen"-Dialog: normale oder Archiv-Ansicht

  function openMdImageModal() {
    if (!S.mde) { toast('❌ Editor nicht bereit', 'err'); return; }
    mdImgZeigtArchiv = false; // jede neue Öffnung startet in der normalen Ansicht
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

  window.mdImgArchivToggle = function() {
    mdImgZeigtArchiv = !mdImgZeigtArchiv;
    loadMdImgGallery();
  };

  async function loadMdImgGallery() {
    var gallery = id('mdimg-gallery');
    if (!gallery) return;
    gallery.innerHTML = '<div class="gallery-loading">Bilder werden geladen…</div>';
    var toggleBtn = id('mdimg-archiv-toggle');
    if (toggleBtn) toggleBtn.textContent = mdImgZeigtArchiv ? '📷 Zurück zu allen Bildern' : '📁 Archivierte Bilder';
    try {
      var files = await apiGetDir('images');
      await loadMedienArchivListe();
      var imgs = files.filter(function(f) {
        var istBild = f.type === 'file' && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(f.name);
        var istArchiviert = medienArchivListe.indexOf(f.name) !== -1;
        return istBild && (mdImgZeigtArchiv ? istArchiviert : !istArchiviert);
      });
      if (imgs.length === 0) {
        gallery.innerHTML = mdImgZeigtArchiv
          ? '<div class="gallery-loading">Keine archivierten Bilder.</div>'
          : '<div class="gallery-loading">Noch keine Bilder vorhanden. Laden Sie eines hoch.</div>';
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
        var prepared = await prepareImageForUpload(file);
        var url = await apiUploadImage(prepared.filename, prepared.base64);
        if (status) status.textContent = '✅ Hochgeladen';
        await loadMdImgGallery();
        mdImgPick(url, prepared.filename);
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

    // TipTap-Bild einfügen (form:'standard', also auf allen normalen
    // Inhaltsseiten inkl. Infomobil - siehe renderStandard).
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
    var size = 'img-mittel', hpos = 'img-links', vpos = null, flow = null, flat = false;
    (raw || '').trim().split(/\s+/).forEach(function(tok) {
      var c = tok.replace(/^\./, '');
      if (MDIMG_SIZE_CLASSES.indexOf(c)   !== -1) size = c;
      if (MDIMG_HALIGN_CLASSES.indexOf(c) !== -1) hpos = c;
      if (MDIMG_VALIGN_CLASSES.indexOf(c) !== -1) vpos = c;
      if (MDIMG_FLOW_CLASSES.indexOf(c)   !== -1) flow = c;
      if (c === 'img-flat') flat = true;
    });
    if (!vpos) vpos = 'img-pos-oben';
    // Alte Bilder ohne Umfluss-Klasse: bisheriges Verhalten nachbilden
    if (!flow) flow = (hpos === 'img-zentriert') ? 'img-block' : 'img-flow';
    return { size: size, hpos: hpos, vpos: vpos, flow: flow, flat: flat };
  }

  function mdImgStyleFor(size, hpos, vpos, flow, flat) {
    var style = flat ? 'border-radius:0;box-shadow:none;height:auto;' : 'border-radius:4px;height:auto;';
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
    img.setAttribute('style', mdImgStyleFor(entry.size, entry.hpos, entry.vpos, entry.flow, entry.flat));
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
        alt: m[1], url: m[2], size: cls.size, hpos: cls.hpos, vpos: cls.vpos, flow: cls.flow, flat: cls.flat,
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
        size: entry.size, hpos: entry.hpos, vpos: entry.vpos, flow: entry.flow, flat: entry.flat
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
      '<div class="mdimg-live-row">' +
        '<span class="mdimg-live-label">Rahmen</span>' +
        '<button type="button" class="mdimg-live-btn" id="mdimg-live-flat" title="Für Logos/Grafiken mit eigenem weißen Hintergrund: entfernt Schatten &amp; Rundung, damit kein Kasten gegen die weiße Seite entsteht.">Ohne Rahmen</button>' +
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
      } else if (e.target.id === 'mdimg-live-flat') {
        var curEntry = _mdLive.marks[_mdLive.panelIndex];
        applyMdImgChange(_mdLive.panelIndex, { flat: !(curEntry && curEntry.flat) });
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
    var flatBtn = p.querySelector('#mdimg-live-flat');
    if (flatBtn) flatBtn.classList.toggle('mdimg-live-btn--active', !!entry.flat);

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
    var flat = patch.flat !== undefined ? patch.flat : entry.flat;
    if (hpos === 'img-zentriert' && flow === 'img-flow') flow = 'img-block';

    var classes = [size, hpos];
    if (vpos !== 'img-pos-oben') classes.push(vpos);
    classes.push(flow);
    if (flat) classes.push('img-flat');

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
  // Contents-API von GitHub (über die Netlify git-gateway) akzeptiert nur
  // Dateien bis ca. 1 MB in einem einzelnen PUT-Request mit Base64-Inhalt –
  // größere Scans/PDFs schlagen dort mit einem harten Fehler fehl. Deshalb
  // vorab prüfen und eine klare, verständliche Meldung zeigen statt erst
  // nach dem Hochladen mit einer kryptischen API-Fehlermeldung zu scheitern.
  var PDF_MAX_BYTES = 1000000; // ~1 MB, entspricht dem Contents-API-Limit

  async function apiUploadPdf(filename, base64Data) {
    var tok = await getToken();
    var safeName = Date.now() + '-' + filename.replace(/[^a-zA-Z0-9._-]/g, '-');
    var body = { message: '📄 PDF hochgeladen: ' + safeName, content: base64Data, branch: BRANCH };
    var r = await fetch(GIT + '/downloads/' + safeName, {
      method: 'PUT',
      headers: { 'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error(await apiUploadErrorMessage(r));
    return '/downloads/' + safeName;
  }

  // Modus des PDF-Modals:
  //  - 'insert'    : Klick auf eine PDF fügt einen Markdown-Link an der
  //                   Cursor-Position in den Text ein (Original-Verhalten)
  //  - 'downloads' : Klick auf eine PDF fügt das Dokument zur "Dokumente &
  //                   Downloads"-Liste der Seite hinzu (kein Markdown-Link)
  var _pdfModalMode = 'insert';
  // Ziel-Container für Modus 'downloads': die generische "#downloads-list"
  // der aktuell geöffneten Seite bzw. des aktuell geöffneten Beitrags (auch
  // bei Aktuelles/Service-Beiträgen, seit deren Umbau aufs gleiche Modell).
  var _pdfModalTargetListId = 'downloads-list';

  function openPdfModal(mode, targetListId) {
    _pdfModalMode = (mode === 'downloads') ? 'downloads' : 'insert';
    _pdfModalTargetListId = targetListId || 'downloads-list';
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
  // Wird über inline onclick="openPdfModal()" / onclick="openPdfModal('downloads')" /
  // onclick="openPdfModal('downloads','dok-list-...')" aufgerufen – inline onclick
  // läuft im globalen Scope, daher muss die Funktion explizit auf window
  // verfügbar gemacht werden.
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
      addDownloadRow(url, filename, _pdfModalTargetListId);
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
      if (file.size > PDF_MAX_BYTES) {
        status.textContent = '❌ Datei zu groß (' + (file.size / 1000000).toFixed(1) + ' MB) – maximal 1 MB möglich. Bitte das PDF vorher verkleinern/komprimieren.';
        input.value = '';
        return;
      }
      status.textContent = '⏳ Wird hochgeladen…';
      try {
        var b64 = await fileToBase64(file);
        var url = await apiUploadPdf(file.name, b64);
        status.textContent = '✅ Hochgeladen!';
        await loadPdfGallery();
        // Direkt verwenden: je nach Modus einfügen oder zur Downloads-Liste hinzufügen
        if (_pdfModalMode === 'downloads') {
          addDownloadRow(url, file.name, _pdfModalTargetListId);
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

  // okLabel/okClass sind optional (Standard: "Löschen" / rot btn-danger),
  // damit bestehende Lösch-Bestätigungen unverändert bleiben. Für nicht-
  // destruktive Aktionen (z. B. Hundebörse "Freigeben") müssen Beschriftung
  // UND Farbe des Bestätigungsbuttons explizit passend übergeben werden –
  // sonst zeigt der wiederverwendete Dialog fälschlich "Löschen" in Rot an,
  // obwohl die dahinterliegende Aktion korrekt ist (Laurin-Bug-Report,
  // Hundebörse "Speichern & Freigeben", 2026-08-28).
  function showConfirm(title, msg, cb, okLabel, okClass, cancelLabel) {
    _confirmCallback = cb;
    id('confirm-title').textContent = title;
    id('confirm-msg').textContent   = msg;
    var okBtn = id('confirm-ok');
    okBtn.textContent = okLabel || 'Löschen';
    okBtn.className = 'btn ' + (okClass || 'btn-danger');
    // cancelLabel optional (Standard weiterhin "Abbrechen") - genutzt vom
    // Navigationsschutz (confirmNav, Phase 5B.4) für "Hier bleiben", das an
    // dieser Stelle verständlicher ist als das generische "Abbrechen".
    id('confirm-cancel').textContent = cancelLabel || 'Abbrechen';
    id('confirm-modal').style.display = 'flex';
  }

  // Ersatz für window.prompt()/window.alert(): die nativen Browser-Dialoge
  // erscheinen oben im Browserfenster (losgelöst vom Admin-Inhalt darunter),
  // was Frank bei der Kategorien-Verwaltung verwirrt hat ("erscheint da oben,
  // nicht direkt daneben"). showPrompt/showAlert nutzen stattdessen dasselbe
  // zentrierte Modal-System wie showConfirm (siehe #prompt-modal/#alert-modal
  // in index.html) – erscheinen also mittig im Programmfenster, in der
  // gleichen Optik wie alle anderen Dialoge im Admin.
  function showPrompt(title, label, defaultValue) {
    return new Promise(function(resolve) {
      id('prompt-title').textContent = title;
      id('prompt-msg').textContent = label || '';
      var input = id('prompt-input');
      input.value = defaultValue || '';
      id('prompt-modal').style.display = 'flex';
      setTimeout(function() { input.focus(); input.select(); }, 0);

      function cleanup(result) {
        id('prompt-modal').style.display = 'none';
        id('prompt-ok').removeEventListener('click', onOk);
        id('prompt-cancel').removeEventListener('click', onCancel);
        id('prompt-backdrop').removeEventListener('click', onCancel);
        input.removeEventListener('keydown', onKeydown);
        resolve(result);
      }
      function onOk() { cleanup(input.value.trim() || null); }
      function onCancel() { cleanup(null); }
      function onKeydown(e) {
        if (e.key === 'Enter') { e.preventDefault(); onOk(); }
        else if (e.key === 'Escape') { onCancel(); }
      }
      id('prompt-ok').addEventListener('click', onOk);
      id('prompt-cancel').addEventListener('click', onCancel);
      id('prompt-backdrop').addEventListener('click', onCancel);
      input.addEventListener('keydown', onKeydown);
    });
  }

  function showAlert(title, msg) {
    return new Promise(function(resolve) {
      id('alert-title').textContent = title;
      id('alert-msg').textContent = msg;
      id('alert-modal').style.display = 'flex';
      function cleanup() {
        id('alert-modal').style.display = 'none';
        id('alert-ok').removeEventListener('click', onOk);
        id('alert-backdrop').removeEventListener('click', onOk);
        resolve();
      }
      function onOk() { cleanup(); }
      id('alert-ok').addEventListener('click', onOk);
      id('alert-backdrop').addEventListener('click', onOk);
    });
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
    // EasyMDE/CodeMirror feuert eigene 'change'-Events statt nativer
    // input/change-DOM-Events auf dem darunterliegenden Textfeld - der
    // delegierte #admin-main-Listener fängt das nicht ab, daher hier
    // explizit. Der initiale Wert wird beim Erzeugen des Editors direkt aus
    // dem <textarea> gelesen (nicht per späterem .setValue() gesetzt), es
    // feuert also kein Fehlalarm beim bloßen Öffnen des Formulars.
    S.mde.codemirror.on('change', markDirty);
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

  /* ── Alte Markdown-Bilder mit Größen-/Positionsklassen erkennen ─────
     ![alt](pfad){.img-mittel .img-links .img-flow} – Syntax des alten
     EasyMDE-Editors (siehe insertMdImage). marked.js kennt diese Attribut-
     Liste nicht von Haus aus (gleiches Problem wie in js/main.js gelöst),
     UND die alten Klassennamen (img-klein/mittel/gross/voll) passen nicht
     zum neuen, schlankeren TipTap-Schema (img-25/50/75/100). Diese Extension
     wandelt beim Umstieg auf TipTap (convertMarkdownToHtml) alte Bilder in
     <img class="img-25/50/75/100 img-links/rechts/zentriert"> um – exakt das
     Schema, das TipTap/initTiptap für neu eingefügte Bilder ohnehin nutzt
     (siehe TT_SIZE_MAP in insertMdImage). img-flow/img-pos-oben/-unten gibt
     es im neuen Schema nicht und werden bewusst weggelassen. */
  (function() {
    if (typeof marked === 'undefined' || !marked || typeof marked.use !== 'function') return;
    var RULE = /^!\[([^\]]*)\]\(([^)\s]+)\)\{([^}]+)\}/;
    var OLD_SIZE_MAP = { 'img-klein':'img-25', 'img-mittel':'img-50', 'img-gross':'img-75', 'img-voll':'img-100' };
    var OLD_HPOS = { 'img-links':1, 'img-rechts':1, 'img-zentriert':1 };

    marked.use({
      extensions: [{
        name: 'imageWithClassesLegacyMigration',
        level: 'inline',
        start: function(src) {
          var m = src.match(/!\[/);
          return m ? m.index : void 0;
        },
        tokenizer: function(src) {
          var match = RULE.exec(src);
          if (!match) return;
          var rawClasses = match[3].trim().split(/\s+/).map(function(c) { return c.replace(/^\./, ''); }).filter(Boolean);
          var newClasses = [];
          var hpos = 'img-links';
          rawClasses.forEach(function(c) {
            if (OLD_SIZE_MAP[c]) newClasses.push(OLD_SIZE_MAP[c]);
            else if (OLD_HPOS[c]) hpos = c;
          });
          if (!newClasses.length) newClasses.push('img-50');
          newClasses.push(hpos);
          return { type: 'imageWithClassesLegacyMigration', raw: match[0], alt: match[1], href: match[2], classes: newClasses.join(' ') };
        },
        renderer: function(token) {
          var altSafe = String(token.alt).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
          var srcSafe = String(token.href).replace(/"/g, '&quot;');
          return '<img src="' + srcSafe + '" alt="' + altSafe + '" class="' + token.classes + '">';
        }
      }]
    });
  })();

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

  /* ── TipTap-Link: Sicherheits-Filter für rohes HTML ─────────────
     Neutralisiert gefährliche href-Ziele (javascript:/data:/vbscript:/file:)
     in HTML, BEVOR es TipTap zur Anzeige übergeben wird – sowohl beim ersten
     Laden eines Feldes (initTiptap) als auch beim Einfügen von HTML aus einer
     fremden Quelle (Copy&Paste, editorProps.transformPastedHTML). Ersetzt nur
     den href-Wert selbst durch "#", der restliche Inhalt (Text, umliegendes
     HTML) bleibt unangetastet – keine vollständige HTML-Sanitisierung nötig,
     da TipTap/ProseMirror ohnehin nur die im Editor-Schema bekannten Tags
     (a/img/Formatierung/Tabellen/…) überhaupt übernimmt. */
  function ttSanitizeHtml(html) {
    if (!html) return html;
    return html.replace(/(<a\b[^>]*\shref\s*=\s*)(["'])\s*(javascript|data|vbscript|file):[^"']*\2/gi,
      function(m, prefix, quote) { return prefix + quote + '#' + quote; });
  }

  /* ── TipTap-Link: URL-Eingabe des Redakteurs normalisieren ──────
     Erkennt/erlaubt: https://, http://, mailto:, tel:, interne Pfade (/…,
     #…). "www.example.de" oder "example.de/pfad" ohne Protokoll bekommt
     automatisch "https://" vorangestellt (Wunsch: Redakteure müssen das
     Protokoll nicht kennen). Alles andere (kein erkennbares Muster, oder
     ausdrücklich unsichere Protokolle wie javascript:) wird abgelehnt →
     null, damit der Aufrufer eine verständliche Fehlermeldung zeigen kann,
     statt eine kaputte oder unsichere URL zu speichern. */
  function ttNormalizeLinkUrl(raw) {
    var url = (raw || '').trim();
    if (!url) return null;
    if (/^(javascript|data|vbscript|file):/i.test(url)) return null;
    if (/^(https?:|mailto:|tel:)/i.test(url)) return url;
    if (/^[\/#]/.test(url)) return url; // interner Pfad (/jaeger/…) oder Anker (#…)
    if (/^www\./i.test(url) || /^[a-z0-9.\-]+\.[a-z]{2,}(\/.*)?$/i.test(url)) {
      return 'https://' + url;
    }
    return null;
  }

  /* ── TipTap-Modul zuverlässig laden (aktiver Retry + Fallback-CDN) ──
     window.TipTap gilt nur als bereit, wenn ALLE benötigten Extensions da
     sind. Das Modul wird via dynamischem import() geladen; schlägt ein Versuch
     fehl (kalter CDN-Start, Netzwerk-Hänger), wird AKTIV erneut geladen – statt
     nur auf einen Wert zu pollen, der nie kommt. So bleibt der Editor nicht
     dauerhaft bei „wird geladen…" hängen. */
  function tiptapReady() {
    var T = window.TipTap;
    return !!(T && T.Editor && T.Extension && T.StarterKit && T.Underline && T.Image &&
              T.Table && T.TableRow && T.TableCell && T.TableHeader &&
              T.TextAlign && T.TextStyle && T.Color && T.Highlight && T.Youtube && T.Link);
  }
  var _tiptapPromise = null;
  function ensureTiptap() {
    if (tiptapReady()) return Promise.resolve();
    if (_tiptapPromise) return _tiptapPromise;
    var PKGS = ['core', 'starter-kit', 'extension-underline', 'extension-image',
                'extension-table', 'extension-table-row', 'extension-table-cell', 'extension-table-header',
                'extension-text-align', 'extension-text-style', 'extension-color', 'extension-highlight', 'extension-youtube',
                'extension-link'];
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
            Extension:   m[0].Extension,
            StarterKit:  m[1].default,
            Underline:   m[2].default,
            Image:       m[3].default,
            Table:       m[4].default,
            TableRow:    m[5].default,
            TableCell:   m[6].default,
            TableHeader: m[7].default,
            TextAlign:   m[8].default,
            TextStyle:   m[9].default,
            Color:       m[10].default,
            Highlight:   m[11].default,
            Youtube:     m[12].default,
            Link:        m[13].default
          };
          if (!(T.Editor && T.Extension && T.StarterKit && T.Underline && T.Image &&
                T.Table && T.TableRow && T.TableCell && T.TableHeader &&
                T.TextAlign && T.TextStyle && T.Color && T.Highlight && T.Youtube && T.Link)) {
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
  // Generisch über renderForm() statt eine bestimmte render*()-Funktion fest
  // zu verdrahten - deckt damit jede aktuelle und künftige TipTap-nutzende
  // Formularart ab (aktuell 'standard' und 'kjm'), nicht nur eine einzelne.
  window.retryTiptapLoad = function() {
    _tiptapPromise = null;
    if (S.section && S.data) renderForm(S.section, S.data);
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
    // Gefährliche Link-Ziele (javascript:/data:/…) unschädlich machen, falls
    // sie je auf anderem Weg (z.B. direkte JSON-Bearbeitung) in den Inhalt
    // gelangt sind – siehe ttSanitizeHtml.
    html = ttSanitizeHtml(html);

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

    // Absatz-/Listenabstand einstellbar machen ("Frank-Wunsch": mehr
    // Word-artige Kontrolle, ohne das Design-System zu sprengen). Statt
    // jeden Node-Typ einzeln zu ersetzen, wird nur ein globales
    // data-spacing-Attribut auf Absätzen/Listen ergänzt – gerendert als
    // data-spacing="compact" im HTML, gesteuert allein über CSS.
    var SpacingAttrs = TT.Extension.create({
      name: 'spacingAttrs',
      addGlobalAttributes: function() {
        return [{
          types: ['paragraph', 'bulletList', 'orderedList'],
          attributes: {
            spacing: {
              default: null,
              parseHTML: function(el) { return el.getAttribute('data-spacing'); },
              renderHTML: function(attrs) { return attrs.spacing ? { 'data-spacing': attrs.spacing } : {}; }
            }
          }
        }];
      }
    });

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
        ImageWithClass,
        // Link-Marke: openOnClick aus (im Editor soll ein Klick den Cursor
        // setzen, nicht die Seite verlassen – Bearbeiten läuft über den
        // Toolbar-Button "🔗 Link", siehe ttLink). autolink/linkOnPaste aus,
        // damit kein Text unerwartet "von selbst" zum Link wird – wer einen
        // Link will, setzt ihn bewusst über den Button. Bereits vorhandene
        // <a href>-HTML-Links (eingefügt oder importiert) werden davon nicht
        // berührt: das Erkennen von echtem HTML beim Laden/Einfügen hängt
        // nicht an autolink/linkOnPaste, sondern daran, dass die Link-
        // Extension überhaupt geladen ist. HTMLAttributes bewusst leer, damit
        // KEIN globales target/rel erzwungen wird – das entscheidet pro Link
        // ttLinkApply() anhand intern/extern (siehe dort).
        TT.Link.configure({ openOnClick: false, autolink: false, linkOnPaste: false, HTMLAttributes: {} }),
        TT.TextStyle,
        TT.Color,
        TT.Highlight.configure({ multicolor: false }),
        TT.TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TT.Youtube.configure({ width: 640, height: 360, nocookie: true }),
        SpacingAttrs
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
        },
        // Eingefügtes HTML (z.B. aus Word/einer anderen Webseite kopiert) kann
        // eigene <a href="javascript:…">-Links enthalten. Vor der Übernahme
        // in den Editor genauso unschädlich machen wie beim ersten Laden
        // (siehe ttSanitizeHtml) – gleicher Filter, gleiche Stelle im Ablauf.
        transformPastedHTML: function(html) { return ttSanitizeHtml(html); }
      },
      onUpdate:         function() { markDirty(); updateTiptapToolbar(fieldId); },
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
  var FLAT_CLS = ['img-flat'];

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
    menu.querySelectorAll('[data-flat]').forEach(function(b) {
      b.classList.toggle('tt-imgmenu-btn--active', cls.indexOf(b.getAttribute('data-flat')) !== -1);
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

  // "Ohne Rahmen": Umschalter (kein Größen-/Positions-Wert, daher toggle statt
  // fixem Setzen) – entfernt Schatten & abgerundete Ecken. Gedacht für Logos/
  // Grafiken mit eigenem weißen Hintergrund, wo der Schatten sonst wie ein
  // sichtbarer Rahmen/Kasten gegen die (ebenfalls weiße) Seite wirkt.
  window.ttImgFlat = function() {
    if (!_ttClickedImg) return;
    var classes = (_ttClickedImg.className || '').split(' ').filter(Boolean);
    var isFlat = classes.indexOf('img-flat') !== -1;
    var cur = classes.filter(function(c) { return FLAT_CLS.indexOf(c) === -1; });
    if (!isFlat) cur.push('img-flat');
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
    // Abstand: an genau EINEM der drei Werte aktiv, nie togglend. isActive
    // ohne Attribut-Filter prüft nur "ist das überhaupt ein Absatz/eine
    // Liste"; erst der jeweilige Attribut-Check (spacing:'compact' / 'wide' /
    // kein spacing-Attribut) entscheidet, welcher der drei Buttons leuchtet.
    var inBlock = editor.isActive('paragraph') || editor.isActive('bulletList') || editor.isActive('orderedList');
    var isCompact = editor.isActive('paragraph', { spacing: 'compact' }) ||
                    editor.isActive('bulletList', { spacing: 'compact' }) ||
                    editor.isActive('orderedList', { spacing: 'compact' });
    var isWide    = editor.isActive('paragraph', { spacing: 'wide' }) ||
                    editor.isActive('bulletList', { spacing: 'wide' }) ||
                    editor.isActive('orderedList', { spacing: 'wide' });
    var state = {
      bold:        editor.isActive('bold'),
      italic:      editor.isActive('italic'),
      underline:   editor.isActive('underline'),
      strike:      editor.isActive('strike'),
      link:        editor.isActive('link'),
      h2:          editor.isActive('heading', { level: 2 }),
      h3:          editor.isActive('heading', { level: 3 }),
      bulletList:  editor.isActive('bulletList'),
      orderedList: editor.isActive('orderedList'),
      alignLeft:   editor.isActive({ textAlign: 'left' }),
      alignCenter: editor.isActive({ textAlign: 'center' }),
      alignRight:  editor.isActive({ textAlign: 'right' }),
      highlight:   editor.isActive('highlight'),
      spacingEng:    isCompact,
      spacingWeit:   isWide,
      spacingNormal: inBlock && !isCompact && !isWide
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
      case 'strike':      c.toggleStrike().run();                 break;
      case 'h2':          c.toggleHeading({ level: 2 }).run();    break;
      case 'h3':          c.toggleHeading({ level: 3 }).run();    break;
      case 'bulletList':  c.toggleBulletList().run();             break;
      case 'orderedList': c.toggleOrderedList().run();            break;
      case 'alignLeft':   c.setTextAlign('left').run();           break;
      case 'alignCenter': c.setTextAlign('center').run();         break;
      case 'alignRight':  c.setTextAlign('right').run();          break;
      case 'highlight':   c.toggleHighlight({ color: '#fff3a3' }).run(); break;
      // Abstand: drei feste Werte statt Umschalter (Frank-Feedback
      // 19.08.2026 – ein Toggle-Button ist unzuverlässig, wenn eine Selektion
      // mehrere Blöcke mit unterschiedlichem Ausgangs-Abstand umfasst: welcher
      // Wert dann als "nächster" gilt, ist nicht eindeutig. Jeder Button setzt
      // hier stattdessen IMMER denselben festen Wert – unabhängig vom
      // aktuellen Zustand, daher immer vorhersagbar.
      case 'spacingEng':
        ['paragraph', 'bulletList', 'orderedList'].forEach(function(type) {
          if (editor.isActive(type)) c.updateAttributes(type, { spacing: 'compact' });
        });
        c.run();
        break;
      case 'spacingNormal':
        ['paragraph', 'bulletList', 'orderedList'].forEach(function(type) {
          if (editor.isActive(type)) c.updateAttributes(type, { spacing: null });
        });
        c.run();
        break;
      case 'spacingWeit':
        ['paragraph', 'bulletList', 'orderedList'].forEach(function(type) {
          if (editor.isActive(type)) c.updateAttributes(type, { spacing: 'wide' });
        });
        c.run();
        break;
    }
    updateTiptapToolbar(fieldId);
  };

  // Textfarbe: eigener Handler statt ttCmd, weil der Wert vom <input type=color>
  // kommt statt von einem festen Button-Klick.
  window.ttColor = function(fieldId, hexValue) {
    var editor = S.tiptapEditors[fieldId];
    if (!editor) return;
    editor.chain().focus().setColor(hexValue).run();
  };

  // Video einfügen: aktuell YouTube (offizielle, zuverlässige TipTap-
  // Extension). Andere Anbieter (z.B. Vimeo) werden absichtlich nicht
  // versucht einzubetten, um keine kaputten Platzhalter zu riskieren.
  window.ttVideo = function(fieldId) {
    var editor = S.tiptapEditors[fieldId];
    if (!editor) return;
    var url = window.prompt('YouTube-Video-URL einfügen:', 'https://www.youtube.com/watch?v=');
    if (!url) return;
    if (!/youtube\.com|youtu\.be/i.test(url)) {
      alert('Aktuell werden nur YouTube-Links unterstützt.');
      return;
    }
    editor.commands.setYoutubeVideo({ src: url });
  };

  /* ── TipTap: Link setzen/bearbeiten/entfernen ───────────────────
     Eigenes kleines Dropdown (#tt-link-menu in index.html) statt window.
     prompt() – gleiches Muster wie das Tabellen-Dropdown (#tt-table-menu)
     direkt darunter: unterhalb des Toolbar-Buttons positioniert, schließt
     bei Klick außerhalb. So bleibt es beim bestehenden Admin-UI-Stil, ohne
     eine neue, größere Modal-Infrastruktur nur für Links zu bauen.
     Ablauf: Cursor/Selektion im Editor bleibt während des Dialogs unver-
     ändert (nur der Fokus wandert kurz ins URL-Feld); "Abbrechen"/Klick
     außerhalb ändert am Editor-Inhalt nichts. */
  var _ttLinkField = null;

  function ttHideLinkMenu() {
    var menu = id('tt-link-menu');
    if (menu) menu.style.display = 'none';
    _ttLinkField = null;
  }

  // Toolbar-Button "🔗 Link": öffnet den Dialog. Ohne Textauswahl UND ohne
  // dass der Cursor bereits in einem Link steht, gibt es nichts, das zu
  // einem Link werden könnte – dann Hinweis statt leerem Dialog.
  window.ttLink = function(evt, fieldId) {
    var editor = S.tiptapEditors[fieldId];
    if (!editor) return;
    if (editor.state.selection.empty && !editor.isActive('link')) {
      showAlert('Kein Text markiert', 'Bitte zuerst den Text markieren, der zu einem Link werden soll – oder mit dem Cursor in einen bestehenden Link klicken, um ihn zu bearbeiten.');
      return;
    }
    _ttLinkField = fieldId;
    var current   = editor.isActive('link') ? (editor.getAttributes('link').href || '') : '';
    var menu      = id('tt-link-menu');
    var input     = id('tt-link-input');
    var removeBtn = id('tt-link-remove');
    if (!menu || !input) return;
    input.value = current;
    if (removeBtn) removeBtn.style.display = current ? '' : 'none';
    var btn = evt.currentTarget;
    menu.style.visibility = 'hidden';
    menu.style.display    = 'block';
    var r = btn.getBoundingClientRect();
    menu.style.top  = (r.bottom + 6) + 'px';
    menu.style.left = r.left + 'px';
    menu.style.visibility = '';
    setTimeout(function() { input.focus(); input.select(); }, 0);
  };

  window.ttLinkCancel = function() { ttHideLinkMenu(); };

  window.ttLinkRemove = function() {
    var editor = S.tiptapEditors[_ttLinkField];
    if (editor) editor.chain().focus().extendMarkRange('link').unsetLink().run();
    ttHideLinkMenu();
  };

  // "Übernehmen": leeres Feld bei bestehendem Link = wie Entfernen (bequemer
  // Ausweg, falls jemand die URL komplett löscht statt den Entfernen-Button
  // zu nutzen); leeres Feld ohne bestehenden Link = nichts tun. Bei einer
  // nicht erkennbaren URL bleibt der Dialog offen und die Eingabe erhalten,
  // statt sie stillschweigend zu verwerfen (siehe ttNormalizeLinkUrl).
  window.ttLinkApply = function() {
    var fieldId = _ttLinkField;
    var editor  = S.tiptapEditors[fieldId];
    if (!editor) { ttHideLinkMenu(); return; }
    var input = id('tt-link-input');
    var raw   = input ? input.value.trim() : '';
    if (!raw) {
      if (editor.isActive('link')) editor.chain().focus().extendMarkRange('link').unsetLink().run();
      ttHideLinkMenu();
      return;
    }
    var url = ttNormalizeLinkUrl(raw);
    if (!url) {
      toast('❌ Diese Adresse wird nicht erkannt. Bitte mit https://, /pfad, mailto: oder tel: beginnen.', 'err');
      return;
    }
    // Intern (Root-Pfad/Anker) und mailto:/tel: bleiben im selben Tab, ohne
    // target/rel – entspricht der bestehenden Konvention auf der Website
    // (siehe z.B. infomobil.html: interne/Kontakt-Links ohne target).
    // Externe http(s)-Links öffnen in einem neuen Tab mit rel="noopener
    // noreferrer" (bestehende Konvention, siehe seiten/index.html Galerie-
    // Links und den Google-Kalender-Datenschutzhinweis).
    var isInternal = /^[\/#]/.test(url) || /^(mailto:|tel:)/i.test(url);
    var attrs = isInternal
      ? { href: url, target: null, rel: null }
      : { href: url, target: '_blank', rel: 'noopener noreferrer' };
    editor.chain().focus().extendMarkRange('link').setLink(attrs).run();
    ttHideLinkMenu();
  };

  (function() {
    var input = id('tt-link-input');
    if (!input) return;
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter')  { e.preventDefault(); window.ttLinkApply(); }
      else if (e.key === 'Escape') { window.ttLinkCancel(); }
    });
  })();

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
    if (menu && menu.style.display !== 'none' &&
        !menu.contains(e.target) && !e.target.closest('.tt-btn')) {
      menu.style.display = 'none';
      _ttTableField = null;
    }
    var linkMenu = id('tt-link-menu');
    if (linkMenu && linkMenu.style.display !== 'none' &&
        !linkMenu.contains(e.target) && !e.target.closest('.tt-btn')) {
      linkMenu.style.display = 'none';
      _ttLinkField = null;
    }
  });

  // Bild-Modal für TipTap öffnen (analog openMdImageModal, aber ohne S.mde-Check)
  window.openTiptapImageModal = function(fieldId) {
    S._tiptapImageField = fieldId;
    mdImgZeigtArchiv = false; // jede neue Öffnung startet in der normalen Ansicht
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
    markDirty(); // Eigener Umschalter, kein natives Checkbox-Element - löst kein input/change-Event aus
  };

  function toggleVal(fieldId) {
    var el = id('f-' + fieldId);
    return el ? el.getAttribute('data-val') === '1' : false;
  }

  /* ────────────────────────────────────────────────────────────
     UI HELPERS
  ──────────────────────────────────────────────────────────── */
  // hideDefaultSave: für Bildschirme wie aktuellesEdit(), die bereits einen
  // eigenen, funktionierenden Speichern-Button in extraBtns mitgeben (mit
  // eigenem onclick statt dem generischen [data-save] → saveCurrentSection()-
  // Mechanismus). Ohne dieses Flag gab es Punkt-9-Bug: zwei "Speichern"-
  // Buttons, wobei der Standard-Button (data-save) dort nie mit
  // bindSaveBtn() verkabelt und damit toter Klick war.
  function panelHeader(title, extraBtns, hideDefaultSave) {
    return '<div class="panel-header">' +
      '<h2>' + escHtml(title) + '</h2>' +
      '<div class="panel-header-actions">' +
        (extraBtns || '') +
        (hideDefaultSave ? '' : '<button class="btn btn-primary" data-save>💾 Speichern</button>') +
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

  // Jahr aus einem DD.MM.YYYY-Datum extrahieren – gleiche Regex-Logik wie
  // getYear() auf der öffentlichen Aktuelles-Seite (aktuelles/index.html).
  function jahrAusDatum(datum) {
    var m = datum && String(datum).match(/(\d{4})/);
    return m ? m[1] : '';
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
    function indexItem(item, label, path) {
      var bereich = path || label;
      _searchIndex.push({
        icon: item.label.match(/^./u)?.[0] || '📄',
        label: label,
        sub: 'Bereich: ' + bereich,
        match: [label, item.key || '', item.file || ''].join(' ').toLowerCase(),
        action: function() { selectSection(item); }
      });
    }
    function traverseNav(items, path) {
      items.forEach(function(item) {
        if (item.isAdd) return;
        var label = (item.label || '').replace(/^[🏠🦌🌿📅📰❓⚙️📥🖼️➕]\s*/u, '');
        if (item.group || item.dynamicChildren) {
          // Gruppen mit eigenem "file" (Wildfleisch/Lernort Natur/Grünes
          // Klassenzimmer, 22.08.2026-Umbau) haben jetzt selbst editierbaren
          // Inhalt - ohne diesen Zweig wären sie über die Suche nicht mehr
          // auffindbar gewesen (vorher gab es dafür einen separaten,
          // durchsuchbaren "Seiteninhalt"-Unterpunkt).
          if (item.file) indexItem(item, label, path);
          if (item.children) traverseNav(item.children, path);
          return;
        }
        if (!item.file && item.form !== 'medien') return;
        indexItem(item, label, path);
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
      { url: '/content/seiten-sub-wildfleisch.json',           icon: '🌿', bereich: 'Verbraucher / Wildfleisch',            dir: 'content/seiten-sub-wildfleisch',           form: 'standard' },
      { url: '/content/seiten-sub-lernort-natur.json',         icon: '🌿', bereich: 'Verbraucher / Lernort Natur',         dir: 'content/seiten-sub-lernort-natur',         form: 'standard' },
      { url: '/content/seiten-sub-gruenes-klassenzimmer.json', icon: '🌿', bereich: 'Verbraucher / Grünes Klassenzimmer', dir: 'content/seiten-sub-gruenes-klassenzimmer', form: 'standard' },
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
  // Nachträglich ergänzt (22.08.2026, Fix für #111/#112): renderService und
  // renderMedian fehlten hier, obwohl sie direkt per onclick="renderService(...)"/
  // "renderMedian()" aus den jeweiligen Archiv-Unterseiten aufgerufen werden -
  // Funktionen, die nur als private "function xxx(){}" in der IIFE existieren
  // (nicht window.xxx = ...), sind für Inline-onclick-Attribute (die im
  // globalen Scope laufen) unsichtbar und werfen "xxx is not defined". Gleiches
  // Muster für die neuen serviceAktuelleAnsichtRendern/aktuellesAktuelleAnsichtRendern-
  // Helfer (Zurück-Button in serviceEdit/aktuellesEdit). Bei künftigen neuen
  // Funktionen, die per onclick="..." aus gerendertem HTML aufgerufen werden,
  // IMMER prüfen: entweder direkt als window.xxx = function(){...} definieren,
  // oder hier in diese Liste aufnehmen - sonst genau dieser Bug erneut.
  window.renderService = renderService;
  window.renderMedian   = renderMedian;
  window.serviceAktuelleAnsichtRendern   = serviceAktuelleAnsichtRendern;
  window.aktuellesAktuelleAnsichtRendern = aktuellesAktuelleAnsichtRendern;
  // Hundebörse-Edit-Ansicht ruft dies per onclick="renderHundeboerse(...)" auf
  // dem "← Zurück zur Hundebörse"-Button auf - genau das oben beschriebene
  // Muster, hier bei der Einführung der Hundebörse trotz Warnung erneut
  // übersehen (Laurin-Bug-Report, 2026-08-28: Button reagierte nicht,
  // Konsole zeigte "renderHundeboerse is not defined").
  window.renderHundeboerse = renderHundeboerse;
  // Partner-Admin (04.09.2026, Frank-Bug-Report "Zurück-Button reagiert
  // nicht"): exakt derselbe Fehler wie oben bei renderService/renderMedian/
  // renderHundeboerse - renderPartner war beim Partner-Modul-Aufbau nur als
  // private "function renderPartner(){}" in der IIFE definiert, der
  // "← Zurück"-Button in partnerEdit() ruft es aber per
  // onclick="confirmNav(function(){renderPartner(...)})" auf, was im
  // globalen Scope läuft ("renderPartner is not defined" in der Konsole).
  window.renderPartner = renderPartner;
  // Waffenbörse-Admin (Arbeitsblock 2, 04.09.2026): Ursache unabhängig
  // geprüft statt die Partner-Lösung blind zu übernehmen - Befund ist aber
  // exakt dasselbe Muster: renderWaffenboerse ist nur als private
  // "function renderWaffenboerse(def, data){}" in der IIFE definiert (siehe
  // weiter oben). Der "← Zurück zur Waffenbörse"-Button in waffenboerseEdit()
  // ruft es per onclick="confirmNav(function(){renderWaffenboerse(S.section,
  // S.data)})" auf - dieses Inline-onclick-Attribut (und die darin per
  // "function(){...}" gebaute Callback-Funktion) läuft im globalen Scope,
  // nicht in der admin.js-IIFE. window.S und window.confirmNav sind bereits
  // oben exponiert, nur renderWaffenboerse selbst fehlte - dadurch bisher
  // "renderWaffenboerse is not defined" beim Klick auf Zurück.
  window.renderWaffenboerse = renderWaffenboerse;

})();
