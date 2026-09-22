{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    impressum.html. Struktur/Optik 1:1 aus dem alten Muster uebernommen
    (.page-content.full, main.main-content mit max-width:720px) - Inhalt
    kommt jetzt serverseitig aus ImpressumController statt aus zwei
    client-seitigen fetch()-Aufrufen.
--}}
<x-layouts.app title="Impressum">
    <x-page-hero title="Impressum" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content" style="max-width:720px;">
                <h2>Angaben gemäß § 5 TMG</h2>
                <p>
                    <strong>{{ $verein }}</strong><br>
                    {!! nl2br(e($adresse)) !!}
                </p>
                @if ($postadresse)
                    <p><strong>Postadresse:</strong><br>{!! nl2br(e($postadresse)) !!}</p>
                @endif
                <p><strong>Vertreten durch:</strong><br>{{ $vertretenDurch }}</p>

                <h3>Kontakt</h3>
                <p>
                    Telefon: <a href="tel:{{ preg_replace('/\s|-|\//', '', $telefon) }}">{{ $telefon }}</a><br>
                    E-Mail: <a href="mailto:{{ $email }}">{{ $email }}</a>
                </p>

                <h3>Registereintrag</h3>
                <p>
                    Eingetragen im Vereinsregister.<br>
                    Registergericht: {{ $registergericht }}<br>
                    Registernummer: {{ $registernummer }}
                </p>

                <h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
                <p>{!! nl2br(e($verantwortlich)) !!}</p>

                <h3>Haftungsausschluss</h3>
                <p>Die Inhalte dieser Website wurden mit größter Sorgfalt erstellt. Für die Richtigkeit,
                   Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.</p>

                <h3>Urheberrecht</h3>
                <p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen
                   dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung und Verbreitung bedürfen
                   der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.</p>
            </main>
        </div>
    </div>
</x-layouts.app>
