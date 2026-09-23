{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    Grundgeruest der neuen, geschuetzten Blade-Admin-Oberflaeche (Header +
    Sidebar + Hauptbereich, siehe Auftrag "Teil 4 - Admin-Shell"). Wird nur
    von Seiten HINTER App\Http\Middleware\EnsureAdminWebSession genutzt
    (siehe routes/web.php) - kein zusaetzlicher Auth-Check hier im Layout
    noetig/sinnvoll (serverseitige Middleware bleibt die alleinige
    Sicherheitsgrenze, siehe Auftrag "keine versteckten Frontend-Checks als
    alleinige Sicherheit").

    Navigation (Teil 5): listet ALLE spaeter zu migrierenden Bereiche schon
    jetzt sichtbar auf (Wiedererkennbarkeit, klare Roadmap fuer den
    Redakteur), aber ausschliesslich "Dashboard" ist ein echter Link - jeder
    andere Punkt ist bewusst ein nicht-klickbarer <span> mit "Folgt"-Marke
    statt eines <a href>, damit garantiert KEINE 404-Navigation entstehen
    kann (Auftrag: "Keine 404-Navigation erzeugen"). Die Liste selbst
    orientiert sich an admin.js' PERM_BY_KEY (siehe Alt-Admin-Inventur) und
    an der im Auftrag Teil 5 selbst vorgegebenen Gruppierung - keine neuen,
    dort nicht genannten Bereiche erfunden.
--}}
@props(['title' => 'Admin'])
@php
    $kjsAdminNav = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '📊'],
        // Phase 7B (Admin-Modul "Inhalte/Seiten"): erster echter Link -
        // "routeIs('admin.inhalte.*')" statt eines exakten Vergleichs, damit
        // die Sidebar auch auf der Bearbeiten-Unterseite als aktiv markiert
        // bleibt.
        ['label' => 'Inhalte (Seiten)', 'route' => 'admin.inhalte.index', 'routePattern' => 'admin.inhalte.*', 'icon' => '📄'],
        ['label' => 'Aktuelles', 'icon' => '📰'],
        ['label' => 'Termine', 'icon' => '📅'],
        ['label' => 'Downloads', 'icon' => '📁'],
        ['label' => 'Partner', 'icon' => '🤝'],
        ['label' => 'Hundeausbildung', 'icon' => '🎓'],
        ['label' => 'Hundebörse', 'icon' => '🐕'],
        ['label' => 'Waffenbörse', 'icon' => '🔫'],
        ['label' => 'Kontaktanfragen', 'icon' => '✉️'],
        ['label' => 'Medien', 'icon' => '🖼️'],
        ['label' => 'Einstellungen', 'icon' => '⚙️'],
        ['label' => 'Benutzer', 'icon' => '👤'],
    ];
    $kjsAdminUser = \App\Support\AdminIdentity::currentUser(request());
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} – KJS Admin</title>
    @vite(['resources/css/admin.css'])
</head>
<body class="kjs-admin">

<header class="admin-topbar">
    <div class="topbar-brand">
        <span class="topbar-icon" aria-hidden="true">🦌</span>
        <span class="topbar-title">KJS Admin</span>
    </div>
    <div class="topbar-actions">
        @if ($kjsAdminUser)
            <span class="topbar-user">{{ $kjsAdminUser['email'] }}</span>
        @endif
        <a class="topbar-home-btn" href="{{ url('/') }}" target="_blank" rel="noopener">🌐 Website ansehen</a>
        <form method="POST" action="{{ url(config('fortify.paths.logout')) }}" style="margin:0;">
            @csrf
            <button type="submit" class="topbar-logout-btn">Abmelden</button>
        </form>
    </div>
</header>

<div class="admin-body">
    <nav class="admin-sidebar" aria-label="Admin-Navigation">
        <div class="sidebar-section">Verwaltung</div>
        @foreach ($kjsAdminNav as $kjsNavItem)
            @if (isset($kjsNavItem['route']))
                <a href="{{ route($kjsNavItem['route']) }}" class="nav-item @if (request()->routeIs($kjsNavItem['routePattern'] ?? $kjsNavItem['route'])) active @endif">
                    <span aria-hidden="true">{{ $kjsNavItem['icon'] }}</span>
                    <span>{{ $kjsNavItem['label'] }}</span>
                </a>
            @else
                <span class="nav-item is-disabled" title="Noch nicht Teil der neuen Laravel-Admin-Oberfläche">
                    <span aria-hidden="true">{{ $kjsNavItem['icon'] }}</span>
                    <span>{{ $kjsNavItem['label'] }}</span>
                    <span class="nav-item__badge">Folgt</span>
                </span>
            @endif
        @endforeach
    </nav>

    <main class="admin-main">
        {{ $slot }}
    </main>
</div>

</body>
</html>
