{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    kreisjjaegermeister/index.html. "aufgaben"/"grusswort" werden serverseitig
    ueber KreisjaegermeisterController::renderRichText() aufbereitet (HTML
    unveraendert durchgereicht, reiner Text/Markdown ueber Str::markdown()) -
    siehe dortiger Klassenkommentar.
--}}
<x-layouts.app title="Kreisjägermeister">
    <x-page-hero title="Kreisjägermeister" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <h2>Der Kreisjägermeister</h2>
                <p>Der Kreisjägermeister ist das höchste ehrenamtliche Amt der Kreisjägerschaft Segeberg e.V.
                   Er vertritt die Interessen der Jägerinnen und Jäger im Kreis gegenüber Behörden,
                   Politik und Öffentlichkeit.</p>

                <div class="kjm-profile-grid">
                    <div>
                        @if ($page?->bild)
                            <img src="{{ $page->bild }}" alt="Foto {{ $page->kontakt_name }}" style="width:100%;border-radius:var(--radius-md);border:3px solid var(--green-main);object-fit:contain;padding:8px;">
                        @else
                            <div class="kjm-avatar-placeholder" style="width:100%;aspect-ratio:1;background:var(--green-light);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--green-main);border:3px solid var(--green-main);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                        @endif
                        <div style="margin-top:1rem;text-align:center;">
                            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--green-main);">Kreisjägermeister</div>
                            <div style="font-weight:700;font-size:1.1rem;margin-top:.25rem;">{{ $page?->kontakt_name ?: '–' }}</div>
                        </div>
                    </div>
                    <div>
                        @if ($aufgabenHtml)
                            {!! $aufgabenHtml !!}
                        @endif
                        @if ($page?->kontakt_email || $page?->kontakt_telefon)
                            <h3>Kontakt</h3>
                            <p>
                                @if ($page->kontakt_email)📧 <a href="mailto:{{ $page->kontakt_email }}">{{ $page->kontakt_email }}</a><br>@endif
                                @if ($page->kontakt_telefon)📞 <a href="tel:{{ preg_replace('/\s|-|\//', '', $page->kontakt_telefon) }}">{{ $page->kontakt_telefon }}</a>@endif
                            </p>
                        @endif
                    </div>
                </div>

                @if ($grusswortHtml)
                    <h3>Grußwort des Kreisjägemeisters</h3>
                    <div style="border-left:4px solid var(--gold);padding:1.5rem 1.5rem 1.5rem 2rem;background:var(--bg-light);border-radius:0 var(--radius-md) var(--radius-md) 0;font-style:italic;margin-top:.75rem;">
                        {!! $grusswortHtml !!}
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Jäger</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <div class="contact-box"></div>
            </aside>
        </div>
    </div>
</x-layouts.app>
