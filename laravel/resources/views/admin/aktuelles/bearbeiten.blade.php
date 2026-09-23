{{--
    Phase 7C (Admin-Modul "Aktuelles") - Anlegen-/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route).

    Bildet GENAU die neun fachlichen Felder ab, die admin.js' aktuellesEdit()
    bereits heute in einem einzigen, immer gleichen Formular zeigt (siehe
    AktuellesController-Klassenkommentar) - keine seitentyp-abhaengigen
    Bloecke wie bei "Inhalte/Seiten" (Phase 7B), da Aktuelles-Beitraege alle
    dieselbe Form haben.

    "Text" ist bewusst ein einfaches Markdown-Textfeld (keine TipTap-
    Instanz): admin.js' fMarkdown()/initMDE() speichert hier tatsaechlich
    Markdown-Quelltext (Beitrag::$text wird oeffentlich erst beim Anzeigen
    ueber Illuminate\Support\Str::markdown() in HTML umgewandelt, siehe
    AktuellesController (oeffentlich)::show()) - anders als Seiteninhalt bei
    Phase 7B, der bereits HTML ist. Ein Rich-Text-Editor wuerde hier
    HTML statt Markdown speichern und die Bedeutung des Felds veraendern -
    deshalb bewusst ein normales <textarea>, keine zweite Editor-Bibliothek.
    Klassisches <form method="POST">: kein fetch()/keine JSON-Runtime noetig.
--}}
<x-layouts.admin :title="$beitrag->titel ?: 'Aktuelles'">
    <div class="panel-header">
        <h1>{{ $istNeu ? '➕ Neuer Beitrag' : '✏️ '.($beitrag->titel ?: '(ohne Titel)') }}</h1>
        <div class="topbar-actions" style="gap:.5rem;">
            @if ($previewUrl)
                {{-- Nur eine echte, bereits vorhandene oeffentliche Route - siehe
                     routes/web.php "aktuelles.show" (nur bei bestehenden Beitraegen,
                     ein neuer Beitrag hat noch keinen Slug/keine oeffentliche Seite). --}}
                <a class="btn btn-outline btn-sm" href="{{ $previewUrl }}" target="_blank" rel="noopener">🌐 Beitrag ansehen</a>
            @endif
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.aktuelles.index') }}">← Zur Liste</a>
        </div>
    </div>

    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.aktuelles.store') : route('admin.aktuelles.update', $beitrag) }}" data-beitrag-form>
            @csrf
            @unless ($istNeu)
                @method('PUT')
            @endunless
            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">

            <div class="form-card">
                <div class="form-card-title">Beitrag</div>
                <div class="field-row">
                    <label class="field-label" for="f-titel">Titel <span class="field-req">*</span></label>
                    <input class="field-input @error('titel') field-input--invalid @enderror" type="text" id="f-titel" name="titel" value="{{ old('titel', $beitrag->titel) }}">
                    @error('titel')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-datum">Datum</label>
                    <input class="field-input @error('datum') field-input--invalid @enderror" type="date" id="f-datum" name="datum" value="{{ old('datum', $beitrag->datum?->toDateString()) }}" style="max-width:220px">
                    @error('datum')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-jahr">Erscheinungsjahr</label>
                    <input class="field-input" type="number" id="f-jahr" name="jahr" value="{{ old('jahr', $beitrag->jahr) }}" style="max-width:140px">
                    <p class="field-hint">Bestimmt, in welchem Archiv-Jahr der Beitrag einsortiert wird (unabhängig vom Datum oben). Normalerweise gleich dem Jahr des Datums.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kategorie_id">Kategorie</label>
                    <select class="field-select @error('kategorie_id') field-input--invalid @enderror" id="f-kategorie_id" name="kategorie_id">
                        <option value="">(keine Kategorie)</option>
                        @foreach ($kategorien as $kategorie)
                            <option value="{{ $kategorie->id }}" @selected((string) old('kategorie_id', $beitrag->kategorie_id) === (string) $kategorie->id)>{{ $kategorie->name }}</option>
                        @endforeach
                    </select>
                    @error('kategorie_id')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                    <p class="field-hint">Neue Kategorie anlegen oder eine ungenutzte löschen: <a href="{{ route('admin.aktuelles.index') }}#kategorien">Kategorien-Verwaltung in der Beitragsliste</a>.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-bild">Bild (Bildpfad)</label>
                    <input class="field-input" type="text" id="f-bild" name="bild" value="{{ old('bild', $beitrag->bild) }}" placeholder="/images/...">
                    <p class="field-hint">Medienauswahl folgt in einer späteren Phase – hier wird nur der Bildpfad angezeigt/gespeichert.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-text">Text (Markdown)</label>
                    <textarea class="field-textarea" id="f-text" name="text" rows="10">{{ old('text', $beitrag->text) }}</textarea>
                    <p class="field-hint">Markdown-Formatierung (z.B. **fett**, [Link](https://...)). Wird auf der Beitragsseite automatisch in Text umgewandelt.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-link">Externer Link (optional)</label>
                    <input class="field-input" type="text" id="f-link" name="link" value="{{ old('link', $beitrag->link) }}" placeholder="https://...">
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Ins Archiv verschieben</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" id="f-archiviert" name="archiviert" value="1" @checked(old('archiviert', $beitrag->archiviert)) style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:.85rem;color:var(--admin-text-muted);">Erscheint nicht mehr in der Beitragsliste der Hauptseite, bleibt über den direkten Link weiter erreichbar</span>
                    </label>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">📁 Dokumente &amp; Downloads</div>
                @php
                    // old(...) nur beim erneuten Anzeigen nach einem Validierungsfehler
                    // gesetzt - dort NICHT nochmals mit +3 auffuellen (siehe Phase 7B,
                    // gleiches Muster), sonst wuerden bei mehreren Fehlversuchen
                    // hintereinander immer mehr leere Zeilen entstehen.
                    $downloadZeilen = old('downloads');
                    if ($downloadZeilen === null) {
                        $downloadZeilen = $istNeu ? [] : $beitrag->downloads->map(fn ($d) => ['titel' => $d->titel, 'datei' => $d->pfad, 'vorschau' => $d->vorschau])->all();
                        $downloadZeilen = array_pad($downloadZeilen, count($downloadZeilen) + 3, ['titel' => '', 'datei' => '', 'vorschau' => '']);
                    }
                @endphp
                @foreach ($downloadZeilen as $i => $zeile)
                    <div class="item-card">
                        <div class="item-body">
                            <input class="field-input" type="text" name="downloads[{{ $i }}][titel]" value="{{ $zeile['titel'] ?? '' }}" placeholder="Titel des Dokuments" style="margin-bottom:.5rem;">
                            <input class="field-input" type="text" name="downloads[{{ $i }}][datei]" value="{{ $zeile['datei'] ?? '' }}" placeholder="Dateipfad (z.B. /downloads/...)" style="margin-bottom:.5rem;">
                            <input class="field-input" type="text" name="downloads[{{ $i }}][vorschau]" value="{{ $zeile['vorschau'] ?? '' }}" placeholder="Vorschaubild-Pfad (optional)">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="form-card">
                <div class="form-card-title">🖼️ Bildergalerie</div>
                <div class="field-row">
                    <label class="field-label" for="f-galerie_titel">Überschrift der Galerie</label>
                    <input class="field-input" type="text" id="f-galerie_titel" name="galerie_titel" value="{{ old('galerie_titel', $beitrag->galerie_titel) }}" placeholder="Bildergalerie">
                </div>
                @php
                    $galerieZeilen = old('galerie');
                    if ($galerieZeilen === null) {
                        $galerieZeilen = $istNeu ? [] : $beitrag->galerieBilder->map(fn ($g) => ['bild' => $g->pfad, 'titel' => $g->titel])->all();
                        $galerieZeilen = array_pad($galerieZeilen, count($galerieZeilen) + 3, ['bild' => '', 'titel' => '']);
                    }
                @endphp
                @foreach ($galerieZeilen as $i => $zeile)
                    <div class="item-card">
                        <div class="item-body">
                            <input class="field-input" type="text" name="galerie[{{ $i }}][bild]" value="{{ $zeile['bild'] ?? '' }}" placeholder="Bildpfad" style="margin-bottom:.5rem;">
                            <input class="field-input" type="text" name="galerie[{{ $i }}][titel]" value="{{ $zeile['titel'] ?? '' }}" placeholder="Beschriftung (optional)">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf „Speichern" übernommen.</span>
                @unless ($istNeu)
                    <button type="submit" form="beitrag-loeschen-{{ $beitrag->id }}" class="btn btn-danger-outline" onclick="return confirm('Diesen Beitrag wirklich löschen? Das kann nicht rückgängig gemacht werden.');">🗑️ Löschen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.aktuelles.index') }}">Abbrechen</a>
                <button type="submit" class="btn btn-primary">💾 Speichern</button>
            </div>
        </form>

        @unless ($istNeu)
            {{-- Eigenstaendiges Formular (statt eines <button formaction>) fuer den
                 Loeschen-Knopf oben: DELETE ist eine andere HTTP-Methode als das
                 Haupt-POST-Formular und braucht daher @method('DELETE') in einem
                 eigenen Formular - der Knopf oben referenziert es per form="...". --}}
            <form method="POST" action="{{ route('admin.aktuelles.loeschen', $beitrag) }}" id="beitrag-loeschen-{{ $beitrag->id }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
            </form>
        @endunless
    </div>
</x-layouts.admin>
