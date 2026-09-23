/**
 * KJS Bad Segeberg - Phase 7B (Admin-Modul "Inhalte/Seiten").
 *
 * Trimmter, ueber npm/Vite gebuendelter TipTap-Rich-Text-Editor fuer die
 * drei "Standard-Seite"-Felder untertitel/intro/inhalt (siehe
 * resources/views/admin/inhalte/bearbeiten.blade.php).
 *
 * Bewusste Abgrenzung zum Alt-Admin (admin/admin.js initTiptap()/
 * ensureTiptap()), siehe Auftrag Teil 6 ("dieselbe fachliche Bedeutung
 * uebernehmen... keinen neuen Editor nur aus Komfortgruenden einfuehren...
 * bestehende Loesung bevorzugen bzw. minimal sauber portieren"):
 *   - GLEICHE Editor-Technologie (TipTap 2) fuer dieselbe fachliche
 *     Bedeutung (Rich-Text mit Ueberschriften/Formatierung/Listen/Links/
 *     Tabellen/Textfarbe) - kein Wechsel auf Markdown/plain-textarea.
 *   - ANDERE Ladeart: npm-Pakete, von Vite gebuendelt (siehe package.json/
 *     vite.config.js), statt admin.js' Laufzeit-CDN-Import mit Retry/
 *     Fallback-CDN. Das vermeidet eine externe Netzwerk-Abhaengigkeit beim
 *     Aufruf des neuen Admin (kein "Editor wird geladen…"/CDN-Ausfall-
     *     Zustand) und ist damit eine echte, im Auftrag ausdruecklich erlaubte
 *     Verbesserung ("minimal sauber portieren"), keine Neuerfindung.
 *   - BEWUSST WEGGELASSEN (Medienverwaltung ist laut Auftrag "NICHT
 *     JETZT"): Bild-Einfuegen-Button/-Modal, Bild-Kontextmenue (Groesse/
 *     Position per Klick), YouTube-Einbettung, Markdown-Paste-Erkennung.
 *     Die Image-Extension bleibt trotzdem geladen (siehe EXTENSIONS unten) -
 *     nicht zum Einfuegen NEUER Bilder, sondern damit bereits vorhandene
 *     <img>-Tags in altem Seiteninhalt beim Laden/Speichern nicht aus dem
 *     TipTap-Schema herausfallen (das waere ein stiller Datenverlust).
 *
 * Progressive Enhancement (Auftrag Teil 9 "keine JSON-Runtime fuer Blade-
 * Liste/Edit noetig"): das Formular funktioniert auch ohne JavaScript -
 * jedes Feld ist primaer ein normales <textarea name="..."> mit dem
 * rohen HTML-Inhalt. Erst wenn dieses Skript laedt, wird pro Feld
 * zusaetzlich ein TipTap-Editor gemountet und der Textarea-Inhalt darin
 * angezeigt; Speichern schreibt den TipTap-Inhalt vor dem nativen Form-
 * Submit zurueck in die Textarea (kein fetch()/keine eigene JSON-Anfrage).
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';

var editors = {};

function extensionsFor() {
  return [
    StarterKit.configure({ heading: { levels: [2, 3] } }),
    Underline,
    Image,
    Link.configure({ openOnClick: false, autolink: false, linkOnPaste: false, HTMLAttributes: {} }),
    TextStyle,
    Color,
    Highlight.configure({ multicolor: false }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
  ];
}

function toggleActive(fieldName, editor) {
  var toolbar = document.querySelector('[data-richtext-toolbar="' + fieldName + '"]');
  if (!toolbar) return;
  toolbar.querySelectorAll('button[data-cmd]').forEach(function (btn) {
    var cmd = btn.getAttribute('data-cmd');
    var active = false;
    if (cmd === 'bold') active = editor.isActive('bold');
    else if (cmd === 'italic') active = editor.isActive('italic');
    else if (cmd === 'underline') active = editor.isActive('underline');
    else if (cmd === 'h2') active = editor.isActive('heading', { level: 2 });
    else if (cmd === 'h3') active = editor.isActive('heading', { level: 3 });
    else if (cmd === 'ul') active = editor.isActive('bulletList');
    else if (cmd === 'ol') active = editor.isActive('orderedList');
    else if (cmd === 'link') active = editor.isActive('link');
    btn.classList.toggle('is-active', active);
  });
}

function wireToolbar(fieldName, editor) {
  var toolbar = document.querySelector('[data-richtext-toolbar="' + fieldName + '"]');
  if (!toolbar) return;
  toolbar.hidden = false;
  toolbar.querySelectorAll('button[data-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-cmd');
      var chain = editor.chain().focus();
      switch (cmd) {
        case 'bold': chain.toggleBold().run(); break;
        case 'italic': chain.toggleItalic().run(); break;
        case 'underline': chain.toggleUnderline().run(); break;
        case 'h2': chain.toggleHeading({ level: 2 }).run(); break;
        case 'h3': chain.toggleHeading({ level: 3 }).run(); break;
        case 'ul': chain.toggleBulletList().run(); break;
        case 'ol': chain.toggleOrderedList().run(); break;
        case 'link':
          var vorher = editor.getAttributes('link').href || 'https://';
          var url = window.prompt('Link-Ziel (URL):', vorher);
          if (url === null) { break; }
          if (url.trim() === '') { chain.extendMarkRange('link').unsetLink().run(); break; }
          chain.extendMarkRange('link').setLink({ href: url.trim() }).run();
          break;
        case 'table': chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); break;
        default: break;
      }
      toggleActive(fieldName, editor);
    });
  });
}

function syncHidden(fieldName) {
  var editor = editors[fieldName];
  var textarea = document.getElementById('f-' + fieldName);
  if (!editor || !textarea) return;
  var html = editor.getHTML();
  textarea.value = html === '<p></p>' ? '' : html;
}

function initField(container) {
  var fieldName = container.getAttribute('data-richtext');
  var textarea = document.getElementById('f-' + fieldName);
  if (!textarea) return;
  try {
    var editor = new Editor({
      element: container,
      extensions: extensionsFor(),
      content: textarea.value || '',
      onUpdate: function () { syncHidden(fieldName); },
      onSelectionUpdate: function () { toggleActive(fieldName, editor); },
    });
    editors[fieldName] = editor;
    container.hidden = false;
    textarea.hidden = true;
    wireToolbar(fieldName, editor);
    toggleActive(fieldName, editor);
  } catch (e) {
    // TipTap konnte nicht initialisiert werden - das Formular bleibt mit
    // der ganz normalen, sichtbaren <textarea> voll funktionsfaehig
    // (siehe Klassenkommentar "Progressive Enhancement").
    console.error('Rich-Text-Editor konnte nicht geladen werden, verwende Textarea:', e);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-richtext]').forEach(initField);
  document.querySelectorAll('form[data-richtext-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      Object.keys(editors).forEach(syncHidden);
    });
  });
});
