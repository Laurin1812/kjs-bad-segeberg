{{--
    Phase 7I (Admin-Modul "Hundeboerse") - Anzeigenliste.

    Statusfilter-Reiter 1:1 wie admin.js' renderHundeboerse() (Alle/Wartet/
    Veroeffentlicht/Abgelehnt/Archiviert) - hier als einfache GET-Links
    (?status=...) statt eigenem JS-Handler, da der Blade-Admin bewusst ohne
    zusaetzliche Runtime auskommt (siehe HundeboerseController-
    Klassenkommentar).

    "Freigeben" (nur bei pending) fragt vor dem Absenden nach (native
    confirm(), kein neues Modal-System) - "Archivieren" (nur bei published/
    rejected) und "Bearbeiten" tun das nicht, exakt wie im alten Admin.
--}}
<x-layouts.admin title="Hundebörse">
    <div class="panel-header">
        <h1>🐕 Hundebörse</h1>
        <a class="btn btn-primary" href="{{ route('admin.hundeboerse.neu') }}">➕ Neue Anzeige</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <div style="display:flex;flex-wrap:wrap;margin-bottom:1.5rem;">
            <a class="btn btn-sm {{ $aktuellerFilter === '' ? 'btn-primary' : 'btn-outline' }}" style="margin:0 .4rem .4rem 0;" href="{{ route('admin.hundeboerse.index') }}">Alle ({{ $zaehler[''] }})</a>
            @foreach ($statusOptionen as $wert => $label)
                <a class="btn btn-sm {{ $aktuellerFilter === $wert ? 'btn-primary' : 'btn-outline' }}" style="margin:0 .4rem .4rem 0;" href="{{ route('admin.hundeboerse.index', ['status' => $wert]) }}">{{ $label }} ({{ $zaehler[$wert] }})</a>
            @endforeach
        </div>

        @if ($anzeigen->isEmpty())
            <p class="hint-card">Keine Anzeigen in dieser Ansicht.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th></th>
                        <th>Titel</th>
                        <th>Typ</th>
                        <th>Rasse</th>
                        <th>Ort</th>
                        <th>Status</th>
                        <th>Erstellt am</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($anzeigen as $anzeige)
                        @php $bild = $anzeige->bilder->first(); @endphp
                        <tr>
                            <td>
                                @if ($bild)
                                    <img src="{{ \App\Support\Images::boerseThumbUrl($bild->pfad) }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.hundeboerse.bearbeiten', $anzeige) }}">{{ $anzeige->title ?: '(ohne Titel)' }}</a>
                            </td>
                            <td>{{ $anzeige->typLabel() }}</td>
                            <td>{{ $anzeige->breed ?: '–' }}</td>
                            <td>{{ trim(($anzeige->postal_code ?: '').' '.($anzeige->city ?: '')) ?: '–' }}</td>
                            <td><span class="seitentyp-badge">{{ $statusOptionen[$anzeige->status] ?? $anzeige->status }}</span></td>
                            <td>{{ $anzeige->created_at?->format('d.m.Y') ?: '–' }}</td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.hundeboerse.bearbeiten', $anzeige) }}">✏️ Bearbeiten</a>
                                    @if ($anzeige->status === 'published')
                                        <a class="btn btn-ghost btn-sm" href="{{ route('hundeboerse.show', $anzeige->id) }}" target="_blank" rel="noopener">🌐 Ansehen</a>
                                    @endif
                                    @if ($anzeige->status === 'pending')
                                        <form method="POST" action="{{ route('admin.hundeboerse.freigeben', $anzeige) }}" onsubmit="return confirm('Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Hundebörse erscheinen.');">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-primary btn-sm">Freigeben</button>
                                        </form>
                                    @endif
                                    @if (in_array($anzeige->status, ['published', 'rejected'], true))
                                        <form method="POST" action="{{ route('admin.hundeboerse.archivieren', $anzeige) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-ghost btn-sm">Archivieren</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.hundeboerse.loeschen', $anzeige) }}" onsubmit="return confirm('Diese Anzeige wirklich unwiderruflich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm">🗑️ Löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.admin>
