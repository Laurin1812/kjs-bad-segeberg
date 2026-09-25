{{--
    Phase 7L (Admin-Modul "Benutzer"), Teil B - Anlegen/Bearbeiten-Formular
    (gemeinsame View fuer beide Faelle, $istNeu unterscheidet Ziel-Route,
    identisch zum Muster von admin/partner/bearbeiten.blade.php).

    "Administrator" ist eine einzelne Checkbox statt eines freien Rollen-
    Feldes (siehe BenutzerController-Klassenkommentar "keine frei erfundenen
    Rollen") - server-seitig ohnehin die einzige Rolle, die der neue
    Blade-Admin ueberhaupt prueft. Passwortfelder sind beim Bearbeiten
    bewusst leer vorbelegt und optional (siehe Klassenkommentar "PASSWORT").
--}}
<x-layouts.admin :title="$istNeu ? 'Benutzer anlegen' : 'Benutzer bearbeiten'">
    <div class="panel-header">
        <h1>👤 {{ $istNeu ? 'Benutzer anlegen' : 'Benutzer bearbeiten' }}</h1>
        <a class="btn btn-outline" href="{{ route('admin.benutzer.index') }}">← Zurück zur Liste</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ $istNeu ? route('admin.benutzer.speichern') : route('admin.benutzer.aktualisieren', $benutzer) }}">
            @csrf
            @unless ($istNeu)
                @method('PUT')
            @endunless

            <div class="form-card">
                <div class="field-row">
                    <label class="field-label" for="f-name">Name</label>
                    <input class="field-input" type="text" id="f-name" name="name" value="{{ old('name', $benutzer->name) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-email">E-Mail (Login)</label>
                    <input class="field-input" type="email" id="f-email" name="email" value="{{ old('email', $benutzer->email) }}">
                </div>
                <div class="field-row" style="align-items:center;gap:.75rem;flex-direction:row;">
                    <label class="field-label" style="min-width:160px;margin:0">Administrator</label>
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        {{-- Verdeckter "0"-Fallback VOR der Checkbox (siehe PartnerController::
                             validateData()-Kommentar) - eine unangehakte Checkbox sendet sonst
                             gar nichts. --}}
                        <input type="hidden" name="ist_admin" value="0">
                        <input type="checkbox" id="f-ist-admin" name="ist_admin" value="1" @checked(old('ist_admin', $istAdmin)) style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:.85rem;color:var(--admin-text-muted);">Voller Zugriff auf den gesamten Admin-Bereich.</span>
                    </label>
                </div>
                @if ($istEigenerAccount)
                    <p class="field-hint">Das ist Ihr eigener Account - Sie können sich hier nicht selbst die Administrator-Rolle entziehen.</p>
                @elseif ($istLetzterAdmin)
                    <p class="field-hint">Das ist der letzte verbleibende Administrator - die Rolle kann nicht entzogen werden, solange kein anderer Administrator existiert.</p>
                @endif
            </div>

            <div class="form-card">
                <div class="form-card-title">🔑 Passwort</div>
                @if ($istNeu)
                    <div class="field-row">
                        <label class="field-label" for="f-password">Passwort</label>
                        <input class="field-input" type="password" id="f-password" name="password" autocomplete="new-password">
                    </div>
                    <div class="field-row">
                        <label class="field-label" for="f-password-confirmation">Passwort wiederholen</label>
                        <input class="field-input" type="password" id="f-password-confirmation" name="password_confirmation" autocomplete="new-password">
                    </div>
                @else
                    <div class="field-row">
                        <label class="field-label" for="f-password">Neues Passwort</label>
                        <input class="field-input" type="password" id="f-password" name="password" autocomplete="new-password">
                        <p class="field-hint">Leer lassen, um das bestehende Passwort zu behalten.</p>
                    </div>
                    <div class="field-row">
                        <label class="field-label" for="f-password-confirmation">Neues Passwort wiederholen</label>
                        <input class="field-input" type="password" id="f-password-confirmation" name="password_confirmation" autocomplete="new-password">
                    </div>
                @endif
            </div>

            <div class="save-bar">
                <span class="save-status">Änderungen werden erst nach dem Klick auf „Speichern" übernommen.</span>
                @unless ($istNeu || $istEigenerAccount || ($istAdmin && $istLetzterAdmin))
                    <button type="submit" form="benutzer-loeschen-{{ $benutzer->id }}" class="btn btn-danger-outline" onclick="return confirm('Benutzer „' + @js($benutzer->name) + '“ (' + @js($benutzer->email) + ') wirklich löschen?');">🗑️ Löschen</button>
                @endunless
                <a class="btn btn-ghost" href="{{ route('admin.benutzer.index') }}">Abbrechen</a>
                <button type="submit" class="btn btn-primary">💾 Speichern</button>
            </div>
        </form>

        @unless ($istNeu || $istEigenerAccount || ($istAdmin && $istLetzterAdmin))
            <form id="benutzer-loeschen-{{ $benutzer->id }}" method="POST" action="{{ route('admin.benutzer.loeschen', $benutzer) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        @endunless
    </div>
</x-layouts.admin>
