{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration) - interne Vorschau-Seite.

    Zweck: visueller Vergleich des neuen Laravel-Blade-Grundlayouts
    (Header/Nav/Footer/Breadcrumb/PAGE-HERO) mit der bestehenden statischen
    Seite (z.B. https://<netlify-site>/jaeger/ueber-uns.html), OHNE dass
    dafür bereits eine echte Content-Seite migriert werden musste. Nur ueber
    die Route "preview.blade-fundament" erreichbar, die in routes/web.php
    per app()->environment('production')-Guard ausschließlich außerhalb von
    Production registriert wird - existiert also in der Live-Produktion gar
    nicht. Diese Datei ist ein reines Hilfsmittel fuer Phase 1 und wird
    spaetestens in Phase 12 (Aufraeumen/Abnahme) wieder entfernt, sobald
    echte Seiten dasselbe Layout produktiv nutzen.
--}}
<x-layouts.app title="Vorschau: Blade-Fundament">
    <x-page-hero title="Vorschau: Blade-Fundament (Phase 1)" />

    {{-- Bewusst KEIN .page-content/.main-content: dieses Content-Page-Layout
         (Sidebar-Widgets etc.) gehoert zu Phase 2/4 und ist hier noch nicht
         uebernommen (siehe Kommentar-Kopf in resources/css/app.css). Diese
         Vorschau-Seite braucht davon nichts - es geht ausschliesslich um den
         visuellen Vergleich von Header/Nav/Footer/Breadcrumb/Page-Hero, der
         Fliesstext darunter nutzt nur die bereits uebernommene Basis-
         Typografie (h2/h3/p) innerhalb von .container. --}}
    <div class="container" style="padding-block: 3rem;">
        <main>
                <h2>Interne Vorschau – Phase 1 (Blade-Fundament)</h2>
                <p>
                    Diese Seite existiert ausschließlich zum visuellen Vergleich des neuen
                    Laravel-Blade-Grundlayouts (Header, Navigation, Footer, Breadcrumb,
                    Page-Hero) mit der bestehenden statischen Seite. Sie ist Teil von
                    Phase 1 des Migrationsplans und wird in Phase 12 wieder entfernt.
                </p>
                <p>
                    Vergleichsseite (bestehende Statik): <code>/jaeger/ueber-uns.html</code>
                    auf der aktuell produktiven Netlify-Auslieferung.
                </p>
                <h3>Was hier bereits aus echtem Laravel/Blade kommt</h3>
                <p>
                    Topbar, Header mit Logo, Hauptnavigations-Container, Mobile-Nav-
                    Container, Breadcrumb-Container, Page-Hero und Footer-Container werden
                    von dieser Seite serverseitig durch Blade-Components gerendert
                    (<code>&lt;x-site-header /&gt;</code>, <code>&lt;x-breadcrumbs /&gt;</code>,
                    <code>&lt;x-page-hero /&gt;</code>, <code>&lt;x-site-footer /&gt;</code>),
                    nicht mehr durch <code>js/components.js</code>.
                </p>
                <h3>Was hier bewusst noch unveraendert aus Phase-1-Sicht ist</h3>
                <p>
                    Die eigentlichen Navigationseinträge, der Footer-Inhalt und der
                    Breadcrumb-Pfad werden weiterhin zur Laufzeit im Browser aus den
                    bestehenden JSON-Inhalten (<code>content/navigation.json</code>,
                    <code>content/footer.json</code>) nachgeladen – genau wie bisher, nur
                    dass der dafür zuständige JavaScript-Code jetzt über
                    <code>resources/js/app.js</code> und Vite eingebunden wird statt über
                    <code>js/main.js</code> und <code>js/components.js</code> direkt.
                    Serverseitiges Rendern dieser Inhalte aus Eloquent ist erst Phase 4.
                </p>
                <h3>Ausdrücklich nicht Teil dieser Vorschau</h3>
                <p>
                    Reguläre Inhaltsseiten (Phase 2), Sondermodule wie Hundebörse,
                    Waffenbörse und Kontaktformular, sowie ein neuer Admin-Bereich – all
                    das bleibt unverändert auf der bestehenden statischen Seite bzw. den
                    bestehenden PHP-Endpunkten und ist nicht Gegenstand von Phase 1.
                </p>
        </main>
    </div>
</x-layouts.app>
