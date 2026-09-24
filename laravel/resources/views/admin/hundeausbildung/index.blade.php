{{--
    Phase 7H (Admin-Modul "Hundeausbildung / Jagdhundeschule") - gefilterte
    Einstiegsseite.

    Zeigt ausschliesslich die Page-Zeilen der section 'hundeausbildung' (Hub
    + Kurs-Unterseiten) - siehe HundeausbildungController-Klassenkommentar
    fuer die Kurzanalyse ("keine eigene Facharchitektur, nur eine weitere
    Page-Familie"). "Bearbeiten" fuehrt auf die bereits bestehende, generische
    Seitenbearbeitung (admin.inhalte.bearbeiten/-update) - KEINE eigene
    Speichern-Logik hier.
--}}
<x-layouts.admin title="Hundeausbildung">
    <div class="panel-header">
        <h1>🎓 Hundeausbildung</h1>
    </div>
    <div class="panel-body">
        <p class="hint-card">Diese Seiten werden über die zentrale Seitenbearbeitung gepflegt.</p>

        @if (! $hub)
            <p class="hint-card">Es wurde noch keine Hundeausbildungs-Übersichtsseite angelegt.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Pfad</th>
                        <th>Seitentyp</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>{{ $hub->titel ?: '(ohne Titel)' }}</strong></td>
                        <td>{{ route('hundeausbildung.hub') }}</td>
                        <td>Jagdhundeschule-Übersicht</td>
                        <td>
                            @if ($hub->veroeffentlicht)
                                <span class="text-muted">Veröffentlicht</span>
                            @else
                                <span class="seitentyp-badge">Unveröffentlicht</span>
                            @endif
                        </td>
                        <td>
                            <div class="item-actions">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.inhalte.bearbeiten', $hub) }}">✏️ Bearbeiten</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('hundeausbildung.hub') }}" target="_blank" rel="noopener">🌐 Öffnen</a>
                            </div>
                        </td>
                    </tr>
                    @foreach ($kurse as $kurs)
                        <tr>
                            <td style="padding-left:1.5rem;">↳ {{ $kurs->titel ?: '(ohne Titel)' }}</td>
                            <td>{{ route('hundeausbildung.show', $kurs->slug) }}</td>
                            <td>Jagdhundeschule-Kurs</td>
                            <td>
                                @if ($kurs->veroeffentlicht)
                                    <span class="text-muted">Veröffentlicht</span>
                                @else
                                    <span class="seitentyp-badge">Unveröffentlicht</span>
                                @endif
                            </td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.inhalte.bearbeiten', $kurs) }}">✏️ Bearbeiten</a>
                                    <a class="btn btn-ghost btn-sm" href="{{ route('hundeausbildung.show', $kurs->slug) }}" target="_blank" rel="noopener">🌐 Öffnen</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.admin>
