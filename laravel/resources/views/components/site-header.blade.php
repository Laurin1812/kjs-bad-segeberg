{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Statisches Markup 1:1 aus js/components.js (Header/Topbar/Mobile-Nav-
    IIFE) uebernommen - selbe Klassen, selbe Struktur, selbe root-relativen
    Pfade (/images/logo.png, /). Bisher wurde dieses HTML zur Laufzeit per
    "mount.outerHTML = ...' in einen leeren <div id="siteHeader"> injiziert;
    jetzt rendert Laravel es direkt serverseitig - fuer Besucher optisch und
    strukturell keine Aenderung (#mainNav/#mobileNavList/#navToggle/
    #mobileNav bleiben exakt gleich benannt).

    Die Topbar-Kontaktdaten (E-Mail/Telefon) sind bewusst weiterhin nur
    Platzhalter: das bestehende Hydration-Skript in resources/js/app.js
    ("Topbar & Geschäftsstelle dynamisch laden", liest aus
    content/einstellungen.json) ueberschreibt sie zur Laufzeit unveraendert
    weiter - keine zweite Datenquelle. Ebenso befuellen die Navigations-
    Module in resources/js/app.js weiterhin #mainNav/#mobileNavList zur
    Laufzeit (Server-seitiges Rendern der Navigationseintraege selbst ist
    Phase 4).

    Nachtrag (Phase-1-Nacharbeit, offener Punkt "Logo wird nicht geladen"):
    "/images/logo.png" war ein 1:1 aus js/components.js uebernommener,
    root-relativer Pfad, der im alten statischen Webroot auf eine dort
    liegende Datei zeigte - im Laravel-Dokumentenstamm (public/) existierte
    diese Datei nie, das Logo lief daher ins Leere. Fix: die echte, von der
    produktiven Seite genutzte Logo-Datei (images/logo.png im alten
    Webroot-Wurzelverzeichnis, 400x400 PNG) liegt jetzt als echtes,
    committetes Laravel-Asset unter public/images/logo.png und wird ueber
    den Standard-Helper asset() referenziert - keine Laufzeit-Abhaengigkeit
    auf den alten Webroot mehr. Der Footer nutzt dieselbe Loesung, siehe
    site-footer.blade.php (dort per window.KJS_ASSETS an das JS-gebaute
    Footer-Markup uebergeben, da der Footer-Inhalt weiterhin per
    resources/js/app.js zusammengebaut wird, siehe dortiger Kommentar).
--}}
<div class="topbar">
    <div class="container">
        <div class="topbar__left">
            <span>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></span>
            <span>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></span>
        </div>
        <div class="topbar__right">
            <div class="topbar__social">
                <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Facebook" title="Facebook" class="topbar__social--facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
                <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Kreisjägerschaft Segeberg auf Instagram" title="Instagram" class="topbar__social--instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg></a>
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
            <ul class="main-nav" id="mainNav"></ul>
        </nav>
        <button class="nav-toggle" id="navToggle" aria-label="Menü öffnen"><span></span><span></span><span></span></button>
    </div>
</header>
<nav class="mobile-nav" id="mobileNav" aria-label="Mobile Navigation">
    <button class="mobile-nav__close" id="mobileNavClose" aria-label="Menü schließen">✕</button>
    <ul id="mobileNavList"></ul>
</nav>
