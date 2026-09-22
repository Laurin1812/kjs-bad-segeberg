{{--
    Phase 6B (Waffenboerse auf Laravel/MySQL): ersetzt waffenboerse/
    anbieten.html - anders als bei der Hundeboerse (Phase 6A) war die
    Waffenboerse-Einreichung im Alt-System bereits ein VOLLSTAENDIG
    fertiger, echter Endpunkt (siehe api/waffenboerse/anzeigen.php::
    kjs_wb_handle_submit()) - hier wird also ein bereits produktiv
    vorgesehenes Formular migriert, nicht erstmals fertiggestellt.

    Bewusst vereinfacht gegenueber dem bisherigen clientseitigen Formular:
    ein echtes natives <form method="POST" enctype="multipart/form-data">
    statt eines per JS abgefangenen fetch()-Submits mit eigenem FormData-
    Aufbau (dieselbe Vereinfachung wie bei der Hundeboerse, siehe
    HundeboerseController-Klassenkommentar) - WaffenboerseAnbietenRequest
    liefert bei Fehlern einen echten Redirect zurueck mit @error()/old().
    Die bisherige Bild-Drag&Drop-Neusortierung/Live-Vorschau entfaellt
    dadurch (FileList ist unveraenderlich). Der Kaliber-Zeilen-Editor
    (Hinzufuegen/Entfernen einfacher Textfelder "kaliber[]") bleibt dagegen
    erhalten - anders als die Bild-Neuordnung ist er NICHT an das
    JS-Submit-Modell gekoppelt und funktioniert unveraendert mit einem
    nativen Formular-POST.

    Eigene "wba-"-CSS-Klassen statt der Hundeboerse-Formular-Klassen
    ("hb-form-section" etc.) - siehe css/style.css-Kommentar zur bewussten
    Entkopplung ("Hundeboerse darf laut Auftrag nicht angefasst werden").
--}}
<x-layouts.app title="Waffe anbieten">
    <x-page-hero title="Waffe anbieten" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Waffenbörse', 'href' => route('waffenboerse.index')],
        ['label' => 'Anbieten'],
    ]" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content">
                @if (session('wb_success'))
                    <div class="wba-success" style="display:block;">
                        <h2>Vielen Dank!</h2>
                        <p>Ihre Anzeige wurde erfolgreich zur Prüfung eingereicht. Sie ist noch nicht öffentlich sichtbar und wird nach Prüfung durch die Kreisjägerschaft freigegeben oder bei Rückfragen entsprechend bearbeitet.</p>
                        <div class="wba-success__actions">
                            <a href="{{ route('waffenboerse.index') }}" class="btn btn-outline-green">← Zurück zur Waffenbörse</a>
                        </div>
                    </div>
                @else
                    <p style="font-size:1.05rem;color:var(--text-body);line-height:1.8;margin-bottom:2rem;">
                        Füllen Sie das Formular aus, um eine Waffe, Optik oder Zubehör anzubieten. Ihre Anzeige wird vor der
                        Veröffentlichung durch die Kreisjägerschaft geprüft.
                    </p>

                    @if ($errors->any())
                        <div class="wba-error-summary" style="display:block;">
                            <strong>Bitte prüfen Sie folgende Angaben:</strong>
                            <ul>
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('waffenboerse.anbieten.store') }}" enctype="multipart/form-data" id="wbaForm">
                        @csrf

                        <div class="kontakt-form wba-form-section">
                            <h3>1. Grunddaten</h3>
                            <div class="form-group {{ $errors->has('titel') ? 'wba-field-error' : '' }}">
                                <label for="wba-titel">Titel *</label>
                                <input type="text" id="wba-titel" name="titel" value="{{ old('titel') }}" placeholder="z. B. Blaser Bockbüchsflinte BBF 95">
                                @error('titel')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-group {{ $errors->has('kategorie') ? 'wba-field-error' : '' }}">
                                <label for="wba-kategorie">Kategorie *</label>
                                <select id="wba-kategorie" name="kategorie">
                                    <option value="">Bitte wählen …</option>
                                    @foreach ($kategorien as $kat)
                                        <option value="{{ $kat }}" {{ old('kategorie') === $kat ? 'selected' : '' }}>{{ $kat }}</option>
                                    @endforeach
                                </select>
                                @error('kategorie')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('hersteller') ? 'wba-field-error' : '' }}">
                                    <label for="wba-hersteller">Hersteller *</label>
                                    <input type="text" id="wba-hersteller" name="hersteller" value="{{ old('hersteller') }}" placeholder="z. B. Blaser">
                                    @error('hersteller')<p class="wba-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="wba-modell">Modell</label>
                                    <input type="text" id="wba-modell" name="modell" value="{{ old('modell') }}" placeholder="z. B. BBF 95">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Kaliber</label>
                                <div class="wba-kaliber-list" id="wbaKaliberList">
                                    @php $altesKaliber = old('kaliber', ['']); @endphp
                                    @foreach ($altesKaliber as $k)
                                        <div class="wba-kaliber-row">
                                            <input type="text" class="wba-kaliber-input" name="kaliber[]" value="{{ $k }}" placeholder="z. B. 7x65R">
                                            <button type="button" class="wba-kaliber-remove" aria-label="Kaliber entfernen">✕</button>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="wba-kaliber-add" id="wbaKaliberAdd">+ Weiteres Kaliber</button>
                                <p class="wba-section-hint" style="margin-top:.5rem;margin-bottom:0;">Bitte je Kaliber ein eigenes Feld verwenden, z.&nbsp;B. „7x65R“, „12/70“, „5,6x52R“ (nicht durch Komma trennen, da Kaliber selbst Kommas enthalten können).</p>
                            </div>
                            <div class="form-group {{ $errors->has('zustand') ? 'wba-field-error' : '' }}">
                                <label for="wba-zustand">Zustand *</label>
                                <select id="wba-zustand" name="zustand">
                                    <option value="">Bitte wählen …</option>
                                    <option value="neu" {{ old('zustand') === 'neu' ? 'selected' : '' }}>Neu</option>
                                    <option value="gebraucht" {{ old('zustand') === 'gebraucht' ? 'selected' : '' }}>Gebraucht</option>
                                </select>
                                @error('zustand')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>2. Preis &amp; Versand</h3>
                            <div class="form-group {{ $errors->has('preis_typ') ? 'wba-field-error' : '' }}">
                                <label for="wba-preis_typ">Preisart *</label>
                                <select id="wba-preis_typ" name="preis_typ">
                                    <option value="">Bitte wählen …</option>
                                    <option value="festpreis" {{ old('preis_typ') === 'festpreis' ? 'selected' : '' }}>Festpreis</option>
                                    <option value="vb" {{ old('preis_typ') === 'vb' ? 'selected' : '' }}>Verhandlungsbasis (VB)</option>
                                </select>
                                @error('preis_typ')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-group {{ $errors->has('preis') ? 'wba-field-error' : '' }}">
                                <label for="wba-preis">Preis (€) *</label>
                                <input type="text" id="wba-preis" name="preis" value="{{ old('preis') }}" inputmode="decimal" placeholder="z. B. 2.500,00">
                                @error('preis')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-group">
                                <label>Versand möglich?</label>
                                <div class="wba-toggle" id="wbaVersandToggle">
                                    <label class="wba-toggle-option" id="wbaVersandOptionJa">
                                        <input type="radio" name="versand_moeglich" value="1" id="wba-versand-ja" {{ old('versand_moeglich') === '1' ? 'checked' : '' }}>
                                        Ja
                                    </label>
                                    <label class="wba-toggle-option" id="wbaVersandOptionNein">
                                        <input type="radio" name="versand_moeglich" value="0" id="wba-versand-nein" {{ old('versand_moeglich') === '0' ? 'checked' : '' }}>
                                        Nein
                                    </label>
                                </div>
                            </div>
                            <div class="form-group" id="wba-versandkosten-wrap" style="display:none;">
                                <label for="wba-versandkosten">Versandkosten</label>
                                <input type="text" id="wba-versandkosten" name="versandkosten" value="{{ old('versandkosten') }}" placeholder="z. B. 15,80">
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>3. Standort</h3>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('plz') ? 'wba-field-error' : '' }}">
                                    <label for="wba-plz">PLZ *</label>
                                    <input type="text" id="wba-plz" name="plz" value="{{ old('plz') }}" inputmode="numeric" placeholder="z. B. 24629">
                                    @error('plz')<p class="wba-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group {{ $errors->has('ort') ? 'wba-field-error' : '' }}">
                                    <label for="wba-ort">Ort *</label>
                                    <input type="text" id="wba-ort" name="ort" value="{{ old('ort') }}" placeholder="z. B. Kisdorf">
                                    @error('ort')<p class="wba-error-text">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>4. Erwerbsberechtigung</h3>
                            <div class="form-group">
                                <label>Erwerbsberechtigung erforderlich?</label>
                                <div class="wba-toggle" id="wbaErwerbToggle">
                                    <label class="wba-toggle-option" id="wbaErwerbOptionJa">
                                        <input type="radio" name="erwerbsberechtigung_erforderlich" value="1" id="wba-erwerb-ja" {{ old('erwerbsberechtigung_erforderlich') === '1' ? 'checked' : '' }}>
                                        Ja
                                    </label>
                                    <label class="wba-toggle-option" id="wbaErwerbOptionNein">
                                        <input type="radio" name="erwerbsberechtigung_erforderlich" value="0" id="wba-erwerb-nein" {{ old('erwerbsberechtigung_erforderlich') === '0' ? 'checked' : '' }}>
                                        Nein
                                    </label>
                                </div>
                                <p class="wba-section-hint" style="margin-top:.5rem;margin-bottom:0;">Bitte wählen Sie „Ja“, wenn für den angebotenen Gegenstand eine gesetzliche Erwerbsberechtigung erforderlich ist.</p>
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>5. Beschreibung *</h3>
                            <div class="form-group {{ $errors->has('beschreibung') ? 'wba-field-error' : '' }}">
                                <label for="wba-beschreibung">Beschreibungstext</label>
                                <textarea id="wba-beschreibung" name="beschreibung" style="min-height:180px;" placeholder="Beschreiben Sie den Zustand, Ausstattung, Zubehör, Grund des Verkaufs …">{{ old('beschreibung') }}</textarea>
                                @error('beschreibung')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section {{ $errors->has('images') ? 'wba-field-error' : '' }}" id="wbaGroup-bilder">
                            <h3>6. Bilder *</h3>
                            <p class="wba-section-hint">Mindestens 1, maximal 10 Bilder. Mehrere Bilder können gleichzeitig ausgewählt werden. Erlaubte Formate: JPG, PNG, WebP (max. 8&nbsp;MB je Bild).</p>
                            <label class="wba-img-add-label" for="wbaImagesInput">Bilder auswählen</label>
                            <input type="file" id="wbaImagesInput" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
                            <p class="wba-img-count" id="wbaImgCount">0 / 10 Bilder ausgewählt</p>
                            @error('images')<p class="wba-error-text">{{ $message }}</p>@enderror
                            @error('images.*')<p class="wba-error-text">{{ $message }}</p>@enderror
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>7. Kontaktdaten</h3>
                            <div class="form-group {{ $errors->has('anbieter_name') ? 'wba-field-error' : '' }}">
                                <label for="wba-anbieter_name">Name *</label>
                                <input type="text" id="wba-anbieter_name" name="anbieter_name" value="{{ old('anbieter_name') }}" placeholder="Vor- und Nachname">
                                @error('anbieter_name')<p class="wba-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('anbieter_email') ? 'wba-field-error' : '' }}">
                                    <label for="wba-anbieter_email">E-Mail *</label>
                                    <input type="email" id="wba-anbieter_email" name="anbieter_email" value="{{ old('anbieter_email') }}" placeholder="max@beispiel.de">
                                    @error('anbieter_email')<p class="wba-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="wba-anbieter_telefon">Telefon/Mobil <span class="wba-optional-tag">(optional)</span></label>
                                    <input type="tel" id="wba-anbieter_telefon" name="anbieter_telefon" value="{{ old('anbieter_telefon') }}" placeholder="z. B. 0172 1234567">
                                </div>
                            </div>
                        </div>

                        <div class="kontakt-form wba-form-section">
                            <h3>8. Zustimmung &amp; Absenden</h3>
                            <div class="form-group {{ $errors->has('confirmCorrect') ? 'wba-field-error' : '' }}" style="display:flex; align-items:flex-start; gap:.75rem;">
                                <input type="checkbox" id="wba-confirmCorrect" name="confirmCorrect" value="1" style="width:auto; margin-top:.2rem;" {{ old('confirmCorrect') ? 'checked' : '' }}>
                                <label for="wba-confirmCorrect" style="font-size:.88rem; font-weight:400; color:var(--text-dark);">
                                    Ich bestätige, dass meine Angaben vollständig und wahrheitsgemäß sind und ich für die Einhaltung der gesetzlichen Vorgaben im Zusammenhang mit dem angebotenen Gegenstand verantwortlich bin. *
                                </label>
                            </div>
                            @error('confirmCorrect')<p class="wba-error-text">{{ $message }}</p>@enderror
                            <div class="form-group {{ $errors->has('confirmContact') ? 'wba-field-error' : '' }}" style="display:flex; align-items:flex-start; gap:.75rem;">
                                <input type="checkbox" id="wba-confirmContact" name="confirmContact" value="1" style="width:auto; margin-top:.2rem;" {{ old('confirmContact') ? 'checked' : '' }}>
                                <label for="wba-confirmContact" style="font-size:.88rem; font-weight:400; color:var(--text-dark);">
                                    Ich stimme zu, dass meine angegebenen Kontaktdaten zur Bearbeitung und Vermittlung dieser Anzeige verwendet werden. Weitere Hinweise in der <a href="{{ route('datenschutz') }}">Datenschutzerklärung</a>. *
                                </label>
                            </div>
                            @error('confirmContact')<p class="wba-error-text">{{ $message }}</p>@enderror
                            <p class="wba-section-hint" style="margin-top:1rem;margin-bottom:0;">Ihre Anzeige wird erst nach Prüfung durch die Kreisjägerschaft veröffentlicht.</p>
                        </div>

                        {{-- Honeypot: fuer Menschen unsichtbares Feld gegen automatisierten
                             Spam (siehe WaffenboerseController::store()). --}}
                        <input type="text" name="_honey" style="display:none;" tabindex="-1" autocomplete="off">

                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                            Anzeige zur Prüfung einreichen
                        </button>
                    </form>
                @endif

                <p style="margin-top:2rem;">
                    <a href="{{ route('waffenboerse.index') }}" class="btn btn-outline-green">&larr; Zurück zur Waffenbörse</a>
                </p>
            </main>
        </div>
    </div>

    {{-- Reine Anzeige-/Formular-Logik (kein Submit-Abfangen, siehe
         Kommentar oben): Kaliber-Zeilen hinzufuegen/entfernen,
         Versand-/Erwerb-Toggle-Optik, Versandkosten-Ein-/Ausblenden,
         Bildanzahl-Anzeige. --}}
    <script>
    (function() {
        'use strict';

        var kaliberList = document.getElementById('wbaKaliberList');
        var kaliberAdd = document.getElementById('wbaKaliberAdd');
        function neueKaliberZeile() {
            var row = document.createElement('div');
            row.className = 'wba-kaliber-row';
            row.innerHTML = '<input type="text" class="wba-kaliber-input" name="kaliber[]" placeholder="z. B. 7x65R">' +
                '<button type="button" class="wba-kaliber-remove" aria-label="Kaliber entfernen">✕</button>';
            kaliberList.appendChild(row);
        }
        if (kaliberAdd) {
            kaliberAdd.addEventListener('click', neueKaliberZeile);
        }
        if (kaliberList) {
            kaliberList.addEventListener('click', function(e) {
                var btn = e.target.closest('.wba-kaliber-remove');
                if (!btn) return;
                var rows = kaliberList.querySelectorAll('.wba-kaliber-row');
                if (rows.length <= 1) {
                    btn.closest('.wba-kaliber-row').querySelector('.wba-kaliber-input').value = '';
                    return;
                }
                btn.closest('.wba-kaliber-row').remove();
            });
        }

        function toggleOptik(name, ids) {
            var ja = document.getElementById(ids.ja);
            var nein = document.getElementById(ids.nein);
            var optJa = document.getElementById(ids.optJa);
            var optNein = document.getElementById(ids.optNein);
            function update() {
                if (optJa) optJa.classList.toggle('is-active', !!ja.checked);
                if (optNein) optNein.classList.toggle('is-active', !!nein.checked);
            }
            document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) { r.addEventListener('change', update); });
            update();
        }
        toggleOptik('versand_moeglich', { ja: 'wba-versand-ja', nein: 'wba-versand-nein', optJa: 'wbaVersandOptionJa', optNein: 'wbaVersandOptionNein' });
        toggleOptik('erwerbsberechtigung_erforderlich', { ja: 'wba-erwerb-ja', nein: 'wba-erwerb-nein', optJa: 'wbaErwerbOptionJa', optNein: 'wbaErwerbOptionNein' });

        var versandJa = document.getElementById('wba-versand-ja');
        var versandNein = document.getElementById('wba-versand-nein');
        var versandkostenWrap = document.getElementById('wba-versandkosten-wrap');
        function updateVersandkostenUI() {
            if (!versandkostenWrap) return;
            versandkostenWrap.style.display = (versandJa && versandJa.checked) ? '' : 'none';
        }
        if (versandJa && versandNein) {
            versandJa.addEventListener('change', updateVersandkostenUI);
            versandNein.addEventListener('change', updateVersandkostenUI);
            updateVersandkostenUI();
        }

        var imgInput = document.getElementById('wbaImagesInput');
        var imgCount = document.getElementById('wbaImgCount');
        if (imgInput && imgCount) {
            imgInput.addEventListener('change', function() {
                var n = imgInput.files ? imgInput.files.length : 0;
                imgCount.textContent = n + ' / 10 Bilder ausgewählt';
            });
        }
    })();
    </script>
</x-layouts.app>
