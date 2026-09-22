{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    termine/index.html. Sichtbarkeit/Sortierung kommen serverseitig aus
    App\Support\TermineRules (1:1-Port von js/content.js). Der Kategorie-
    Filter bleibt reines Zeigen/Verstecken der bereits gerenderten
    Tabellenzeilen (kein fetch() mehr). Google-Kalender-Embed nutzt die
    datenschutzfreundliche Zwei-Klick-Einbindung.
--}}
<x-layouts.app title="Termine">
    <x-page-hero title="Termine" bg-image="/images/termine.jpg" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content">
                <h2>{{ $ueberschrift }}</h2>
                @if ($einleitung)
                    <p>{{ $einleitung }}</p>
                @endif

                <div id="termine-filter-bar" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center; margin: 2rem 0 1.5rem; padding: 1.25rem; background: var(--bg-light); border-radius: var(--radius-md);">
                    <strong style="align-self:center; font-size:.9rem; color:var(--text-muted);">Filter:</strong>
                    <a href="#" class="btn btn-primary btn-sm termine-filter-btn" data-filter="Alle">Alle</a>
                    @foreach ($kategorien as $kat)
                        <a href="#" class="btn btn-outline-green btn-sm termine-filter-btn" data-filter="{{ $kat }}">{{ $kat }}</a>
                    @endforeach
                </div>

                <div class="termine-table-wrap">
                    <table class="termine-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Veranstaltung</th>
                                <th>Ort</th>
                                <th>Revier</th>
                                <th>Kategorie</th>
                            </tr>
                        </thead>
                        <tbody id="termineTableBody">
                            @forelse ($termine as $t)
                                @php
                                    $ortTeile = array_filter([$t->strasse, trim(($t->plz ?? '').' '.($t->ort ?? '')) ?: $t->ort]);
                                    $gold = in_array($t->kategorie, $goldKategorien, true);
                                @endphp
                                <tr data-kategorie="{{ $t->kategorie }}">
                                    <td><strong>{{ $t->datum?->format('d.m.Y') }}</strong></td>
                                    <td><span>{{ $t->uhrzeit }}</span></td>
                                    <td><span>{{ $t->veranstaltung }}</span></td>
                                    <td><span>{{ implode(', ', $ortTeile) }}</span></td>
                                    <td>@if ($t->revier)<span class="badge badge--gray">{{ $t->revier }}</span>@endif</td>
                                    <td><span class="badge{{ $gold ? ' badge--gold' : '' }}">{{ $t->kategorie }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">Keine Termine vorhanden.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 2.5rem; padding: 1.5rem; background: var(--green-light); border-radius: var(--radius-md); border-left: 4px solid var(--green-main);">
                    <h4 style="color: var(--green-dark); margin-bottom: .5rem;">📋 Termin vorschlagen</h4>
                    <p style="margin:0; font-size: .9rem;">
                        Möchten Sie einen Termin für Ihren Hegering oder Ihre Gruppe eintragen lassen?
                        Schreiben Sie uns an <a href="mailto:termine@kjs-bad-segeberg.de">termine@kjs-bad-segeberg.de</a>.
                    </p>
                </div>

                @if ($googleKalenderUrl)
                    <div style="margin-top:3rem;">
                        <h3 style="margin-bottom:1rem;">{{ $googleKalenderTitel }}</h3>
                        <div class="kjs-embed-placeholder" data-embed-src="{{ $googleKalenderUrl }}" style="position:relative;aspect-ratio:auto;min-height:600px;background:var(--green-light,#eef3ec);border-radius:var(--radius-md,8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;overflow:hidden;text-align:center;padding:1rem;">
                            <button type="button" class="btn btn-outline-green" onclick="kjsActivateEmbed(this)">📅 Kalender anzeigen</button>
                            <p style="margin:0;font-size:.78rem;color:var(--text-muted,#666);max-width:26rem;">Beim Klick wird der Kalender von Google geladen. Dabei können Daten an Google übertragen werden.</p>
                        </div>
                        <p style="font-size:.8rem; color:var(--text-muted); margin-top:.75rem;">
                            Dieser Kalender wird von Google bereitgestellt.
                            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Datenschutzhinweise von Google</a>
                        </p>
                    </div>
                @endif
            </main>
        </div>
    </div>

    <script>
    (function () {
        var bar = document.getElementById('termine-filter-bar');
        var rows = document.querySelectorAll('#termineTableBody tr[data-kategorie]');
        if (!bar) return;
        bar.querySelectorAll('.termine-filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var filter = btn.dataset.filter;
                bar.querySelectorAll('.termine-filter-btn').forEach(function (b) {
                    b.classList.toggle('btn-primary', b.dataset.filter === filter);
                    b.classList.toggle('btn-outline-green', b.dataset.filter !== filter);
                });
                rows.forEach(function (row) {
                    row.style.display = (filter === 'Alle' || row.getAttribute('data-kategorie') === filter) ? '' : 'none';
                });
            });
        });
    })();
    </script>
</x-layouts.app>
