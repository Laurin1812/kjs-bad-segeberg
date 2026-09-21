{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Zentrales Grundlayout, das ab Phase 2 von allen echten Content-Seiten
    genutzt wird (<x-layouts.app>...</x-layouts.app>). Ersetzt das bisherige
    Muster, bei dem jede einzelne .html-Datei ihr komplettes <head>/<body>-
    Grundgerüst selbst mitgeschrieben hat (~40+ fast identische Kopien).

    Uebernommen 1:1 aus dem bestehenden Muster (siehe z.B. index.html,
    jaeger/ueber-uns.html):
      - der design.json-Farb-/Schrift-Override im <head> (unveraendert, liest
        weiterhin von /api/content/design.json, keine neue Datenquelle)
      - lang="de", die Standard-Meta-Tags, das Grundprinzip
        "<title>{Seitentitel} – Kreisjägerschaft Segeberg e.V."

    Neu/anders gegenueber vorher (bewusste Verbesserung, keine Verhaltens-
    aenderung fuer Besucher):
      - Header/Footer/Breadcrumb sind jetzt echte Blade-Components
        (<x-site-header>, <x-site-footer>, <x-breadcrumbs>) statt per JS
        nachtraeglich injizierter Platzhalter-<div>s. Das JS aus
        js/components.js (Header-HTML zusammenbauen) entfaellt dadurch fuer
        umgestellte Seiten; die Navigations-/Footer-/Breadcrumb-BEFUELLUNG
        (Laufzeitdaten aus navigation.json/footer.json) bleibt bewusst noch
        in resources/js/app.js (Phase 4 macht das serverseitig aus Eloquent).
      - @vite(...) ersetzt die bisherigen einzelnen <link>/<script>-Tags auf
        css/style.css bzw. js/main.js.
--}}
@props([
    'title' => null,
    'description' => null,
])
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $description ?? 'Kreisjägerschaft Segeberg e.V. – Ihr Ansprechpartner für Jagd, Natur und Wildtierhege im Kreis Bad Segeberg.' }}">
    <title>{{ $title ? $title.' – Kreisjägerschaft Segeberg e.V.' : 'Kreisjägerschaft Segeberg e.V.' }}</title>

    <script>
      fetch('/api/content/design.json').then(r=>r.json()).then(d=>{
        const r = document.documentElement.style;
        if(d.farbe_gruen) r.setProperty('--green-main', d.farbe_gruen);
        if(d.farbe_dunkelgruen) r.setProperty('--green-dark', d.farbe_dunkelgruen);
        if(d.farbe_akzent) r.setProperty('--gold', d.farbe_akzent);
        if(d.schrift_ueberschrift) r.setProperty('--font-heading', `"${d.schrift_ueberschrift}", serif`);
        if(d.schrift_text) r.setProperty('--font-sans', `"${d.schrift_text}", sans-serif`);
        if(d.schriftgroesse_h1) r.setProperty('--fs-h1', d.schriftgroesse_h1);
        if(d.schriftgroesse_h2) r.setProperty('--fs-h2', d.schriftgroesse_h2);
        if(d.schriftgroesse_h3) r.setProperty('--fs-h3', d.schriftgroesse_h3);
        if(d.schriftgroesse_text) r.setProperty('--fs-text', d.schriftgroesse_text);
      }).catch(()=>{});
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<x-site-header />

{{ $slot }}

<x-site-footer />

</body>
</html>
