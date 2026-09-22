{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    aktuelles/beitrag.html. URL-Schema modernisiert (siehe
    AktuellesController-Klassenkommentar): /aktuelles/beitrag/{slug} statt
    "?i=<Array-Index>". Bildergalerie nutzt die site-weite Lightbox
    (.galerie-item, siehe resources/js/app.js), Markdown wird serverseitig
    ueber Illuminate\Support\Str::markdown() gerendert. Ein unbekannter Slug
    fuehrt bereits in AktuellesController::show() (firstOrFail()) zu einer
    echten Laravel-404-Seite - kein PHP-/JSON-Fallback.
--}}
<x-layouts.app :title="$beitrag->titel">
    <x-page-hero :title="$beitrag->titel" :bg-image="$beitrag->bild ? \App\Support\Images::cardUrl($beitrag->bild) : '/images/stock/hero-waldweg.jpg'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Aktuelles', 'href' => route('aktuelles.index')],
        ['label' => $beitrag->titel],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <a href="{{ route('aktuelles.index') }}" class="content-back-link">← Zurück zu Aktuelles</a>

                <div class="news-card__meta" style="margin-bottom:1.5rem;">
                    <span class="news-card__cat">{{ $beitrag->kategorie?->name }}</span>
                    <span class="news-card__date" style="margin-left:1rem;">{{ $beitrag->datum?->format('d.m.Y') }}</span>
                </div>

                @if ($beitrag->bild)
                    <img src="{{ $beitrag->bild }}" alt="{{ $beitrag->titel }}" class="beitrag-titelbild">
                @endif

                @if ($textHtml)
                    {!! $textHtml !!}
                @endif

                @if ($beitrag->link)
                    <p style="margin-top:2rem;">
                        <a href="{{ $beitrag->link }}" class="btn btn-primary" target="_blank" rel="noopener">Mehr erfahren →</a>
                    </p>
                @endif

                @if ($beitrag->galerieBilder->isNotEmpty())
                    <div class="galerie-section">
                        <div class="galerie-section__title">{{ $beitrag->galerie_titel ?: 'Bildergalerie' }}</div>
                        <div class="galerie-grid">
                            @foreach ($beitrag->galerieBilder as $bild)
                                <a href="{{ $bild->pfad }}" class="galerie-item">
                                    <img src="{{ \App\Support\Images::cardUrl($bild->pfad) }}" data-full="{{ $bild->pfad }}" onerror="kjsImgFallback(this)" alt="{{ $bild->titel }}" loading="lazy">
                                    @if ($bild->titel)
                                        <div class="galerie-item__caption">{{ $bild->titel }}</div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                @if ($weitereBeitraege->isNotEmpty())
                    <div class="sidebar-widget">
                        <h4>Weitere Beiträge</h4>
                        <ul class="sidebar-nav">
                            @foreach ($weitereBeitraege as $a)
                                <li><a href="{{ route('aktuelles.show', $a->slug) }}">{{ $a->titel }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($beitrag->downloads->isNotEmpty())
                    <div class="sidebar-widget sidebar-widget--downloads">
                        <h4><span class="download-heading-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span> Dokumente &amp; Downloads</h4>
                        <div class="downloads-list downloads-list--sidebar">
                            @foreach ($beitrag->downloads as $dl)
                                <div class="download-item--sidebar">
                                    @if ($dl->vorschau)
                                        <a href="{{ $dl->pfad }}" target="_blank" rel="noopener noreferrer" class="download-item__thumb">
                                            <img src="{{ $dl->vorschau }}" alt="{{ $dl->titel }}" loading="lazy">
                                        </a>
                                    @else
                                        <span class="download-item__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
                                    @endif
                                    <span class="download-item__name">{{ $dl->titel }}</span>
                                    <span class="download-item__actions">
                                        <a href="{{ $dl->pfad }}" target="_blank" rel="noopener noreferrer" class="download-action" title="Öffnen" aria-label="{{ $dl->titel }} öffnen"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg></a>
                                        <a href="{{ $dl->pfad }}" download class="download-action" title="Herunterladen" aria-label="{{ $dl->titel }} herunterladen"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-kontaktbox />
            </aside>
        </div>
    </div>
</x-layouts.app>
