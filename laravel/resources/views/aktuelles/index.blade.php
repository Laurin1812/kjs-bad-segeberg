{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    aktuelles/index.html. Jahr-Archiv (Sidebar) und Kategorie-Mehrfachfilter
    laufen jetzt ueber echte GET-Query-Parameter (?jahr=&kategorie[]=),
    serverseitig in AktuellesController::index() ausgewertet - kein
    clientseitiges JSON-Filtern mehr. Kartenoptik (.news__grid/.news-card)
    unveraendert aus css/style.css uebernommen.
--}}
<x-layouts.app title="Aktuelles">
    <x-page-hero title="Aktuelles" bg-image="/images/stock/hero-waldweg.jpg" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Aktuelles'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <div style="margin-bottom:2rem;">
                    <h2>{{ $headline }}</h2>

                    @if ($verfuegbareKategorien->isNotEmpty())
                        <form method="GET" action="{{ route('aktuelles.index') }}" style="margin-top:1rem;">
                            @if ($aktivesJahr && $aktivesJahr !== 'alle')
                                <input type="hidden" name="jahr" value="{{ $aktivesJahr }}">
                            @elseif ($aktivesJahr === 'alle')
                                <input type="hidden" name="jahr" value="alle">
                            @endif
                            <div style="font-size:.82rem;font-weight:600;color:var(--text-muted);margin-bottom:.4rem;">
                                Kategorien filtern (mehrere möglich)
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:.4rem .9rem;font-size:.88rem;">
                                <label style="display:flex;align-items:center;gap:.35rem;cursor:pointer;">
                                    <input type="checkbox" onclick="this.closest('form').querySelectorAll('input[name=\'kategorie[]\']').forEach(function(c){c.checked=false;});this.form.submit();" {{ $ausgewaehlteKategorien->isEmpty() ? 'checked' : '' }}>
                                    Alle anzeigen
                                </label>
                                @foreach ($verfuegbareKategorien as $kat)
                                    <label style="display:flex;align-items:center;gap:.35rem;cursor:pointer;">
                                        <input type="checkbox" name="kategorie[]" value="{{ $kat }}" onchange="this.form.submit();" {{ $ausgewaehlteKategorien->contains($kat) ? 'checked' : '' }}>
                                        {{ $kat }}
                                    </label>
                                @endforeach
                            </div>
                            <noscript><button type="submit" class="btn btn-sm btn-outline-green" style="margin-top:.6rem;">Filtern</button></noscript>
                        </form>
                    @endif
                </div>

                <div class="news__grid">
                    @forelse ($beitraege as $b)
                        <article class="news-card">
                            <a href="{{ route('aktuelles.show', $b->slug) }}" style="text-decoration:none;color:inherit;display:block;">
                                <div class="news-card__img-wrap">
                                    @if ($b->bild)
                                        <img class="news-card__img" src="{{ \App\Support\Images::cardUrl($b->bild) }}" data-full="{{ $b->bild }}" onerror="kjsImgFallback(this)" alt="{{ $b->titel }}" loading="lazy">
                                    @else
                                        <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:180px;">
                                            <img src="/images/logo.png" alt="KJS" style="height:64px;opacity:.3;">
                                        </div>
                                    @endif
                                </div>
                                <div class="news-card__body">
                                    <div class="news-card__meta">
                                        <span class="news-card__cat">{{ $b->kategorie?->name }}</span>
                                        <span class="news-card__date">{{ $b->datum?->format('d.m.Y') }}</span>
                                    </div>
                                    <h3 class="news-card__title">{{ $b->titel }}</h3>
                                    <p class="news-card__excerpt">{{ \App\Support\Text::excerpt($b->text) }}</p>
                                    <span style="font-size:.88rem;font-weight:600;color:var(--green-main);">Weiterlesen →</span>
                                </div>
                            </a>
                        </article>
                    @empty
                        <p style="color:var(--text-muted)">Keine Beiträge für diese Auswahl.</p>
                    @endforelse
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Archiv</h4>
                    <ul class="sidebar-nav">
                        @forelse ($jahre as $jahr)
                            <li>
                                <a href="{{ route('aktuelles.index', ['jahr' => $jahr]) }}" style="{{ $aktivesJahr === $jahr ? 'font-weight:700;' : '' }}">{{ $jahr }}</a>
                            </li>
                        @empty
                            <li><span style="color:var(--text-muted);font-size:.85rem">Keine Beiträge</span></li>
                        @endforelse
                        <li style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.5rem;">
                            <a href="{{ route('aktuelles.index', ['jahr' => 'alle']) }}" style="font-size:.85rem;color:var(--text-muted);{{ $aktivesJahr === 'alle' ? 'font-weight:700;' : '' }}">Alle anzeigen</a>
                        </li>
                    </ul>
                </div>
                <x-kontaktbox title="Kontakt" />
            </aside>
        </div>
    </div>
</x-layouts.app>
