{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    Eigenes, MINIMALES Grundgeruest fuer die drei Auth-Seiten (Login,
    Passwort vergessen, Passwort zuruecksetzen) - bewusst NICHT
    <x-layouts.app> (oeffentliche Seite mit Header/Nav/Footer waere hier
    fehl am Platz) und auch NICHT <x-layouts.admin> (das Dashboard-Layout
    unten setzt eine angemeldete Sitzung mit Topbar/Sidebar voraus, die es
    vor dem Login naturgemaess noch nicht gibt). Laedt ausschliesslich
    resources/css/admin.css (siehe dortiger Kopfkommentar) - kein
    app.css/app.js der oeffentlichen Seite.
--}}
@props(['title' => 'Anmeldung'])
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
{{ $slot }}
</body>
</html>
