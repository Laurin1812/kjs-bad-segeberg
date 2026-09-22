<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * KJS Bad Segeberg - Phase 6B (Waffenboerse auf Laravel/MySQL).
 *
 * Serverseitige Validierung der oeffentlichen "Waffe anbieten"-Einreichung
 * - bildet dieselben Pflichtfelder/Grenzwerte ab wie die bisherige
 * clientseitige Validierung in waffenboerse/anbieten.html UND die
 * serverseitige Validierung in api/waffenboerse/anzeigen.php
 * (kjs_wb_handle_submit()), siehe dortige Kommentare fuer die
 * urspruengliche Begruendung jeder einzelnen Regel. Keine Annahme "kommt
 * schon vom eigenen Formular" - komplette Neuvalidierung, da dies eine
 * oeffentliche, unauthentifizierte Route ist.
 *
 * "versand_moeglich"/"erwerbsberechtigung_erforderlich" sind im PHP-
 * Original trotz clientseitigem "*" bewusst OHNE harten Server-Fehler
 * (Default: false, falls nicht mitgeschickt, siehe
 * kjs_wb_handle_submit()) - 1:1 uebernommen, keine neue, strengere Regel
 * erfinden, wo das Original keine hatte.
 *
 * "confirmCorrect"/"confirmContact" (Zustimmungs-Checkboxen) werden hier
 * zusaetzlich serverseitig als Pflichtfeld gefuehrt - im PHP-Original nur
 * clientseitig geprueft, aber fuer eine oeffentliche Route ist eine rein
 * clientseitige Zustimmungspruefung keine echte Absicherung (analog
 * HundeboerseAnbietenRequest).
 */
class WaffenboerseAnbietenRequest extends FormRequest
{
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
            'titel' => ['required', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],
            'kategorie' => ['required', 'string', 'exists:waffenboerse_kategorien,name'],
            'hersteller' => ['required', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],
            'modell' => ['nullable', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],

            // Bis zu 20 Kaliber-Zeilen, je Zeile max. 100 Zeichen, keine
            // Zeilenumbrueche innerhalb einer Zeile (siehe
            // kjs_wb_handle_submit()).
            'kaliber' => ['nullable', 'array', 'max:20'],
            'kaliber.*' => ['nullable', 'string', 'max:100', 'regex:/^[^\r\n]*$/'],

            'zustand' => ['required', 'string', 'in:neu,gebraucht'],

            'preis_typ' => ['required', 'string', 'in:festpreis,vb'],
            'preis' => ['required', 'regex:/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+([.,]\d{1,2})?$/'],

            'versand_moeglich' => ['nullable', 'boolean'],
            'versandkosten' => ['nullable', 'string', 'max:40'],

            'plz' => ['required', 'regex:/^\d{5}$/'],
            'ort' => ['required', 'string', 'max:190'],

            'erwerbsberechtigung_erforderlich' => ['nullable', 'boolean'],

            'beschreibung' => ['required', 'string', 'max:20000'],

            'anbieter_name' => ['required', 'string', 'max:190', 'regex:/^[^\r\n]*$/'],
            'anbieter_email' => ['required', 'email', 'max:190'],
            'anbieter_telefon' => ['nullable', 'string', 'max:60'],

            // Bis zu 10 Bilder, jeweils max. 8 MB - exakt dieselben Grenzen
            // wie im PHP-Original (kjs_boerse_handle_image_uploads(...,
            // 'waffenboerse', 10, 8*1024*1024)), nicht geraten.
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            'confirmCorrect' => ['accepted'],
            'confirmContact' => ['accepted'],

            // Honeypot: fuer Menschen unsichtbares Feld (siehe
            // waffenboerse/anbieten.html) - muss leer bleiben. Siehe
            // HundeboerseAnbietenRequest::rules() fuer die ausfuehrliche
            // Begruendung, warum dies kein hartes "prohibited" ist.
            '_honey' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'titel.required' => 'Bitte geben Sie einen Titel für die Anzeige an.',
            'kategorie.required' => 'Bitte wählen Sie eine Kategorie aus.',
            'kategorie.exists' => 'Bitte wählen Sie eine gültige Kategorie aus.',
            'hersteller.required' => 'Bitte geben Sie den Hersteller an.',
            'zustand.required' => 'Bitte wählen Sie den Zustand aus.',
            'preis_typ.required' => 'Bitte wählen Sie eine Preisart aus.',
            'preis.required' => 'Bitte geben Sie einen gültigen Preis an.',
            'preis.regex' => 'Bitte geben Sie einen gültigen Preis an.',
            'plz.required' => 'Bitte geben Sie eine gültige 5-stellige PLZ an.',
            'plz.regex' => 'Bitte geben Sie eine gültige 5-stellige PLZ an.',
            'ort.required' => 'Bitte geben Sie den Ort an.',
            'beschreibung.required' => 'Bitte geben Sie eine Beschreibung an.',
            'anbieter_name.required' => 'Bitte geben Sie Ihren Namen an.',
            'anbieter_email.required' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'anbieter_email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'images.required' => 'Bitte laden Sie mindestens ein Bild hoch.',
            'images.min' => 'Bitte laden Sie mindestens ein Bild hoch.',
            'images.max' => 'Es können maximal 10 Bilder hochgeladen werden.',
            'images.*.mimes' => 'Nicht unterstütztes Bildformat - erlaubt sind JPG, PNG und WebP.',
            'images.*.max' => 'Ein Bild ist zu groß (maximal 8 MB pro Bild).',
            'confirmCorrect.accepted' => 'Bitte bestätigen Sie diese Angabe.',
            'confirmContact.accepted' => 'Bitte stimmen Sie der Verwendung Ihrer Kontaktdaten zu.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Zusaetzliche Plausibilitaetspruefung analog getimagesize() in
        // api/lib/boerse_upload.php - siehe HundeboerseAnbietenRequest::
        // withValidator() fuer die ausfuehrliche Begruendung.
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->file('images', []) as $image) {
                if ($image && $image->isValid() && @getimagesize($image->getRealPath()) === false) {
                    $validator->errors()->add('images', 'Eine Datei konnte nicht als Bild gelesen werden.');
                    break;
                }
            }
        });
    }
}
