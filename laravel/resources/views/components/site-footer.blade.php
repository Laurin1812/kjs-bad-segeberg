{{--
    Phase 1 (Blade-Fundament) + Phase 4 (Startseite + komplette Laravel-
    Navigation, Laravel-Vollmigration).

    Ersetzt das bisherige "ZENTRALER FOOTER"-Modul in resources/js/app.js
    (fetch aus content/footer.json). Inhalte kommen jetzt serverseitig aus
    $kjsFooter (siehe App\View\Composers\FooterComposer, settings-Tabelle
    Gruppe "footer" + footer_links-Tabelle) - kein /api/content/footer.json-
    Request mehr. Markup 1:1 aus der bisherigen renderFooter()-Funktion
    uebernommen, Links laufen durch Navigation::prettyHref() (siehe
    FooterComposer) und zeigen dadurch auf die neuen Laravel-Routen statt
    auf die alten ".html"-Pfade.
--}}
<footer class="site-footer" id="siteFooter">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <img src="{{ asset('images/logo-dunkel.png') }}" alt="KJS Logo" style="height:58px;width:auto;margin-bottom:1rem;">
                <span class="footer-about__name">Kreisjägerschaft Segeberg e.V.</span>
                <span class="footer-about__sub">Mitglied im Landesjagdverband Schleswig-Holstein</span>
                @if ($kjsFooter['ueberText'])
                    <p>{{ $kjsFooter['ueberText'] }}</p>
                @endif
                <div class="footer-social">
                    <a href="{{ $kjsFooter['facebookUrl'] ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Facebook" class="footer-social--facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
                    <a href="{{ $kjsFooter['instagramUrl'] ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Instagram" class="footer-social--instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg></a>
                </div>
            </div>

            @foreach ([['Über die KJS', $kjsFooter['ueberKjs']], ['Schnellübersicht', $kjsFooter['uebersicht']], ['Informationen', $kjsFooter['informationen']]] as [$titel, $links])
                @if ($links->isNotEmpty())
                    <div class="footer-col">
                        <h5>{{ $titel }}</h5>
                        <ul>
                            @foreach ($links as $link)
                                <li><a href="{{ $link['href'] ?: '#' }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="footer-bottom">
            <span>{{ $kjsFooter['copyright'] }}</span>
            <div class="footer-bottom__links">
                <a href="{{ route('impressum') }}">Impressum</a>
                <a href="{{ route('datenschutz') }}">Datenschutz</a>
                <a href="/admin/" class="admin-login-link" target="_blank" rel="noopener noreferrer">Login</a>
            </div>
        </div>
    </div>
</footer>
