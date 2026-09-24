{{--
    Phase 7I (Admin-Modul "Hundeboerse") - Anlegen/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route -
    identisch zum Muster von admin/partner/bearbeiten.blade.php, admin.js'
    hundeboerseNeu()->hundeboerseEdit(0) teilt sich ebenfalls dieselbe
    Maske).

    Einzelhund-/Wurf-Felder werden bewusst BEIDE immer angezeigt (statt wie
    im alten Admin per JS ein-/auszublenden) - der Blade-Admin kommt ohne
    zusaetzliche Runtime aus (siehe HundeboerseController-Klassenkommentar);
    nur die fuer den gewaehlten Typ jeweils relevanten Felder werden
    tatsaechlich ausgewertet/angezeigt, die anderen bleiben ungenutzt stehen -
    genau wie im alten admin.js, das ebenfalls beide Feldsaetze immer im DOM
    haelt und niemals inhaltlich bereinigt.

    Bildergalerie: bestehende Bilder mit "Entfernen"-Checkbox (staged, wird
    erst mit "Speichern" wirksam - Preservation, siehe Controller), neue
    Bilder per Datei-Upload (bis zu insgesamt 10, serverseitig geprueft).

    Speichern-Varianten (siehe HundeboerseController::update()): drei
    Submit-Buttons mit demselben "aktion"-Namen, unterschiedlichem Wert -
    Standard-HTML, kein JS noetig.
--}}
<x-layouts.admin :title="$istNeu ? 'Anzeige anlegen' : 'Anzeige bearbeiten'">
    <div class="panel-header">
        <h1>🐕 {{ $istNeu ? 'Neue Anzeige' : ($anzeige->title ?: 'Anzeige bearbeiten') }}</h1>
        <a class="btn btn-outline" href="{{ route('admin.hundeboerse.index') }}">← Zurück zur Liste</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.hundeboerse.speichern') : route('admin.hundeboerse.aktualisieren', $anzeige) }}" enctype="multipart/form-data">
            @csrf
            @unless ($istNeu)
                @method('PUT')
            @endunless

            <div class="form-card">
                <div class="form-card-title">📌 Status</div>
                <div class="field-row">
                    <label class="field-label" for="f-status">Aktueller Status</label>
                    <select class="field-input" id="f-status" name="status">
                        @foreach ($statusOptionen as $wert => $label)
                            <option value="{{ $wert }}" @selected(old('status', $anzeige->status) === $wert)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($istNeu)
                    <p class="field-hint">Eingegangen am {{ $anzeige->created_at?->format('d.m.Y') }}@if ($anzeige->updated_at) · zuletzt geändert am {{ $anzeige->updated_at->format('d.m.Y') }}@endif</p>
                @endunless
            </div>

            <div class="form-card">
                <div class="form-card-title">🐕 Grunddaten</div>
                <div class="field-row">
                    <label class="field-label">Art der Anzeige</label>
                    <div style="display:flex;gap:1.5rem;">
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                            <input type="radio" name="type" value="single" @checked(old('type', $anzeige->type) === 'single')> Einzelhund
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                            <input type="radio" name="type" value="litter" @checked(old('type', $anzeige->type) === 'litter')> Wurf
                        </label>
                    </div>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-title">Titel</label>
                    <input class="field-input" type="text" id="f-title" name="title" value="{{ old('title', $anzeige->title) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-breed">Rasse</label>
                    <input class="field-input" type="text" id="f-breed" name="breed" value="{{ old('breed', $anzeige->breed) }}">
                </div>

                <p class="field-hint">Einzelhund-Felder (nur relevant, wenn oben „Einzelhund" gewählt ist):</p>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-dog_name">Name des Hundes</label>
                        <input class="field-input" type="text" id="f-dog_name" name="dog_name" value="{{ old('dog_name', $anzeige->dog_name) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-gender">Geschlecht</label>
                        <select class="field-input" id="f-gender" name="gender">
                            <option value="">Bitte wählen …</option>
                            <option value="male" @selected(old('gender', $anzeige->gender) === 'male')>Rüde</option>
                            <option value="female" @selected(old('gender', $anzeige->gender) === 'female')>Hündin</option>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-birth_date">Geburtsdatum</label>
                    <input class="field-input" type="date" id="f-birth_date" name="birth_date" value="{{ old('birth_date', $anzeige->birthDateIso()) }}">
                </div>

                <p class="field-hint">Wurf-Felder (nur relevant, wenn oben „Wurf" gewählt ist):</p>
                <div class="field-row">
                    <label class="field-label" for="f-litter_date">Wurfdatum</label>
                    <input class="field-input" type="date" id="f-litter_date" name="litter_date" value="{{ old('litter_date', $anzeige->litterDateIso()) }}">
                </div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-male_count">Anzahl Rüden</label>
                        <input class="field-input" type="text" id="f-male_count" name="male_count" value="{{ old('male_count', $anzeige->male_count) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-female_count">Anzahl Hündinnen</label>
                        <input class="field-input" type="text" id="f-female_count" name="female_count" value="{{ old('female_count', $anzeige->female_count) }}">
                    </div>
                </div>

                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-color">Farbe</label>
                        <input class="field-input" type="text" id="f-color" name="color" value="{{ old('color', $anzeige->color) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-coat">Haarart</label>
                        <input class="field-input" type="text" id="f-coat" name="coat" value="{{ old('coat', $anzeige->coat) }}">
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">💶 Preis &amp; Standort</div>
                <div class="field-row">
                    <label class="field-label" for="f-price_type">Preisart</label>
                    <select class="field-input" id="f-price_type" name="price_type">
                        <option value="fixed" @selected(old('price_type', $anzeige->price_type) === 'fixed')>Festpreis</option>
                        <option value="negotiable" @selected(old('price_type', $anzeige->price_type) === 'negotiable')>Verhandlungsbasis (VB)</option>
                        <option value="on_request" @selected(old('price_type', $anzeige->price_type) === 'on_request')>Auf Anfrage</option>
                        <option value="none" @selected(old('price_type', $anzeige->price_type) === 'none')>Keine Angabe / kostenlose Abgabe</option>
                    </select>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-price">Preis (€)</label>
                    <input class="field-input" type="text" id="f-price" name="price" value="{{ old('price', $anzeige->price) }}">
                    <p class="field-hint">Nur relevant bei „Festpreis"/„Verhandlungsbasis".</p>
                </div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-postal_code">PLZ</label>
                        <input class="field-input" type="text" id="f-postal_code" name="postal_code" value="{{ old('postal_code', $anzeige->postal_code) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-city">Ort</label>
                        <input class="field-input" type="text" id="f-city" name="city" value="{{ old('city', $anzeige->city) }}">
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">🎯 Jagdliche Informationen</div>
                <div class="field-row">
                    <label class="field-label" for="f-hunting_tests">Prüfungen</label>
                    <textarea class="field-textarea" id="f-hunting_tests" name="hunting_tests" rows="3">{{ old('hunting_tests', $anzeige->hunting_tests) }}</textarea>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-training_level">Ausbildungsstand / weitere Angaben</label>
                    <textarea class="field-textarea" id="f-training_level" name="training_level" rows="3">{{ old('training_level', $anzeige->training_level) }}</textarea>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">🧬 Abstammung</div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-father">Vater</label>
                        <input class="field-input" type="text" id="f-father" name="father" value="{{ old('father', $anzeige->father) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-mother">Mutter</label>
                        <input class="field-input" type="text" id="f-mother" name="mother" value="{{ old('mother', $anzeige->mother) }}">
                    </div>
                </div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-father_tests">Prüfungen Vater</label>
                        <input class="field-input" type="text" id="f-father_tests" name="father_tests" value="{{ old('father_tests', $anzeige->father_tests) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-mother_tests">Prüfungen Mutter</label>
                        <input class="field-input" type="text" id="f-mother_tests" name="mother_tests" value="{{ old('mother_tests', $anzeige->mother_tests) }}">
                    </div>
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Zuchtverband vorhanden?</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="hidden" name="has_zuchtverband" value="0">
                        <input type="checkbox" id="f-has_zuchtverband" name="has_zuchtverband" value="1" @checked(old('has_zuchtverband', $anzeige->has_zuchtverband)) style="width:18px;height:18px;cursor:pointer;">
                    </label>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-zuchtverband">Zuchtverband</label>
                    <input class="field-input" type="text" id="f-zuchtverband" name="zuchtverband" value="{{ old('zuchtverband', $anzeige->zuchtverband) }}" list="zuchtverbaende-liste">
                    <datalist id="zuchtverbaende-liste">
                        @foreach ($zuchtverbaende as $name)
                            <option value="{{ $name }}"></option>
                        @endforeach
                    </datalist>
                    <p class="field-hint">Nur relevant, wenn oben „Zuchtverband vorhanden?" angehakt ist.</p>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📝 Beschreibung</div>
                <textarea class="field-textarea" name="description" rows="6">{{ old('description', $anzeige->description) }}</textarea>
            </div>

            <div class="form-card">
                <div class="form-card-title">🖼️ Bildergalerie</div>
                <p class="field-hint">Bis zu {{ \App\Support\HundeboerseUpdater::MAX_BILDER }} Bilder. Das erste Bild gilt als Hauptbild.</p>
                @unless ($istNeu)
                    @if ($anzeige->bilder->isNotEmpty())
                        <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
                            @foreach ($anzeige->bilder as $bild)
                                <div style="text-align:center;">
                                    <img src="{{ \App\Support\Images::boerseThumbUrl($bild->pfad) }}" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:8px;display:block;margin-bottom:.35rem;">
                                    <label style="font-size:.8rem;display:flex;align-items:center;gap:.3rem;justify-content:center;cursor:pointer;">
                                        <input type="checkbox" name="bild_entfernen[]" value="{{ $bild->id }}"> Entfernen
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted" style="font-size:.85rem;">Noch keine Bilder vorhanden.</p>
                    @endif
                @endunless
                <div class="field-row">
                    <label class="field-label" for="f-images">Neue Bilder hinzufügen</label>
                    <input class="field-input" type="file" id="f-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📞 Anbieter / Kontaktdaten</div>
                <div class="field-row">
                    <label class="field-label" for="f-provider_name">Name des Anbieters</label>
                    <input class="field-input" type="text" id="f-provider_name" name="provider_name" value="{{ old('provider_name', $anzeige->provider_name) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-contact_person">Ansprechpartner</label>
                    <input class="field-input" type="text" id="f-contact_person" name="contact_person" value="{{ old('contact_person', $anzeige->contact_person) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-email">E-Mail</label>
                    <input class="field-input" type="text" id="f-email" name="email" value="{{ old('email', $anzeige->email) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-phone">Telefon/Mobil</label>
                    <input class="field-input" type="text" id="f-phone" name="phone" value="{{ old('phone', $anzeige->phone) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-contact_notes">Weitere Hinweise (optional)</label>
                    <textarea class="field-textarea" id="f-contact_notes" name="contact_notes" rows="2">{{ old('contact_notes', $anzeige->contact_notes) }}</textarea>
                </div>
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf einen der Speichern-Buttons übernommen.</span>
                @unless ($istNeu)
                    <button type="submit" form="hb-loeschen-{{ $anzeige->id }}" class="btn btn-danger-outline" onclick="return confirm('Diese Anzeige wirklich unwiderruflich löschen?');">🗑️ Löschen</button>
                    <button type="submit" name="aktion" value="ablehnen" class="btn btn-danger-outline">Anzeige ablehnen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.hundeboerse.index') }}">Abbrechen</a>
                <button type="submit" name="aktion" value="speichern" class="btn btn-outline">💾 Speichern</button>
                <button type="submit" name="aktion" value="freigeben" class="btn btn-primary" onclick="return confirm('Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Hundebörse erscheinen.');">Speichern &amp; Freigeben</button>
            </div>
        </form>

        @unless ($istNeu)
            <form id="hb-loeschen-{{ $anzeige->id }}" method="POST" action="{{ route('admin.hundeboerse.loeschen', $anzeige) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        @endunless
    </div>
</x-layouts.admin>
