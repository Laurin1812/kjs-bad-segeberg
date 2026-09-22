{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    partner/detail.html. "rahmenvertrag"/"vorteile" werden als echte
    typisierte Werte gerendert (siehe PartnerController-Klassenkommentar) -
    kein unbekannter Partner fuehrt hier hin, PartnerController::show()
    liefert bei fehlendem/inaktivem Partner bereits eine echte Laravel-404.
--}}
<x-layouts.app :title="$partner->name ?: 'Partner'">
    <x-page-hero :title="$partner->name ?: '(Ohne Namen)'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Jäger', 'href' => '/jaeger/uebersicht'],
        ['label' => 'Partner', 'href' => route('partner.index')],
        ['label' => $partner->name ?: 'Partner'],
    ]" />

    <div class="page-content">
        <div class="container pn-detail-layout">
            <main class="main-content">
                <a href="{{ route('partner.index') }}" class="content-back-link">← Zurück zu allen Partnern</a>

                <div class="pn-detail-head">
                    <div class="pn-detail-head__logo{{ $partner->logo ? '' : ' pn-logo-placeholder' }}">
                        @if ($partner->logo)
                            <img src="{{ $partner->logo }}" alt="{{ $partner->name }}">
                        @else
                            <img src="/images/logo.png" alt="KJS">
                        @endif
                    </div>
                    <div class="pn-detail-head__body">
                        <h2>{{ $partner->name ?: '(Ohne Namen)' }}</h2>
                        @if ($partner->kurzbeschreibung)
                            <p style="color:var(--text-muted);margin-bottom:1rem;">{{ $partner->kurzbeschreibung }}</p>
                        @endif
                        @if ($partner->website)
                            <a href="{{ $partner->website }}" class="btn btn-primary pn-website-btn" target="_blank" rel="noopener noreferrer">Zur Website ↗</a>
                        @endif
                    </div>
                </div>

                @php
                    $vorteileText = $partner->vorteile->pluck('text')->filter()->implode(', ');
                @endphp
                @if ($partner->beschreibung || $partner->ansprechpartner || $partner->telefon || $partner->email || $partner->rahmenvertrag || $vorteileText || $partner->weitere_infos)
                    <div class="pn-detail-body">
                        @if ($partner->beschreibung)
                            <h2 class="pn-section-title">Über {{ $partner->name ?: 'diesen Partner' }}</h2>
                            <p>{{ $partner->beschreibung }}</p>
                        @endif

                        @if ($partner->ansprechpartner || $partner->telefon || $partner->email)
                            <h2>Kontakt</h2>
                            <div class="steckbrief">
                                <dl>
                                    @if ($partner->ansprechpartner)
                                        <dt>Ansprechpartner</dt><dd>{{ $partner->ansprechpartner }}</dd>
                                    @endif
                                    @if ($partner->telefon)
                                        <dt>Telefon</dt><dd>{{ $partner->telefon }}</dd>
                                    @endif
                                    @if ($partner->email)
                                        <dt>E-Mail</dt><dd>{{ $partner->email }}</dd>
                                    @endif
                                </dl>
                            </div>
                        @endif

                        @if ($partner->rahmenvertrag || $vorteileText || $partner->weitere_infos)
                            <h2>Weitere Informationen</h2>
                            <div class="steckbrief">
                                <dl>
                                    @if ($partner->rahmenvertrag)
                                        <dt>Rahmenvertrag</dt><dd>Ja</dd>
                                    @endif
                                    @if ($vorteileText)
                                        <dt>Vorteile / Leistungen</dt><dd>{{ $vorteileText }}</dd>
                                    @endif
                                    @if ($partner->weitere_infos)
                                        <dt>Weitere Hinweise</dt><dd>{{ $partner->weitere_infos }}</dd>
                                    @endif
                                </dl>
                            </div>
                        @endif
                    </div>
                @endif
            </main>
            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Partner</h4>
                    <p style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">Einen Überblick über alle Partner der Kreisjägerschaft finden Sie in der Übersicht.</p>
                    <p style="margin-top:.75rem;"><a href="{{ route('partner.index') }}" class="btn btn-outline-green btn-sm">Alle Partner ansehen</a></p>
                </div>
            </aside>
        </div>
    </div>

</x-layouts.app>
