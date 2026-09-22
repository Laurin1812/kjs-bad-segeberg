<?php

namespace App\Http\Controllers;

use App\Http\Requests\KontaktRequest;
use App\Mail\KontaktAnfrageMail;
use App\Models\Hegering;
use App\Models\KontaktAnfrage;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * KJS Bad Segeberg - Phase 4 Abschluss-Nacharbeit ("Kontakt-Route") +
 * Phase 6C (Kontaktformular vollstaendig auf Laravel).
 *
 * Ersetzt kontakt/index.html vollstaendig. Die Navigation (settings-Gruppe
 * "navigation", siehe App\Support\Navigation) verweist bereits seit Phase 4
 * auf "/kontakt" (und mehrere bereits migrierte Seiten verlinken ebenfalls
 * dorthin, z.B. pages/show.blade.php "Kontakt aufnehmen"-Buttons).
 *
 * kontakt/index.html im alten Webroot bestand aus zwei Teilen: einer
 * informativen Kontaktspalte (Adresse/Telefon/E-Mail/Postadresse/
 * Sprechzeiten aus content/einstellungen.json, seit Phase 4 durch show()
 * unten abgedeckt - EINSCHLIESSLICH des datenschutzfreundlichen Google-
 * Maps-Zwei-Klick-Embeds, siehe kontakt.blade.php-Kommentar dort; das war
 * beim ersten 6C-Durchgang versehentlich uebersehen worden und wurde in
 * einem Nachtrag ergaenzt) UND einem echten Kontaktformular (POST an
 * api/contact.php, Honeypot, Hegering-Auswahl, siehe Alt-System-Analyse in
 * der Phase-6C-Aufgabenstellung). Seit Phase 6C ist auch das Formular
 * Teil dieser Route (show() liefert das Formular mit aus, store() nimmt
 * die POST-Einreichung entgegen) - die alte PHP-/JSON-Laufzeit
 * (api/contact.php, api/kontakt/*.php) wird fuer NEUE Einreichungen nicht
 * mehr gebraucht. Admin-Verwaltung der Anfragen (bisher
 * api/kontakt/admin/liste.php + status.php: Liste/Status aendern, KEIN
 * Loeschen) ist weiterhin NICHT Teil dieser Route/Phase - siehe
 * app/Models/KontaktAnfrage.php-Kommentar. Fehlt ein Settings-Feld in der
 * DB, wird es in der View schlicht weggelassen statt eines erfundenen
 * Platzhalters. Der "Zur Seite des Kreisjägermeisters"-Link aus derselben
 * alten Spalte bleibt bewusst aussen vor - er verlinkt auf eine eigene,
 * bereits seit Phase 2 existierende Laravel-Seite (route('kreisjaegermeister'))
 * und ist reine Seiten-Navigation, kein Kontaktdaten-Baustein wie die Karte.
 */
class KontaktController extends Controller
{
    public function show(): View
    {
        $einstellungen = Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');

        $oeffnungszeitenRoh = $einstellungen['oeffnungszeiten'] ?? null;
        $oeffnungszeiten = is_string($oeffnungszeitenRoh) && $oeffnungszeitenRoh !== ''
            ? (json_decode($oeffnungszeitenRoh, true) ?: [])
            : [];

        return view('kontakt', [
            'ueberschrift' => (string) ($einstellungen['kontakt_ueberschrift'] ?? ''),
            'text' => (string) ($einstellungen['kontakt_text'] ?? ''),
            'adresse' => (string) ($einstellungen['adresse'] ?? ''),
            'telefon' => (string) ($einstellungen['telefon'] ?? ''),
            'email' => (string) ($einstellungen['email'] ?? ''),
            'postadresse' => (string) ($einstellungen['postadresse'] ?? ''),
            'postadresseTelefon' => (string) ($einstellungen['postadresse_telefon'] ?? ''),
            'postadresseEmail' => (string) ($einstellungen['postadresse_email'] ?? ''),
            'oeffnungszeiten' => is_array($oeffnungszeiten) ? $oeffnungszeiten : [],
            // Fuer das Hegering-<select> im Formular unten - dieselbe
            // bereits bestehende Eloquent-Datenquelle wie
            // HegeringeController::index() (jaeger/hegeringe.html-
            // Nachbau), statt des alten client-seitigen
            // fetch('../api/content/hegeringe.json') - keine neue
            // Architektur, nur Wiederverwendung des schon vorhandenen
            // Hegering-Models.
            'hegeringe' => Hegering::orderBy('sortierung')->get(),
            'betreffOptionen' => KontaktRequest::ALLOWED_BETREFF,
        ]);
    }

    /**
     * Nimmt die oeffentliche Kontaktformular-Einreichung entgegen. Ersetzt
     * api/contact.php fachlich 1:1 - siehe dortige Analyse fuer die
     * urspruengliche Reihenfolge/Begruendung:
     *
     * 1. Honeypot zuerst (vor jeglicher DB-/Mail-Arbeit) - bei Treffer eine
     *    stille "Erfolg"-Weiterleitung, exakt wie im PHP-Original (HTTP
     *    200 success:true, nichts gespeichert/verschickt). Kein
     *    Unterschied fuer einen Bot zwischen "abgelehnt" und "angenommen"
     *    erkennbar.
     * 2. Datensatz IMMER ZUERST speichern (status "neu", wie im
     *    PHP-Original hartkodiert) - der Mailversand-Versuch darf den
     *    bereits erfolgreichen DB-Save niemals gefaehrden. Siehe
     *    Auftrag Punkt 3: "Mail-Fehler duerfen nicht dazu fuehren, dass
     *    die Anfrage lautlos komplett verloren geht."
     * 3. Danach isoliert (try/catch) der Mailversand-Versuch - Erfolg/
     *    Misserfolg wird auf demselben Datensatz vermerkt
     *    (mail_versendet/mail_fehler), genau wie
     *    kjs_contact_mark_mail_status() im PHP-Original. Eine
     *    Transport-Exception hier darf niemals nach aussen durchschlagen.
     * 4. PRG-Redirect zurueck auf /kontakt mit Erfolgsmeldung - dadurch
     *    erzeugt ein Browser-Neuladen/Zurueck nach dem Redirect keine
     *    doppelte Anfrage (der GET danach loest keinen erneuten POST/
     *    INSERT mehr aus).
     */
    public function store(KontaktRequest $request): RedirectResponse
    {
        $daten = $request->validated();

        if (trim((string) ($daten['_honey'] ?? '')) !== '') {
            return redirect()->route('kontakt')->with('kontakt_success', true);
        }

        $vollerName = trim($daten['vorname'].' '.$daten['nachname']);

        $anfrage = KontaktAnfrage::create([
            'name' => $vollerName,
            'email' => $daten['email'],
            'telefon' => (string) ($daten['telefon'] ?? ''),
            'anliegen' => $daten['betreff'],
            'bereits_jaeger' => (string) ($daten['bereits_jaeger'] ?? ''),
            'hegering' => (string) ($daten['hegering'] ?? ''),
            'nachricht' => $daten['nachricht'],
            'status' => 'neu',
        ]);

        $this->mailVersuchen($anfrage);

        return redirect()->route('kontakt')->with('kontakt_success', true);
    }

    /**
     * Isolierter Mailversand-Versuch - siehe store()-Kommentar Punkt 3.
     * Entspricht kjs_contact_mail_versuchen() im PHP-Original: kein
     * konfigurierter Empfaenger -> "server_not_configured" (kein Fehler,
     * die Anfrage bleibt trotzdem gespeichert), eine Transport-Exception
     * beim Versand -> "send_failed" (geloggt, nicht nach aussen
     * durchgereicht). Das Aktualisieren von mail_versendet/mail_fehler ist
     * bewusst in dasselbe try/catch eingeschlossen wie der Versand selbst
     * - schlaegt auch dieses Nachtragen fehl, ist das nur ein
     * Protokollierungsdetail und darf den bereits erfolgreichen
     * Primaer-Save (store() oben) nicht mehr gefaehrden.
     */
    private function mailVersuchen(KontaktAnfrage $anfrage): void
    {
        $empfaenger = config('mail.contact_recipient');

        if (! $empfaenger) {
            try {
                $anfrage->mail_versendet = false;
                $anfrage->mail_fehler = 'server_not_configured';
                $anfrage->save();
            } catch (Throwable $e) {
                Log::error('Kontaktanfrage #'.$anfrage->id.': Mail-Status "server_not_configured" konnte nicht gespeichert werden.', ['exception' => $e]);
            }

            return;
        }

        try {
            Mail::to($empfaenger)->send(new KontaktAnfrageMail($anfrage));

            $anfrage->mail_versendet = true;
            $anfrage->mail_fehler = null;
            $anfrage->save();
        } catch (Throwable $e) {
            Log::error('Kontaktanfrage #'.$anfrage->id.': Mailversand fehlgeschlagen.', ['exception' => $e]);

            try {
                $anfrage->mail_versendet = false;
                $anfrage->mail_fehler = 'send_failed';
                $anfrage->save();
            } catch (Throwable $inner) {
                Log::error('Kontaktanfrage #'.$anfrage->id.': Mail-Status "send_failed" konnte nicht gespeichert werden.', ['exception' => $inner]);
            }
        }
    }
}
