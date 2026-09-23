{{--
    Phase 7C (Admin-Modul "Aktuelles") - Beitragsliste.

    Rein server-gerendert (wie Phase 7B "Inhalte/Seiten"): $beitraege kommt
    fertig sortiert (neueste zuerst, App\Support\AktuellesRules -
    unveraendert dieselbe Regel wie die oeffentliche Aktuelles-Seite) aus
    AktuellesController::index(), keine fetch()/JSON-Ladevorgaenge im
    Browser noetig. Eine einzige Liste statt der Alt-Admin-Trennung
    "aktuelle Liste" / eigene Archiv-Unterseite (siehe Klassenkommentar
    AktuellesController) - archivierte Beitraege sind hier per Badge
    erkennbar statt auf einer eigenen Seite versteckt.

    Kategorien-Verwaltung (Nachbesserung, siehe AktuellesController-
    Klassenkommentar "Kategorien"): bewusst hier auf der Liste statt im
    Beitragsformular, damit Anlegen/Loeschen nicht pro Formular dupliziert
    werden muss - $kategorien kommt bereits mit Verwendungs-Anzahl
    (withCount('beitraege')) aus dem Controller.
--}}
<x-layouts.admin title="Aktuelles">
    <div class="panel-header">
        <h1>📰 Aktuelles</h1>
        <a class="btn btn-primary" href="{{ route('admin.aktuelles.neu') }}">➕ Neuer Beitrag</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif

        @if ($beitraege->isEmpty())
            <p class="hint-card">Es sind noch keine Aktuelles-Beiträge vorhanden.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Datum</th>
                        <th>Kategorie</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($beitraege as $beitrag)
                        <tr>
                            <td>
                                <a href="{{ route('admin.aktuelles.bearbeiten', $beitrag) }}">
                                    {{ $beitrag->titel ?: '(ohne Titel)' }}
                                </a>
                            </td>
                            <td>{{ $beitrag->datum?->format('d.m.Y') ?: '–' }}</td>
                            <td>{{ $beitrag->kategorie?->name ?: '–' }}</td>
                            <td>
                                @if ($beitrag->archiviert)
                                    <span class="seitentyp-badge">📦 Archiviert</span>
                                @else
                                    <span class="text-muted">Aktiv</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.aktuelles.bearbeiten', $beitrag) }}">✏️ Bearbeiten</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('aktuelles.show', $beitrag->slug) }}" target="_blank" rel="noopener">🌐 Ansehen</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel-body" id="kategorien" style="margin-top:1.5rem;">
        <div class="form-card">
            <div class="form-card-title">🏷️ Kategorien</div>

            @if (session('kategorie_fehler'))
                <p class="kjs-admin-status--error" style="margin-bottom:1rem;">{{ session('kategorie_fehler') }}</p>
            @endif
            @if (session('kategorie_status'))
                <p class="kjs-admin-status--success" style="margin-bottom:1rem;">{{ session('kategorie_status') }}</p>
            @endif

            @forelse ($kategorien as $kategorie)
                <div class="item-card">
                    <div class="item-body">
                        {{ $kategorie->name }}
                        <span class="text-muted">({{ $kategorie->beitraege_count }} {{ $kategorie->beitraege_count === 1 ? 'Beitrag' : 'Beiträge' }})</span>
                    </div>
                    <div class="item-actions">
                        {{-- Security-Fix (Nutzer-Feedback): $kategorie->name ist Nutzereingabe
                             und landet hier in einem JS-String-Kontext (onsubmit-Attribut) -
                             normales Blade-"{{ }}"-HTML-Escaping schuetzt NICHT davor, da der
                             Browser HTML-Entities im Attributwert vor der JS-Ausfuehrung wieder
                             dekodiert (ein Name mit einem Apostroph wuerde sonst aus dem
                             einfach gequoteten confirm()-String ausbrechen koennen). @js()
                             (Illuminate\Support\Js::from()) erzeugt stattdessen ein JSON-
                             Literal, das sowohl als JS-Ausdruck als auch als HTML-Attributwert
                             sicher ist - Text/Verhalten des Dialogs bleiben dabei identisch,
                             nur die Verkettung ersetzt die bisherige direkte Interpolation. --}}
                        <form method="POST" action="{{ route('admin.aktuelles.kategorien.loeschen', $kategorie) }}" onsubmit="return confirm('Kategorie „' + @js($kategorie->name) + '“ wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                            <button type="submit" class="btn btn-danger-outline btn-sm">🗑️ Löschen</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="hint-card">Noch keine Kategorien angelegt.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.aktuelles.kategorien.anlegen') }}" style="display:flex;gap:.75rem;align-items:flex-end;margin-top:1rem;flex-wrap:wrap;">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                <div class="field-row" style="flex:1;min-width:200px;margin:0;">
                    <label class="field-label" for="f-neue-kategorie">Neue Kategorie</label>
                    <input class="field-input" type="text" id="f-neue-kategorie" name="name" placeholder="Name der Kategorie" required maxlength="190">
                </div>
                <button type="submit" class="btn btn-outline">+ Neu</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
