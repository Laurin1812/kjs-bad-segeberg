{{--
    Phase 7B - ein Rich-Text-Feld (untertitel/intro/inhalt), siehe
    resources/js/admin-inhalte-editor.js fuer die TipTap-Anbindung.

    Erwartet: $field (Feldname), $label, $value (aktueller HTML-Inhalt),
    optional $hint.
--}}
@php $htmlWert = old($field, $value); @endphp
<div class="field-row">
    <label class="field-label" for="f-{{ $field }}">{{ $label }}</label>
    <div class="richtext-toolbar" data-richtext-toolbar="{{ $field }}" hidden>
        <button type="button" data-cmd="bold" title="Fett"><strong>F</strong></button>
        <button type="button" data-cmd="italic" title="Kursiv"><em>K</em></button>
        <button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>
        <button type="button" data-cmd="h2" title="Überschrift 2">H2</button>
        <button type="button" data-cmd="h3" title="Überschrift 3">H3</button>
        <button type="button" data-cmd="ul" title="Aufzählung">• Liste</button>
        <button type="button" data-cmd="ol" title="Nummerierte Liste">1. Liste</button>
        <button type="button" data-cmd="link" title="Link setzen/entfernen">🔗 Link</button>
        <button type="button" data-cmd="table" title="Tabelle einfügen">⊞ Tabelle</button>
    </div>
    <div class="richtext-box" id="richtext-{{ $field }}" data-richtext="{{ $field }}" hidden></div>
    <textarea class="field-textarea @error($field) field-textarea--invalid @enderror" id="f-{{ $field }}" name="{{ $field }}" rows="6">{{ $htmlWert }}</textarea>
    @isset($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endisset
    @error($field)
        <p class="kjs-admin-field-error">{{ $message }}</p>
    @enderror
</div>
