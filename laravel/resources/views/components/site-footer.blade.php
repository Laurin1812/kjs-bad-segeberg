{{--
    Phase 1 (Blade-Fundament, Laravel-Vollmigration).

    Statischer Footer-Container, unveraendert 1:1 aus dem bestehenden Muster
    uebernommen (z.B. jaeger/ueber-uns.html: <footer class="site-footer"
    id="siteFooter"></footer>). Die eigentliche Befuellung passiert weiterhin
    zur Laufzeit durch das "ZENTRALER FOOTER"-Modul in resources/js/app.js
    (fetch aus content/footer.json) - Server-seitiges Rendern des Footer-
    Inhalts aus Eloquent ist Phase 4, hier geht es nur um das Grundlayout.
--}}
<footer class="site-footer" id="siteFooter"></footer>
