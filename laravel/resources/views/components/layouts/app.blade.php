{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Zentrales Grundlayout, das ab Phase 2 von allen echten Content-Seiten
    genutzt wird (<x-layouts.app>...</x-layouts.app>). Ersetzt das bisherige
    Muster, bei dem jede einzelne .html-Datei ihr komplettes <head>/<body>-
    Grundgerüst selbst mitgeschrieben hat (~40+ fast identische Kopien).

    Uebernommen 1:1 aus dem bestehenden Muster (siehe z.B. index.html,
    jaeger/ueber-uns.html):
      - der Farb-/Schrift-Override im <head> (WICHTIG, Phase-3-Nacharbeit
        "100% Laravel": laedt seit dieser Korrektur NICHT mehr per
        clientseitigem "fetch('/api/content/design.json')", sondern
        serverseitig ueber App\View\Composers\DesignComposer direkt aus der
        "settings"-Tabelle, Gruppe "design" - siehe dortiger
        Klassenkommentar. Kein JSON-Request, keine zweite Datenquelle mehr.)
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

    {{-- Phase-1-Nacharbeit ("Logo wird nicht geladen"): echte, statische
         Laravel-Assets ueber den Standard-asset()-Helper aufgeloest - siehe
         Kommentar in components/site-header.blade.php fuer die volle
         Begruendung. window.KJS_ASSETS ist die einzige Bruecke zwischen
         diesen Blade-generierten URLs und dem weiterhin JS-gebauten Footer
         (resources/js/app.js liest logoDunkel hier aus, mit dem alten
         hart-codierten Pfad als Fallback, falls das Skript aus irgendeinem
         Grund vor diesem Block laeuft). --}}
    <script>
      window.KJS_ASSETS = {
        logo: "{{ asset('images/logo.png') }}",
        logoDunkel: "{{ asset('images/logo-dunkel.png') }}"
      };
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        Phase-3-Nacharbeit ("100% Laravel"): ersetzt den bisherigen
        clientseitigen design.json-Fetch. $kjsDesign kommt serverseitig aus
        App\View\Composers\DesignComposer (settings-Tabelle, Gruppe
        "design") - dieselbe "nur setzen, wenn Wert vorhanden"-Logik wie
        zuvor im JS, nur direkt als CSS-Custom-Property-Override auf
        :root ausgegeben (nach dem app.css-Link, damit die Werte die
        dortigen Standardwerte ueberschreiben). Werte kommen aus der
        Admin-Oberflaeche gepflegten Einstellungen (kein Benutzereingabe-
        Formular auf der oeffentlichen Seite) - {!! !!} bewusst statt
        {{ }}, da HTML-Entities ("&quot;" statt '"') in einem <style>-Block
        als Rohtext interpretiert wuerden und die Werte damit ungueltig
        machen wuerden; zusaetzlich werden spitze Klammern defensiv
        entfernt, um ein Herausbrechen aus dem <style>-Element
        auszuschliessen.
    --}}
    @php
        $kjsCss = static fn (?string $v) => $v ? str_replace(['<', '>'], '', $v) : null;
        $kjsRootVars = array_filter([
            'green-main' => $kjsCss($kjsDesign['farbe_gruen'] ?? null),
            'green-dark' => $kjsCss($kjsDesign['farbe_dunkelgruen'] ?? null),
            'gold' => $kjsCss($kjsDesign['farbe_akzent'] ?? null),
            'font-heading' => ($h = $kjsCss($kjsDesign['schrift_ueberschrift'] ?? null)) ? '"'.$h.'", serif' : null,
            'font-sans' => ($s = $kjsCss($kjsDesign['schrift_text'] ?? null)) ? '"'.$s.'", sans-serif' : null,
            'fs-h1' => $kjsCss($kjsDesign['schriftgroesse_h1'] ?? null),
            'fs-h2' => $kjsCss($kjsDesign['schriftgroesse_h2'] ?? null),
            'fs-h3' => $kjsCss($kjsDesign['schriftgroesse_h3'] ?? null),
            'fs-text' => $kjsCss($kjsDesign['schriftgroesse_text'] ?? null),
        ]);
    @endphp
    @if ($kjsRootVars)
        <style>
            :root {
                @foreach ($kjsRootVars as $kjsProp => $kjsValue)
                    --{{ $kjsProp }}: {!! $kjsValue !!};
                @endforeach
            }
        </style>
    @endif
</head>
<body>

<x-site-header />

{{ $slot }}

<x-site-footer />

</body>
</html>
