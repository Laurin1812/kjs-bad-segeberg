{{--
    Phase 6B (Waffenboerse auf Laravel/MySQL): ersetzt waffenboerse/
    detail.html. WaffenboerseController::show() liefert bei fehlender/nicht
    veroeffentlichter Anzeige bereits eine echte Laravel-404 (kein
    erfundenes Ziel).

    Anzeigenkopf (Galerie links / Eckdaten rechts) nutzt bewusst eigene
    "wb-"-Klassen statt der Hundeboerse-Klassen ("hb-detail-top" etc.) -
    exakt wie im Alt-System (siehe css/style.css-Kommentar: "die
    Hundebörse darf laut Auftrag nicht angefasst werden, beide Formulare/
    Detailseiten sollen sich unabhaengig voneinander weiterentwickeln
    lassen"). Der Kontaktbereich ("Anbieter kontaktieren") nutzt dagegen
    bewusst die bereits bestehenden "hb-kontaktbereich"/"hb-kontakt-
    highlight"-Klassen weiter - das war im Alt-System schon so (rein
    generische, bereits mehrfach geteilte Layout-Bausteine, keine
    Hundeboerse-spezifische Optik).

    "beschreibung" ist bereits fertiges, sanitisiertes HTML (aus dem
    TipTap-Editor des alten Admin-Bereichs bzw. - fuer neue Einreichungen -
    aus Text::freeTextToSafeParagraphs(), siehe WaffenboerseController::
    store()) und wird daher bewusst UNESCAPED ({!! !!}) ausgegeben, genau
    wie im PHP-Original (detail.html: "html += a.beschreibung").
--}}
@php
    $galerie = $anzeige->bilder;
    $hauptbild = $galerie->first()->pfad ?? null;
    $standort = trim(($anzeige->plz ?? '').' '.($anzeige->ort ?? ''));
    $herstellerModell = trim(collect([$anzeige->hersteller, $anzeige->modell])->filter()->implode(' · '));
    $providerName = trim((string) $anzeige->anbieter_name);
    $hatKontaktdaten = (bool) ($anzeige->anbieter_email || $anzeige->anbieter_telefon);
