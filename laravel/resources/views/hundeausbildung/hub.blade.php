{{--
    Phase 3 (Dynamische Seitenfamilien & Hundeausbildung, Laravel-
    Vollmigration): ersetzt aufgaben/hundeausbildung.html (Hub-Seite).
    Wie im Original werden hier bewusst KEINE Downloads/Bildergalerie
    gerendert (das Hub-Template hatte das nie, siehe HundeausbildungController-
    Klassenkommentar) - das gibt es erst auf den einzelnen Kurs-Seiten
    (hundeausbildung/show.blade.php).
--}}
@php
    $bildKlasse = \App\Support\Images::groesseClass($hub->bild_groesse);
    if ($hub->bild_flat) {
        $bildKlasse .= ' img-flat';
    }
    $bildStyle = $hub->bild_flat ? 'margin-bottom:1.5rem;' : 'border-radius:8px;margin-bottom:1.5rem;';
    $inhaltHtml = \App\Support\Text::renderInhalt($hub->inhalt);
@endphp
<x-layouts.app :title="$hub->titel ?: 'Hundeausbildung'" :description="strip_tags($hub->intro ?? '') ?: null">
    <x-page-hero :title="$hub->titel ?: 'Hundeausbildung'" :bg-image="$hub->hero_bild ?: '/images/hundeausbildung.jpg'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Aufgaben'],
        ['label' => $hub->titel ?: 'Hundeausbildung'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                @if ($hub->untertitel)
                    <h2>{!! $hub->untertitel !!}</h2>
                @endif

                @if ($hub->intro)
                    <p>{!! $hub->intro !!}</p>
                @endif

                @if ($hub->bild)
                    <img src="{{ $hub->bild }}" alt="{{ $hub->bild_alt }}" class="{{ $bildKlasse }}" style="{{ $bildStyle }}" onerror="this.style.display='none'">
                @endif

                @if ($inhaltHtml)
                    <div>{!! $inhaltHtml !!}</div>
                @endif

                @if ($hub->kontakt_name || $hub->kontakt_email)
                    <p><strong>Kontakt:</strong> {{ $hub->kontakt_name }}
                        @if ($hub->kontakt_email)
                            &middot; <a href="mailto:{{ $hub->kontakt_email }}">{{ $hub->kontakt_email }}</a>
                        @endif
                    </p>
                @endif

                <div class="btn-group" style="margin-top:2rem;">
                    <a href="{{ route('termine') }}" class="btn btn-primary">Termine ansehen</a>
                    <a href="/kontakt" class="btn btn-outline-green">Kontakt aufnehmen</a>
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Jagdhundeschule</h4>
                    <a href="{{ route('hundeausbildung.index') }}" class="btn btn-primary" style="display:block;text-align:center;">
                        @if ($anzahlKurse > 0)
                            Alle {{ $anzahlKurse }} Kurse & Themen ansehen →
                        @else
                            Alle Kurse & Themen ansehen →
                        @endif
                    </a>
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
