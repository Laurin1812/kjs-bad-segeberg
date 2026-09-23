{{--
    Phase 7B (Admin-Modul "Inhalte/Seiten") - Seitenliste.

    Rein server-gerendert (Auftrag Teil 2 "Keine JSON-Runtime im Browser
    nötig"): $gruppen kommt fertig aus InhalteController::index() (siehe
    dortiger Klassenkommentar fuer den genauen Seiten-Ausschnitt), keine
    fetch()/JSON-Ladevorgaenge im Browser noetig.
--}}
<x-layouts.admin title="Inhalte (Seiten)">
    <div class="panel-header">
        <h1>📄 Inhalte (Seiten)</h1>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif

        @if (empty($gruppen))
            <p class="hint-card">Es sind noch keine Seiten in dieser Kategorie vorhanden.</p>
        @endif

        @foreach ($gruppen as $gruppe)
            <div class="seiten-liste-section">
                <h2>{{ $gruppe['bereich'] }}</h2>
                <table class="seiten-liste-tabelle">
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Slug / Pfad</th>
                            <th>Typ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gruppe['zeilen'] as $zeile)
                            <tr @class(['ist-unterseite' => $zeile['istUnterseite']])>
                                <td>
                                    <a href="{{ route('admin.inhalte.bearbeiten', $zeile['page']) }}">
                                        {{ $zeile['istUnterseite'] ? '↳ ' : '' }}{{ $zeile['page']->titel ?: '(ohne Titel)' }}
                                    </a>
                                </td>
                                <td><code>{{ $zeile['page']->slug }}</code></td>
                                <td><span class="seitentyp-badge">{{ $zeile['typ'] }}</span></td>
                                <td>
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.inhalte.bearbeiten', $zeile['page']) }}">✏️ Bearbeiten</a>
                                    @if ($zeile['url'])
                                        <a class="btn btn-ghost btn-sm" href="{{ $zeile['url'] }}" target="_blank" rel="noopener">🌐 Ansehen</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
