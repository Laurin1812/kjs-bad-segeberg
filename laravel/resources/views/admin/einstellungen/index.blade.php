{{--
    Phase 7L (Admin-Modul "Einstellungen"), Teil A.

    Vier unabhaengige Formulare auf einer Seite (siehe EinstellungenController-
    Klassenkommentar) - jedes mit eigenem "expected_version"-Feld, eigener
    Speichern-Aktion und eigener Konflikterkennung. "Sprechzeiten" bewusst als
    Freitext ("Tage | Uhrzeit", eine Zeile je Eintrag) statt eines neuen
    Add/Delete-Zeilen-Widgets - siehe EinstellungenUpdater-Klassenkommentar
    ("oeffnungszeiten").
--}}
<x-layouts.admin title="Einstellungen">
    <div class="panel-header">
        <h1>⚙️ Einstellungen</h1>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        {{-- ─────────────────── Darstellung (Gruppe "design") ─────────────────── --}}
        <form method="POST" action="{{ route('admin.einstellungen.aktualisieren', 'darstellung') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $gruppen['darstellung']['version'] }}">
            <div class="form-card">
                <div class="form-card-title">🎨 Darstellung</div>
                <div class="field-row">
                    <label class="field-label" for="f-farbe-gruen">Hauptfarbe Grün</label>
                    <input class="field-input" type="color" id="f-farbe-gruen" name="farbe_gruen" value="{{ old('farbe_gruen', $gruppen['darstellung']['werte']['farbe_gruen'] ?: '#2e6b30') }}" style="max-width:120px;">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-farbe-dunkelgruen">Dunkelgrün (Header/Footer)</label>
                    <input class="field-input" type="color" id="f-farbe-dunkelgruen" name="farbe_dunkelgruen" value="{{ old('farbe_dunkelgruen', $gruppen['darstellung']['werte']['farbe_dunkelgruen'] ?: '#1a4a1c') }}" style="max-width:120px;">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-farbe-akzent">Akzentfarbe (Gold)</label>
                    <input class="field-input" type="color" id="f-farbe-akzent" name="farbe_akzent" value="{{ old('farbe_akzent', $gruppen['darstellung']['werte']['farbe_akzent'] ?: '#b8860b') }}" style="max-width:120px;">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-schrift-ueberschrift">Überschrift-Schrift</label>
                    <select class="field-input" id="f-schrift-ueberschrift" name="schrift_ueberschrift">
                        @foreach (['Playfair Display', 'Georgia', 'Merriweather', 'Lora', 'EB Garamond'] as $font)
                            <option value="{{ $font }}" @selected(old('schrift_ueberschrift', $gruppen['darstellung']['werte']['schrift_ueberschrift']) === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-schrift-text">Text-Schrift</label>
                    <select class="field-input" id="f-schrift-text" name="schrift_text">
                        @foreach (['Inter', 'Open Sans', 'Roboto', 'Lato', 'Source Sans Pro'] as $font)
                            <option value="{{ $font }}" @selected(old('schrift_text', $gruppen['darstellung']['werte']['schrift_text']) === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-h1">Schriftgröße H1 (Haupttitel)</label>
                    <input class="field-input" type="text" id="f-h1" name="schriftgroesse_h1" value="{{ old('schriftgroesse_h1', $gruppen['darstellung']['werte']['schriftgroesse_h1']) }}" placeholder="2.8rem">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-h2">Schriftgröße H2 (Abschnittstitel)</label>
                    <input class="field-input" type="text" id="f-h2" name="schriftgroesse_h2" value="{{ old('schriftgroesse_h2', $gruppen['darstellung']['werte']['schriftgroesse_h2']) }}" placeholder="2rem">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-h3">Schriftgröße H3 (Unterabschnitt)</label>
                    <input class="field-input" type="text" id="f-h3" name="schriftgroesse_h3" value="{{ old('schriftgroesse_h3', $gruppen['darstellung']['werte']['schriftgroesse_h3']) }}" placeholder="1.4rem">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-text">Schriftgröße Fließtext</label>
                    <input class="field-input" type="text" id="f-text" name="schriftgroesse_text" value="{{ old('schriftgroesse_text', $gruppen['darstellung']['werte']['schriftgroesse_text']) }}" placeholder="1rem">
                </div>
            </div>
            <div class="save-bar">
                <button type="submit" class="btn btn-primary">💾 Darstellung speichern</button>
            </div>
        </form>

        {{-- ─────────────────── Kontakt & Stammdaten (Gruppe "einstellungen") ─────────────────── --}}
        <form method="POST" action="{{ route('admin.einstellungen.aktualisieren', 'kontakt') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $gruppen['kontakt']['version'] }}">
            <div class="form-card">
                <div class="form-card-title">📞 Kontakt &amp; Stammdaten</div>
                <p class="field-hint">Diese Angaben erscheinen automatisch in der Kopfzeile, in der Kontaktbox auf allen Seiten, im Impressum und auf der Kontaktseite.</p>
                <div class="field-row">
                    <label class="field-label" for="f-telefon-header">Telefonnummer in der Kopfzeile</label>
                    <input class="field-input" type="text" id="f-telefon-header" name="telefon_header" value="{{ old('telefon_header', $gruppen['kontakt']['werte']['telefon_header']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-telefon">Telefon Geschäftsstelle</label>
                    <input class="field-input" type="text" id="f-telefon" name="telefon" value="{{ old('telefon', $gruppen['kontakt']['werte']['telefon']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-email">E-Mail Geschäftsstelle</label>
                    <input class="field-input" type="text" id="f-email" name="email" value="{{ old('email', $gruppen['kontakt']['werte']['email']) }}">
                    <p class="field-hint">Erscheint auch in der Kopfzeile.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-adresse">Adresse Geschäftsstelle</label>
                    <textarea class="field-textarea" id="f-adresse" name="adresse" rows="3">{{ old('adresse', $gruppen['kontakt']['werte']['adresse']) }}</textarea>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-postadresse">Postadresse</label>
                    <textarea class="field-textarea" id="f-postadresse" name="postadresse" rows="4">{{ old('postadresse', $gruppen['kontakt']['werte']['postadresse']) }}</textarea>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-postadresse-telefon">Telefon (zur Postadresse)</label>
                    <input class="field-input" type="text" id="f-postadresse-telefon" name="postadresse_telefon" value="{{ old('postadresse_telefon', $gruppen['kontakt']['werte']['postadresse_telefon']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-postadresse-email">E-Mail (zur Postadresse)</label>
                    <input class="field-input" type="text" id="f-postadresse-email" name="postadresse_email" value="{{ old('postadresse_email', $gruppen['kontakt']['werte']['postadresse_email']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-oz">Sprechzeiten</label>
                    <textarea class="field-textarea" id="f-oz" name="oeffnungszeiten_text" rows="3">{{ old('oeffnungszeiten_text', $gruppen['kontakt']['oeffnungszeitenText']) }}</textarea>
                    <p class="field-hint">Eine Zeile je Sprechzeit, Format „Tage | Uhrzeit", z. B. „Montag bis Freitag | 10:00 – 17:00 Uhr".</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-ko-ueberschrift">Überschrift (Kontaktseite)</label>
                    <input class="field-input" type="text" id="f-ko-ueberschrift" name="kontakt_ueberschrift" value="{{ old('kontakt_ueberschrift', $gruppen['kontakt']['werte']['kontakt_ueberschrift']) }}" placeholder="So erreichen Sie uns">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-ko-text">Einleitungstext (Kontaktseite)</label>
                    <textarea class="field-textarea" id="f-ko-text" name="kontakt_text" rows="3">{{ old('kontakt_text', $gruppen['kontakt']['werte']['kontakt_text']) }}</textarea>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kal-url">Google Kalender URL</label>
                    <input class="field-input" type="text" id="f-kal-url" name="google_kalender_url" value="{{ old('google_kalender_url', $gruppen['kontakt']['werte']['google_kalender_url']) }}" placeholder="Einbettungs-URL aus Google Kalender">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kal-titel">Kalender-Überschrift</label>
                    <input class="field-input" type="text" id="f-kal-titel" name="google_kalender_titel" value="{{ old('google_kalender_titel', $gruppen['kontakt']['werte']['google_kalender_titel']) }}" placeholder="z.B. Terminbuchung">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-timetree">Infomobil – TimeTree Public-Calendar-Link</label>
                    <input class="field-input" type="text" id="f-timetree" name="infomobil_timetree_url" value="{{ old('infomobil_timetree_url', $gruppen['kontakt']['werte']['infomobil_timetree_url']) }}" placeholder="https://timetreeapp.com/public_calendars/…">
                    <p class="field-hint">Der Button „Verfügbarkeit prüfen" auf der Infomobil-Seite erscheint nur, solange hier ein Link eingetragen ist.</p>
                </div>
            </div>
            <div class="save-bar">
                <button type="submit" class="btn btn-primary">💾 Kontakt &amp; Stammdaten speichern</button>
            </div>
        </form>

        {{-- ─────────────────── Footer & Social (Gruppe "footer") ─────────────────── --}}
        <form method="POST" action="{{ route('admin.einstellungen.aktualisieren', 'footer') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $gruppen['footer']['version'] }}">
            <div class="form-card">
                <div class="form-card-title">🦶 Footer &amp; Social</div>
                <div class="field-row">
                    <label class="field-label" for="f-ft-ueber">Über-uns Text</label>
                    <textarea class="field-textarea" id="f-ft-ueber" name="ueber_text" rows="3">{{ old('ueber_text', $gruppen['footer']['werte']['ueber_text']) }}</textarea>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-ft-copyright">Copyright-Text</label>
                    <input class="field-input" type="text" id="f-ft-copyright" name="copyright" value="{{ old('copyright', $gruppen['footer']['werte']['copyright']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-ft-fb">Facebook URL</label>
                    <input class="field-input" type="text" id="f-ft-fb" name="facebook_url" value="{{ old('facebook_url', $gruppen['footer']['werte']['facebook_url']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-ft-ig">Instagram URL</label>
                    <input class="field-input" type="text" id="f-ft-ig" name="instagram_url" value="{{ old('instagram_url', $gruppen['footer']['werte']['instagram_url']) }}">
                </div>
            </div>
            <div class="save-bar">
                <button type="submit" class="btn btn-primary">💾 Footer &amp; Social speichern</button>
            </div>
        </form>

        {{-- ─────────────────── Impressum (Gruppe "impressum") ─────────────────── --}}
        <form method="POST" action="{{ route('admin.einstellungen.aktualisieren', 'impressum') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $gruppen['impressum']['version'] }}">
            <div class="form-card">
                <div class="form-card-title">⚖️ Impressum</div>
                <p class="field-hint">Adresse, Postadresse, Telefon und E-Mail werden automatisch von „Kontakt &amp; Stammdaten" oben übernommen.</p>
                <div class="field-row">
                    <label class="field-label" for="f-imp-verein">Vereinsname</label>
                    <input class="field-input" type="text" id="f-imp-verein" name="verein" value="{{ old('verein', $gruppen['impressum']['werte']['verein']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-imp-vertreten">Vertreten durch</label>
                    <input class="field-input" type="text" id="f-imp-vertreten" name="vertreten_durch" value="{{ old('vertreten_durch', $gruppen['impressum']['werte']['vertreten_durch']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-imp-registergericht">Registergericht</label>
                    <input class="field-input" type="text" id="f-imp-registergericht" name="registergericht" value="{{ old('registergericht', $gruppen['impressum']['werte']['registergericht']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-imp-registernummer">Registernummer</label>
                    <input class="field-input" type="text" id="f-imp-registernummer" name="registernummer" value="{{ old('registernummer', $gruppen['impressum']['werte']['registernummer']) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-imp-verantwortlich">Verantwortlich (§18)</label>
                    <textarea class="field-textarea" id="f-imp-verantwortlich" name="verantwortlich" rows="2">{{ old('verantwortlich', $gruppen['impressum']['werte']['verantwortlich']) }}</textarea>
                </div>
            </div>
            <div class="save-bar">
                <button type="submit" class="btn btn-primary">💾 Impressum speichern</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
