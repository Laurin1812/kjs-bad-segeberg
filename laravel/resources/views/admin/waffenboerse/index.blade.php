{{--
    Phase 7J (Admin-Modul "Waffenboerse") - Anzeigenliste.

    Strukturell 1:1 wie admin/hundeboerse/index.blade.php (siehe dortigen
    Kommentar) - Statusfilter-Reiter als einfache GET-Links, "Freigeben"
    fragt vor dem Absenden nach, "Archivieren"/"Bearbeiten" tun das nicht.
--}}
<x-layouts.admin title="Waffenbörse">
    <div class="panel-header">
        <h1>🔫 Waffenbörse</h1>
        <div style="display:flex;gap:.5rem;">
            <a class="btn btn-outline" href="{{ route('admin.waffenboerse.kategorien.index') }}">🗂️ Kategorien verwalten</a>
            <a class="btn btn-primary" href="{{ route('admin.waffenboerse.neu') }}">➕ Neue Anzeige</a>
        </div>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <div style="display:flex;flex-wrap:wrap;margin-bottom:1.5rem;">
            <a class="btn btn-sm {{ $aktuellerFilter === '' ? 'btn-primary' : 'btn-outline' }}" style="margin:0 .4rem .4rem 0;" href="{{ route('admin.waffenboerse.index') }}">Alle ({{ $zaehler[''] }})</a>
            @foreach ($statusOptionen as $wert => $label)
                <a class="btn btn-sm {{ $aktuellerFilter === $wert ? 'btn-primary' : 'btn-outline' }}" style="margin:0 .4rem .4rem 0;" href="{{ route('admin.waffenboerse.index', ['status' => $wert]) }}">{{ $label }} ({{ $zaehler[$wert] }})</a>
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
                        <th>Kategorie / Hersteller</th>
                        <th>Kaliber</th>
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
                                <a href="{{ route('admin.waffenboerse.bearbeiten', $anzeige) }}">{{ $anzeige->titel ?: '(ohne Titel)' }}</a>
                                @if ($anzeige->erwerbsberechtigung_erforderlich)
                                    <span class="seitentyp-badge">Erwerbsberechtigung erforderlich</span>
                                @endif
                            </td>
                            <td>{{ trim(($anzeige->kategorie ?: '').' '.($anzeige->hersteller ?: '')) ?: '–' }}</td>
                            <td>{{ $anzeige->kaliberText() ?: '–' }}</td>
                            <td>{{ trim(($anzeige->plz ?: '').' '.($anzeige->ort ?: '')) ?: '–' }}</td>
                            <td><span class="seitentyp-badge">{{ $statusOptionen[$anzeige->status] ?? $anzeige->status }}</span></td>
                            <td>{{ $anzeige->erstellt_am?->format('d.m.Y') ?: '–' }}</td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.waffenboerse.bearbeiten', $anzeige) }}">✏️ Bearbeiten</a>
                                    @if ($anzeige->status === 'published')
                                        <a class="btn btn-ghost btn-sm" href="{{ route('waffenboerse.show', $anzeige->id) }}" target="_blank" rel="noopener">🌐 Ansehen</a>
                                    @endif
                                    @if ($anzeige->status === 'pending')
                                        <form method="POST" action="{{ route('admin.waffenboerse.freigeben', $anzeige) }}" onsubmit="return confirm('Diese Anzeige jetzt freigeben? Sie soll später auf der öffentlichen Waffenbörse erscheinen.');">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-primary btn-sm">Freigeben</button>
                                        </form>
                                    @endif
                                    @if (in_array($anzeige->status, ['published', 'rejected'], true))
                                        <form method="POST" action="{{ route('admin.waffenboerse.archivieren', $anzeige) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-ghost btn-sm">Archivieren</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.waffenboerse.loeschen', $anzeige) }}" onsubmit="return confirm('Diese Anzeige wirklich unwiderruflich löschen?');">
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
