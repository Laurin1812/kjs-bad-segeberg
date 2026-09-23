{{--
    Phase 7D (Admin-Modul "Termine") - Anlegen-/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route).

    Bildet GENAU die neun fachlichen Felder ab, die admin.js' termineEdit()
    bereits heute in einem einzigen, immer gleichen Formular zeigt (siehe
    Admin\TermineController-Klassenkommentar) - keine Kategorie-Anlegen/
    Loeschen-Buttons (bewusste Nutzer-Entscheidung Phase 7D, siehe dortiger
    Kommentar "Kategorien") und kein strikter Pflicht-Dropdown fuer "Revier"
    (freie Texteingabe mit Vorschlaegen, wie im Alt-Admin).
--}}
<x-layouts.admin :title="$termin->veranstaltung ?: 'Termine'">
    <div class="panel-header">
        <h1>{{ $istNeu ? '➕ Neuer Termin' : '✏️ '.($termin->veranstaltung ?: '(ohne Titel)') }}</h1>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.termine.index') }}">← Zur Liste</a>
    </div>

    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.termine.store') : route('admin.termine.update', $termin) }}">
            @csrf
            @unless ($istNeu)
                @method('PUT')
            @endunless
            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">

            <div class="form-card">
                <div class="form-card-title">Termin</div>
                <div class="field-row">
                    <label class="field-label" for="f-datum">Datum <span class="field-req">*</span></label>
                    <input class="field-input @error('datum') field-input--invalid @enderror" type="date" id="f-datum" name="datum" value="{{ old('datum', $termin->datum?->toDateString()) }}" style="max-width:220px">
                    @error('datum')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-uhrzeit">Uhrzeit</label>
                    <input class="field-input" type="text" id="f-uhrzeit" name="uhrzeit" value="{{ old('uhrzeit', $termin->uhrzeit) }}" placeholder="z.B. 18:00 Uhr" style="max-width:220px">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-veranstaltung">Veranstaltung</label>
                    <input class="field-input @error('veranstaltung') field-input--invalid @enderror" type="text" id="f-veranstaltung" name="veranstaltung" value="{{ old('veranstaltung', $termin->veranstaltung) }}">
                    @error('veranstaltung')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-strasse">Straße</label>
                    <input class="field-input" type="text" id="f-strasse" name="strasse" value="{{ old('strasse', $termin->strasse) }}" placeholder="optional">
                </div>
                <div class="field-row field-row-2">
                    <div>
                        <label class="field-label" for="f-plz">PLZ</label>
                        <input class="field-input" type="text" id="f-plz" name="plz" value="{{ old('plz', $termin->plz) }}">
                    </div>
                    <div>
                        <label class="field-label" for="f-ort">Ort</label>
                        <input class="field-input" type="text" id="f-ort" name="ort" value="{{ old('ort', $termin->ort) }}">
                    </div>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-revier">Revier / Hegering</label>
                    <input class="field-input" list="dl-revier" type="text" id="f-revier" name="revier" value="{{ old('revier', $termin->revier) }}" placeholder="Auswählen oder eigenen Text eingeben">
                    <datalist id="dl-revier">
                        @foreach ($hegeringOptionen as $option)
                            <option value="{{ $option }}">
                        @endforeach
                    </datalist>
                    <p class="field-hint">Vorschläge aus der Liste oder eigenen Text eingeben.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kategorie">Kategorie</label>
                    <select class="field-select" id="f-kategorie" name="kategorie">
                        <option value="">(keine Kategorie)</option>
                        @foreach ($kategorien as $kategorie)
                            <option value="{{ $kategorie }}" @selected((string) old('kategorie', $termin->kategorie) === (string) $kategorie)>{{ $kategorie }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Ins Archiv verschieben</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" id="f-archiviert" name="archiviert" value="1" @checked(old('archiviert', $termin->archiviert)) style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:.85rem;color:var(--admin-text-muted);">Erscheint nicht mehr auf der Live-Seite (zusätzlich werden Termine automatisch 7 Tage nach ihrem Datum ausgeblendet)</span>
                    </label>
                </div>
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf „Speichern" übernommen.</span>
                @unless ($istNeu)
                    <button type="submit" form="termin-loeschen-{{ $termin->id }}" class="btn btn-danger-outline" onclick="return confirm('Diesen Termin wirklich löschen?');">🗑️ Löschen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.termine.index') }}">Abbrechen</a>
                <button type="submit" class="btn btn-primary">💾 Speichern</button>
            </div>
        </form>

        @unless ($istNeu)
            <form method="POST" action="{{ route('admin.termine.loeschen', $termin) }}" id="termin-loeschen-{{ $termin->id }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
            </form>
        @endunless
    </div>
</x-layouts.admin>
