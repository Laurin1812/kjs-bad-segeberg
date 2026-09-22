{{--
    Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
    Vollmigration).

    GEMEINSAME generische View fuer FesteSeiteController UND
    RegistrySeiteController - ersetzt gleichzeitig
      - die ~24 festen Vorlagen-Seiten (jaeger/hochwild.html,
        aufgaben/jagdhorn.html, verbraucher/wildfleisch.html & Co.,
        $mode==='feste'),
      - alle per Registry hinzugefuegten Zusatzseiten + "weitere"-Seiten +
        dynamisch angelegten Unterseiten (frueher gemeinsam ueber
        seiten/index.html?s=<slug> gerendert, $mode==='registry').
    Beide Legacy-Vorlagen unterscheiden sich in zwei Punkten faktisch (kein
    Redesign, sondern 1:1 uebernommene bestehende Abweichung):
      - "feste" Seiten haben eine "Weiterführende Links"-Box
        (linkliste/linkliste_titel) und einen Kontakt-/Zurück-Button am
        Fuss, aber KEINE Downloads-/Galerie-Anzeige (siehe
        jaeger/hochwild.html, aufgaben/jagdhorn.html - "downloads"/"galerie"
        werden von der API zwar mitgeliefert, aber vom Template nie
        gerendert).
      - "registry" Seiten (seiten/index.html) zeigen dagegen Downloads UND
        Bildergalerie, aber keine Linkliste und keine CTA-Buttons.
    Beide Modi teilen sich Titel/Intro/Bild/Inhalt/Kontakt sowie die
    "Unterseiten"-Sidebar-Box (Kind-Seiten dieser Seite, falls vorhanden).
--}}
@props([])
@php
    $bildKlasse = \App\Support\Images::groesseClass($page->bild_groesse);
    if ($page->bild_flat) {
        $bildKlasse .= ' img-flat';
    }
    $bildStyle = $page->bild_flat ? 'margin-bottom:1.5rem;' : 'border-radius:8px;margin-bottom:1.5rem;';
    $inhaltHtml = \App\Support\Text::renderInhalt($page->inhalt);
    $subSeiten = $page->relationLoaded('children') ? $page->children : collect();
    $sektionLabel = ['jaeger' => 'Jäger', 'aufgaben' => 'Aufgaben', 'verbraucher' => 'Verbraucher', 'weitere' => 'Weitere Themen'][$section] ?? ucfirst($section);
    // Phase 4 (Auftrag Punkt 3, "Bei Unterseiten Elternseite einbeziehen"):
    // RegistrySeiteController::sub() uebergibt zusaetzlich die Elternseite -
    // damit wird der Trail vier statt drei Ebenen tief (Startseite ->
    // Section -> Elternseite -> diese Unterseite).
    $kjsBreadcrumbs = [
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => $sektionLabel],
    ];
    if (isset($parent)) {
        $kjsBreadcrumbs[] = ['label' => $parent->nav_label ?: $parent->titel, 'href' => url($section.'/'.$parent->slug)];
    }
    $kjsBreadcrumbs[] = ['label' => $page->nav_label ?: $page->titel];
