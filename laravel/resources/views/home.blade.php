{{--
    KJS Bad Segeberg - Phase 4 (Startseite + komplette Laravel-Navigation,
    Laravel-Vollmigration).

    Ersetzt die bisherige Laravel-Welcome-Seite ("/") UND das produktive
    index.html (statische Datei, Inhalte per mehreren Inline-<script>-
    Bloecken aus /api/content/startseite.json, /api/content/aktuelles.json,
    /api/content/termine.json nachgeladen). Markup/Design 1:1 aus index.html
    uebernommen (Auftrag Punkt 1: "Bestehendes Design und bestehende
    Inhalte der produktiven Startseite möglichst 1:1 übernehmen") - einzige
    Aenderung ist die Datenquelle: alle vorher per JS nachtraeglich
    ersetzten Textstellen/Bilder kommen jetzt direkt serverseitig aus
    HomeController (Eloquent/MySQL), kein fetch() mehr. Die "Was wir
    anbieten"-Kachelreihe war im Original NIE datengetrieben (fest verdrahtete
    Icons/Bilder/Texte in index.html, keine Entsprechung in der settings-
    Tabelle) und bleibt daher unveraendert statisch.
--}}
@php
    $heroTitelZeile1 = $s['hero_titel'] ?? '';
    $heroTitelZeile2 = $s['hero_titel_zeile2'] ?? '';
    $heroBgBild = $heroSlides->isEmpty() ? ($s['hero_bild'] ?? '/images/hero.jpg') : null;
