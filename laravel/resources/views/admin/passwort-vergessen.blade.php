{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    "Passwort vergessen"-Formular. POST geht direkt an Fortifys bestehenden
    Endpunkt config('fortify.paths.password.email') - dieser verschickt bei
    Erfolg (Password::RESET_LINK_SENT) eine Reset-Mail mit einem Link auf
    admin.password.reset (siehe AuthPageController::passwortZuruecksetzen())
    und zeigt die Erfolgsmeldung per session('status') hier auf derselben
    Seite an (Laravel\Fortify\Http\Responses\
    SuccessfulPasswordResetLinkRequestResponse::toResponse() -> back()).
    Ohne konfigurierten Mailversand (siehe KontaktController-Analogie:
    MAIL_MAILER) schlaegt der eigentliche Versand fehl, der Grundweg
    (Formular -> Broker -> Redirect-mit-Meldung) funktioniert aber
    unabhaengig davon bereits vollstaendig - "Passwort-Reset-Grundweg,
    sofern technisch [...] sauber moeglich" (Auftrag Teil 2).
--}}
<x-layouts.admin-auth title="Passwort vergessen">
<div id="login-screen">
    <div class="login-box">
        <div class="login-icon" aria-hidden="true">🔑</div>
        <h1>Passwort vergessen</h1>
        <p class="login-sub">Wir senden Ihnen einen Link zum Zurücksetzen des Passworts per E-Mail.</p>

        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1rem;">{{ session('status') }}</p>
        @endif

        @error('email')
            <p class="login-error" style="margin-bottom:1rem;">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ url(config('fortify.paths.password.email')) }}">
            @csrf
            <div>
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Link anfordern</button>
        </form>

        <p class="login-hint">
            <a href="{{ route('admin.login') }}">← Zurück zur Anmeldung</a>
        </p>
    </div>
</div>
</x-layouts.admin-auth>
