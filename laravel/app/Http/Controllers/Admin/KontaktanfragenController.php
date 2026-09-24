<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KontaktAnfrage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7G (Admin-Modul "Kontaktanfragen").
 *
 * Sechstes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (nach
 * Inhalte/Aktuelles/Termine/Downloads/Partner, Phasen 7B-7F). Bildet die
 * bereits seit Phase 6C produktiven Datensaetze aus App\Models\KontaktAnfrage
 * (Tabelle "kontakt_anfragen") ab - siehe App\Http\Controllers\
 * KontaktController (oeffentlich) fuer den unveraendert bleibenden
 * Schreibweg (Kontaktformular -> store() -> KontaktAnfrage::create() ->
 * KontaktAnfrageMail-Versandversuch). Dieses Modul liest/aendert
 * ausschliesslich bestehende Zeilen, es erzeugt nie selbst welche.
 *
 * BEWUSST KEIN NEUES TICKETSYSTEM/CRM (Auftrag): dies ist ein einfaches
 * Eingangs-Postfach fuer eingegangene Anfragen, keine Bearbeitungshistorie,
 * keine Zuweisung, keine Antwort-Mail-Funktion (der alte Admin kennt keine
 * davon, siehe admin.js-Analyse unten).
 *
 * ALT-ADMIN-ANALYSE (admin.js, Abschnitt "KONTAKTANFRAGEN" ab Kommentar
 * "09.09.2026, Kontaktformular ausfallsicher machen"): eigener Bereich mit
 * eigenen, alten PHP-Endpunkten (KA_ENDPOINT_LISTE = '/api/kontakt/admin/
 * liste.php', KA_ENDPOINT_STATUS = '/api/kontakt/admin/status.php') - beides
 * KEINE Laravel-Routen, siehe unten "SCHREIBWEG/API". Der alte Admin zeigt:
 * - Liste: Name, E-Mail, Anliegen (Betreff), Eingangsdatum, Status-Badge
 *   (🆕 Neu / ✅ Bearbeitet), sowie ein Warnzeichen falls die
 *   Benachrichtigungs-Mail nicht versendet werden konnte.
 * - Detail: per Klick auf die Zeile INLINE auf-/zugeklappt (kein eigener
 *   Seitenaufruf) - zeigt zusaetzlich Telefon, "bereits Jaeger/in" und
 *   Hegering (beide nur falls vorhanden), Bearbeitet-Datum (falls gesetzt)
 *   und die vollstaendige Nachricht, AUSSCHLIESSLICH ueber escHtml()
 *   dargestellt (nie roh per innerHTML - siehe Auftrag Punkt 8, hier 1:1
 *   uebernommen als "kein {!! !!} fuer Nachrichtentext").
 * - Status: einfacher Zwei-Wege-Umschalter zwischen 'neu' und 'bearbeitet'
 *   (Buttons "✅ Als bearbeitet markieren" / "↩️ Auf „Neu" zuruecksetzen").
 *   KEIN separates "gelesen/ungelesen"-Feld - "status" IST das einzige
 *   Statusfeld und uebernimmt funktional dieselbe Rolle.
 * - Loeschen: existiert im alten Admin AUSDRUECKLICH NICHT (Hinweistext dort
 *   woertlich: "Keine Anfrage kann hier gelöscht werden - erledigte
 *   Anfragen werden als „Bearbeitet" markiert.") - deshalb bietet auch
 *   dieses Modul KEIN Loeschen an (Auftrag: bestehendes Verhalten
 *   uebernehmen, keine neue Fachfunktion einfuehren). Eine Anfrage bleibt
 *   dauerhaft erhalten, "erledigt" wird ausschliesslich ueber den Status-
 *   Wechsel auf 'bearbeitet' abgebildet (siehe statusWechseln() unten). Die
 *   im urspruenglichen Auftrag genannte Beispielroute "DELETE /admin/
 *   kontaktanfragen/{kontaktAnfrage}" war lediglich ein Beispiel fuer den
 *   Fall, dass eine Loeschfunktion fachlich bereits existiert - keine
 *   Entscheidung, das Alt-Verhalten um eine neue Funktion zu erweitern
 *   (Nutzer-Korrektur).
 * - Archivieren/Wiederherstellen, Filter, Sortier-Optionen, Beantworten-
 *   Funktion: existieren im alten Admin nicht - werden hier folgerichtig
 *   NICHT eingefuehrt (Auftrag "keine neue Architektur/Funktion ungefragt").
 *
 * SCHREIBWEG/API (Analyse-Auftrag Punkt D): es gibt KEINEN Laravel-JSON-
 * Schreib-/Lese-Endpunkt fuer Kontaktanfragen (kein AdminListController::
 * kontaktanfragen(), kein content/kontaktanfragen.json) - die alten PHP-
 * Endpunkte oben sind die einzige bisherige "Admin-API" und werden von
 * diesem Modul nicht genutzt/ersetzt (sie bleiben bis zum vollstaendigen
 * Cutover unangetastet bestehen). Dieses Modul ist daher komplett eigen-
 * staendig, ohne eine gemeinsame *Updater-Klasse (anders als Partner/
 * Termine/Downloads) - es gibt keine mit einem zweiten Schreibweg zu
 * teilende Feld-Zuweisung, da es weder ein Anlegen- noch ein Bearbeiten-
 * Formular fuer die Absenderdaten gibt (siehe "KEINE BEARBEITEN-MASKE").
 *
 * CONTENTVERSIONING: bewusst NICHT verwendet. ContentVersioning schuetzt
 * einen GEMEINSAMEN Vollarray-Schreibweg (z.B. "die gesamte partner.json/
 * -Tabelle wird bei jedem Speichern neu geschrieben") vor gleichzeitigen,
 * sich gegenseitig ueberschreibenden Aenderungen an anderer Stelle.
 * Kontaktanfragen sind dagegen eigenstaendige, voneinander unabhaengige
 * Zeilen ohne konkurrierenden zweiten Schreibweg (kein Laravel-JSON-Pfad,
 * siehe oben) - ein Status-Wechsel oder Loeschen betrifft ausschliesslich
 * die eine betroffene Zeile per ID, es gibt nichts, das "veraltet" sein
 * koennte. ContentVersioning hier einzufuehren waere reine Gewohnheit ohne
 * fachlichen Nutzen (Auftrag Punkt D: "keinen Schreibweg kuenstlich
 * einfuehren, wenn keiner existiert").
 *
 * KEINE BEARBEITEN-MASKE fuer Absenderdaten (Auftrag ausdruecklich): eine
 * Kontaktanfrage ist eine eingegangene Nachricht, kein normaler Content-
 * Datensatz - Name/E-Mail/Telefon/Nachricht etc. werden nirgends editierbar
 * angeboten, nur angezeigt.
 *
 * STATUS ("gelesen/ungelesen", Auftrag): oeffnen der Detailseite (anzeigen())
 * aendert den Status NICHT automatisch (1:1 wie beim alten Admin, dessen
 * Auf-/Zuklappen ebenfalls keinen Seiteneffekt hat) - der Status wird
 * ausschliesslich ueber den expliziten Button/Route statusWechseln()
 * veraendert, exakt der zweite vom Auftrag erlaubte Weg ("explizite
 * Statusaenderung"). Methodennamen orientieren sich am tatsaechlichen
 * Datenmodell (status enum 'neu'/'bearbeitet'), nicht an einer erfundenen
 * "gelesen/ungelesen"-Terminologie, die es in der DB nicht gibt.
 *
 * KEIN LOESCHEN (Nutzer-Korrektur, siehe Klassenkommentar "ALT-ADMIN-
 * ANALYSE" oben): der alte Admin kennt bewusst keine Loeschfunktion fuer
 * Kontaktanfragen - eine Anfrage bleibt immer erhalten, "erledigt" wird
 * ausschliesslich ueber den Status abgebildet. Dieses Modul uebernimmt das
 * 1:1, ohne eine neue Fachfunktion einzufuehren.
 */
class KontaktanfragenController extends Controller
{
    public function index(): View
    {
        return view('admin.kontaktanfragen.index', [
            'anfragen' => KontaktAnfrage::orderByDesc('erstellt_am')->get(),
        ]);
    }

    public function anzeigen(KontaktAnfrage $kontaktAnfrage): View
    {
        return view('admin.kontaktanfragen.anzeigen', [
            'anfrage' => $kontaktAnfrage,
        ]);
    }

    /**
     * Siehe Klassenkommentar "STATUS": einfacher Zwei-Wege-Umschalter,
     * exakt wie admin.js' kontaktanfrageStatusSetzen() (dort per explizitem
     * Zielwert, hier - wie bei TermineController::archivToggle() bereits
     * etabliert - als Umkehrung des aktuellen Werts, da es nur die zwei
     * moeglichen Zustaende gibt). "bearbeitet_am" wird NUR beim Wechsel auf
     * 'bearbeitet' gesetzt (1:1 wie die alte api/kontakt/admin/status.php,
     * siehe KontaktAnfrage-Modellkommentar "bearbeitet_am ist NICHT
     * Eloquents automatisches updated_at") - beim Zurueckwechseln auf 'neu'
     * bleibt es bewusst stehen (historischer Wert "zuletzt bearbeitet am",
     * kein Loeschen von Information).
     */
    public function statusWechseln(KontaktAnfrage $kontaktAnfrage): RedirectResponse
    {
        if ($kontaktAnfrage->status === 'bearbeitet') {
            $kontaktAnfrage->status = 'neu';
        } else {
            $kontaktAnfrage->status = 'bearbeitet';
            $kontaktAnfrage->bearbeitet_am = now();
        }
        $kontaktAnfrage->save();

        return back()->with('status', $kontaktAnfrage->status === 'bearbeitet' ? 'Als bearbeitet markiert.' : 'Auf „Neu" zurückgesetzt.');
    }
}
