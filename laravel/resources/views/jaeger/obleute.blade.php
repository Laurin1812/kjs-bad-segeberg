{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): analoge
    Seite zu jaeger/vorstand.html fuer das Gremium "obmann" - siehe
    PersonenGremiumController-Klassenkommentar.
--}}
<x-layouts.app title="Obleute">
    <x-page-hero title="Obleute" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Jäger', 'href' => '/jaeger/uebersicht'],
        ['label' => 'KJS Segeberg'],
        ['label' => 'Obleute'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <h2>Die Obleute der KJS Segeberg</h2>
                <p>
                    Die Obleute der Kreisjägerschaft Segeberg e.V. übernehmen fachliche Verantwortung
                    in ihrem jeweiligen Aufgabenbereich und stehen den Mitgliedern dort als
                    Ansprechpartner zur Verfügung.
                </p>

                <div class="persons-grid" style="margin-top: 2.5rem;">
                    @forelse ($mitglieder as $m)
                        <div class="person-card">
                            <div class="person-card__avatar-wrap">
                                @if ($m->bild)
                                    <img src="{{ \App\Support\Images::cardUrl($m->bild) }}" data-full="{{ $m->bild }}" onerror="kjsImgFallback(this)" alt="{{ $m->name }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                                @else
                                    <div class="person-card__avatar-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                                @endif
                            </div>
                            <div class="person-card__body">
                                <span class="person-card__role">{{ $m->rolle }}</span>
                                <div class="person-card__name">{{ $m->name }}</div>
                                <div class="person-card__contact">
                                    @if ($m->email)<a href="mailto:{{ $m->email }}">{{ $m->email }}</a>@endif
                                    @if ($m->telefon)<a href="tel:{{ preg_replace('/\s|\/|\./', '', $m->telefon) }}">{{ $m->telefon }}</a>@endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p>Daten zu den Obleuten konnten nicht geladen werden.</p>
                    @endforelse
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <x-kontaktbox />
            </aside>
        </div>
    </div>
</x-layouts.app>
