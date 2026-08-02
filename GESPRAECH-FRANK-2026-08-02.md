# Notizen aus dem Gespräch mit Frank – 02.08.2026

Quelle: von Laurin transkribiertes/zusammengefasstes Gespräch mit Frank Hülser (Geschäftsführer), teils beim gemeinsamen Durchklicken der Joomla-Version und des eigenen Admin-Bereichs. Hier strukturiert, damit die Punkte nach und nach abgearbeitet werden können. Rein private/jagdliche Themen (Leitern, Waffenverkauf-Verhandlungen im Detail, Treffen mit Dritten) sind rausgefiltert – außer sie enthalten eine konkrete Anforderung an die Website.

---

## 0. Grundsatzentscheidung (bestätigt)

- Joomla-Version (mit Oliver) wird **nicht** weiterverfolgt. Frank fand sie beim Live-Durchklicken „Katastrophe" – Menü kaputt, Bilder fehlen, Farb-/Schriftkonflikt beim Logo-Einbau, Admin schwer verständlich (teils Englisch), keine gute KI-Anbindung möglich.
- Zurück zum eigenen CMS – wird von beiden als klar überlegen empfunden, gerade weil es mit Claude weitergebaut werden kann. Frank hat sich auch alternative Headless-CMS angeschaut (**PayloadCMS, Directus, Strapi** – alle "code first"), aber Fokus bleibt erstmal: das eigene System fertig kriegen. Diese Alternativen ggf. später nochmal mit Carsten besprechen.
- Unmut über Oliver: nimmt sich zu wenig Zeit, wirft ständig neue große Themen auf (z.B. Joomla-Umstieg), obwohl klar war, dass die Seite online muss. Frank bespricht das separat mit Oliver.
- Neuer möglicher Ansprechpartner: **Carsten (Jungs?)** – sehr KI-affin, 20 Jahre Erfahrung im Seitenbau. Frank will ihn fragen: welches CMS/welcher Ansatz passt am besten zu KI-gestütztem Arbeiten, und ob er bei der rechtlichen Absicherung (Impressum/Datenschutz) und ggf. bei technischen Themen (mobile Darstellung, Testing) unterstützen kann. Ziel: evtl. feste Termine zwischen Laurin und Carsten.

---

## 1. Go-Live – konkrete offene Punkte (siehe auch `NAECHSTE-SCHRITTE-GO-LIVE.md`)

