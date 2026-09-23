{{--
    Phase 7D (Admin-Modul "Termine") - Terminliste.

    Rein server-gerendert (wie Phase 7B "Inhalte/Seiten" und Phase 7C
    "Aktuelles"): $termine kommt fertig sortiert (naechster Termin zuerst,
    App\Support\TermineRules::sortiere() - dieselbe Regel wie die
    oeffentliche Termine-Seite, hier aber bewusst OHNE deren
    Sichtbarkeitsfilter) aus TermineController::index(). Anders als die
    oeffentliche Seite zeigt diese Liste ausdruecklich AUCH archivierte und
    laengst vergangene Termine, damit der Admin sie weiterhin bearbeiten
    kann.

    Ueberschrift/Einleitungstext-Formular unten: 1:1 aus admin.js'
    renderTermine() uebernommen (dort im selben Panel wie die Liste, nicht
    als eigenes Modul).
--}}
<x-layouts.admin title="Termine">
    <div class="panel-header">
        <h1>📅 Termine</h1>
        <a class="btn btn-primary" href="{{ route('admin.termine.neu') }}">➕ Neuer Termin</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        @if ($termine->isEmpty())
            <p class="hint-card">Es sind noch keine Termine vorhanden.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th>Veranstaltung</th>
                        <th>Datum</th>
                        <th>Uhrzeit</th>
                        <th>Ort</th>
                        <th>Revier</th>
                        <th>Kategorie</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($termine as $termin)
                        <tr>
                            <td>
                                <a href="{{ route('admin.termine.bearbeiten', $termin) }}">
                                    {{ $termin->veranstaltung ?: '(ohne Titel)' }}
                                </a>
                            </td>
                            <td>{{ $termin->datum?->format('d.m.Y') ?: '–' }}</td>
                            <td>{{ $termin->uhrzeit ?: '–' }}</td>
                            <td>{{ $termin->ort ?: '–' }}</td>
                            <td>{{ $termin->revier ?: '–' }}</td>
                            <td>{{ $termin->kategorie ?: '–' }}</td>
                            <td>
                                @if ($termin->archiviert)
                                    <span class="seitentyp-badge">📦 Archiviert</span>
                                @else
                                    <span class="text-muted">Aktiv</span>
                                @endif
                            </td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.termine.bearbeiten', $termin) }}">✏️ Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.termine.archivieren', $termin) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                                        <button type="submit" class="btn btn-ghost btn-sm">
                                            {{ $termin->archiviert ? '↩️ Wiederherstellen' : '📦 Archivieren' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.termine.loeschen', $termin) }}" onsubmit="return confirm('Diesen Termin wirklich löschen?');">
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

    <div class="panel-body" style="margin-top:1.5rem;">
        <div class="form-card">
            <div class="form-card-title">⚙️ Überschrift &amp; Einleitungstext (Live-Seite)</div>
            <form method="POST" action="{{ route('admin.termine.einstellungen') }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                <div class="field-row">
                    <label class="field-label" for="f-ueberschrift">Überschrift</label>
                    <input class="field-input" type="text" id="f-ueberschrift" name="ueberschrift" value="{{ old('ueberschrift', $ueberschrift) }}">
                </div>
                <div class="field-row">
                    <label class="field-label" for="f-einleitung">Einleitungstext</label>
                    <textarea class="field-textarea" id="f-einleitung" name="einleitung" rows="3">{{ old('einleitung', $einleitung) }}</textarea>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Speichern</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
