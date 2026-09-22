{{--
    Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
    Vollmigration): ersetzt aufgaben/jagdhundeschule.html?s=<slug>
    (Detail-Modus). Anders als die generische pages/show.blade.php
    (feste/registry) zeigt dieser Kurs-Typ sowohl Downloads ALS AUCH
    Bildergalerie UND einen Zurück-Link (1:1 aus dem Original uebernommen,
    siehe renderDownloadsSidebar()/renderGalerie() in aufgaben/
    jagdhundeschule.html).
--}}
@php
    $bildKlasse = \App\Support\Images::groesseClass($kurs->bild_groesse);
    if ($kurs->bild_flat) {
        $bildKlasse .= ' img-flat';
    }
    $bildStyle = $kurs->bild_flat ? 'margin-bottom:1.5rem;' : 'border-radius:8px;margin-bottom:1.5rem;';
    $inhaltHtml = \App\Support\Text::renderInhalt($kurs->inhalt);
@endphp
<x-layouts.app :title="$kurs->titel ?: 'Jagdhundeschule'" :description="strip_tags($kurs->intro ?? '') ?: null">
    <x-page-hero :title="$kurs->titel ?: 'Jagdhundeschule'" :bg-image="$kurs->hero_bild ?: '/images/hundeausbildung.jpg'" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <a href="{{ route('hundeausbildung.index') }}" class="content-back-link">← Zurück zu allen Themen</a>

                <h2 style="margin-top:0">{{ $kurs->titel }}</h2>

                @if ($kurs->bild)
                    <img src="{{ $kurs->bild }}" alt="{{ $kurs->bild_alt }}" class="{{ $bildKlasse }}" style="{{ $bildStyle }}" onerror="this.style.display='none'">
                @endif

                @if ($inhaltHtml)
                    <div>{!! $inhaltHtml !!}</div>
                @endif

                @if ($kurs->kontakt_name || $kurs->kontakt_email)
                    <p><strong>Kontakt:</strong> {{ $kurs->kontakt_name }}
                        @if ($kurs->kontakt_email)
                            &middot; <a href="mailto:{{ $kurs->kontakt_email }}">{{ $kurs->kontakt_email }}</a>
                        @endif
                    </p>
                @endif

                <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                    <a href="/kontakt" class="btn btn-primary">Kontakt aufnehmen</a>
                </div>

                @if ($kurs->galerieBilder->isNotEmpty())
                    <div class="galerie-section">
                        <div class="galerie-section__title">{{ $kurs->galerie_titel ?: 'Bildergalerie' }}</div>
                        <div class="galerie-grid">
                            @foreach ($kurs->galerieBilder as $bild)
                                <a href="{{ $bild->pfad }}" target="_blank" rel="noopener noreferrer" class="galerie-item">
                                    <img src="{{ $bild->pfad }}" alt="{{ $bild->titel }}" loading="lazy">
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
                <div class="sidebar-widget">
                    <h4>Jagdhundeschule</h4>
                    <ul class="sidebar-nav">
                        @php $gruppen = $seitenNav->groupBy(fn ($k) => $k->gruppe ?: ''); @endphp
                        @foreach ($gruppen as $gruppe => $seitenInGruppe)
                            @if ($gruppe !== '')
                                <li class="sidebar-group-header" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--green-dark);padding:.5rem 0 .25rem;margin-top:.5rem;border-top:1px solid var(--border);pointer-events:none;">{{ $gruppe }}</li>
                            @endif
                            @foreach ($seitenInGruppe as $k)
                                <li><a href="{{ route('hundeausbildung.show', $k->slug) }}" class="{{ $k->id === $kurs->id ? 'active' : '' }}" @if($gruppe !== '') style="padding-left:.75rem;" @endif>{{ $k->nav_label ?: $k->titel }}</a></li>
                            @endforeach
                        @endforeach
                        <li style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.5rem;">
                            <a href="{{ route('hundeausbildung.hub') }}" style="font-size:.85rem;color:var(--text-muted);">← Zurück zur Übersicht</a>
                        </li>
                    </ul>
                </div>

                @if ($kurs->downloads->isNotEmpty())
                    <div class="sidebar-widget sidebar-widget--downloads">
                        <h4><span class="download-heading-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span> Dokumente &amp; Downloads</h4>
                        <div class="downloads-list downloads-list--sidebar">
                            @foreach ($kurs->downloads as $dl)
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

                <div class="sidebar-widget">
                    <h4>Aufgaben des Hegerings</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <x-kontaktbox title="Kontakt" />
            </aside>
        </div>
    </div>

    <script>
        if (window.setBreadcrumbTrail) {
            window.setBreadcrumbTrail([
                { label: 'Startseite', href: '/' },
                { label: 'Aufgaben' },
                { label: 'Jagdhundeschule', href: '{{ route('hundeausbildung.index') }}' },
                { label: @json($kurs->titel) }
            ]);
        }
    </script>
</x-layouts.app>
