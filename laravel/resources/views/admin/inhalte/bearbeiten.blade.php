{{--
    Phase 7B (Admin-Modul "Inhalte/Seiten") - Bearbeiten-Formular.

    Bildet exakt die Felder ab, die admin.js' renderStandard()/collectStandard()
    fuer dieselbe Seitenart anzeigt (siehe InhalteController-Klassenkommentar
    und Abschlussbericht Punkt 4) - keine generische "alles editierbar"-Maske.
    Klassisches <form method="POST"> mit @method('PUT'): kein fetch()/keine
    JSON-Runtime noetig (Auftrag Teil 9).
--}}
<x-layouts.admin :title="$page->titel ?: $seitentyp">
    <div class="panel-header">
        <h1>✏️ {{ $page->titel ?: '(ohne Titel)' }}</h1>
        <div class="topbar-actions" style="gap:.5rem;">
            <span class="seitentyp-badge">{{ $seitentyp }} · {{ $bereich }}</span>
            @if ($previewUrl)
                {{-- Auftrag Teil 7: nur eine echte, bereits vorhandene oeffentliche
                     Route - siehe InhalteController::publicUrl(). --}}
                <a class="btn btn-outline btn-sm" href="{{ $previewUrl }}" target="_blank" rel="noopener">🌐 Seite ansehen</a>
            @endif
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.inhalte.index') }}">← Zur Liste</a>
        </div>
    </div>

    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        <form method="POST" action="{{ route('admin.inhalte.update', $page) }}" data-richtext-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">

            <div class="form-card">
                <div class="form-card-title">Seiteninhalt</div>
                <div class="field-row">
                    <label class="field-label" for="f-titel">Seitentitel <span class="field-req">*</span></label>
                    <input class="field-input @error('titel') field-input--invalid @enderror" type="text" id="f-titel" name="titel" value="{{ old('titel', $page->titel) }}">
                    <p class="field-hint">Die große Überschrift ganz oben auf der Seite.</p>
                    @error('titel')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>

                @if ($istDynamisch)
                    <div class="field-row">
                        <label class="field-label" for="f-nav_label">Menü-Bezeichnung</label>
                        <input class="field-input" type="text" id="f-nav_label" name="nav_label" value="{{ old('nav_label', $page->nav_label) }}">
                        <p class="field-hint">Wie die Seite im Menü und in der Seitenleiste heißt. Meist wie der Seitentitel.</p>
                    </div>
                @endif

                @include('admin.inhalte._richtext-field', ['field' => 'untertitel', 'label' => 'Untertitel', 'value' => $page->untertitel, 'hint' => 'Kurzer Text direkt unter dem Titel. Optional.'])
                @include('admin.inhalte._richtext-field', ['field' => 'intro', 'label' => 'Einleitungstext', 'value' => $page->intro, 'hint' => 'Der erste Textblock der Seite, oberhalb des Hauptinhalts.'])
                @include('admin.inhalte._richtext-field', ['field' => 'inhalt', 'label' => 'Textinhalt', 'value' => $page->inhalt, 'hint' => 'Der Haupttext der Seite.'])
            </div>

            <div class="form-card">
                <div class="form-card-title">Bilder</div>
                <div class="field-row">
                    <label class="field-label" for="f-hero_bild">Hero-Hintergrundbild (Bildpfad)</label>
                    <input class="field-input" type="text" id="f-hero_bild" name="hero_bild" value="{{ old('hero_bild', $page->hero_bild) }}" placeholder="/images/...">
                    <p class="field-hint">Das große Bild im Kopfbereich. Medienauswahl folgt in einer späteren Phase – hier wird nur der Bildpfad angezeigt/gespeichert.</p>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-bild">Inhaltsbild (Bildpfad)</label>
                    <input class="field-input" type="text" id="f-bild" name="bild" value="{{ old('bild', $page->bild) }}" placeholder="/images/...">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-bild_groesse">Bildgröße</label>
                    <select class="field-select" id="f-bild_groesse" name="bild_groesse">
                        @foreach (['img-25' => '25%', 'img-50' => '50%', 'img-75' => '75%', 'img-100' => '100%'] as $wert => $label)
                            <option value="{{ $wert }}" @selected(old('bild_groesse', $page->bild_groesse ?: 'img-100') === $wert)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-bild_alt">Bild-Beschreibung</label>
                    <input class="field-input" type="text" id="f-bild_alt" name="bild_alt" value="{{ old('bild_alt', $page->bild_alt) }}">
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-title">Kontakt (optional)</div>
                <div class="field-row">
                    <label class="field-label" for="f-kontakt_name">Kontaktname</label>
                    <input class="field-input" type="text" id="f-kontakt_name" name="kontakt_name" value="{{ old('kontakt_name', $page->kontakt_name) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-kontakt_email">Kontakt E-Mail</label>
                    <input class="field-input @error('kontakt_email') field-input--invalid @enderror" type="text" id="f-kontakt_email" name="kontakt_email" value="{{ old('kontakt_email', $page->kontakt_email) }}">
                    @error('kontakt_email')
                        <p class="kjs-admin-field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if ($hatUnterseitenSystem)
                <div class="form-card">
                    <div class="form-card-title">📄 Unterseiten-Kasten (Seitenleiste)</div>
                    <div class="field-row">
                        <label class="field-label" for="f-unterseiten_titel">Überschrift des Kastens</label>
                        <input class="field-input" type="text" id="f-unterseiten_titel" name="unterseiten_titel" value="{{ old('unterseiten_titel', $page->unterseiten_titel) }}" placeholder="Unterseiten zu {{ $page->titel }}">
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-title">🔗 Link-Liste (Seitenleiste)</div>
                    <div class="field-row">
                        <label class="field-label" for="f-linkliste_titel">Überschrift der Linkliste</label>
                        <input class="field-input" type="text" id="f-linkliste_titel" name="linkliste_titel" value="{{ old('linkliste_titel', $page->linkliste_titel) }}" placeholder="Weiterführende Links">
                    </div>
                    @php
                        // old(...) nur beim erneuten Anzeigen nach einem Validierungsfehler
                        // gesetzt - dort NICHT nochmals mit +3 auffuellen (sonst wuerden bei
                        // mehreren Fehlversuchen hintereinander immer mehr leere Zeilen
                        // entstehen), sondern exakt das zurueckgeben, was zuletzt abgeschickt
                        // wurde (inkl. der vom Nutzer noch ungenutzt gelassenen Leerzeilen).
                        $linkZeilen = old('linkliste');
                        if ($linkZeilen === null) {
                            $linkZeilen = $page->links->map(fn ($l) => ['titel' => $l->label, 'url' => $l->href])->all();
                            $linkZeilen = array_pad($linkZeilen, count($linkZeilen) + 3, ['titel' => '', 'url' => '']);
                        }
                    @endphp
                    @foreach ($linkZeilen as $i => $zeile)
                        <div class="item-card">
                            <div class="item-body">
                                <input class="field-input" type="text" name="linkliste[{{ $i }}][titel]" value="{{ $zeile['titel'] ?? '' }}" placeholder="Beschriftung" style="margin-bottom:.5rem;">
                                <input class="field-input" type="text" name="linkliste[{{ $i }}][url]" value="{{ $zeile['url'] ?? '' }}" placeholder="https://... oder /weitere/...">
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="form-card">
                <div class="form-card-title">📁 Dokumente &amp; Downloads</div>
                @php
                    // Siehe Kommentar bei $linkZeilen oben (kein wiederholtes Auffuellen bei old()).
                    $downloadZeilen = old('downloads');
                    if ($downloadZeilen === null) {
                        $downloadZeilen = $page->downloads->map(fn ($d) => ['titel' => $d->titel, 'datei' => $d->pfad, 'vorschau' => $d->vorschau])->all();
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
                    <input class="field-input" type="text" id="f-galerie_titel" name="galerie_titel" value="{{ old('galerie_titel', $page->galerie_titel) }}" placeholder="Bildergalerie">
                </div>
                @php
                    // Siehe Kommentar bei $linkZeilen oben (kein wiederholtes Auffuellen bei old()).
                    $galerieZeilen = old('galerie');
                    if ($galerieZeilen === null) {
                        $galerieZeilen = $page->galerieBilder->map(fn ($g) => ['bild' => $g->pfad, 'titel' => $g->titel])->all();
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

            @if ($zeigeAntragUrl)
                <div class="form-card">
                    <div class="form-card-title">Mitgliedsantrag</div>
                    <div class="field-row">
                        <label class="field-label" for="f-antrag_url">Mitgliedsantrag-URL</label>
                        <input class="field-input" type="text" id="f-antrag_url" name="antrag_url" value="{{ old('antrag_url', $page->antrag_url) }}" placeholder="https://...">
                    </div>
                </div>
            @endif

            @if ($zeigeHundeboerseCta)
                <div class="form-card">
                    <div class="form-card-title">🐕 Hundebörse-Verweis (Seitenleiste)</div>
                    <div class="field-row">
                        <label class="field-label" for="f-hundeboerse_cta_titel">Überschrift</label>
                        <input class="field-input" type="text" id="f-hundeboerse_cta_titel" name="hundeboerse_cta_titel" value="{{ old('hundeboerse_cta_titel', $page->hundeboerse_cta_titel) }}" placeholder="Hundebörse">
                    </div>
                    <div class="field-row">
                        <label class="field-label" for="f-hundeboerse_cta_text">Text</label>
                        <input class="field-input" type="text" id="f-hundeboerse_cta_text" name="hundeboerse_cta_text" value="{{ old('hundeboerse_cta_text', $page->hundeboerse_cta_text) }}">
                    </div>
                    <div class="field-row">
                        <label class="field-label" for="f-hundeboerse_cta_button">Button-Beschriftung</label>
                        <input class="field-input" type="text" id="f-hundeboerse_cta_button" name="hundeboerse_cta_button" value="{{ old('hundeboerse_cta_button', $page->hundeboerse_cta_button) }}" placeholder="ZUR HUNDEBÖRSE →">
                    </div>
                </div>
            @endif

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf „Speichern" übernommen.</span>
                <a class="btn btn-ghost" href="{{ route('admin.inhalte.index') }}">Abbrechen</a>
                <button type="submit" class="btn btn-primary">💾 Speichern</button>
            </div>
        </form>
    </div>

    @vite(['resources/js/admin-inhalte-editor.js'])
</x-layouts.admin>