@endphp
<x-layouts.app :title="$page->titel" :description="strip_tags($page->intro ?? '') ?: null">
    <x-page-hero :title="$page->titel" :bg-image="$page->hero_bild ?: '/images/stock/hero-default.jpg'" :breadcrumbs="$kjsBreadcrumbs" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                @if ($page->untertitel)
                    <h2>{!! $page->untertitel !!}</h2>
                @endif

                @if ($page->intro)
                    <p>{!! $page->intro !!}</p>
                @endif

                @if ($page->bild)
                    <img src="{{ $page->bild }}" alt="{{ $page->bild_alt }}" class="{{ $bildKlasse }}" style="{{ $bildStyle }}" onerror="this.style.display='none'">
                @endif

                @if ($inhaltHtml)
                    <div>{!! $inhaltHtml !!}</div>
                @elseif (! $page->intro)
                    <p style="color:var(--text-muted);">Kein Inhalt vorhanden.</p>
                @endif

                @if ($page->kontakt_name || $page->kontakt_email)
                    <p style="margin-top:1.5rem;">
                        <strong>Kontakt:</strong> {{ $page->kontakt_name }}
                        @if ($page->kontakt_email)
                            &middot; <a href="mailto:{{ $page->kontakt_email }}">{{ $page->kontakt_email }}</a>
                        @endif
                    </p>
                @endif

                {{--
                    Phase 4 (Auftrag Punkt 7, "Jäger-Übersicht"): das Kachel-
                    Raster von jaeger/index.html, jetzt aus MySQL/Nav-Daten
                    erzeugt statt hart codiert (siehe FesteSeiteController /
                    App\Support\Navigation::jaegerUebersichtKacheln()). Nur
                    auf der einen Seite vorhanden, die diese Daten bekommt
                    (aktuell ausschliesslich jaeger/uebersicht).
                --}}
                @isset($geschwister)
                    @if (! empty($geschwister))
                        <div class="news__grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem;margin:2.5rem 0;">
                            @foreach ($geschwister as $kachel)
                                <a href="{{ $kachel['href'] }}" class="service-card" style="text-decoration:none;color:inherit;">
                                    <div class="service-card__img-wrap">
                                        @if ($kachel['bild'])
                                            <img class="service-card__img" src="{{ $kachel['bild'] }}" alt="{{ $kachel['label'] }}" loading="lazy" onerror="kjsImgFallback(this)">
                                        @else
                                            <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:140px;"><img src="{{ asset('images/logo.png') }}" alt="KJS" style="height:56px;opacity:.35;"></div>
                                        @endif
                                    </div>
                                    <div class="service-card__body">
                                        <div class="service-card__title">{{ $kachel['label'] }}</div>
                                        @if ($kachel['beschreibung'])
                                            <p class="service-card__text">{{ $kachel['beschreibung'] }}</p>
                                        @endif
                                        <span class="read-more">Mehr erfahren</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endisset

                @if ($mode === 'registry' && $page->galerieBilder->isNotEmpty())
                    <div class="galerie-section">
                        <div class="galerie-section__title">{{ $page->galerie_titel ?: 'Bildergalerie' }}</div>
                        <div class="galerie-grid">
                            @foreach ($page->galerieBilder as $bild)
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

                @if ($mode === 'feste')
                    <div class="btn-group" style="margin-top:2rem;">
                        @if ($page->antrag_url)
                            <a href="{{ $page->antrag_url }}" class="btn btn-primary">Mitgliedsantrag online</a>
                            <a href="/kontakt" class="btn btn-outline-green">Fragen? Kontakt aufnehmen</a>
                        @elseif ($section === 'jaeger')
                            <a href="/kontakt" class="btn btn-primary">Jetzt Kontakt aufnehmen</a>
                            <a href="/jaeger/uebersicht" class="btn btn-outline-green">Zurück zur Übersicht</a>
                        @else
                            <a href="{{ route('termine') }}" class="btn btn-primary">Termine ansehen</a>
                            <a href="/kontakt" class="btn btn-outline-green">Kontakt aufnehmen</a>
                        @endif
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                @if ($mode === 'feste' && $page->links->isNotEmpty())
                    <div class="sidebar-widget sidebar-widget--highlight">
                        <h4>{{ $page->linkliste_titel ?: 'Weiterführende Links' }}</h4>
                        <ul class="sidebar-nav">
                            @foreach ($page->links as $link)
                                @php $extern = str_starts_with($link->href, 'http://') || str_starts_with($link->href, 'https://'); @endphp
                                <li><a href="{{ $link->href }}" @if($extern) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label ?: $link->href }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($mode === 'registry' && $page->downloads->isNotEmpty())
                    <div class="sidebar-widget sidebar-widget--downloads">
                        <h4><span class="download-heading-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span> Dokumente &amp; Downloads</h4>
                        <div class="downloads-list downloads-list--sidebar">
                            @foreach ($page->downloads as $dl)
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

                @if ($subSeiten->isNotEmpty())
                    <div class="sidebar-widget">
                        <h4>{{ $page->unterseiten_titel ?: ($mode === 'feste' ? 'Unterseiten zu '.$page->titel : 'Weitere Seiten') }}</h4>
                        <ul class="unterseiten-tiles">
                            @foreach ($subSeiten as $sub)
                                <li>
                                    <a class="unterseite-tile" href="{{ url($section.'/'.$page->slug.'/'.$sub->slug) }}">
                                        <span class="unterseite-tile__icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
                                        <span class="unterseite-tile__label">{{ $sub->nav_label ?: $sub->titel ?: $sub->slug }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>

                <x-kontaktbox :title="$mode === 'registry' ? 'Fragen?' : 'Geschäftsstelle'" />
            </aside>
        </div>
    </div>
</x-layouts.app>
