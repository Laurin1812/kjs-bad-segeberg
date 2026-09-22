<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * KJS Bad Segeberg - Phase 6C (Kontaktformular vollstaendig auf Laravel).
 *
 * Serverseitige Validierung der oeffentlichen Kontaktformular-Einreichung -
 * bildet dieselben Pflichtfelder/Grenzwerte ab wie die bisherige
 * clientseitige Validierung in kontakt/index.html UND die serverseitige
 * Validierung in api/contact.php, siehe dortige Kommentare/Analyse fuer die
 * urspruengliche Begruendung jeder einzelnen Regel. Keine Annahme "kommt
 * schon vom eigenen Formular" - komplette Neuvalidierung, da dies eine
 * oeffentliche, unauthentifizierte Route ist.
 *
 * "bereits_jaeger"/"hegering"/"telefon" sind im HTML-Formular teils als
 * "required" markiert (die beiden Radio-Buttons von "bereits_jaeger"),
 * aber im PHP-Original (api/contact.php) OHNE harte serverseitige Pflicht -
 * dort werden nur vorname/nachname/email/betreff/nachricht tatsaechlich
 * validiert, alle anderen Felder nur laengenbegrenzt. 1:1 uebernommen,
 * keine neue, strengere Regel erfinden, wo das Original keine hatte (siehe
 * analoge Begruendung in WaffenboerseAnbietenRequest fuer
 * "versand_moeglich"/"erwerbsberechtigung_erforderlich").
 *
 * "telefon" ist hier bewusst auf max:60 statt der alten PHP-Grenze von 190
 * Zeichen begrenzt - nicht erfunden, sondern schlicht die tatsaechliche
 * Spaltenbreite der bestehenden "kontakt_anfragen"-Tabelle (siehe Migration
 * 2026_09_14_000120_create_kontakt_anfragen_table.php: telefon
 * string(60)), die bereits seit Phase 1 so angelegt ist und fuer Phase 6C
 * nicht geaendert wird.
 *
 * "datenschutz" (Zustimmungs-Checkbox) wird hier zusaetzlich serverseitig
 * als Pflichtfeld gefuehrt - im PHP-Original/HTML nur clientseitig "required"
 * (kein serverseitiger Check in api/contact.php), aber fuer eine
 * oeffentliche Route ist eine rein clientseitige Zustimmungspruefung keine
 * echte Absicherung (analog HundeboerseAnbietenRequest/
 * WaffenboerseAnbietenRequest fuer "confirmCorrect"/"confirmContact").
 *
 * "vorname"/"nachname" bekommen zusaetzlich die Zeilenumbruch-Injection-
 * Pruefung aus api/contact.php (kjs_has_line_breaks()) - dort zusaetzlich
 * auch auf "betreff" angewendet, was hier durch die feste "in:"-Liste
 * ohnehin schon ausgeschlossen ist.
 */
class KontaktRequest extends FormRequest
{
    /**
     * Exakt dieselben 11 Werte wie api/contact.php ($allowedBetreff) UND
     * kontakt/index.html (<select id="betreff">, Options-TEXT ist der
     * Wert) - nicht neu erfinden, nicht ergaenzen/kuerzen.
     *
     * @var list<string>
     */
    public const ALLOWED_BETREFF = [
        'Allgemeine Anfrage',
        'Mitgliedschaft',
        'Jägerausbildung (Jagdschein)',
        'Schießwesen',
        'Hundeausbildung',
        'Jagdhornblasen',
        'Naturschutz',
        'Jungwildrettung',
        'Infomobil',
        'Pressenanfrage',
        'Sonstiges',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vorname' => ['required', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],
            'nachname' => ['required', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],
            'email' => ['required', 'email', 'max:190'],
            'telefon' => ['nullable', 'string', 'max:60'],

            // Siehe Klassenkommentar: im PHP-Original ohne harte Pflicht,
            // trotz clientseitigem "required" auf den Radio-Buttons.
            'bereits_jaeger' => ['nullable', 'string', 'max:20'],
            'hegering' => ['nullable', 'string', 'max:190'],

            'betreff' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_BETREFF)],
            'nachricht' => ['required', 'string', 'max:5000'],

            'datenschutz' => ['accepted'],

            // Honeypot: fuer Menschen unsichtbares Feld (siehe
            // kontakt/index.html, Feldname "_honey") - muss leer bleiben.
            // Siehe HundeboerseAnbietenRequest fuer die ausfuehrliche
            // Begruendung, warum dies kein hartes "prohibited" ist (der
            // Honeypot-Check selbst passiert bewusst im Controller, nicht
            // hier - ein Bot soll keinen Unterschied zwischen "Validierung
            // fehlgeschlagen" und "als Spam verworfen" erkennen koennen).
            '_honey' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vorname.required' => 'Bitte geben Sie Ihren Vornamen an.',
            'nachname.required' => 'Bitte geben Sie Ihren Nachnamen an.',
            'email.required' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'betreff.required' => 'Bitte wählen Sie ein Anliegen aus.',
            'betreff.in' => 'Bitte wählen Sie ein gültiges Anliegen aus.',
            'nachricht.required' => 'Bitte geben Sie eine Nachricht ein.',
            'nachricht.max' => 'Ihre Nachricht ist zu lang (maximal 5000 Zeichen).',
            'datenschutz.accepted' => 'Bitte stimmen Sie der Datenschutzerklärung zu.',
        ];
    }
}
