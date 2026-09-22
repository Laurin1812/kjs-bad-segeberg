{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    faq/index.html. Reines Read-only-Akkordeon, keine Business-Logik.
--}}
<x-layouts.app title="FAQ – Häufige Fragen">
    <x-page-hero title="FAQ – Häufige Fragen" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'FAQ'],
    ]" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content">
                <h2>Antworten auf häufige Fragen</h2>
                <p style="color:var(--text-muted); margin-bottom:2.5rem;">
                    Hier haben wir die häufigsten Fragen rund um Jagd, Mitgliedschaft und unsere
                    Kreisjägerschaft für Sie zusammengestellt.
                </p>

                @foreach ($kategorien as $kat)
                    <h3 style="color:var(--green-dark);margin:2rem 0 1.25rem;">{{ $kat->titel }}</h3>
                    <div class="faq-list">
                        @foreach ($kat->fragen as $f)
                            <details class="faq-item">
                                <summary>{{ $f->frage }}</summary>
                                <div class="faq-item__body">{!! $f->antwort !!}</div>
                            </details>
                        @endforeach
                    </div>
                @endforeach

                <div style="margin-top:2.5rem;padding:1.75rem;background:var(--green-light);border-radius:var(--radius-md);text-align:center;">
                    <h3 style="color:var(--green-dark);margin-bottom:.75rem;">Keine Antwort gefunden?</h3>
                    <p style="margin-bottom:1.25rem;color:var(--text-muted);">Wir helfen gerne weiter. Schreiben Sie uns einfach eine Nachricht.</p>
                    @if($kjsAllgemeineEmail)
                        <a href="mailto:{{ $kjsAllgemeineEmail }}" class="btn btn-primary">Jetzt Kontakt aufnehmen</a>
                    @endif
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
