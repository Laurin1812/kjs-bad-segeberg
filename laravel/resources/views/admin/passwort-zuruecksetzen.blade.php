{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    Formular fuer den eigentlichen Passwort-Reset. $token/$email kommen aus
    dem Link der Reset-Mail (siehe AuthPageController::
    passwortZuruecksetzen()) und werden 1:1 an Fortifys bestehenden
    Endpunkt config('fortify.paths.password.update') weitergereicht -
    dieselben drei Felder, die Laravel\Fortify\Http\Controllers\
    NewPasswordController::store() erwartet (token/email/password[
    _confirmation]). Bei Erfolg landet der Redakteur dank der
    "password-reset"-Umleitung in config/fortify.php direkt (mit
    Erfolgsmeldung) auf der Login-Seite.
--}}
<x-layouts.admin-auth title="Passwort zurücksetzen">
<div id="login-screen">
    <div class="login-box">
        <div class="login-icon" aria-hidden="true">🔑</div>
        <h1>Neues Passwort</h1>
        <p class="login-sub">Bitte vergeben Sie ein neues Passwort.</p>

        @error('email')
            <p class="login-error" style="margin-bottom:1rem;">{{ $message }}</p>
        @enderror
        @error('password')
            <p class="login-error" style="margin-bottom:1rem;">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ url(config('fortify.paths.password.update')) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password">Neues Passwort</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation">Neues Passwort bestätigen</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Passwort speichern</button>
        </form>

        <p class="login-hint">
            <a href="{{ route('admin.login') }}">← Zurück zur Anmeldung</a>
        </p>
    </div>
</div>
</x-layouts.admin-auth>
