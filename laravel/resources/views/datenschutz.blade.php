{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    datenschutz.html. Der Fliesstext ist (wie im Original) fest verdrahtet,
    nur der "Verantwortlicher"-Absatz kommt dynamisch aus der Settings-
    Gruppe "einstellungen" (jetzt serverseitig statt per fetch()).
--}}
<x-layouts.app title="Datenschutz">
    <x-page-hero title="Datenschutzerklärung" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content" style="max-width:720px;">
                <h2>Datenschutzerklärung</h2>
                <p>Der Schutz Ihrer persönlichen Daten ist uns ein besonderes Anliegen.
                   Wir verarbeiten Ihre Daten daher ausschließlich auf Grundlage der gesetzlichen
                   Bestimmungen (DSGVO, TMG).</p>

                <h3>Verantwortlicher</h3>
                <p>
                    Kreisjägerschaft Segeberg e.V.<br>
                    @if ($adresse){!! nl2br(e($adresse)) !!}@endif
                    @if ($postadresse)<br>Postadresse: {!! nl2br(e($postadresse)) !!}@endif
                    @if ($email)<br>E-Mail: <a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                </p>

                <h3>Datenerfassung auf dieser Website</h3>
                <p>Diese Website erhebt keine personenbezogenen Daten, soweit Sie uns diese nicht
                   freiwillig übermitteln (z. B. über das Kontaktformular). Die von Ihnen eingegebenen
                   Daten werden ausschließlich zur Bearbeitung Ihrer Anfrage genutzt.</p>

                <h3>Server-Log-Dateien</h3>
                <p>Der Provider dieser Website erhebt und speichert automatisch Informationen in sogenannten
                   Server-Log-Dateien (IP-Adresse, Browsertyp, Betriebssystem, Referrer-URL, Zugriffszeitpunkt).
                   Diese Daten können nicht bestimmten Personen zugeordnet werden.</p>

                <h3>Kontaktformular</h3>
                <p>Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben aus dem
                   Formular zur Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert.
                   Diese Daten geben wir nicht ohne Ihre Einwilligung weiter.</p>

                <h3>Ihre Rechte</h3>
                <p>Sie haben jederzeit das Recht auf unentgeltliche Auskunft über Ihre gespeicherten
                   personenbezogenen Daten, deren Herkunft, Empfänger und den Zweck der Datenverarbeitung
                   sowie ein Recht auf Berichtigung oder Löschung dieser Daten.</p>
                <p>Anfragen richten Sie bitte an: <a href="mailto:datenschutz@kjs-bad-segeberg.de">datenschutz@kjs-bad-segeberg.de</a></p>
                <p style="color:var(--text-muted); font-size:.85rem; margin-top:2rem;">Stand: Mai 2025</p>
            </main>
        </div>
    </div>
</x-layouts.app>
