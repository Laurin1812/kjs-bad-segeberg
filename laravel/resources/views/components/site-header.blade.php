{{--
    Phase 1 (Blade-Fundament) + Phase 4 (Startseite + komplette Laravel-
    Navigation, Laravel-Vollmigration).

    Statisches Markup weiterhin 1:1 aus js/components.js (Header/Topbar/
    Mobile-Nav-IIFE) uebernommen - selbe Klassen, selbe Struktur
    (#mainNav/#mobileNavList/#navToggle/#mobileNav bleiben exakt gleich
    benannt, das mobile Oeffnen/Schliessen bleibt reines JS-UI-Verhalten,
    siehe resources/js/app.js).

    NEU in Phase 4 ("100% Laravel", Auftrag Punkt 2/4): Topbar-Kontaktdaten
    UND die kompletten Desktop-/Mobile-Navigationseintraege werden jetzt
    serverseitig gerendert (per View-Composer $kjsTopbar/$kjsNav, siehe
    App\View\Composers\TopbarComposer/NavigationComposer). Die bisherigen
    Laufzeit-Module in resources/js/app.js ("Topbar & Geschäftsstelle
    dynamisch laden" aus content/einstellungen.json, "ZENTRALE NAVIGATION"
    aus navigation.json/navigation-extra.json + Registry-Dateien) sind
    dadurch vollstaendig entfallen - kein /api/content/*.json-Request mehr
    fuer Topbar/Navigation. Die reine Hover-Flyout-UX (verzoegertes
    Schliessen der Dropdowns) bleibt als eigenstaendiges UI-Modul in
    resources/js/app.js erhalten (siehe dortiger Kommentar
    "Hover-Flyouts") - die Dropdowns selbst funktionieren dank CSS
    (:hover) auch ganz ohne JavaScript.
--}}
@php
    $kjsCurrentPath = '/'.ltrim(request()->path(), '/');
    $kjsIsActiveHref = function (?string $href) use ($kjsCurrentPath) {
        if (! $href || $href === '#' || str_starts_with($href, 'http')) {
            return false;
        }
        $href = $href === '' ? '/' : $href;

        return $href === '/' ? $kjsCurrentPath === '/' : str_starts_with($kjsCurrentPath, $href);
    };
    $kjsHasActiveChild = function (array $children) use (&$kjsHasActiveChild, $kjsIsActiveHref) {
        foreach ($children as $child) {
            if (($child['type'] ?? null) === 'flyout') {
                if ($kjsHasActiveChild($child['children'] ?? [])) {
                    return true;
                }

                continue;
            }
            if ($kjsIsActiveHref($child['href'] ?? null)) {
                return true;
            }
        }

        return false;
    };
@endphp
<div class="topbar">
    <div class="container">
        <div class="topbar__left">
            @if ($kjsTopbar['email'])
                <span><span class="topbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span> <a href="mailto:{{ $kjsTopbar['email'] }}">{{ $kjsTopbar['email'] }}</a></span>
            @endif
            @if ($kjsTopbar['telefon'])
                <span><span class="topbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span> <a href="tel:{{ preg_replace('/\s|\/|\./', '', $kjsTopbar['telefon']) }}">{{ $kjsTopbar['telefon'] }}</a></span>
            @endif
        </div>
        <div class="topbar__right">
            <div class="topbar__social">
                <a href="{{ $kjsTopbar['facebookUrl'] ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Facebook" title="Facebook" class="topbar__social--facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
                <a href="{{ $kjsTopbar['instagramUrl'] ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Instagram" title="Instagram" class="topbar__social--instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg></a>
            </div>
        </div>
    </div>
</div>
<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="site-logo">
            <img src="{{ asset('images/logo.png') }}" alt="KJS Segeberg Logo" style="height:76px;width:auto;">
            <div class="site-logo__text">
                <span class="site-logo__name">Kreisjägerschaft</span>
                <span class="site-logo__sub">Segeberg <span class="no-caps">e.V.</span></span>
            </div>
        </a>
        <nav aria-label="Hauptnavigation">
            <ul class="main-nav" id="mainNav">
                @foreach ($kjsNav as $item)
                    @if ($item['children'] === null)
                        <li class="{{ $kjsIsActiveHref($item['href']) ? 'active' : '' }}"><a href="{{ $item['href'] ?: '#' }}" data-navkey="{{ $item['key'] }}">{{ $item['label'] }}</a></li>
                    @else
                        <li class="{{ $kjsHasActiveChild($item['children']) ? 'active' : '' }}">
                            <a href="#" data-navkey="{{ $item['key'] }}">{{ $item['label'] }} <span class="arrow">▾</span></a>
                            <ul class="dropdown">
                                @foreach ($item['children'] as $child)
                                    @if ($child['type'] === 'flyout')
                                        <li class="has-sub"><a href="#">{{ $child['label'] }} <span class="arrow-right">▸</span></a>
                                            <ul class="dropdown dropdown--sub">
                                                @foreach ($child['children'] as $leaf)
                                                    <li><a href="{{ $leaf['href'] ?: '#' }}">{{ $leaf['label'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @else
                                        <li><a href="{{ $child['href'] ?: '#' }}">{{ $child['label'] }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
        <button class="nav-toggle" id="navToggle" aria-label="Menü öffnen"><span></span><span></span><span></span></button>
    </div>
</header>
<nav class="mobile-nav" id="mobileNav" aria-label="Mobile Navigation">
    <button class="mobile-nav__close" id="mobileNavClose" aria-label="Menü schließen">✕</button>
    <ul id="mobileNavList">
        @foreach ($kjsNav as $item)
            @if ($item['children'] === null)
                <li><a href="{{ $item['href'] ?: '#' }}">{{ $item['label'] }}</a></li>
            @else
                <li>
                    <details>
                        <summary>{{ $item['label'] }}</summary>
                        <ul class="mobile-nav__sub">
                            @foreach ($item['children'] as $child)
                                @if ($child['type'] === 'flyout')
                                    @foreach ($child['children'] as $leaf)
                                        <li><a href="{{ $leaf['href'] ?: '#' }}">{{ $leaf['label'] }}</a></li>
                                    @endforeach
                                @else
                                    <li><a href="{{ $child['href'] ?: '#' }}">{{ $child['label'] }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </details>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
