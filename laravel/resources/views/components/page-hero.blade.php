{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Statisches PAGE-HERO-Grundgerüst, 1:1 aus dem bestehenden Muster
    uebernommen (siehe jaeger/ueber-uns.html: .page-hero > .page-hero__bg +
    .container > h1#page-title + .breadcrumb#siteBreadcrumb, in genau dieser
    Reihenfolge - H1 VOR dem Breadcrumb). Ab Phase 2 werden echte Content-
    Seiten <x-page-hero :title="..." :bg-image="..."> nutzen; fuer Phase 1
    existiert diese Component nur als Baustein fuer die interne Vorschau-
    Seite (siehe resources/views/preview/blade-fundament.blade.php), ohne
    dass irgendeine echte Inhaltsseite bereits umgestellt wuerde.

    Das dynamische Nachladen von hero_bild/titel aus der jeweiligen
    content/*.json (siehe <script> in jaeger/ueber-uns.html, Zeile ~40-42)
    ist bewusst NICHT Teil dieser Component - das gehoert zur Content-Logik
    der jeweiligen Seite und ist außerhalb des Phase-1-Umfangs (Blade-
    Fundament betrifft nur Header/Nav/Footer/Breadcrumb-Grundlayout).
--}}
@props([
    'title' => null,
    'bgImage' => '/images/stock/hero-default.jpg',
    'breadcrumbs' => [],
])
<div class="page-hero">
    <div class="page-hero__bg" style="background-image: url('{{ $bgImage }}')"></div>
    <div class="container">
        @if ($title)
            <h1 id="page-title">{{ $title }}</h1>
        @endif
        <x-breadcrumbs :items="$breadcrumbs" />
    </div>
</div>
