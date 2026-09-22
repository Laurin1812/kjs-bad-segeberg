{{--
    Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
    ersetzt hundeboerse/anbieten.html - erstmals eine ECHTE, serverseitig
    validierte oeffentliche Einreichung (siehe Auftrag: "soll jetzt
    erstmals sauber fertiggestellt werden"; das PHP-Original
    api/hundeboerse/anzeigen.php war zwar bereits fertig, aber nicht Teil
    dieser Migration - siehe HundeboerseController-Klassenkommentar).

    Bewusst vereinfacht gegenueber dem bisherigen clientseitigen Formular:
    ein echtes natives <form method="POST" enctype="multipart/form-data">
    statt eines per JS abgefangenen fetch()-Submits mit eigenem FormData-
    Aufbau - HundeboerseAnbietenRequest liefert bei Fehlern einen echten
    Redirect zurueck mit @error()/old() (Standard-Laravel-Muster). Die
    bisherige Bild-Drag&Drop-Neusortierung/Live-Vorschau (reine Client-
    Komprimierung ohne echten Upload) entfaellt dadurch - sie war ohnehin
    an das JS-Submit-Modell gekoppelt und liesse sich mit einem nativen
    <input type="file" multiple> nicht 1:1 uebernehmen (FileList ist
    unveraenderlich). Erhalten bleiben: Typ-Umschalter (Einzelhund/Wurf),
    Preisart-Umschalter, Zuchtverband-Umschalter, Bildanzahl-Anzeige -
    alles reine Anzeige-Logik ohne Submit-Abfangen.

    Hero-Bild: ausschliesslich das echte, mitgelieferte Hundeboerse-Hero
    (HundeboerseMeta::hero_bild) bzw. derselbe echte Bestandspfad als
    technischer Fallback, wenn es in der DB fehlt - kein Stock-Motiv.
--}}
<x-layouts.app title="Hund / Wurf anbieten">
    <x-page-hero title="Hund / Wurf anbieten" :bg-image="$heroBild ?: '/images/1787934528232-ChatGPT-Image-28.-Aug.-2026--18_28_24.png'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Hundebörse', 'href' => route('hundeboerse.index')],
        ['label' => 'Anbieten'],
    ]" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content">
                @if (session('hb_success'))
                    <div class="hb-success-box" style="display:block;">
                        <h3>Vielen Dank!</h3>
                        <p>Ihre Anzeige wurde erfolgreich zur Prüfung eingereicht. Sie ist noch nicht öffentlich sichtbar und wird nach Prüfung durch die Kreisjägerschaft freigegeben oder bei Rückfragen entsprechend bearbeitet.</p>
                    </div>
                @else
                    <p style="font-size:1.05rem;color:var(--text-body);line-height:1.8;margin-bottom:2rem;">
                        Füllen Sie das Formular aus, um einen Hund oder Wurf einzustellen. Ihre Anzeige wird vor der
                        Veröffentlichung durch die Kreisjägerschaft geprüft.
                    </p>

                    @if ($errors->any())
                        <div class="hb-error-summary">
                            <strong>Bitte prüfen Sie folgende Angaben:</strong>
                            <ul>
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('hundeboerse.anbieten.store') }}" enctype="multipart/form-data" id="hbAngebotForm">
                        @csrf

                        <div class="kontakt-form hb-form-section">
                            <h3>1. Art der Anzeige *</h3>
                            <div class="hb-typ-toggle">
                                <label class="hb-typ-option" id="hbTypOptionSingle">
                                    <input type="radio" name="type" value="single" id="hb-typ-single" {{ old('type', 'single') === 'single' ? 'checked' : '' }}>
                                    Einzelhund
                                </label>
                                <label class="hb-typ-option" id="hbTypOptionLitter">
                                    <input type="radio" name="type" value="litter" id="hb-typ-litter" {{ old('type') === 'litter' ? 'checked' : '' }}>
                                    Wurf
                                </label>
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>2. Grunddaten</h3>
                            <div class="form-group {{ $errors->has('title') ? 'hb-field-error' : '' }}">
                                <label for="hb-title">Titel *</label>
                                <input type="text" id="hb-title" name="title" value="{{ old('title') }}" placeholder="z. B. Jagdlich geführter Deutsch Kurzhaar Rüde abzugeben">
                                @error('title')<p class="hb-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('breed') ? 'hb-field-error' : '' }}">
                                    <label for="hb-breed">Rasse *</label>
                                    <input type="text" id="hb-breed" name="breed" value="{{ old('breed') }}" placeholder="z. B. Deutsch Kurzhaar">
                                    @error('breed')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="hb-color">Farbe</label>
                                    <input type="text" id="hb-color" name="color" value="{{ old('color') }}" placeholder="z. B. Dunkelbraun mit Brustfleck">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="hb-coat">Haarart</label>
                                <input type="text" id="hb-coat" name="coat" value="{{ old('coat') }}" placeholder="z. B. Kurzhaar">
                            </div>

                            <div id="hbBlockSingle">
                                <div class="form-group">
                                    <label for="hb-dogName">Name des Hundes</label>
                                    <input type="text" id="hb-dogName" name="dogName" value="{{ old('dogName') }}" placeholder="z. B. Bruno">
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-group {{ $errors->has('birthDate') ? 'hb-field-error' : '' }}">
                                        <label for="hb-birthDate">Geburtsdatum *</label>
                                        <input type="date" id="hb-birthDate" name="birthDate" value="{{ old('birthDate') }}">
                                        @error('birthDate')<p class="hb-error-text">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="form-group {{ $errors->has('gender') ? 'hb-field-error' : '' }}">
                                        <label for="hb-gender">Geschlecht *</label>
                                        <select id="hb-gender" name="gender">
                                            <option value="">Bitte wählen …</option>
                                            <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Rüde</option>
                                            <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Hündin</option>
                                        </select>
                                        @error('gender')<p class="hb-error-text">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            <div id="hbBlockLitter" style="display:none;">
                                <div class="form-group {{ $errors->has('litterDate') ? 'hb-field-error' : '' }}">
                                    <label for="hb-litterDate">Wurfdatum *</label>
                                    <input type="date" id="hb-litterDate" name="litterDate" value="{{ old('litterDate') }}">
                                    @error('litterDate')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label for="hb-maleCount">Anzahl Rüden</label>
                                        <input type="number" id="hb-maleCount" name="maleCount" value="{{ old('maleCount') }}" min="0" step="1" placeholder="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="hb-femaleCount">Anzahl Hündinnen</label>
                                        <input type="number" id="hb-femaleCount" name="femaleCount" value="{{ old('femaleCount') }}" min="0" step="1" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>3. Preis &amp; Standort</h3>
                            <div class="form-group">
                                <label for="hb-priceType">Preisart</label>
                                <select id="hb-priceType" name="priceType">
                                    <option value="fixed" {{ old('priceType') === 'fixed' ? 'selected' : '' }}>Festpreis</option>
                                    <option value="negotiable" {{ old('priceType') === 'negotiable' ? 'selected' : '' }}>Verhandlungsbasis (VB)</option>
                                    <option value="on_request" {{ old('priceType', 'on_request') === 'on_request' ? 'selected' : '' }}>Preis auf Anfrage</option>
                                    <option value="none" {{ old('priceType') === 'none' ? 'selected' : '' }}>Keine Preisangabe</option>
                                </select>
                            </div>
                            <div class="form-group" id="hb-price-wrap" style="display:none;">
                                <label for="hb-price">Preis (€)</label>
                                <input type="number" id="hb-price" name="price" value="{{ old('price') }}" min="0" step="1" placeholder="z. B. 1200">
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('postalCode') ? 'hb-field-error' : '' }}">
                                    <label for="hb-postalCode">PLZ *</label>
                                    <input type="text" id="hb-postalCode" name="postalCode" value="{{ old('postalCode') }}" inputmode="numeric" placeholder="z. B. 23795">
                                    @error('postalCode')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group {{ $errors->has('city') ? 'hb-field-error' : '' }}">
                                    <label for="hb-city">Ort *</label>
                                    <input type="text" id="hb-city" name="city" value="{{ old('city') }}" placeholder="z. B. Bad Segeberg">
                                    @error('city')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <p class="hb-section-hint" style="margin-bottom:0;">Es wird nur der ungefähre Standort veröffentlicht, keine genaue Adresse.</p>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>4. Jagdliche Informationen <span class="hb-optional-tag">(optional)</span></h3>
                            <div class="form-group">
                                <label for="hb-huntingTests">Prüfungen</label>
                                <input type="text" id="hb-huntingTests" name="huntingTests" value="{{ old('huntingTests') }}" placeholder="z. B. VJP 72 Punkte, HZP 181 Punkte">
                            </div>
                            <div class="form-group">
                                <label for="hb-trainingLevel">Ausbildungsstand / weitere Angaben</label>
                                <textarea id="hb-trainingLevel" name="trainingLevel" placeholder="z. B. regelmäßig im Revier eingesetzt, wasser- und fährtenfest …">{{ old('trainingLevel') }}</textarea>
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>5. Abstammung <span class="hb-optional-tag">(optional)</span></h3>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="hb-father">Vater</label>
                                    <input type="text" id="hb-father" name="father" value="{{ old('father') }}" placeholder="Name des Vaters">
                                </div>
                                <div class="form-group">
                                    <label for="hb-fatherTests">Prüfungen Vater</label>
                                    <input type="text" id="hb-fatherTests" name="fatherTests" value="{{ old('fatherTests') }}" placeholder="z. B. VJP, HZP">
                                </div>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="hb-mother">Mutter</label>
                                    <input type="text" id="hb-mother" name="mother" value="{{ old('mother') }}" placeholder="Name der Mutter">
                                </div>
                                <div class="form-group">
                                    <label for="hb-motherTests">Prüfungen Mutter</label>
                                    <input type="text" id="hb-motherTests" name="motherTests" value="{{ old('motherTests') }}" placeholder="z. B. VJP, HZP">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Zuchtverband vorhanden?</label>
                                <div class="hb-typ-toggle" style="max-width:420px;">
                                    <label class="hb-typ-option" id="hbZvOptionJa">
                                        <input type="radio" name="hasZuchtverband" value="1" id="hb-zuchtverbandVorhanden-ja" {{ old('hasZuchtverband') ? 'checked' : '' }}>
                                        Ja
                                    </label>
                                    <label class="hb-typ-option" id="hbZvOptionNein">
                                        <input type="radio" name="hasZuchtverband" value="0" id="hb-zuchtverbandVorhanden-nein" {{ old('hasZuchtverband') ? '' : 'checked' }}>
                                        Nein
                                    </label>
                                </div>
                            </div>
                            <div class="form-group" id="hbGroup-zuchtverband" style="display:none;">
                                <label for="hb-zuchtverband">Zuchtverband</label>
                                <input type="text" id="hb-zuchtverband" name="zuchtverband" value="{{ old('zuchtverband') }}" list="hb-zuchtverband-liste" placeholder="z. B. JGHV, VDH, DK …">
                                <datalist id="hb-zuchtverband-liste">
                                    @foreach ($zuchtverbaende as $zv)
                                        <option value="{{ $zv }}">
                                    @endforeach
                                </datalist>
                                <p class="hb-section-hint" style="margin-top:.35rem;margin-bottom:0;">Vorschläge aus bereits verwendeten Zuchtverbänden oder eigenen Namen eingeben.</p>
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>6. Beschreibung *</h3>
                            <div class="form-group {{ $errors->has('description') ? 'hb-field-error' : '' }}">
                                <label for="hb-description">Freitext</label>
                                <textarea id="hb-description" name="description" style="min-height:180px;" placeholder="Beschreiben Sie den Hund bzw. Wurf – Wesen, Erfahrung, Umgebung, was den Hund/Wurf ausmacht …">{{ old('description') }}</textarea>
                                @error('description')<p class="hb-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section {{ $errors->has('images') ? 'hb-field-error' : '' }}" id="hbGroup-images">
                            <h3>7. Bilder *</h3>
                            <p class="hb-section-hint">Mindestens 1, maximal 10 Bilder. Mehrere Bilder können gleichzeitig ausgewählt werden. Erlaubte Formate: JPG, PNG, WebP (max. 8&nbsp;MB je Bild).</p>
                            <label class="hb-img-add-label" for="hbImagesInput">Bilder auswählen</label>
                            <input type="file" id="hbImagesInput" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
                            <p class="hb-img-count" id="hbImgCount">0 Bilder ausgewählt</p>
                            @error('images')<p class="hb-error-text">{{ $message }}</p>@enderror
                            @error('images.*')<p class="hb-error-text">{{ $message }}</p>@enderror
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>8. Anbieter / Kontakt</h3>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('providerName') ? 'hb-field-error' : '' }}">
                                    <label for="hb-providerName">Name des Anbieters *</label>
                                    <input type="text" id="hb-providerName" name="providerName" value="{{ old('providerName') }}" placeholder="Vor- und Nachname bzw. Verein">
                                    @error('providerName')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="hb-contactPerson">Ansprechpartner (optional)</label>
                                    <input type="text" id="hb-contactPerson" name="contactPerson" value="{{ old('contactPerson') }}" placeholder="falls abweichend vom Anbieter">
                                </div>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('email') ? 'hb-field-error' : '' }}">
                                    <label for="hb-email">E-Mail *</label>
                                    <input type="email" id="hb-email" name="email" value="{{ old('email') }}" placeholder="max@beispiel.de">
                                    @error('email')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="hb-phone">Telefon / Mobil</label>
                                    <input type="tel" id="hb-phone" name="phone" value="{{ old('phone') }}" placeholder="z. B. 0172 1234567">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="hb-contactNotes">Weitere Hinweise (optional)</label>
                                <textarea id="hb-contactNotes" name="contactNotes" placeholder="z. B. am besten erreichbar abends, Besichtigung nach Absprache …">{{ old('contactNotes') }}</textarea>
                            </div>
                        </div>

                        <div class="kontakt-form hb-form-section">
                            <h3>9. Hinweise &amp; Zustimmung</h3>
                            <div class="form-group {{ $errors->has('confirmCorrect') ? 'hb-field-error' : '' }}" style="display:flex; align-items:flex-start; gap:.75rem;">
                                <input type="checkbox" id="hb-confirmCorrect" name="confirmCorrect" value="1" style="width:auto; margin-top:.2rem;" {{ old('confirmCorrect') ? 'checked' : '' }}>
                                <label for="hb-confirmCorrect" style="font-size:.88rem; font-weight:400; color:var(--text-dark);">
                                    Ich bestätige, dass meine Angaben korrekt sind. *
                                </label>
                            </div>
                            @error('confirmCorrect')<p class="hb-error-text">{{ $message }}</p>@enderror
                            <div class="form-group {{ $errors->has('confirmPrivacy') ? 'hb-field-error' : '' }}" style="display:flex; align-items:flex-start; gap:.75rem;">
                                <input type="checkbox" id="hb-confirmPrivacy" name="confirmPrivacy" value="1" style="width:auto; margin-top:.2rem;" {{ old('confirmPrivacy') ? 'checked' : '' }}>
                                <label for="hb-confirmPrivacy" style="font-size:.88rem; font-weight:400; color:var(--text-dark);">
                                    Ich bin mit der Verarbeitung meiner eingegebenen Daten gemäß der <a href="{{ route('datenschutz') }}">Datenschutzerklärung</a> einverstanden. *
                                </label>
                            </div>
                            @error('confirmPrivacy')<p class="hb-error-text">{{ $message }}</p>@enderror
                            <p class="hb-section-hint" style="margin-top:1rem;margin-bottom:0;">Ihre Anzeige wird erst nach Prüfung durch die Kreisjägerschaft veröffentlicht.</p>
                        </div>

                        {{-- Honeypot: fuer Menschen unsichtbares Feld gegen automatisierten
                             Spam (siehe HundeboerseController::store()). --}}
                        <input type="text" name="_honey" style="display:none;" tabindex="-1" autocomplete="off">

                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                            Anzeige zur Prüfung einreichen
                        </button>
                    </form>
                @endif

                <p style="margin-top:2rem;">
                    <a href="{{ route('hundeboerse.index') }}" class="btn btn-outline-green">&larr; Zurück zur Hundebörse</a>
                </p>
            </main>
        </div>
    </div>

    {{-- Reine Anzeige-Logik (kein Submit-Abfangen, siehe Kommentar oben):
         Typ-/Preisart-/Zuchtverband-Umschalter + Bildanzahl-Anzeige. --}}
    <script>
    (function() {
        'use strict';

        function currentTyp() {
            return document.getElementById('hb-typ-litter').checked ? 'litter' : 'single';
        }
        var blockSingle = document.getElementById('hbBlockSingle');
        var blockLitter = document.getElementById('hbBlockLitter');
        var optSingle = document.getElementById('hbTypOptionSingle');
        var optLitter = document.getElementById('hbTypOptionLitter');
        function updateTypUI() {
            if (!blockSingle) return;
            var isLitter = currentTyp() === 'litter';
            blockSingle.style.display = isLitter ? 'none' : '';
            blockLitter.style.display = isLitter ? '' : 'none';
            optSingle.classList.toggle('is-active', !isLitter);
            optLitter.classList.toggle('is-active', isLitter);
        }
        document.querySelectorAll('input[name="type"]').forEach(function(r) { r.addEventListener('change', updateTypUI); });
        updateTypUI();

        var priceTypeSelect = document.getElementById('hb-priceType');
        var priceWrap = document.getElementById('hb-price-wrap');
        function updatePriceUI() {
            var v = priceTypeSelect.value;
            priceWrap.style.display = (v === 'fixed' || v === 'negotiable') ? '' : 'none';
        }
        if (priceTypeSelect) {
            priceTypeSelect.addEventListener('change', updatePriceUI);
            updatePriceUI();
        }

        var zvOptionJa = document.getElementById('hbZvOptionJa');
        var zvOptionNein = document.getElementById('hbZvOptionNein');
        var zuchtverbandWrap = document.getElementById('hbGroup-zuchtverband');
        function updateZuchtverbandUI() {
            if (!zuchtverbandWrap) return;
            var ja = document.getElementById('hb-zuchtverbandVorhanden-ja').checked;
            zuchtverbandWrap.style.display = ja ? '' : 'none';
            zvOptionJa.classList.toggle('is-active', ja);
            zvOptionNein.classList.toggle('is-active', !ja);
        }
        document.querySelectorAll('input[name="hasZuchtverband"]').forEach(function(r) { r.addEventListener('change', updateZuchtverbandUI); });
        updateZuchtverbandUI();

        var imgInput = document.getElementById('hbImagesInput');
        var imgCount = document.getElementById('hbImgCount');
        if (imgInput && imgCount) {
            imgInput.addEventListener('change', function() {
                var n = imgInput.files ? imgInput.files.length : 0;
                imgCount.textContent = n + ' / 10 Bilder ausgewählt';
            });
        }
    })();
    </script>
</x-layouts.app>
