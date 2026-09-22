{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    partner/index.html. URL-Schema modernisiert (siehe
    PartnerController-Klassenkommentar): /partner/detail/{external_id}
    statt "detail.html?id=".
--}}
<x-layouts.app title="Partner">
    <x-page-hero title="Partner" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Jäger', 'href' => '/jaeger/uebersicht'],
        ['label' => 'Partner'],
    ]" />

    <div class="page-content full pn-overview-layout">
        <div class="container">
            <main class="main-content" id="pn-main">
                <p style="font-size:1.05rem;color:var(--text-body);line-height:1.8;margin-bottom:2rem;">
                    Die Kreisjägerschaft Segeberg e.V. arbeitet mit einer Reihe von Partnern aus Jagd, Naturschutz und
                    Wirtschaft zusammen. Einen Überblick finden Sie hier – für weitere Informationen zu einem Partner
                    einfach auf die jeweilige Kachel klicken.
                </p>

                <div class="news__grid" id="partner-grid">
                    @forelse ($partner as $p)
                        <article class="news-card">
                            <a href="{{ route('partner.show', $p->external_id) }}" style="text-decoration:none;color:inherit;">
                                <div class="news-card__img-wrap">
                                    @if ($p->logo)
                                        <img class="news-card__img" src="{{ \App\Support\Images::cardUrl($p->logo) }}" data-full="{{ $p->logo }}" onerror="kjsImgFallback(this)" alt="{{ $p->name }}" loading="lazy">
                                    @else
                                        <div class="pn-logo-placeholder"><img src="/images/logo.png" alt="KJS"></div>
                                    @endif
                                </div>
                                <div class="news-card__body">
                                    <h3 class="news-card__title">{{ $p->name ?: '(Ohne Namen)' }}</h3>
                                    @if ($p->kurzbeschreibung)
                                        <p style="font-size:.86rem;color:var(--text-muted);text-align:center;margin:0;">{{ $p->kurzbeschreibung }}</p>
                                    @endif
                                </div>
                            </a>
                        </article>
                    @empty
                        <p style="color:var(--text-muted);">Aktuell sind keine Partner hinterlegt.</p>
                    @endforelse
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
