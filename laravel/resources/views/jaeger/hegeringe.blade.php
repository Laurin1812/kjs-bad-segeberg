{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt die
    Hegeringe-Uebersichtsseite. Kartenraster (.hegering-grid/.hegering-card,
    siehe resources/css/app.css) - reines Read-only, keine Business-Logik.
--}}
<x-layouts.app title="Hegeringe">
    <x-page-hero title="Hegeringe" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <h2>Die Hegeringe der KJS Segeberg</h2>
                <p>
                    Die Kreisjägerschaft Segeberg e.V. gliedert sich in mehrere Hegeringe, die jeweils
                    für die Reviere in ihrem Gebiet zuständig sind und von einem Hegeringobmann bzw.
                    einer Hegeringobfrau geleitet werden.
                </p>

                <div class="hegering-grid" style="margin-top: 2.5rem;">
                    @forelse ($hegeringe as $h)
                        <div class="hegering-card">
                            <span class="hegering-card__num">Hegering {{ $h->nummer }}</span>
                            <h4>{{ $h->name }}</h4>
                            @if ($h->obmann)
                                <p><strong>{{ $h->geschlecht === 'w' ? 'Hegeringobfrau' : 'Hegeringobmann' }}:</strong> {{ $h->obmann }}</p>
                            @endif
                            @if ($h->gemeinden)
                                <p>{{ $h->gemeinden }}</p>
                            @endif
                            @if ($h->email)
                                <p><a href="mailto:{{ $h->email }}">{{ $h->email }}</a></p>
                            @endif
                            @if ($h->telefon)
                                <p><a href="tel:{{ preg_replace('/\s|\/|\./', '', $h->telefon) }}">{{ $h->telefon }}</a></p>
                            @endif
                        </div>
                    @empty
                        <p>Hegeringe konnten nicht geladen werden.</p>
                    @endforelse
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
                <div class="contact-box">
                    <h4>Geschäftsstelle</h4>
                    <p>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></p>
                    <p>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