@endphp
<x-layouts.app title="Kreisjägerschaft Segeberg e.V." description="Kreisjägerschaft Segeberg e.V. – Ihr Ansprechpartner für Jagd, Natur und Wildtierhege im Kreis Bad Segeberg.">

    {{-- ===== HERO ===== --}}
    <section class="hero">
        @if ($heroSlides->isNotEmpty())
            @foreach ($heroSlides as $i => $slide)
                <div class="hero__bg--slide{{ $i === 0 ? ' is-active' : '' }}" style="background-image:url('{{ $slide->bild }}')" data-dauer="{{ $slide->dauer ?: 6 }}"></div>
            @endforeach
        @else
            <div class="hero__bg" style="background-image:url('{{ $heroBgBild }}')"></div>
        @endif
        <div class="hero__overlay"></div>
        <div class="container">
            <div class="hero__content">
                <span class="hero__badge">🌿 Kreis Bad Segeberg</span>
                <h1>
                    @if ($heroTitelZeile1 || $heroTitelZeile2)
                        {{ $heroTitelZeile1 }}
                        @if ($heroTitelZeile1 && $heroTitelZeile2)
                            <br>
                        @endif
                        @if ($heroTitelZeile2)
                            <span>{{ $heroTitelZeile2 }}</span>
                        @endif
                    @else
                        In der Natur –<br><span>für Wildtier, Wald und Mensch</span>
                    @endif
                </h1>
                <p class="hero__sub">
                    {{ $s['hero_untertitel'] ?? 'Die Kreisjägerschaft Segeberg e.V. vertritt über 1.600 Jägerinnen und Jäger im Kreis Bad Segeberg. Wir engagieren uns für nachhaltige Jagd, aktiven Naturschutz und die Verbindung von Mensch und Natur.' }}
                </p>
                <div class="hero__cta">
                    <a href="/jaeger/jaeger-werden" class="btn btn-primary">Ich will Jäger werden</a>
                    <a href="/jaeger/mitglied-werden" class="btn btn-gold">Ich will Mitglied werden</a>
                </div>
            </div>
        </div>
        <div class="hero__scroll" aria-hidden="true">
            {{ $s['hero_button_text'] ?? 'Mehr entdecken' }}
            <span class="hero__scroll-arrow"></span>
        </div>
    </section>

    @if ($heroSlides->count() > 1)
        <script>
            (function () {
                var slides = document.querySelectorAll('.hero__bg--slide');
                if (slides.length < 2) return;
                (function loop(idx) {
                    var dauer = parseFloat(slides[idx].getAttribute('data-dauer')) || 6;
                    setTimeout(function () {
                        var next = (idx + 1) % slides.length;
                        slides[idx].classList.remove('is-active');
                        slides[next].classList.add('is-active');
                        loop(next);
                    }, dauer * 1000);
                })(0);
            })();
        </script>
    @endif

    {{-- ===== QUICK LINKS ===== --}}
    <section class="quicklinks" aria-label="Schnellzugriff">
        <div class="quicklinks__grid">
            @foreach ([1, 2, 3, 4] as $n)
                <a href="{{ $quicklinkHref($s['quicklink_'.$n.'_link'] ?? null) ?: '#' }}" class="quicklinks__item">
                    <span class="quicklinks__icon"><img src="{{ asset('images/logo-dunkel.png') }}" alt="KJS Logo"></span>
                    <div>
                        <div class="quicklinks__label">{{ $s['quicklink_'.$n.'_label'] ?? '' }}</div>
                        <div class="quicklinks__title">{{ $s['quicklink_'.$n.'_titel'] ?? '' }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ===== STATS ===== --}}
    <section class="stats" aria-label="Zahlen und Fakten">
        <div class="container">
            <div class="stats__grid">
                @foreach ([1, 2, 3] as $n)
                    <div>
                        <span class="stat__number">{{ $s['statistik_'.$n.'_zahl'] ?? '' }}</span>
                        <span class="stat__label">{{ $s['statistik_'.$n.'_label'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== WELCOME ===== --}}
    <section class="welcome">
        <div class="container">
            <div class="welcome__image">
                <img src="{{ asset('images/naturschutz.webp') }}" alt="Waldlandschaft im Kreis Bad Segeberg" loading="lazy">
                <div class="welcome__img-badge">Kreis Bad Segeberg</div>
            </div>
            <div class="welcome__text">
                <span class="welcome__tag">{{ $s['willkommen_tag'] ?? 'Willkommen' }}</span>
                @php
                    $kjsWillkommenZeile1 = $s['willkommen_titel_zeile1'] ?? '';
                    $kjsWillkommenZeile2 = $s['willkommen_titel_zeile2'] ?? '';
                @endphp
                <h2>
                    {{ $kjsWillkommenZeile1 }}
                    @if ($kjsWillkommenZeile1 && $kjsWillkommenZeile2)
                        <br>
                    @endif
                    {{ $kjsWillkommenZeile2 }}
                </h2>
                @if ($s['willkommen_text'] ?? null)
                    <p>{{ $s['willkommen_text'] }}</p>
                @endif
                @if ($s['willkommen_zitat'] ?? null)
                    <blockquote class="welcome__quote">„{{ $s['willkommen_zitat'] }}"</blockquote>
                @endif
                @if ($s['willkommen_text2'] ?? null)
                    <p>{{ $s['willkommen_text2'] }}</p>
                @endif
                <div class="welcome__signature">
                    <div>
                        <span class="welcome__sig-name">{{ $s['willkommen_signatur_name'] ?? 'Ihr Kreisjägermeister' }}</span>
                        <span class="welcome__sig-role">{{ $s['willkommen_signatur_rolle'] ?? 'Kreisjägerschaft Segeberg e.V.' }}</span>
                    </div>
                </div>
                <div style="margin-top:1.75rem">
                    <a href="{{ route('kreisjaegermeister') }}" class="btn btn-primary">Zum Kreisjägermeister</a>
                </div>
            </div>
        </div>
    </section>

    {{--
        ===== SERVICES / AUFGABEN =====
        Bewusst unveraendert statisch: diese Kachelreihe war schon im
        Original NICHT datengetrieben (feste Icons/Bilder/Beschreibungen
        direkt in index.html, keine Entsprechung in der settings-Tabelle
        oder einer anderen DB-Tabelle) - kein "hart codierter Platzhalter"
        im Sinne des Auftrags, sondern redaktioneller Fixinhalt, der 1:1
        uebernommen wird. Links zeigen auf die echten Laravel-Routen.
    --}}
    <section class="section section--alt services">
        <div class="container">
            <h2 class="section-title">Was wir anbieten</h2>
            <div class="section-divider"></div>
            <p class="section-subtitle">Unsere Aufgaben und Tätigkeitsbereiche im Überblick</p>

            <div class="services__grid">
                @foreach ([
                    ['aufgaben/schiessen', 'schiessen.jpg', 'Schießwesen', 'Regelmäßiges Übungsschießen, Schießstandbetrieb und Aus­bildung für sichere und verantwortungsvolle Jagdausübung.'],
                    ['aufgaben/hundeausbildung', 'hundeausbildung.jpg', 'Hundeausbildung', 'Ausbildung und Prüfung von Jagdhunden – von der Grundausbildung bis zur Prüfung für erfahrene Gespanne.'],
                    ['aufgaben/schweisshunde', 'schweisshunde.jpg', 'Schweißhundeführer', 'Speziell ausgebildete Gespanne zur sicheren Nachsuche und Wildbreteinhaltung im gesamten Kreisgebiet.'],
                    ['aufgaben/jugend', 'jugendarbeit.jpg', 'Jugendarbeit', 'Naturerlebnisse, Wildtierkunde und Umweltbildung für Kinder und Jugendliche – Jagd erleben und verstehen.'],
                    ['aufgaben/jagdhorn', 'jagdhornblasen.jpg', 'Jagdhornblasen', 'Pflege der jagdlichen Tradition durch aktive Bläsergruppen und Teilnahme an Wettbewerben auf Landes- und Bundesebene.'],
                    ['aufgaben/naturschutz', 'naturschutz.webp', 'Naturschutz', 'Biotoppflege, Heckenanlage und Schutzmaßnahmen für heimische Wildtiere und ihre Lebensräume.'],
                    ['aufgaben/jungwildrettung', 'jungwildrettung.jpg', 'Jungwildrettung', 'Koordinierte Drohneneinsätze zur Rettung von Rehkitzen und Bodenbrütern vor der Frühjahrsmahd.'],
                    ['termine', 'termine.jpg', 'Termine & Events', 'Alle Veranstaltungen, Schießtage, Hegering­treffen und Sonderevents der KJS Segeberg auf einen Blick.'],
                ] as [$href, $bild, $titel, $text])
                    <a href="/{{ $href }}" class="service-card">
                        <div class="service-card__img-wrap">
                            <img class="service-card__img" src="{{ asset('images/'.$bild) }}" alt="{{ $titel }}" loading="lazy">
                        </div>
                        <div class="service-card__body">
                            <div class="service-card__icon"><img src="{{ asset('images/logo.png') }}" alt="KJS Logo" class="service-card__logo"></div>
                            <div class="service-card__title">{{ $titel }}</div>
                            <p class="service-card__text">{{ $text }}</p>
                            <span class="read-more">Mehr lesen</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== NEWS ===== --}}
    <section class="section">
        <div class="container">
            <h2 class="section-title">Was passiert in der Kreisjägerschaft Segeberg</h2>
            <div class="section-divider"></div>
            <p class="section-subtitle">Aktuelle Nachrichten, Berichte und Hinweise aus unserer Gemeinschaft</p>

            <div class="news__grid" id="home-news-grid">
                @forelse ($beitraege as $b)
                    <article class="news-card">
                        <a href="{{ route('aktuelles.show', $b->slug) }}" style="text-decoration:none;color:inherit;display:block;">
                            <div class="news-card__img-wrap">
                                @if ($b->bild)
                                    <img class="news-card__img" src="{{ \App\Support\Images::cardUrl($b->bild) }}" data-full="{{ $b->bild }}" onerror="kjsImgFallback(this)" alt="{{ $b->titel }}" loading="lazy">
                                @else
                                    <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:180px;">
                                        <img src="{{ asset('images/logo.png') }}" alt="KJS" style="height:64px;opacity:.3;">
                                    </div>
                                @endif
                            </div>
                            <div class="news-card__body">
                                <div class="news-card__meta">
                                    <span class="news-card__cat">{{ $b->kategorie?->name }}</span>
                                    <span class="news-card__date">{{ $b->datum?->format('d.m.Y') }}</span>
                                </div>
                                <h3 class="news-card__title">{{ $b->titel }}</h3>
                                <p class="news-card__excerpt">{{ \App\Support\Text::excerpt($b->text) }}</p>
                                <span class="read-more">Weiterlesen</span>
                            </div>
                        </a>
                    </article>
                @empty
                    <p style="color:var(--text-muted);">Zurzeit keine Beiträge vorhanden.</p>
                @endforelse
            </div>

            <div class="news__more">
                <a href="{{ route('aktuelles.index') }}" class="btn btn-outline-green">Mehr Beiträge ansehen</a>
            </div>
        </div>
    </section>

    {{-- ===== TERMINE PREVIEW ===== --}}
    <section class="section section--alt">
        <div class="container">
            <h2 class="section-title">Nächste Termine</h2>
            <div class="section-divider"></div>
            <p class="section-subtitle">Aktuelle Veranstaltungen der Kreisjägerschaft Segeberg</p>

            <div class="termine-list" id="home-termine-list">
                @forelse ($termine as $t)
                    @php
                        $ortTeile = array_filter([$t->strasse, trim(($t->plz ?? '').' '.($t->ort ?? ''))]);
                        $ortText = implode(', ', $ortTeile) ?: $t->ort;
                    @endphp
                    <div class="termine-item">
                        <div class="termine-item__date">
                            <span class="termine-item__day">{{ $t->datum?->format('d') ?? '–' }}</span>
                            <span class="termine-item__month">{{ $t->datum ? ucfirst($t->datum->translatedFormat('M')) : '' }}</span>
                        </div>
                        <div>
                            <div class="termine-item__title">{{ $t->veranstaltung }}</div>
                            <div class="termine-item__info">
                                @if ($t->uhrzeit)
                                    <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> {{ $t->uhrzeit }} Uhr</span>
                                @endif
                                @if ($ortText)
                                    <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> {{ $ortText }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('termine') }}" class="btn btn-sm btn-outline-green">Details</a>
                    </div>
                @empty
                    <p style="color:var(--text-muted);">Zurzeit keine Termine vorhanden.</p>
                @endforelse
            </div>

            <div class="news__more">
                <a href="{{ route('termine') }}" class="btn btn-primary">Alle Termine anzeigen</a>
            </div>
        </div>
    </section>

    {{-- ===== TESTIMONIALS ===== --}}
    @if ($testimonialsSichtbar)
        <section class="testimonials">
            <div class="container">
                <h2 class="section-title">{{ $s['testimonials_titel'] ?? 'Was unsere Jäger und Mitglieder sagen' }}</h2>
                <div class="section-divider"></div>
                <p class="section-subtitle">{{ $s['testimonials_untertitel'] ?? 'Stimmen aus unserer Gemeinschaft' }}</p>

                @if ($testimonials->isNotEmpty())
                    <div class="testimonials__grid">
                        @foreach ($testimonials as $t)
                            <div class="testimonial-card">
                                <p class="testimonial-card__text">„{{ $t->text }}"</p>
                                <div class="testimonial-card__author">
                                    @if ($t->icon)
                                        <div class="testimonial-card__avatar">{{ $t->icon }}</div>
                                    @endif
                                    <div>
                                        <span class="testimonial-card__name">{{ $t->name }}</span>
                                        <span class="testimonial-card__role">{{ $t->rolle }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ===== CTA BANNER ===== --}}
    <section class="section" style="background: var(--green-light); padding-block: 4rem;">
        <div class="container text-center">
            <h2 style="color: var(--green-dark); margin-bottom: .75rem;">Werden Sie Teil der Jagdgemeinschaft</h2>
            <div class="section-divider"></div>
            <p style="max-width: 580px; margin: 0 auto 2rem; color: var(--text-muted);">
                Ob als Jäger oder Fördermitglied – bei der Kreisjägerschaft Segeberg
                sind Sie Teil einer aktiven Gemeinschaft, die Natur und Jagd verantwortungsvoll lebt.
            </p>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                <a href="/jaeger/jaeger-werden" class="btn btn-primary">Jäger/in werden</a>
                <a href="/jaeger/mitglied-werden" class="btn btn-gold">Fördermitglied werden</a>
            </div>
        </div>
    </section>
</x-layouts.app>
