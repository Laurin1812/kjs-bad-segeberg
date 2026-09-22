<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * KJS Bad Segeberg - Phase 6A (Sondermodule inventarisieren + Hundeboerse
 * auf Laravel/MySQL).
 *
 * Serverseitige Validierung der oeffentlichen "Hund / Wurf anbieten"-
 * Einreichung - bildet dieselben Pflichtfelder/Grenzwerte ab wie die
 * bisherige clientseitige Validierung in hundeboerse/anbieten.html
 * (validate()) UND die serverseitige Validierung in
 * api/hundeboerse/anzeigen.php (POST-Zweig), siehe dortige Kommentare fuer
 * die urspruengliche Begruendung jeder einzelnen Regel. Keine Annahme
 * "kommt schon vom eigenen Formular" - komplette Neuvalidierung, da dies
 * eine oeffentliche, unauthentifizierte Route ist.
 *
 * "confirmCorrect"/"confirmPrivacy" (Zustimmungs-Checkboxen) werden hier
 * zusaetzlich serverseitig als Pflichtfeld gefuehrt - im PHP-Original nur
 * clientseitig geprueft, aber fuer eine oeffentliche Route ist eine rein
 * clientseitige Zustimmungspruefung keine echte Absicherung.
 */
class HundeboerseAnbietenRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:single,litter'],
            'title' => ['required', 'string', 'max:190'],
            'breed' => ['required', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'max:190'],
            'coat' => ['nullable', 'string', 'max:190'],

            'dogName' => ['nullable', 'string', 'max:190'],
            'birthDate' => ['required_if:type,single', 'nullable', 'date'],
            'gender' => ['required_if:type,single', 'nullable', 'string', 'in:male,female'],

            'litterDate' => ['required_if:type,litter', 'nullable', 'date'],
            'maleCount' => ['nullable', 'integer', 'min:0'],
            'femaleCount' => ['nullable', 'integer', 'min:0'],

            'priceType' => ['required', 'string', 'in:fixed,negotiable,on_request,none'],
            'price' => ['nullable', 'numeric', 'min:0'],

            'postalCode' => ['required', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:190'],

            'huntingTests' => ['nullable', 'string', 'max:190'],
            'trainingLevel' => ['nullable', 'string', 'max:5000'],

            'father' => ['nullable', 'string', 'max:190'],
            'fatherTests' => ['nullable', 'string', 'max:190'],
            'mother' => ['nullable', 'string', 'max:190'],
            'motherTests' => ['nullable', 'string', 'max:190'],
            'hasZuchtverband' => ['nullable', 'boolean'],
            'zuchtverband' => ['nullable', 'string', 'max:190'],

            'description' => ['required', 'string', 'max:5000'],

            'providerName' => ['required', 'string', 'max:190'],
            'contactPerson' => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'contactNotes' => ['nullable', 'string', 'max:5000'],

            // Bis zu 10 Bilder, jeweils max. 8 MB, MIME-Typ wird von Laravel
            // anhand des tatsaechlichen Dateiinhalts geprueft (nicht des
            // Client-Headers) - siehe api/lib/boerse_upload.php-Vorbild.
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            'confirmCorrect' => ['accepted'],
            'confirmPrivacy' => ['accepted'],

            // Honeypot: fuer Menschen unsichtbares Feld (siehe
            // hundeboerse/anbieten.html) - muss leer bleiben. Kein "prohibited",
            // weil ein ausgefuelltes Feld nicht als harter Validierungsfehler
            // gemeldet werden soll (das wuerde Bots verraten, WARUM es
            // fehlschlaegt) - stattdessen prueft der Controller dieses Feld
            // separat und tut bei Befuellung nur so, als waere alles
            // erfolgreich (siehe HundeboerseController::store()).
            '_honey' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Bitte geben Sie die Art der Anzeige an.',
            'title.required' => 'Bitte geben Sie einen Titel für die Anzeige an.',
            'breed.required' => 'Bitte geben Sie die Rasse an.',
            'birthDate.required_if' => 'Bitte geben Sie das Geburtsdatum an.',
            'gender.required_if' => 'Bitte geben Sie das Geschlecht an.',
            'litterDate.required_if' => 'Bitte geben Sie das Wurfdatum an.',
            'postalCode.required' => 'Bitte geben Sie eine gültige 5-stellige PLZ an.',
            'postalCode.regex' => 'Bitte geben Sie eine gültige 5-stellige PLZ an.',
            'city.required' => 'Bitte geben Sie den Ort an.',
            'description.required' => 'Bitte geben Sie eine Beschreibung an.',
            'providerName.required' => 'Bitte geben Sie den Namen des Anbieters an.',
            'email.required' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'images.required' => 'Bitte laden Sie mindestens ein Bild hoch.',
            'images.min' => 'Bitte laden Sie mindestens ein Bild hoch.',
            'images.max' => 'Es können maximal 10 Bilder hochgeladen werden.',
            'images.*.mimes' => 'Nicht unterstütztes Bildformat - erlaubt sind JPG, PNG und WebP.',
            'images.*.max' => 'Ein Bild ist zu groß (maximal 8 MB pro Bild).',
            'confirmCorrect.accepted' => 'Bitte bestätigen Sie die Richtigkeit Ihrer Angaben.',
            'confirmPrivacy.accepted' => 'Bitte stimmen Sie der Datenverarbeitung zu.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Zusaetzliche Plausibilitaetspruefung analog getimagesize() in
        // api/lib/boerse_upload.php: eine Datei, die die MIME-Pruefung
        // besteht, aber sich nicht als Bild dekodieren laesst, faellt hier
        // durch - BoerseUploads::store() macht dieselbe Pruefung ohnehin
        // nochmal, aber ein Fehlschlag soll dem Einreicher als echter
        // Validierungsfehler angezeigt werden, nicht als stillschweigend
        // uebersprungenes Bild.
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
