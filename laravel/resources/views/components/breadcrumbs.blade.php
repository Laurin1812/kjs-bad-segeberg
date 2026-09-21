{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Statischer Breadcrumb-Container, unveraendert 1:1 aus dem bestehenden
    Muster uebernommen (z.B. jaeger/ueber-uns.html: <nav class="breadcrumb"
    aria-label="Breadcrumb" id="siteBreadcrumb"></nav>). Die Befuellung
    (Pfad -> Trail aus navigation.json, inkl. setBreadcrumbCurrentTitle/
    setBreadcrumbTrail fuer dynamische Seiten) passiert weiterhin zur
    Laufzeit im Breadcrumb-Modul von resources/js/app.js (1:1 aus
    js/components.js uebernommen) - Server-seitiges Rendern des Trails aus
    Eloquent ist Phase 4.
--}}
<nav class="breadcrumb" aria-label="Breadcrumb" id="siteBreadcrumb"></nav>
