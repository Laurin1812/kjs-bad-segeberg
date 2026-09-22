{{--
    Phase 3 Nacharbeit (100%-Laravel-Architektur-Korrektur).

    Wiederverwendbare globale Kontakt-/Geschaeftsstellenbox - ersetzt die
    zuvor in mehreren Phase-3-Views duplizierten, hart codierten Werte
    ("info@kjs-bad-segeberg.de", "04551 / 12 34 56"). Laedt die zentralen
    Kontaktdaten stattdessen direkt aus der "settings"-Tabelle (Gruppe
    "einstellungen", genau dieselbe Quelle, aus der auch die JSON-Read-API
    Api\SettingsContentController::einstellungen() sowie das bestehende
    ".contact-box"-Hydrations-Modul in resources/js/app.js schoepfen - siehe
    dortiger Kommentar "Topbar & Geschaeftsstelle dynamisch laden"). "email"
    und "telefon" sind dieselben beiden Felder, die dieses JS-Modul fuer
    ".contact-box"-Elemente einsetzt (telefon_header ist bewusst NUR fuer
    die Topbar reserviert, siehe dortige Logik).

    Fehlt ein Wert in der DB, wird das jeweilige Feld schlicht weggelassen -
    NIE ein erfundener Platzhalter (Auftrag: "lieber das Feld nicht anzeigen
    als einen Platzhalter ausgeben"). Seiten-/Kurs-eigene Ansprechpartner
    (Page::kontakt_name/kontakt_email) bleiben unveraendert Teil der
    jeweiligen Seite selbst (siehe pages/show.blade.php,
    hundeausbildung/*.blade.php) - diese Komponente betrifft ausschliesslich
    die GLOBALEN Geschaeftsstellen-Kontaktdaten.
--}}
@props(['title' => 'Geschäftsstelle'])
@php
    $kjsEinstellungen = \App\Models\Setting::where('gruppe', 'einstellungen')->pluck('value', 'key');
    $kjsKontaktEmail = trim((string) ($kjsEinstellungen['email'] ?? '')) ?: null;
    $kjsKontaktTelefon = trim((string) ($kjsEinstellungen['telefon'] ?? '')) ?: null;
@endphp
@if ($kjsKontaktEmail || $kjsKontaktTelefon)
    <div class="contact-box">
        <h4>{{ $title }}</h4>
        @if ($kjsKontaktEmail)
            <p>📧 <a href="mailto:{{ $kjsKontaktEmail }}">{{ $kjsKontaktEmail }}</a></p>
        @endif
        @if ($kjsKontaktTelefon)
            <p>📞 <a href="tel:{{ preg_replace('/\s|\/|\./', '', $kjsKontaktTelefon) }}">{{ $kjsKontaktTelefon }}</a></p>
        @endif
    </div>
@endif
