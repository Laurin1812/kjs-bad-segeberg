{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Gemeinsames Grundgerüst für die Laravel-Fehlerseiten (404/419/500) im
    KJS-Design - nutzt bewusst dasselbe Layout wie jede echte Seite
    (<x-layouts.app>, also mit vollem Header/Nav/Footer), damit ein Besucher,
    der z.B. auf einen alten/kaputten Link trifft, trotzdem die normale
    Seitennavigation zur Verfügung hat statt einer isolierten Fehlerseite
    ohne Kontext. Nutzt nur bereits in Phase 1 uebernommene Basis-Typografie
    (h1/p) und .container - keine Abhaengigkeit von Phase-2+-CSS.
--}}
@props([
    'code' => null,
    'headline' => 'Es ist ein Fehler aufgetreten.',
    'text' => 'Bitte versuchen Sie es erneut oder kehren Sie zur Startseite zurück.',
])
<x-layouts.app :title="$code ? 'Fehler '.$code : 'Fehler'">
    <div class="container" style="padding-block: 5rem; text-align: center;">
        <main>
            @if ($code)
                <p style="font-family: var(--font-heading); font-size: 3.5rem; font-weight: 700; color: var(--green-main); margin-bottom: .5rem;">{{ $code }}</p>
            @endif
            <h1 style="margin-bottom: 1rem;">{{ $headline }}</h1>
            <p style="color: var(--text-muted); max-width: 480px; margin-inline: auto;">{{ $text }}</p>
            <p style="margin-top: 2rem;">
                <a href="/" style="display: inline-block; padding: .75rem 1.75rem; background: var(--green-main); color: #fff; border-radius: var(--radius-md); font-weight: 600;">Zur Startseite</a>
            </p>
        </main>
    </div>
</x-layouts.app>
