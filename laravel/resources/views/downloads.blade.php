{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    downloads/index.html. Zeigt NUR die echte, DB-gestuetzte zentrale
    Download-Bibliothek - die im Original hart hinterlegten Platzhalter-
    Downloads (Satzung/Beitragsordnung-Beispiele etc.) wurden bewusst NICHT
    uebernommen (siehe DownloadsController-Klassenkommentar).
--}}
<x-layouts.app title="Downloads & Dokumente">
    <x-page-hero title="Downloads & Dokumente" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Downloads'],
    ]" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content">
                <h2>{{ $titel }}</h2>
                @if ($intro)
                    <p style="color:var(--text-muted);margin-bottom:2rem;">{{ $intro }}</p>
                @endif

                @forelse ($kategorien as $kat)
                    <div class="download-section-title">{{ $kat->titel }}</div>
                    <div class="downloads-list">
                        @foreach ($kat->downloads as $dl)
                            <div class="download-item">
                                <div class="download-item__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></div>
                                <div class="download-item__meta">
                                    <div class="download-item__name">{{ $dl->titel }}</div>
                                    @if ($dl->beschreibung)
                                        <div class="download-item__info">{{ $dl->beschreibung }}</div>
                                    @endif
                                </div>
                                <a href="{{ $dl->pfad }}" class="btn btn-sm btn-outline-green" download>⬇ Download</a>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p style="color:var(--text-muted);">Aktuell sind keine Downloads hinterlegt.</p>
                @endforelse

                <div style="margin-top: 2.5rem; padding: 1.5rem; background: var(--green-light); border-radius: var(--radius-md); border-left: 4px solid var(--green-main);">
                    <h4 style="color: var(--green-dark); margin-bottom: .5rem;">Dokument nicht gefunden?</h4>
                    <p style="margin:0; font-size: .9rem;">
                        Falls Sie ein bestimmtes Dokument suchen und es hier nicht finden können,
                        wenden Sie sich bitte an unsere Geschäftsstelle
                        @if($kjsAllgemeineEmail)
                            : <a href="mailto:{{ $kjsAllgemeineEmail }}">{{ $kjsAllgemeineEmail }}</a>.
                        @else
                            .
                        @endif
                    </p>
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
