{{--
    Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
    ersetzt hundeboerse/detail.html. HundeboerseController::show() liefert
    bei fehlender/nicht veroeffentlichter Anzeige bereits eine echte
    Laravel-404 (kein erfundenes Ziel).

    Bewusst NICHT uebernommen (bei 0 vorhandenen lat/lng-Bestandsdaten - die
    Geokodierung war im Alt-System ausschliesslich eine ADMIN-Funktion
    (hbGeocode() in admin.js), es gibt in dieser Phase noch keine Laravel-
    Admin-Oberflaeche fuer die Hundeboerse (siehe Auftrag: "Admin-Bearbeitung
    wird spaeter in der Laravel-Admin-Migration umgesetzt") - eine
    interaktive Leaflet/OpenStreetMap-Karte haette hier also fuer JEDE ueber
    dieses Formular eingereichte Anzeige zwangslaeufig nichts anzuzeigen
    (hatKoordinaten waere immer false). "lat"/"lng" bleiben als Spalten
    vollstaendig erhalten (siehe Migration/Model) und werden angezeigt,
    sobald eine spaetere Admin-Migration sie befuellt - kein Datenverlust,
    nur (noch) keine tote Karten-UI ohne echten Anwendungsfall. Die
    Standort-Angabe (PLZ+Ort) bleibt als reiner Text erhalten.

    Hero-Bild: primaer das erste Galeriebild der Anzeige selbst; ohne
    Galeriebild das echte, mitgelieferte Hundeboerse-Hero
    (HundeboerseMeta::hero_bild); fehlt auch das, derselbe echte
    Bestandspfad als technischer Fallback - kein Stock-Motiv.
--}}
@php
    $galerie = $anzeige->bilder;
    $hauptbild = $galerie->first()->pfad ?? null;
    $standort = trim(($anzeige->postal_code ?? '').' '.($anzeige->city ?? ''));
    $providerName = trim((string) $anzeige->provider_name);
    $contactPerson = trim((string) $anzeige->contact_person);
    $hatKontaktdaten = (bool) ($anzeige->email || $anzeige->phone);
@endphp
<x-layouts.app :title="$anzeige->title ?: 'Hundebörse'">
    <x-page-hero :title="$anzeige->title ?: '(Ohne Titel)'" :bg-image="$hauptbild ?: ($heroBild ?: '/images/1787934528232-ChatGPT-Image-28.-Aug.-2026--18_28_24.png')" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Hundebörse', 'href' => route('hundeboerse.index')],
        ['label' => $anzeige->title ?: 'Anzeige'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                <a href="{{ route('hundeboerse.index') }}" class="content-back-link">← Zurück zur Hundebörse</a>

                <div class="hb-detail-top">
                    @if ($galerie->count())
                        <div class="hb-gallery">
                            <img src="{{ $hauptbild }}" alt="{{ $anzeige->title }}" class="hb-main-image" id="hb-main-image">
                            @if ($galerie->count() > 1)
                                <div class="hb-thumbs" id="hb-thumbs">
                                    @foreach ($galerie as $i => $g)
                                        <button type="button" class="hb-thumb{{ $i === 0 ? ' is-active' : '' }}" data-src="{{ $g->pfad }}" data-alt="{{ $g->titel ?: $anzeige->title }}" aria-label="Bild {{ $i + 1 }} von {{ $galerie->count() }} anzeigen">
                                            <img src="{{ \App\Support\Images::boerseThumbUrl($g->pfad) }}" data-full="{{ $g->pfad }}" onerror="kjsImgFallback(this)" alt="{{ $g->titel ?: $anzeige->title }}" loading="lazy">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="hb-gallery"><div style="background:var(--green-light);border-radius:var(--radius-md);aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;"><img src="/images/logo.png" alt="KJS" style="height:80px;opacity:.3;"></div></div>
                    @endif

                    <div class="hb-eckdaten">
                        <div class="hb-eckdaten__top">
                            <span class="news-card__cat">{{ $anzeige->typLabel() }}</span>
                            <h2 style="margin:.75rem 0 .25rem;">{{ $anzeige->title ?: '(Ohne Titel)' }}</h2>
                            @if ($anzeige->breed)
                                <p style="color:var(--text-muted);font-size:1rem;margin-bottom:.5rem;">{{ $anzeige->breed }}</p>
                            @endif
                            @if ($standort)
                                <p style="color:var(--text-muted);font-size:.92rem;margin-bottom:.25rem;">{{ $standort }}</p>
                            @endif
                            @if ($anzeige->typZeile())
                                <p style="color:var(--text-muted);font-size:.92rem;margin-bottom:1rem;">{{ $anzeige->typZeile() }}</p>
                            @endif
                            <p style="font-size:1.3rem;font-weight:800;color:var(--green-dark);margin-bottom:.5rem;">{{ $anzeige->preisText() }}</p>
                        </div>
                        @if ($hatKontaktdaten)
                            <div class="hb-eckdaten__bottom"><a href="#hb-kontakt" class="btn btn-primary" id="hb-kontakt-btn">Anbieter kontaktieren</a></div>
                        @endif
                    </div>
                </div>

                @php
                    $steckbrief = [];
                    if ($anzeige->type === 'litter') {
                        $steckbrief['Rasse'] = $anzeige->breed;
                        $steckbrief['Wurfdatum'] = $anzeige->formatiertesDatum($anzeige->litter_date);
                        $steckbrief['Rüden'] = $anzeige->male_count;
                        $steckbrief['Hündinnen'] = $anzeige->female_count;
                        $steckbrief['Farbe'] = $anzeige->color;
                        $steckbrief['Haarart'] = $anzeige->coat;
                    } else {
                        $steckbrief['Rasse'] = $anzeige->breed;
                        $steckbrief['Geburtsdatum'] = $anzeige->formatiertesDatum($anzeige->birth_date);
                        $steckbrief['Geschlecht'] = $anzeige->gender === 'male' ? 'Rüde' : ($anzeige->gender === 'female' ? 'Hündin' : '');
                        $steckbrief['Farbe'] = $anzeige->color;
                        $steckbrief['Haarart'] = $anzeige->coat;
                        $steckbrief['Prüfungen'] = $anzeige->hunting_tests;
                    }
                    $steckbrief = array_filter($steckbrief, fn ($v) => $v !== null && $v !== '');
                @endphp
                @if (count($steckbrief))
                    <h2>Steckbrief</h2>
                    <div class="steckbrief"><dl>
                        @foreach ($steckbrief as $label => $wert)
                            <dt>{{ $label }}</dt><dd>{{ $wert }}</dd>
                        @endforeach
                    </dl></div>
                @endif

                @php
                    $abstammung = [];
                    $abstammung['Vater'] = $anzeige->father;
                    $abstammung['Prüfungen Vater'] = $anzeige->father_tests;
                    $abstammung['Mutter'] = $anzeige->mother;
                    $abstammung['Prüfungen Mutter'] = $anzeige->mother_tests;
                    if ($anzeige->type !== 'litter') {
                        $abstammung['Weitere jagdliche Prüfungen'] = $anzeige->hunting_tests;
                    }
                    $abstammung['Ausbildungsstand / weitere Angaben'] = $anzeige->training_level;
                    $abstammung = array_filter($abstammung, fn ($v) => $v !== null && $v !== '');
                    if ($anzeige->has_zuchtverband === true && $anzeige->zuchtverband) {
                        $abstammung['Zuchtverband'] = $anzeige->zuchtverband;
                    } elseif ($anzeige->has_zuchtverband === false) {
                        $abstammung['Zuchtverband'] = 'Kein Zuchtverband';
                    }
                @endphp
                @if (count($abstammung))
                    <h2>Abstammung &amp; jagdliche Informationen</h2>
                    <div class="steckbrief"><dl>
                        @foreach ($abstammung as $label => $wert)
                            <dt>{{ $label }}</dt><dd>{{ $wert }}</dd>
                        @endforeach
                    </dl></div>
                @endif

                @if ($anzeige->description)
                    <h2>{{ $anzeige->type === 'litter' ? 'Über den Wurf' : 'Über den Hund' }}</h2>
                    {{-- Freitext aus einem einfachen <textarea> (kein Rich-Text-Editor,
                         siehe hundeboerse/anbieten.html) - escaped und mit
                         nl2br() dargestellt statt Markdown-Rendering, damit
                         KEINE HTML-Interpretation eines oeffentlich eingereichten
                         Freitexts stattfindet (das bisherige marked.js+DOMPurify
                         war fuer denselben simplen Anwendungsfall bereits eine
                         Zusatz-Abhaengigkeit, hier durch die serverseitige
                         Escaping+nl2br()-Ausgabe ersetzt). --}}
                    <p>{!! nl2br(e($anzeige->description)) !!}</p>
                @endif

                @if ($standort)
                    <h2>Standort</h2>
                    <p>{{ $standort }}<br><span style="font-size:.85rem;color:var(--text-muted);">Es wird nur der ungefähre Standort angezeigt, keine genaue Adresse.</span></p>
                @endif

                @if ($hatKontaktdaten)
                    @php
                        $telefonHref = $anzeige->phone ? 'tel:'.preg_replace('/\s|-|\//', '', $anzeige->phone) : '';
                        $betreff = rawurlencode('Anfrage zu: '.($anzeige->title ?: 'Ihrer Hundebörse-Anzeige'));
                        $mailtoHref = $anzeige->email ? 'mailto:'.$anzeige->email.'?subject='.$betreff : '';
                    @endphp
                    <div class="hb-kontaktbereich" id="hb-kontakt">
                        <h2>Anbieter kontaktieren</h2>
                        <div class="hb-kontaktbereich__info">
                            @if ($providerName && $contactPerson && strcasecmp($providerName, $contactPerson) !== 0)
                                <p><strong>Anbieter:</strong> {{ $providerName }}</p>
                                <p><strong>Ansprechpartner:</strong> {{ $contactPerson }}</p>
                            @elseif ($providerName || $contactPerson)
                                <p><strong>Anbieter:</strong> {{ $providerName ?: $contactPerson }}</p>
                            @endif
                            @if ($anzeige->phone)
                                <p><strong>Telefon:</strong> <a href="{{ $telefonHref }}">{{ $anzeige->phone }}</a></p>
                            @endif
                            @if ($anzeige->email)
                                <p><strong>E-Mail:</strong> <a href="{{ $mailtoHref }}">{{ $anzeige->email }}</a></p>
                            @endif
                        </div>
                        <div class="hb-kontaktbereich__actions">
                            @if ($anzeige->email)
                                <a href="{{ $mailtoHref }}" class="btn btn-primary">📧 E-Mail schreiben</a>
                            @endif
                            @if ($anzeige->phone)
                                <a href="{{ $telefonHref }}" class="btn btn-outline-green">📞 Anrufen</a>
                            @endif
                        </div>
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h4>Hundebörse</h4>
                    <p style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">Weitere aktuelle Hunde und Würfe finden Sie in der Übersicht.</p>
                    <p style="margin-top:.75rem;"><a href="{{ route('hundeboerse.index') }}" class="btn btn-outline-green btn-sm">Alle Anzeigen ansehen</a></p>
                </div>
                <div class="contact-box">
                    <h4>Kontakt KJS</h4>
                    <p>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></p>
                    <p>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></p>
                </div>
            </aside>
        </div>
    </div>

    {{-- Bildergalerie: Klick auf Thumbnail tauscht das Hauptbild aus - 1:1
         derselbe Delegation-Ansatz wie im PHP-Original, arbeitet aber rein
         auf dem bereits server-gerenderten DOM (kein fetch()). "Anbieter
         kontaktieren"-Button scrollt zum Kontaktbereich (nur vorhanden,
         wenn hatKontaktdaten true ist, siehe oben). Kein @push('scripts') -
         das Grundlayout (components/layouts/app.blade.php) definiert keinen
         passenden @stack, ein <script> direkt im Slot-Inhalt reicht hier
         (laeuft nach dem DOM der obigen Elemente). --}}
    <script>
    (function() {
        var thumbsWrap = document.getElementById('hb-thumbs');
        if (thumbsWrap) {
            var mainImgEl = document.getElementById('hb-main-image');
            thumbsWrap.addEventListener('click', function(e) {
                var btn = e.target.closest('.hb-thumb');
                if (!btn || !mainImgEl) return;
                mainImgEl.src = btn.getAttribute('data-src');
                mainImgEl.alt = btn.getAttribute('data-alt') || mainImgEl.alt;
                thumbsWrap.querySelectorAll('.hb-thumb').forEach(function(t) { t.classList.remove('is-active'); });
                btn.classList.add('is-active');
            });
        }

        var kontaktBtn = document.getElementById('hb-kontakt-btn');
        if (kontaktBtn) {
            kontaktBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var ziel = document.getElementById('hb-kontakt');
                if (!ziel) return;
                ziel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                ziel.classList.add('hb-kontakt-highlight');
                setTimeout(function() { ziel.classList.remove('hb-kontakt-highlight'); }, 2000);
            });
        }
    })();
    </script>
</x-layouts.app>
