{{--
    Phase 4 Abschluss-Nacharbeit ("Kontakt-Route"): ersetzt den bisher
    fehlenden "/kontakt"-Navigationspunkt (404) durch eine echte Laravel-
    Inhaltsseite - Header/Footer/Breadcrumb/Page-Hero wie bei den uebrigen
    Content-Seiten (siehe z.B. datenschutz.blade.php/pages/show.blade.php),
    "page-content"-Zweispalten-Layout (bereits aus Phase 1/2 vorhandenes
    CSS, kein neues Markup noetig). Zeigt ausschliesslich die informativen
    Kontaktdaten aus der Settings-Gruppe "einstellungen" - das eigentliche
    Kontaktformular-Sondermodul (Formular mit Hegering-Auswahl/Google-Maps)
    ist bewusst NICHT Teil dieser Seite (siehe KontaktController-
    Klassenkommentar). Jedes Feld wird nur angezeigt, wenn es in der DB
    einen Wert hat - kein erfundener Platzhaltertext.
--}}
<x-layouts.app title="Kontakt">
    <x-page-hero title="Kontakt" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Kontakt'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                @if ($ueberschrift)
                    <h2>{{ $ueberschrift }}</h2>
                @endif

                @if ($text)
                    <p>{{ $text }}</p>
                @endif

                @if (! $ueberschrift && ! $text)
                    <h2>Kontakt</h2>
                    <p>Haben Sie Fragen oder möchten Sie uns erreichen? Unsere Kontaktdaten finden Sie hier.</p>
                @endif
            </main>

            <aside class="sidebar">
                @if ($adresse || $telefon || $email || $postadresse || ! empty($oeffnungszeiten))
                    <div class="sidebar-widget">
                        <h4>Kontaktdaten</h4>

                        @if ($adresse)
                            <p>{!! nl2br(e($adresse)) !!}</p>
                        @endif

                        @if ($telefon)
                            <p>📞 <a href="tel:{{ preg_replace('/\s|\/|\./', '', $telefon) }}">{{ $telefon }}</a></p>
                        @endif

                        @if ($email)
                            <p>📧 <a href="mailto:{{ $email }}">{{ $email }}</a></p>
                        @endif

                        @if ($postadresse)
                            <p>
                                <strong>Postadresse:</strong><br>
                                {!! nl2br(e($postadresse)) !!}
                                @if ($postadresseTelefon)
                                    <br>Tel.: <a href="tel:{{ preg_replace('/\s|\/|\./', '', $postadresseTelefon) }}">{{ $postadresseTelefon }}</a>
                                @endif
                                @if ($postadresseEmail)
                                    <br>E-Mail: <a href="mailto:{{ $postadresseEmail }}">{{ $postadresseEmail }}</a>
                                @endif
                            </p>
                        @endif

                        @if (! empty($oeffnungszeiten))
                            <p>
                                <strong>Sprechzeiten:</strong><br>
                                @foreach ($oeffnungszeiten as $zeit)
                                    @if (! empty($zeit['tage']) || ! empty($zeit['zeiten']))
                                        {{ $zeit['tage'] ?? '' }}@if (! empty($zeit['tage']) && ! empty($zeit['zeiten'])): @endif{{ $zeit['zeiten'] ?? '' }}<br>
                                    @endif
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endif

                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
