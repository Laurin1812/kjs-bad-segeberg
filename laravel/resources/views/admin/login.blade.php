{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    Deutsche Login-Seite fuer den neuen Blade-Admin. Das <form> schickt ganz
    gewoehnliches POST direkt an Fortifys eigenen, bereits bestehenden
    Login-Endpunkt (config('fortify.paths.login') = "api/admin/auth/login",
    siehe config/fortify.php) - dieselbe Route, die admin.js seit der
    Netlify-Identity-Ablösung per fetch() anspricht. Fortifys eigene
    Login-Pipeline erledigt serverseitig ALLES Sicherheitsrelevante bereits
    von selbst, ganz ohne eigenen Code hier: CSRF (@csrf unten + "web"-
    Middleware-Gruppe), Rate-Limiting (config('fortify.limiters.login') =
    null -> Fortifys eingebauter 5-Versuche/60-Sekunden-Limiter),
    Session-Regeneration bei Erfolg (Laravel\Fortify\Actions\
    AttemptToAuthenticate). Bei falschen Zugangsdaten liefert Fortifys
    LoginRequest einen normalen 302-Redirect zurueck hierher samt
    $errors-Bag (kein JSON, da dieses Formular kein "wantsJson()" sendet) -
    siehe @error('email') unten.
--}}
<x-layouts.admin-auth title="Anmeldung">
<div id="login-screen">
    <div class="login-box">
        <div class="login-icon" aria-hidden="true">🦌</div>
        <h1>KJS Admin</h1>
        <p class="login-sub">Bitte melden Sie sich an.</p>

        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1rem;">{{ session('status') }}</p>
        @endif

        @error('email')
            <p class="login-error" style="margin-bottom:1rem;">{{ $message }}</p>
        @enderror

        <form id="login-form" method="POST" action="{{ url(config('fortify.paths.login')) }}">
            @csrf
            <div>
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Anmelden</button>
        </form>

        <p class="login-hint">
            <a href="{{ route('admin.password.request') }}">Passwort vergessen?</a>
        </p>
        <p class="login-hint">
            <a href="{{ url('/') }}">← Zurück zur Website</a>
        </p>
    </div>
</div>
</x-layouts.admin-auth>
