{{--
    Phase 7J (Admin-Modul "Waffenboerse") - Anlegen/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route -
    identisch zum Muster von admin/hundeboerse/bearbeiten.blade.php).

    Abweichungen von Hundeboerse (siehe Admin\WaffenboerseController-
    Klassenkommentar fuer die Begruendung):
    - Kaliber: EIN Textarea-Feld, eine Kaliberangabe pro Zeile (kein
      Komma-Trennfeld, keine dynamischen JS-Zeilen) - wird bei jedem
      Speichern komplett neu aufgebaut (siehe WaffenboerseUpdater::
      replaceKaliber()).
    - Kategorie: Dropdown aus der echten, admin-verwalteten Kategorienliste
      (siehe "Kategorien verwalten"-Link) statt einer reinen
      Vorschlagsliste.
    - Beschreibung: echtes Rich-Text-Feld (bereits bestehende Komponente
      aus Phase 7B), da "beschreibung" bereits echtes HTML ist - siehe
      Controller-Klassenkommentar "Beschreibung".

    Bildergalerie: bestehende Bilder mit "Entfernen"-Checkbox (staged, wird
    erst mit "Speichern" wirksam - Preservation, siehe Controller), neue
    Bilder per Datei-Upload (bis zu insgesamt 10, serverseitig geprueft).

    Speichern-Varianten (siehe WaffenboerseController::update()): drei
    Submit-Buttons mit demselben "aktion"-Namen, unterschiedlichem Wert -
    Standard-HTML, kein JS noetig (die Rich-Text-Komponente ist reine
    progressive Verbesserung, das Formular funktioniert auch ohne sie).
--}}
<x-layouts.admin :title="$istNeu ? 'Anzeige anlegen' : 'Anzeige bearbeiten'">
    <div class="panel-header">
        <h1>🔫 {{ $istNeu ? 'Neue Anzeige' : ($anzeige->titel ?: 'Anzeige bearbeiten') }}</h1>
        <a class="btn btn-outline" href="{{ route('admin.waffenboerse.index') }}">← Zurück zur Liste</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.waffenboerse.speichern') : route('admin.waffenboerse.aktualisieren', $anzeige) }}" enctype="multipart/form-data" data-richtext-form>
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
                    <p class="field-hint">Eingegangen am {{ $anzeige->erstellt_am?->format('d.m.Y') }}@if ($anzeige->aktualisiert_am) · zuletzt geändert am {{ $anzeige->aktualisiert_am->format('d.m.Y') }}@endif</p>
                @endunless
            </div>

            <div class="form-card">
                <div class="form-card-title">🔫 Grunddaten</div>
                <div class="field-row">
                    <label class="field-label" for="f-titel">Titel</label>
                    <input class="field-input" type="text" id="f-titel" name="titel" value="{{ old('titel', $anzeige->titel) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kategorie">Kategorie</label>
                    <select class="field-input" id="f-kategorie" name="kategorie">
                        <option value="">Bitte wählen …</option>
                        @foreach ($kategorien as $kat)
                            <option value="{{ $kat->name }}" @selected(old('kategorie', $anzeige->kategorie) === $kat->name)>{{ $kat->name }}</option>
                        @endforeach
                        @if ($anzeige->kategorie && ! $kategorien->contains('name', $anzeige->kategorie))
                            <option value="{{ $anzeige->kategorie }}" selected>{{ $anzeige->kategorie }} (nicht mehr in der Liste)</option>
                        @endif
                    </select>
                    <p class="field-hint">Neue Kategorien anlegen oder nicht mehr benötigte löschen: <a href="{{ route('admin.waffenboerse.kategorien.index') }}">Kategorien verwalten</a>.</p>
                </div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-hersteller">Hersteller</label>
                        <input class="field-input" type="text" id="f-hersteller" name="hersteller" value="{{ old('hersteller', $anzeige->hersteller) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-modell">Modell</label>
                        <input class="field-input" type="text" id="f-modell" name="modell" value="{{ old('modell', $anzeige->modell) }}">
                    </div>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kaliber">Kaliber</label>
                    <textarea class="field-textarea" id="f-kaliber" name="kaliber" rows="3">{{ old('kaliber', $kaliberText) }}</textarea>
                    <p class="field-hint">Eine Kaliberangabe pro Zeile (Kombiwaffen können mehrere haben, z.B. „7x65R“ und „12/70“ in getrennten Zeilen). Bis zu {{ \App\Support\WaffenboerseUpdater::MAX_KALIBER }} Zeilen.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-zustand">Zustand</label>
                    <select class="field-input" id="f-zustand" name="zustand">
                        @foreach ($zustandOptionen as $wert => $label)
                            <option value="{{ $wert }}" @selected(old('zustand', $anzeige->zustand) === $wert)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">💶 Preis &amp; Versand</div>
                <div class="field-row">
                    <label class="field-label" for="f-preis_typ">Preisart</label>
                    <select class="field-input" id="f-preis_typ" name="preis_typ">
                        @foreach ($preisTypOptionen as $wert => $label)
                            <option value="{{ $wert }}" @selected(old('preis_typ', $anzeige->preis_typ) === $wert)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-preis">Preis (€)</label>
                    <input class="field-input" type="text" id="f-preis" name="preis" value="{{ old('preis', $anzeige->preis) }}">
                    <p class="field-hint">Nur relevant bei „Festpreis"/„Verhandlungsbasis".</p>
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Versand möglich?</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="hidden" name="versand_moeglich" value="0">
                        <input type="checkbox" id="f-versand_moeglich" name="versand_moeglich" value="1" @checked(old('versand_moeglich', $anzeige->versand_moeglich)) style="width:18px;height:18px;cursor:pointer;">
                    </label>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-versandkosten">Versandkosten</label>
                    <input class="field-input" type="text" id="f-versandkosten" name="versandkosten" value="{{ old('versandkosten', $anzeige->versandkosten) }}">
                    <p class="field-hint">Nur relevant, wenn oben „Versand möglich?" angehakt ist.</p>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📍 Standort</div>
                <div class="field-row-2">
                    <div>
                        <label class="field-label" for="f-plz">PLZ</label>
                        <input class="field-input" type="text" id="f-plz" name="plz" value="{{ old('plz', $anzeige->plz) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-ort">Ort</label>
                        <input class="field-input" type="text" id="f-ort" name="ort" value="{{ old('ort', $anzeige->ort) }}">
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">⚠️ Erwerbsberechtigung</div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:220px;margin:0">Erwerbsberechtigung erforderlich?</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="hidden" name="erwerbsberechtigung_erforderlich" value="0">
                        <input type="checkbox" id="f-erwerb" name="erwerbsberechtigung_erforderlich" value="1" @checked(old('erwerbsberechtigung_erforderlich', $anzeige->erwerbsberechtigung_erforderlich)) style="width:18px;height:18px;cursor:pointer;">
                    </label>
                </div>
                <p class="field-hint">Wird bei „Ja" auf der öffentlichen Detailseite deutlich sichtbar angezeigt.</p>
            </div>

            <div class="form-card">
                <div class="form-card-title">📝 Beschreibung</div>
                @include('admin.inhalte._richtext-field', ['field' => 'beschreibung', 'label' => 'Freitext-Beschreibung', 'value' => $anzeige->beschreibung])
            </div>

            <div class="form-card">
                <div class="form-card-title">🖼️ Bildergalerie</div>
                <p class="field-hint">Bis zu {{ \App\Support\WaffenboerseUpdater::MAX_BILDER }} Bilder. Das erste Bild gilt als Hauptbild.</p>
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
                    <label class="field-label" for="f-anbieter_name">Name des Anbieters</label>
                    <input class="field-input" type="text" id="f-anbieter_name" name="anbieter_name" value="{{ old('anbieter_name', $anzeige->anbieter_name) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-anbieter_email">E-Mail</label>
                    <input class="field-input" type="text" id="f-anbieter_email" name="anbieter_email" value="{{ old('anbieter_email', $anzeige->anbieter_email) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-anbieter_telefon">Telefon/Mobil (optional)</label>
                    <input class="field-input" type="text" id="f-anbieter_telefon" name="anbieter_telefon" value="{{ old('anbieter_telefon', $anzeige->anbieter_telefon) }}">
                </div>
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf einen der Speichern-Buttons übernommen.</span>
                @unless ($istNeu)
                    <button type="submit" form="wb-loeschen-{{ $anzeige->id }}" class="btn btn-danger-outline" onclick="return confirm('Diese Anzeige wirklich unwiderruflich löschen?');">🗑️ Löschen</button>
                    <button type="submit" name="aktion" value="ablehnen" class="btn btn-danger-outline">Anzeige ablehnen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.waffenboerse.index') }}">Abbrechen</a>
                <button type="submit" name="aktion" value="speichern" class="btn btn-outline">💾 Speichern</button>
                <button type="submit" name="aktion" value="freigeben" class="btn btn-primary" onclick="return confirm('Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Waffenbörse erscheinen.');">Speichern &amp; Freigeben</button>
            </div>
        </form>

        @unless ($istNeu)
            <form id="wb-loeschen-{{ $anzeige->id }}" method="POST" action="{{ route('admin.waffenboerse.loeschen', $anzeige) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        @endunless
    </div>

    @vite(['resources/js/admin-inhalte-editor.js'])
</x-layouts.admin>
