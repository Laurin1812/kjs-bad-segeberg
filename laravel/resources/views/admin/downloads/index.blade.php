{{--
    Phase 7E (Admin-Modul "Downloads", zentrale Download-Bibliothek).

    Rein server-gerendert (wie Phase 7B/7C/7D), EIN Panel fuer alle
    Kategorien + deren Downloads (kein separates Bearbeiten-Formular pro
    Eintrag - siehe Admin\DownloadsController-Klassenkommentar
    "Schreiblogik"/Analysebericht Punkt E "keine neue große UX erfinden,
    kleine Formulare sind ausdrücklich gewünscht"). $kategorien kommt bereits
    mit ihren Bibliotheks-Downloads (owner_type NULL) sortiert aus
    DownloadsController::index() - AUCH leere Kategorien werden gezeigt,
    damit der Admin sie befuellen kann (anders als die oeffentliche Seite).

    Jede Kategorie- bzw. Download-Aktion ist ein eigenes, kleines Formular
    (Kategorie umbenennen/löschen, Download bearbeiten/löschen/anlegen) -
    kein clientseitiges JS zum Live-Bearbeiten wie im Alt-Admin, dafuer ohne
    dessen "ein großes Formular, ein Speichern-Klick fuer alles"-Risiko.
--}}
<x-layouts.admin title="Downloads">
    <div class="panel-header">
        <h1>📥 Downloads</h1>
        <form method="POST" action="{{ route('admin.downloads.kategorien.anlegen') }}">
            @csrf
            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
            <button type="submit" class="btn btn-primary">➕ Kategorie hinzufügen</button>
        </form>
    </div>

    <div class="panel-body">
        @if (session('downloads_status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('downloads_status') }}</p>
        @endif
        @if (session('downloads_fehler'))
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ session('downloads_fehler') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        @forelse ($kategorien as $kategorie)
            <div class="form-card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
                    <form method="POST" action="{{ route('admin.downloads.kategorien.umbenennen', $kategorie) }}" style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:220px;">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                        <input class="field-input" style="flex:1;font-weight:600;" type="text" name="titel" value="{{ old('titel', $kategorie->titel) }}" maxlength="190" required>
                        <button type="submit" class="btn btn-outline btn-sm">💾 Speichern</button>
                    </form>
                    {{-- Security: Kategorie-Titel ist Nutzereingabe und landet hier in
                         einem JS-String-Kontext (onsubmit) - @js() erzeugt ein sicheres
                         JSON-Literal statt direkter Interpolation (siehe dasselbe Muster
                         in admin.aktuelles.index). --}}
                    <form method="POST" action="{{ route('admin.downloads.kategorien.loeschen', $kategorie) }}" onsubmit="return confirm('Kategorie „' + @js($kategorie->titel) + '“ und alle enthaltenen Downloads wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                        <button type="submit" class="btn btn-danger-outline btn-sm">🗑️ Kategorie löschen</button>
                    </form>
                </div>

                @forelse ($kategorie->downloads as $download)
                    <div class="item-card" style="flex-direction:column;align-items:stretch;margin-bottom:.75rem;">
                        <form method="POST" action="{{ route('admin.downloads.eintraege.aktualisieren', $download) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                            <div class="field-row-2">
                                <div>
                                    <label class="field-label">Titel</label>
                                    <input class="field-input" type="text" name="titel" value="{{ old('titel', $download->titel) }}" placeholder="Ohne Angabe: URL/Pfad wird als Titel verwendet">
                                </div>
                                <div>
                                    <label class="field-label">Beschreibung</label>
                                    <input class="field-input" type="text" name="beschreibung" value="{{ old('beschreibung', $download->beschreibung) }}" placeholder="optional">
                                </div>
                            </div>
                            <div class="field-row-2">
                                <div>
                                    <label class="field-label">URL / Dateipfad <span class="field-req">*</span></label>
                                    <input class="field-input" type="text" name="pfad" value="{{ old('pfad', $download->pfad) }}" required>
                                </div>
                                <div>
                                    <label class="field-label">Typ</label>
                                    <select class="field-select" name="typ">
                                        <option value="">(kein Typ)</option>
                                        @foreach ($typOptionen as $typ)
                                            <option value="{{ $typ }}" @selected((string) old('typ', $download->typ) === (string) $typ)>{{ $typ }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="item-actions" style="margin-top:.5rem;">
                                <button type="submit" class="btn btn-outline btn-sm">💾 Speichern</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.downloads.eintraege.loeschen', $download) }}" onsubmit="return confirm('Diesen Download wirklich löschen?');" style="margin-top:.4rem;">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                            <button type="submit" class="btn btn-danger-outline btn-sm">🗑️ Download löschen</button>
                        </form>
                    </div>
                @empty
                    <p class="hint-card" style="margin-bottom:.75rem;">Noch keine Downloads in dieser Kategorie.</p>
                @endforelse

                <div class="item-card" style="flex-direction:column;align-items:stretch;background:var(--admin-bg-muted, transparent);">
                    <div class="form-card-title" style="font-size:.85rem;">+ Download hinzufügen</div>
                    <form method="POST" action="{{ route('admin.downloads.eintraege.anlegen', $kategorie) }}">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                        <div class="field-row-2">
                            <div>
                                <label class="field-label">Titel</label>
                                <input class="field-input" type="text" name="titel" placeholder="Ohne Angabe: URL/Pfad wird als Titel verwendet">
                            </div>
                            <div>
                                <label class="field-label">Beschreibung</label>
                                <input class="field-input" type="text" name="beschreibung" placeholder="optional">
                            </div>
                        </div>
                        <div class="field-row-2">
                            <div>
                                <label class="field-label">URL / Dateipfad <span class="field-req">*</span></label>
                                <input class="field-input" type="text" name="pfad" required>
                            </div>
                            <div>
                                <label class="field-label">Typ</label>
                                <select class="field-select" name="typ">
                                    <option value="">(kein Typ)</option>
                                    @foreach ($typOptionen as $typ)
                                        <option value="{{ $typ }}">{{ $typ }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="item-actions" style="margin-top:.5rem;">
                            <button type="submit" class="btn btn-outline btn-sm">+ Download hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <p class="hint-card">Es sind noch keine Download-Kategorien vorhanden.</p>
        @endforelse
    </div>

    <div class="panel-body" style="margin-top:1.5rem;">
        <div class="form-card">
            <div class="form-card-title">⚙️ Titel &amp; Einleitungstext (Live-Seite)</div>
            <form method="POST" action="{{ route('admin.downloads.einstellungen') }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                <div class="field-row">
                    <label class="field-label" for="f-titel">Seitentitel</label>
                    <input class="field-input" type="text" id="f-titel" name="titel" value="{{ old('titel', $titel) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-intro">Einleitungstext</label>
                    <textarea class="field-textarea" id="f-intro" name="intro" rows="3">{{ old('intro', $intro) }}</textarea>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Speichern</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