@endphp
<x-layouts.app :title="$anzeige->titel ?: 'Waffenbörse'">
    <x-page-hero :title="$anzeige->titel ?: '(Ohne Titel)'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Waffenbörse', 'href' => route('waffenboerse.index')],
        ['label' => $anzeige->titel ?: 'Anzeige'],
    ]" />

    <div class="page-content">
        <div class="container">
            {{-- "id=wb-detail-inhalt" + "main"/"aside"-Zweiteilung 1:1 aus
                 waffenboerse/detail.html uebernommen: die generische Regel
                 ".page-content .container { display:grid; grid-template-
                 columns:1fr 290px; }" (siehe css/style.css) erwartet GENAU
                 zwei direkte Grid-Kinder (Hauptspalte + 290px-Sidebar) -
                 ohne das "main"/"aside"-Paar wuerde ".wb-detail-top" selbst
                 in die schmale 290px-Sidebarspalte gequetscht (beim
                 Nachbau entdeckt, identisches Strukturmuster wie
                 hundeboerse/show.blade.php). Die "#wb-detail-inhalt"-ID
                 wird zusaetzlich von einigen der oben portierten CSS-Regeln
                 vorausgesetzt (Titel-/Meta-/Preis-Margin-Reset, siehe
                 css/style.css-Kommentar dort). --}}
            <main class="main-content" id="wb-detail-inhalt">
            <a href="{{ route('waffenboerse.index') }}" class="content-back-link">← Zurück zur Waffenbörse</a>

            <div class="wb-detail-top">
                @if ($hauptbild)
                    <div class="wb-gallery">
                        <img src="{{ $hauptbild }}" alt="{{ $anzeige->titel }}" class="wb-main-image" id="wb-main-image" onerror="kjsImgFallback(this)">
                        @if ($galerie->count() > 1)
                            <div class="wb-thumbs" id="wb-thumbs">
                                @foreach ($galerie as $i => $g)
                                    <button type="button" class="wb-thumb{{ $i === 0 ? ' is-active' : '' }}" data-src="{{ $g->pfad }}" data-alt="{{ $g->titel ?: $anzeige->titel }}" aria-label="Bild {{ $i + 1 }} von {{ $galerie->count() }} anzeigen">
                                        <img src="{{ \App\Support\Images::boerseThumbUrl($g->pfad) }}" data-full="{{ $g->pfad }}" onerror="kjsImgFallback(this)" alt="{{ $g->titel ?: $anzeige->titel }}" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="wb-gallery"><div class="wb-main-image wb-main-image--empty"><img src="/images/logo.png" alt="KJS" style="height:80px;opacity:.3;"></div></div>
                @endif

                <div class="wb-head">
                    <span class="news-card__cat wb-head__cat">{{ $anzeige->kategorie }}</span>
                    <h2 class="wb-head__title">{{ $anzeige->titel ?: '(Ohne Titel)' }}</h2>
                    @if ($herstellerModell)
                        <p class="wb-head__meta">{{ $herstellerModell }}</p>
                    @endif
                    @if ($standort)
                        <p class="wb-head__meta wb-meta-icon">{{ $standort }}</p>
                    @endif
                    <p class="wb-head__price">{{ $anzeige->preisText() }}</p>
                    @if ($anzeige->erwerbsberechtigung_erforderlich)
                        <div class="wb-erwerb-badge">Erwerbsberechtigung erforderlich</div>
                    @endif
                    @if ($hatKontaktdaten)
                        <div class="wb-head__cta"><a href="#wb-kontakt" class="btn btn-primary" id="wb-kontakt-btn">Anbieter kontaktieren</a></div>
                    @endif
                </div>
            </div>

            @php
                $kerndaten = collect([
                    'Kategorie' => $anzeige->kategorie,
                    'Hersteller' => $anzeige->hersteller,
                    'Modell' => $anzeige->modell,
                    'Kaliber' => $anzeige->kaliberText(),
                    'Zustand' => $anzeige->zustandText(),
                    'Erwerbsberechtigung' => $anzeige->erwerbsberechtigung_erforderlich ? 'Erforderlich' : 'Nicht erforderlich',
                    'Versand' => $anzeige->versand_moeglich ? 'Möglich' : 'Nur Abholung',
                ])->filter(fn ($wert) => $wert !== null && $wert !== '');
                if ($anzeige->versand_moeglich && $anzeige->versandkosten) {
                    $kerndaten['Versandkosten'] = $anzeige->versandkostenText();
                }
            @endphp
            @if ($kerndaten->isNotEmpty())
                <h2>Kerndaten</h2>
                <div class="steckbrief">
                    <dl>
                        @foreach ($kerndaten as $label => $wert)
                            <dt>{{ $label }}</dt>
                            <dd>{{ $wert }}</dd>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if ($anzeige->beschreibung)
                <h2>Beschreibung</h2>
                {!! $anzeige->beschreibung !!}
            @endif

            <div class="wb-rechtshinweis">
                <p>Der Erwerb erlaubnispflichtiger Waffen und Munition setzt die jeweils gesetzlich erforderliche Erwerbsberechtigung voraus. Anbieter und Interessenten sind selbst für die Einhaltung der gesetzlichen Vorgaben verantwortlich.</p>
            </div>

            @if ($hatKontaktdaten)
                <div class="hb-kontaktbereich" id="wb-kontakt">
                    <h2>Anbieter kontaktieren</h2>
                    <div class="hb-kontaktbereich__info">
                        @if ($providerName)
                            <p><strong>Anbieter:</strong> {{ $providerName }}</p>
                        @endif
                        @if ($anzeige->anbieter_telefon)
                            <p><strong>Telefon:</strong> <a href="tel:{{ preg_replace('/\s|-|\//', '', $anzeige->anbieter_telefon) }}">{{ $anzeige->anbieter_telefon }}</a></p>
                        @endif
                        @if ($anzeige->anbieter_email)
                            <p><strong>E-Mail:</strong> <a href="mailto:{{ $anzeige->anbieter_email }}?subject={{ urlencode('Anfrage zu: '.($anzeige->titel ?: 'Ihrer Waffenbörse-Anzeige')) }}">{{ $anzeige->anbieter_email }}</a></p>
                        @endif
                    </div>
                    <div class="hb-kontaktbereich__actions">
                        @if ($anzeige->anbieter_email)
                            <a href="mailto:{{ $anzeige->anbieter_email }}?subject={{ urlencode('Anfrage zu: '.($anzeige->titel ?: 'Ihrer Waffenbörse-Anzeige')) }}" class="btn btn-primary">📧 E-Mail schreiben</a>
                        @endif
                        @if ($anzeige->anbieter_telefon)
                            <a href="tel:{{ preg_replace('/\s|-|\//', '', $anzeige->anbieter_telefon) }}" class="btn btn-outline-green">📞 Anrufen</a>
                        @endif
                    </div>
                </div>
            @endif
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Waffenbörse</h4>
                    <p style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">Weitere aktuelle Angebote finden Sie in der Übersicht.</p>
                    <p style="margin-top:.75rem;"><a href="{{ route('waffenboerse.index') }}" class="btn btn-outline-green btn-sm">Alle Anzeigen ansehen</a></p>
                    <p style="margin-top:.5rem;"><a href="{{ route('waffenboerse.anbieten') }}" class="btn btn-outline-green btn-sm">Waffe anbieten</a></p>
                </div>
                <div class="contact-box">
                    <h4>Kontakt KJS</h4>
                    <p>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></p>
                    <p>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></p>
                </div>
            </aside>
        </div>
    </div>

    {{-- Bildergalerie: Klick auf Thumbnail tauscht das Hauptbild - kein
         @push('scripts') (siehe HundeboerseController-Vorbild in
         hundeboerse/show.blade.php: der App-Layout definiert keinen
         passenden @stack), daher als einfaches Inline-<script> direkt hier
         im Slot-Inhalt platziert. --}}
    <script>
        (function () {
            var thumbsWrap = document.getElementById('wb-thumbs');
            var mainImgEl = document.getElementById('wb-main-image');
            if (thumbsWrap && mainImgEl) {
                thumbsWrap.addEventListener('click', function (e) {
                    var btn = e.target.closest('.wb-thumb');
                    if (!btn) return;
                    mainImgEl.src = btn.getAttribute('data-src');
                    mainImgEl.alt = btn.getAttribute('data-alt') || mainImgEl.alt;
                    thumbsWrap.querySelectorAll('.wb-thumb').forEach(function (t) { t.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                });
            }
            var kontaktBtn = document.getElementById('wb-kontakt-btn');
            if (kontaktBtn) {
                kontaktBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var ziel = document.getElementById('wb-kontakt');
                    if (!ziel) return;
                    ziel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    ziel.classList.add('hb-kontakt-highlight');
                    setTimeout(function () { ziel.classList.remove('hb-kontakt-highlight'); }, 2000);
                });
            }
        })();
    </script>
</x-layouts.app>