- **Kontaktseite:** Adresse der Geschäftsstelle (Hauptzentrale, "Am Schießstand") muss als zusätzlicher Kontaktpunkt ergänzt werden (Postadresse fehlt noch, siehe Franks E-Mail-Signatur als Referenz).
- **Kontaktformular:** Frank ging davon aus, dass die E-Mail-Anbindung noch fehlt. Ist technisch bereits über formsubmit.co verkabelt (siehe Go-Live-Datei) – trotzdem gemeinsam einmal live testen, damit sicher keine Mails verloren gehen ("das wäre eine Katastrophe").
- **Cookie-Banner** muss vor Go-Live aktiviert werden (aktuell noch nicht aktiv).
- **Rechtssicherheit Impressum/Datenschutz:** Inhalte sind im Admin editierbar und schon befüllt, aber sollten juristisch geprüft werden (Frank nennt z.B. einen Dienst wie „IT-Recht" oder fragt Carsten). Auch Olivers Einwand aufgreifen, dass es „nicht rechtssicher" sei wegen Hacking-Risiko.
- **Login-Link in der Fußzeile entfernen (Sicherheit):** Laurin hat (via Claude Code) einen Login-Link ganz unten im Footer der öffentlichen Seite eingebaut, der direkt in den Adminbereich führt. Für die Entwicklung/Testphase okay, aber **vor dem eigentlichen Go-Live unbedingt entfernen** – sonst finden potenzielle Angreifer den Admin-Zugang viel zu leicht. Klarer Punkt für die finale Go-Live-Checkliste kurz vor dem Umschalten.
- **Aktuelles/Termine:** Inhalte grundsätzlich vorhanden, aber Archiv-Trennung 2025/2026 fehlt noch, alte Inhalte von der bisherigen Seite müssen teilweise übernommen werden.
- **Top-Menü:** „Test"-Menüpunkt fliegt raus (bekannt, siehe bestehendes TEMPORÄR in `js/main.js`). Ein Menüpunkt gefällt Frank aktuell nicht / hat noch keinen guten Namen – muss noch überlegt werden. Eventuell Platz für einen zusätzlichen Menüpunkt oben nutzen.
- **Benutzerverwaltung im Admin:** Einladen neuer Nutzer funktioniert noch nicht. Zusätzlich fehlt eine Rechtetrennung zwischen Lese- und Schreibzugriff für Personen mit Admin-Zugang – Frank hält das für wichtig (kam separat auch beim Thema TimeTree/Kalender auf).
- **Login-Zugang für Frank:** Beim gemeinsamen Testen kam Frank nicht auf seinen Login, musste Passwort zurücksetzen. Bestätigt: der Zugangsdaten-Ordner auf dem Desktop ist sinnvoll und sollte zeitnah befüllt werden (siehe separates Thema im Chat).

---

## 2. CMS/Admin – mittelfristig (nach Go-Live, Testseite-Pattern ausrollen)

- Bild-Handling auf der Testseite funktioniert inzwischen gut (Größe, Position links/zentriert/rechts) – soll als Vorlage auf die anderen Seiten übertragen werden.
- **Neuer Wunsch:** Bildergalerie-Funktion – mehrere Bilder hintereinander/als Strecke hochladen und anzeigen können, nicht nur Einzelbilder. Besonders gewünscht für die Infomobil-Seite.
- **Live/Test-Trennung (wichtiger Workflow-Wunsch von Frank):** Es sollte immer einen entschiedenen Live-Bereich und einen separaten Testbereich geben, in dem gefahrlos experimentiert werden kann (Beispiel-Idee: eine Kopie/„Test2"-Umgebung). Änderungen/Prompts dabei dokumentieren (Log/Doku), damit nachvollziehbar bleibt, was gemacht wurde.
- **Admin-Vorschau für verschiedene Endgeräte:** Idee für einen Knopf/Umschalter im Admin, um die Seite direkt in Handy-/Tablet-/Desktop-Ansicht gegenzuprüfen, ohne extra Tools zu benutzen. Frank schätzt das als vergleichsweise einfach umsetzbar ein ("wahrscheinlich das leichteste"). Hinweis: echte externe Mobile-Test-Tools funktionieren aktuell eh nicht, weil die Domain noch nicht öffentlich erreichbar ist.
- **Schriftgrößen pro Textblock/Seite** einstellbar machen, nicht nur global (aktuell nur globale Schriftart/-größe in den Design-Einstellungen).
- **Bekannter Bug:** Wenn das Logo eingebunden wird, geht die eingestellte Schriftart verloren/wird überschrieben – noch nicht gelöst.
- Kleinere UI-Fixen (niedrige Priorität, bewusst zurückgestellt): Striche/Linien neben dem Logo im Header wirken unpassend, Menüführung oben insgesamt nochmal anschauen (Frank empfindet es nach nochmaligem Anschauen aber als nicht mehr so schlimm) – **explizit als "später" markiert, jetzt nicht anfassen**.
- Vorstand/Obleute-Sektion: soll auf das gleiche Grid-Element-Pattern umgestellt werden wie andere dynamische Listen (aktuell nur einfache Namensliste).
- Navigation zwischen Unterseiten (z.B. Kreisjägermeister-Bereich): Zurück-Button fehlt teilweise beim Rüberspringen zwischen Seiten – für bessere Bedienung ergänzen.

---

## 3. Kalender/Infomobil-Buchung (TimeTree)

- Ziel: Im Infomobil-Bereich einen Kalender einbauen, über den Mitglieder sehen können, wann das Infomobil gebucht/blockiert ist.
- **Wichtig:** Auf der öffentlichen Seite darf **kein Name** auftauchen, nur ein Status wie „reserviert/belegt". Interne Ansicht (TimeTree) darf Namen zeigen.
- Aktuelles Problem in TimeTree: keine Unterscheidung zwischen Lese- und Schreibrechten für alle, die Zugriff haben – muss geprüft werden, wie/ob das dort einstellbar ist.
- Frank braucht einen eigenen TimeTree-Zugang, um sich das anzuschauen.

---

## 4. Content-/Feature-Ideen (Backlog, ausdrücklich „später")

- Verbraucher/Natur-Bereich: schöne Beschreibungstexte zu heimischen Tierarten und zu invasiven Arten (z.B. Waschbär) ergänzen – reine Content-Aufgabe.
- Möglicher Blog-Bereich rund um Wildbret/Kochen/Zerwirken, inkl. Lehrvideos (z.B. Zerlegetechnik) – macht die Seite als Infoquelle für Mitglieder interessanter, kein Werbedruck, sondern Mehrwert.
- Mitglieder-Marktplatz für den regionalen Verkauf von Jagdwaffen innerhalb der Jägerschaft (ähnlich Kleinanzeigen/„Gun Finder"), eher über ein fertiges Plugin/Verlinkung als Eigenbau. Nicht nur für KJS Segeberg gedacht, sondern potenziell für mehrere Kreisjägerschaften in Schleswig-Holstein gemeinsam. Rechtlich zu klären: Nachweis der Waffenbesitzkarte beim Verkauf – Frank prüft das vorerst manuell selbst.
- Hochsitze/Leitern-Partnerschaft: Kontakt zu einem Handwerker, der Jagd-Hochsitze/Leitern aus einem besonderen Holz (Lärchenholz) baut. Frank möchte dessen Produkte später über die Seite mit anbieten/verlinken (ähnlich wie die MingPolice-Partnerschaft unten) – vorerst nur als Idee für den Backlog, keine Umsetzung jetzt.
- Jagdreisen-Bereich: Reiseziele vorstellen/buchbar machen (z.B. Schweden), ggf. als Blogartikel mit Buchungsmöglichkeit.
- Partnerschaften/Rabatte einbinden: Verhandlungen mit „MingPolice" (Fallenmelder-Sensoren) und einem Sitzleiter-Hersteller (Lärchenholz), jeweils ca. 20 % Mitgliederrabatt. Technische Anforderung: ein Rabattcode, der nicht öffentlich/missbräuchlich nutzbar ist (nur für angemeldete Mitglieder sichtbar/gültig) – Umsetzung noch offen.
- Referenz für andere Kreisjägerschaften: Frank ist offen, KJS Segeberg als positives Beispiel zu zeigen, sobald fertig (aktuell wirken die meisten der 18 Kreisjägerschaften-Seiten in SH veraltet).

---

## 5. Offene Fragen (zu klären, teils mit Carsten/Oliver)

- Welchen Menüpunkt-Namen findet Frank aktuell unpassend – was soll stattdessen dort stehen? (noch offen)
- Infomobil-Kalender: Frank telefonierte dazu kurz mit einer Kollegin, Details/Ergebnis noch unklar – bei Gelegenheit nachfragen, was dabei rauskam.
- Ist eine Rechtetrennung (Lese-/Schreibzugriff) im eigenen Admin technisch sinnvoll/nötig umsetzbar, oder reicht es, den Nutzerkreis klein zu halten?
- Soll die Live/Test-Trennung als feste Kopie des Repos/Sites umgesetzt werden (z.B. Staging-Branch oder zweite Netlify-Site), oder reicht ein lokal getrenntes Arbeiten?
- Ergebnis von Franks Gespräch mit Carsten (CMS-Einschätzung, rechtliche Unterstützung, evtl. feste Termine) – nachhalten, sobald bekannt.
- Ergebnis von Franks Gespräch mit Oliver morgen (03.08.) – wie geht es mit ihm weiter, übernimmt er noch etwas oder zieht sich Laurin komplett raus?

---

*Erstellt: 2026-08-02. Wird nach und nach abgearbeitet – Punkte bei Erledigung hier abhaken oder in `NAECHSTE-SCHRITTE-GO-LIVE.md` übernehmen.*
