/* =========================================================
   KJS – gemeinsame Datenschicht für Termine & Aktuelles
   (31.08.2026, Architektur-Audit Phase 1, Punkte 6+7)

   Vorher hatten Startseite, Termine-Seite, Aktuelles-Übersicht und die
   Beitrags-Detailseite jeweils eigene, unabhängige Kopien derselben
   fachlichen Regeln (Sichtbarkeit, Sortierung, "was gilt als archiviert").
   Das führte bereits zu echten Abweichungen (z.B. keine Datums-Sortierung
   auf termine/index.html, archivierte Beiträge in den Aktuelles-
   Standardansichten). Diese Datei ist die EINE Stelle, an der diese Regeln
   stehen - alle Seiten binden sie per <script> ein und rufen sie auf,
   statt die Logik erneut zu schreiben.

   Bewusst NICHT verändert: die JSON-Datenstruktur (content/termine.json,
   content/aktuelles.json) und die Optik der einzelnen Seiten. Diese Datei
   liefert nur Daten/Arrays zurück, keine HTML-Ausgabe.
   ========================================================= */
(function (global) {
  'use strict';

  var KJSContent = global.KJSContent || {};

  /* ---------------------------------------------------------
     TERMINE
     Regeln (unverändert übernommen aus index.html/termine/index.html,
     Frank-Wunsch 20.08.2026): ein Termin verschwindet, sobald er im Admin
     archiviert wurde ODER sein Datum mehr als 7 Tage zurückliegt. Bei
     unlesbarem Datum sicherheitshalber weiter anzeigen statt auszublenden.
     --------------------------------------------------------- */
  KJSContent.Termine = (function () {
    function parseDatum(raw) {
      if (!raw) return null;
      var m1 = raw.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
      if (m1) return new Date(+m1[3], +m1[2] - 1, +m1[1]);
      var m2 = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (m2) return new Date(+m2[1], +m2[2] - 1, +m2[3]);
      return null;
    }

    function istSichtbar(t) {
      if (t.archiviert) return false;
      var d = parseDatum(t.datum);
      if (!d) return true;
      var grenze = new Date(d.getTime());
      grenze.setDate(grenze.getDate() + 7);
      grenze.setHours(23, 59, 59, 999);
      return grenze >= new Date();
    }

    // Aufsteigend nach Datum (nächster Termin zuerst). Termine mit
    // unlesbarem Datum landen ans Ende statt die Sortierung zu verfälschen.
    function sortiere(termine) {
      return (termine || []).slice().sort(function (a, b) {
        var da = parseDatum(a.datum), db = parseDatum(b.datum);
        if (!da && !db) return 0;
        if (!da) return 1;
        if (!db) return -1;
        return da - db;
      });
    }

    function sichtbareSortiert(alleTermine) {
      return sortiere((alleTermine || []).filter(istSichtbar));
    }

    function laden() {
      return fetch('/content/termine.json?_=' + Date.now()).then(function (r) { return r.json(); });
    }

    return {
      parseDatum: parseDatum,
      istSichtbar: istSichtbar,
      sortiere: sortiere,
      sichtbareSortiert: sichtbareSortiert,
      laden: laden
    };
  })();

  /* ---------------------------------------------------------
     AKTUELLES
     "archiviert" bedeutet überall dasselbe: erscheint nicht mehr in den
     öffentlichen Standardansichten, bleibt aber als Datensatz erhalten
     (Architektur-Audit A3). postYear()/sortKey() unverändert aus
     aktuelles/index.html übernommen (b.jahr hat Vorrang vor dem Jahr aus
     b.datum, für per Admin nachträglich einsortierte Beiträge).
     --------------------------------------------------------- */
  KJSContent.Aktuelles = (function () {
    function getYear(datum) {
      var m = datum && String(datum).match(/(\d{4})/);
      return m ? parseInt(m[1], 10) : 0;
    }

    function postYear(b) {
      if (b && b.jahr) {
        var j = parseInt(b.jahr, 10);
        if (j) return j;
      }
      return getYear(b && b.datum);
    }

    function sortKey(b) {
      var year = postYear(b) || 0;
      var monat = 0, tag = 0;
      var raw = (b && b.datum) || '';
      var m1 = raw.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
      var m2 = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
      if (m1) { tag = parseInt(m1[1], 10); monat = parseInt(m1[2], 10); }
      else if (m2) { monat = parseInt(m2[2], 10); tag = parseInt(m2[3], 10); }
      return year * 10000 + monat * 100 + tag;
    }

    function nichtArchiviert(beitraege) {
      return (beitraege || []).filter(function (b) { return !b.archiviert; });
    }

    // Neueste zuerst. Sortiert immer eine KOPIE (slice()), damit
    // aufrufender Code weiterhin per indexOf() den echten Index im
    // ungefilterten Original-Array aus content/aktuelles.json ermitteln
    // kann (wichtig für die "?i="-Links auf beitrag.html).
    function sortiertNeuesteZuerst(beitraege) {
      return (beitraege || []).slice().sort(function (a, b) { return sortKey(b) - sortKey(a); });
    }

    function alleSichtbar(beitraege) {
      return sortiertNeuesteZuerst(nichtArchiviert(beitraege));
    }

    function jahresAuswahl(beitraege, jahr) {
      return sortiertNeuesteZuerst(nichtArchiviert(beitraege).filter(function (b) { return postYear(b) === jahr; }));
    }

    // "Standardauswahl" der /aktuelles/-Übersichtsseite ohne gewählten
    // Jahres-Filter: aktuelles Jahr (nicht archiviert), mit Fallback auf
    // alle nicht archivierten Beiträge, falls das aktuelle Jahr leer ist.
    // einstellungen.hauptseite_anzahl wirkt hier weiterhin als "letzte N"-
    // Override, genau wie vor dieser Zentralisierung (unverändertes
    // Verhalten, nur an einer Stelle statt dupliziert). Dieses Feld hat
    // aktuell keine eigene Admin-Oberfläche mehr (am 22.08.2026 bewusst
    // entfernt, siehe admin.js) - es bleibt trotzdem wirksam, falls es
    // jemals wieder gesetzt wird.
    function standardAuswahl(beitraege, einstellungen) {
      var einst = einstellungen || {};
      var sortiert = sortiertNeuesteZuerst(nichtArchiviert(beitraege));
      var anzahl = parseInt(einst.hauptseite_anzahl, 10) || 0;
      if (anzahl > 0) return sortiert.slice(0, anzahl);
      var currentYear = new Date().getFullYear();
      var thisYear = sortiert.filter(function (b) { return postYear(b) === currentYear; });
      return thisYear.length > 0 ? thisYear : sortiert;
    }

    // Für die Startseiten-Vorschau: die N neuesten nicht archivierten
    // Beiträge, korrekt nach Datum sortiert (vorher: rohe JSON-Reihenfolge
    // ohne Sortierung, "funktionierte" nur zufällig durch unshift() beim
    // Anlegen neuer Beiträge im Admin).
    function neuesteNichtArchiviert(beitraege, anzahl) {
      return sortiertNeuesteZuerst(nichtArchiviert(beitraege)).slice(0, anzahl);
    }

    // Jahre für die Archiv-Sidebar: nur Jahre, in denen mindestens ein
    // NICHT archivierter Beitrag existiert (01.09.2026, Regressionstest
    // Phase 0+1 Punkt 1). Vorher wurden alle Jahre aus _allBeitraege
    // gebildet, auch wenn ein Jahr ausschließlich archivierte Beiträge
    // enthielt - so ein Jahr landete anklickbar in der Sidebar, zeigte dann
    // aber "Keine Beiträge für diese Auswahl", weil jahresFilter() bereits
    // korrekt filtert. Neueste zuerst.
    function sichtbareJahre(beitraege) {
      var jahre = [];
      nichtArchiviert(beitraege).forEach(function (b) {
        var y = postYear(b);
        if (y && jahre.indexOf(y) === -1) jahre.push(y);
      });
      jahre.sort(function (a, b) { return b - a; });
      return jahre;
    }

    // "Weitere Beiträge" auf der Detailseite (beitrag.html): sichtbare
    // (nicht archivierte) Beiträge desselben Jahres, ohne den aktuell
    // angezeigten Beitrag selbst. eigenerIndex ist der Index im
    // UNGEFILTERTEN Original-Array (wie bei den anderen Seiten), damit
    // "der aktuelle Beitrag" zuverlässig ausgeschlossen wird, auch wenn
    // zwei Beiträge inhaltlich identisch wären.
    function weitereBeitraegeDesJahres(beitraege, eigenerIndex, jahr) {
      return (beitraege || []).filter(function (b, i) {
        return i !== eigenerIndex && !b.archiviert && postYear(b) === jahr;
      });
    }

    function laden() {
      return fetch('/content/aktuelles.json?_=' + Date.now()).then(function (r) { return r.json(); });
    }

    return {
      getYear: getYear,
      postYear: postYear,
      sortKey: sortKey,
      nichtArchiviert: nichtArchiviert,
      sortiertNeuesteZuerst: sortiertNeuesteZuerst,
      alleSichtbar: alleSichtbar,
      jahresAuswahl: jahresAuswahl,
      standardAuswahl: standardAuswahl,
      neuesteNichtArchiviert: neuesteNichtArchiviert,
      sichtbareJahre: sichtbareJahre,
      weitereBeitraegeDesJahres: weitereBeitraegeDesJahres,
      laden: laden
    };
  })();

  global.KJSContent = KJSContent;
})(window);
