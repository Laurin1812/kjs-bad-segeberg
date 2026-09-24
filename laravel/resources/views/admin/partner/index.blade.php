{{--
    Phase 7F (Admin-Modul "Partner") - Partnerliste.

    Rein server-gerendert (wie Phase 7B/7C/7D/7E). $partner kommt bereits
    nach "sortierung" sortiert aus PartnerController::index() - dieselbe
    Reihenfolge, in der die oeffentliche Uebersichtsseite die AKTIVEN
    Partner zeigt (siehe oeffentlicher PartnerController::index()), hier
    aber bewusst OHNE deren "aktiv"-Filter (der Admin muss auch deaktivierte
    Partner sehen/bearbeiten koennen, siehe PartnerController-
    Klassenkommentar).

    Reihenfolge-Buttons (▲/▼) statt Drag&Drop - siehe PartnerController-
    Klassenkommentar "Reihenfolge".
--}}
<x-layouts.admin title="Partner">
    <div class="panel-header">
        <h1>🤝 Partner</h1>
        <a class="btn btn-primary" href="{{ route('admin.partner.neu') }}">➕ Partner hinzufügen</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        @if ($partner->isEmpty())
            <p class="hint-card">Es sind noch keine Partner vorhanden.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Kurzbeschreibung</th>
                        <th>Website</th>
                        <th>Status</th>
                        <th>Reihenfolge</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partner as $i => $p)
                        <tr>
                            <td>
                                @if ($p->logo)
                                    <img src="{{ $p->logo }}" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:4px;background:#fff;border:1px solid var(--admin-border);">
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.partner.bearbeiten', $p) }}">
                                    {{ $p->name ?: '(Kein Name)' }}
                                </a>
                            </td>
                            <td>{{ $p->kurzbeschreibung ?: '–' }}</td>
                            <td>{{ $p->website ?: '–' }}</td>
                            <td>
                                @if ($p->aktiv)
                                    <span class="text-muted">Aktiv</span>
                                @else
                                    <span class="seitentyp-badge">Inaktiv</span>
                                @endif
                            </td>
                            <td>
                                <div class="item-actions">
                                    <form method="POST" action="{{ route('admin.partner.hoch', $p) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                                        <button type="submit" class="btn btn-ghost btn-sm" @disabled($i === 0) title="Nach oben">▲</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.partner.runter', $p) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                                        <button type="submit" class="btn btn-ghost btn-sm" @disabled($i === $partner->count() - 1) title="Nach unten">▼</button>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.partner.bearbeiten', $p) }}">✏️ Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.partner.loeschen', $p) }}" onsubmit="return confirm('Diesen Partner wirklich löschen? Alternativ kann er über „Bearbeiten“ auch nur deaktiviert werden.');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
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
