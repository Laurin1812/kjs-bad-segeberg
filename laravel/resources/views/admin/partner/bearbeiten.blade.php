{{--
    Phase 7F (Admin-Modul "Partner") - Anlegen/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route,
    identisch zum Muster von admin/aktuelles/bearbeiten.blade.php und
    admin/termine/bearbeiten.blade.php).

    Bildet GENAU die Felder ab, die admin.js' partnerEdit() bereits heute in
    einem einzigen, immer gleichen Formular zeigt (siehe PartnerController-
    Klassenkommentar) - mit zwei bewussten Abweichungen von der alten
    Vanilla-JS-Maske:

    - "Rahmenvertrag" ist hier eine Checkbox statt eines Freitextfelds (die
      DB-Spalte ist bereits seit Phase 2 ein echtes Boolean, siehe
      PartnerUpdater-Klassenkommentar).
    - "Logo" ist ein reines Pfad-Textfeld, kein Datei-Upload
      ("Medienauswahl folgt in einer späteren Phase", 1:1 wie bei Aktuelles).

    "Vorteile / Leistungen" bleibt bewusst Freitext (eine Zeile = ein
    Vorteil) - siehe PartnerUpdater-Klassenkommentar.

    Klassisches <form method="POST">: kein fetch()/keine JSON-Runtime
    noetig.
--}}
<x-layouts.admin :title="$istNeu ? 'Partner anlegen' : 'Partner bearbeiten'">
    <div class="panel-header">
        <h1>🤝 {{ $istNeu ? 'Partner anlegen' : 'Partner bearbeiten' }}</h1>
        <a class="btn btn-outline" href="{{ route('admin.partner.index') }}">← Zurück zur Liste</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.partner.store') : route('admin.partner.aktualisieren', $partner) }}">
            @csrf
            @unless ($istNeu)
                @method('PUT')
            @endunless
            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">

            <div class="form-card">
                <div class="field-row">
                    <label class="field-label" for="f-name">Name</label>
                    <input class="field-input" type="text" id="f-name" name="name" value="{{ old('name', $partner->name) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-logo">Logo (Bildpfad)</label>
                    <input class="field-input" type="text" id="f-logo" name="logo" value="{{ old('logo', $partner->logo) }}" placeholder="/images/...">
                    <p class="field-hint">Medienauswahl folgt in einer späteren Phase – hier wird nur der Bildpfad angezeigt/gespeichert.</p>
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Aktiv</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        {{-- Verdeckter "0"-Fallback VOR der Checkbox (siehe PartnerController::
                             validateData()-Kommentar "aktiv"/"rahmenvertrag"): eine unangehakte
                             Checkbox sendet in HTML-Formularen sonst GAR NICHTS, waere also von
                             einem Teil-Request, der dieses Feld bewusst weglaesst, nicht zu
                             unterscheiden. Mit diesem Fallback sendet ein echtes, vollstaendiges
                             Formular "aktiv" immer (entweder "0" oder, wenn angehakt, "1" -
                             Browser uebernehmen bei gleichem Namen den letzten Wert). --}}
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" id="f-aktiv" name="aktiv" value="1" @checked(old('aktiv', $partner->aktiv)) style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:.85rem;color:var(--admin-text-muted);">In der öffentlichen Übersicht sichtbar. Deaktiviert bleibt der Partner hier weiter bearbeitbar.</span>
                    </label>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kurzbeschreibung">Kurzbeschreibung / Kategorie</label>
                    <input class="field-input" type="text" id="f-kurzbeschreibung" name="kurzbeschreibung" value="{{ old('kurzbeschreibung', $partner->kurzbeschreibung) }}" placeholder='z.B. "Optik &amp; Zubehör" (erscheint auf der Kachel)'>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-beschreibung">Ausführliche Beschreibung</label>
                    <textarea class="field-textarea" id="f-beschreibung" name="beschreibung" rows="6">{{ old('beschreibung', $partner->beschreibung) }}</textarea>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📞 Kontakt (optional)</div>
                <div class="field-row">
                    <label class="field-label" for="f-ansprechpartner">Ansprechpartner</label>
                    <input class="field-input" type="text" id="f-ansprechpartner" name="ansprechpartner" value="{{ old('ansprechpartner', $partner->ansprechpartner) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-telefon">Telefonnummer</label>
                    <input class="field-input" type="text" id="f-telefon" name="telefon" value="{{ old('telefon', $partner->telefon) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-email">E-Mail</label>
                    <input class="field-input" type="text" id="f-email" name="email" value="{{ old('email', $partner->email) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-website">Website</label>
                    <input class="field-input" type="text" id="f-website" name="website" value="{{ old('website', $partner->website) }}" placeholder="https://...">
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📄 Weitere Angaben (optional)</div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Rahmenvertrag</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        {{-- Verdeckter "0"-Fallback, siehe Kommentar bei "Aktiv" oben. --}}
                        <input type="hidden" name="rahmenvertrag" value="0">
                        <input type="checkbox" id="f-rahmenvertrag" name="rahmenvertrag" value="1" @checked(old('rahmenvertrag', $partner->rahmenvertrag)) style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:.85rem;color:var(--admin-text-muted);">Mit der KJS besteht ein Rahmenvertrag.</span>
                    </label>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-vorteile">Vorteile / Leistungen</label>
                    <textarea class="field-textarea" id="f-vorteile" name="vorteile" rows="3">{{ old('vorteile', $vorteileText) }}</textarea>
                    <p class="field-hint">Eine Zeile = ein Vorteil.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-weitere_infos">Weitere Hinweise</label>
                    <textarea class="field-textarea" id="f-weitere_infos" name="weitere_infos" rows="3">{{ old('weitere_infos', $partner->weitere_infos) }}</textarea>
                </div>
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf „Speichern" übernommen.</span>
                @unless ($istNeu)
                    <button type="submit" form="partner-loeschen-{{ $partner->id }}" class="btn btn-danger-outline" onclick="return confirm('Diesen Partner wirklich löschen? Alternativ kann er über „Aktiv“ auch nur deaktiviert werden.');">🗑️ Löschen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.partner.index') }}">Abbrechen</a>
                <button type="submit" class="btn btn-primary">💾 Speichern</button>
            </div>
        </form>

        @unless ($istNeu)
            <form id="partner-loeschen-{{ $partner->id }}" method="POST" action="{{ route('admin.partner.loeschen', $partner) }}" style="display:none;">
                @csrf
                @method('DELETE')
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
            </form>
        @endunless
    </div>
</x-layouts.admin>
