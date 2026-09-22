{{--
    Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
    Vollmigration): ersetzt aufgaben/jagdhundeschule.html (Uebersichts-Modus,
    frueher ohne "?s=<slug>"-Parameter). URL-Schema modernisiert - der
    einzelne Kurs bekommt jetzt eine echte Route (hundeausbildung.show)
    statt eines Query-Parameters, siehe HundeausbildungController-
    Klassenkommentar.
--}}
@php
    $gruppen = $kurse->groupBy(fn ($k) => $k->gruppe ?: '');
@endphp
<x-layouts.app title="Jagdhundeschule" description="Ausbildungskurse, Prüfungen und Themen rund um den Jagdhund der Kreisjägerschaft Segeberg.">
    <x-page-hero title="Jagdhundeschule" :bg-image="$hub->hero_bild ?: '/images/hundeausbildung.jpg'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Aufgaben'],
        ['label' => 'Jagdhundeschule'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <h2 style="margin-top:0">Jagdhundeschule KJS Segeberg</h2>
                <p style="color:var(--text-muted);margin-bottom:2rem;">
                    Informationen zu Ausbildungskursen, Prüfungen, Spezialthemen und Veranstaltungen rund um den Jagdhund.
                </p>

                @if ($kurse->isEmpty())
                    <p class="text-muted">Noch keine Seiten vorhanden.</p>
                @else
                    <div class="news__grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.25rem;">
                        @foreach ($kurse as $k)
                            <a href="{{ route('hundeausbildung.show', $k->slug) }}" class="news-card" style="text-decoration:none;color:inherit;">
                                <div class="news-card__img-wrap">
                                    @if ($k->vorschaubild)
                                        <img class="news-card__img" src="{{ $k->vorschaubild }}" alt="{{ $k->nav_label ?: $k->titel }}" loading="lazy" onerror="kjsImgFallback(this)">
                                    @else
                                        <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:140px;"><span style="font-size:2rem;">🐕</span></div>
                                    @endif
                                </div>
                                <div class="news-card__body" style="padding:1rem;">
                                    <h3 class="news-card__title" style="font-size:.95rem;">{{ $k->nav_label ?: $k->titel }}</h3>
                                    @if ($k->kurzbeschreibung)
                                        <p class="news-card__excerpt" style="font-size:.82rem;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;">{{ $k->kurzbeschreibung }}</p>
                                    @endif
                                    <span style="font-size:.82rem;font-weight:600;color:var(--green-main);">Mehr erfahren →</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Jagdhundeschule</h4>
                    @if ($kurse->isEmpty())
                        <ul class="sidebar-nav"><li><em style="color:var(--text-muted);font-size:.85rem;">Keine Seiten vorhanden</em></li></ul>
                    @else
                        <ul class="sidebar-nav">
                            @foreach ($gruppen as $gruppe => $seitenInGruppe)
                                @if ($gruppe !== '')
                                    <li class="sidebar-group-header" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--green-dark);padding:.5rem 0 .25rem;margin-top:.5rem;border-top:1px solid var(--border);pointer-events:none;">{{ $gruppe }}</li>
                                @endif
                                @foreach ($seitenInGruppe as $k)
                                    <li><a href="{{ route('hundeausbildung.show', $k->slug) }}" @if($gruppe !== '') style="padding-left:.75rem;" @endif>{{ $k->nav_label ?: $k->titel }}</a></li>
                                @endforeach
                            @endforeach
                            <li style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.5rem;">
                                <a href="{{ route('hundeausbildung.hub') }}" style="font-size:.85rem;color:var(--text-muted);">← Zurück zur Übersicht</a>
                            </li>
                        </ul>
                    @endif
                </div>
                <div class="sidebar-widget">
                    <h4>Aufgaben des Hegerings</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <x-kontaktbox title="Kontakt" />
            </aside>
        </div>
    </div>
</x-layouts.app>
