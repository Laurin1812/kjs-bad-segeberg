{{--
    Phase 1 (Blade-Fundament) + Phase 4 (Startseite + komplette Laravel-
    Navigation, Laravel-Vollmigration).

    Ersetzt die bisherige clientseitige Breadcrumb-Hydration (ZENTRALE
    BREADCRUMB-KOMPONENTE in resources/js/app.js, liest den Pfad + fetch aus
    navigation.json und baute den Trail per JS zusammen, inkl. der
    window.setBreadcrumbTrail()/setBreadcrumbCurrentTitle()-Aufrufe in den
    einzelnen Blade-Views). Der Trail wird jetzt vollstaendig serverseitig
    von der jeweiligen View/dem jeweiligen Controller als "items"-Prop
    uebergeben (siehe <x-page-hero :breadcrumbs="[...]"> in den einzelnen
    Content-Views) - kein "Wird geladen …"-Platzhalter mehr, kein
    /api/content/navigation.json-Request mehr fuer Breadcrumbs.

    "items": Liste aus ['label' => string, 'href' => string|null] - ein
    fehlender/null "href" (typischerweise der letzte Eintrag) wird als
    aktuelle Seite ohne Link gerendert (aria-current="page"), identisch zum
    bisherigen render()-Verhalten in resources/js/app.js.
--}}
@props(['items' => []])
<nav class="breadcrumb" aria-label="Breadcrumb" id="siteBreadcrumb">
    @foreach ($items as $i => $item)
        @if ($i > 0)<span class="sep">/</span>@endif
        @if (! empty($item['href']) && $i < count($items) - 1)
            <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
        @else
            <span @if($i === count($items) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
