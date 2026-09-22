{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    jaeger/vorstand.html. "Verwandte Seiten"-Sidebar-Widget nutzt das
    generische data-related-nav-Modul aus resources/js/app.js.
--}}
<x-layouts.app title="Vorstand">
    <x-page-hero title="Vorstand" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Jäger', 'href' => '/jaeger/uebersicht'],
        ['label' => 'KJS Segeberg'],
        ['label' => 'Vorstand'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <h2>Der Vorstand der KJS Segeberg</h2>
                <p>
                    Der Vorstand der Kreisjägerschaft Segeberg e.V. besteht aus ehrenamtlich tätigen
                    Jägerinnen und Jägern, die sich für die Interessen ihrer Mitglieder und für die
                    nachhaltige Jagd im Kreis Bad Segeberg einsetzen.
                </p>
                <p>
                    Der Vorstand tagt regelmäßig und ist Ansprechpartner für alle Mitglieder in
                    Fragen rund um Jagd, Naturschutz, Ausbildung und Vereinsleben.
                </p>

                <div class="persons-grid" style="margin-top: 2.5rem;">
                    @forelse ($mitglieder as $m)
                        <div class="person-card">
                            <div class="person-card__avatar-wrap">
                                @if ($m->bild)
                                    <img src="{{ \App\Support\Images::cardUrl($m->bild) }}" data-full="{{ $m->bild }}" onerror="kjsImgFallback(this)" alt="{{ $m->name }}" style="width:100%;height:100%;object-fit:cover;">
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
                        <p>Vorstandsdaten konnten nicht geladen werden.</p>
                    @endforelse
                </div>

                <div style="margin-top: 2.5rem; padding: 1.5rem; background: var(--green-light); border-radius: var(--radius-md); border-left: 4px solid var(--green-main);">
                    <h4 style="color: var(--green-dark); margin-bottom: .5rem;">📌 Hinweis</h4>
                    <p style="margin:0; font-size: .9rem;">
                        Die Vorstandsdaten werden regelmäßig aktualisiert. Alle Vorstandsmitglieder sind
                        ehrenamtlich tätig.
                        @if($kjsAllgemeineEmail)
                            Bitte richten Sie allgemeine Anfragen an
                            <a href="mailto:{{ $kjsAllgemeineEmail }}">{{ $kjsAllgemeineEmail }}</a>.
                        @endif
                    </p>
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <x-kontaktbox />
                <div class="sidebar-widget">
                    <h4>Nächste Termine</h4>
                    <ul class="sidebar-nav">
                        <li><a href="{{ route('termine') }}">Alle Termine ansehen →</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
