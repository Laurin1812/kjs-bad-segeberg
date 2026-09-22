{{--
    Phase 6B (Waffenboerse auf Laravel/MySQL): ersetzt waffenboerse/
    index.html. Server-gerenderte Kachel-Uebersicht (.news__grid/.news-card,
    wie Aktuelles/Partner/Hundeboerse) statt der bisherigen clientseitigen
    fetch()-Kartenerzeugung - siehe WaffenboerseController. Nur
    status="published" (siehe dortiger Klassenkommentar).

    Client-seitige Filter-/Sortierleiste der alten Uebersicht bewusst NICHT
    uebernommen (siehe WaffenboerseController-Klassenkommentar).

    Hero-Bild: kein dediziertes Waffenboerse-Hero im alten Datenmodell
    vorhanden (waffenboerse_meta hat - anders als hundeboerse_meta - kein
    "hero_bild"-Feld, und auch waffenboerse/index.html selbst nutzte bereits
    das generische "/images/stock/hero-default.jpg"). Daher bewusst KEIN
    :bg-image angegeben - <x-page-hero> faellt automatisch auf denselben,
    bereits sitenweit genutzten neutralen Standard zurueck (kein neues/
    erfundenes Motiv, siehe Auftrag).
--}}
<x-layouts.app title="Waffenbörse">
    <x-page-hero title="Waffenbörse" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Waffenbörse'],
    ]" />

    <div class="page-content">
        <div class="container hb-overview-layout">
            <div class="hb-overview-intro">
                <div class="wb-intro-row">
                    <p style="font-size:1.05rem;color:var(--text-body);line-height:1.8;margin-bottom:0;">
                        Hier finden Mitglieder der Kreisjägerschaft Segeberg aktuell angebotene Waffen, Optik und Zubehör aus privater Hand.
                        Der Erwerb erlaubnispflichtiger Waffen und Munition setzt die jeweils gesetzlich erforderliche Erwerbsberechtigung voraus – Anbieter und Interessenten sind selbst für die Einhaltung der gesetzlichen Vorgaben verantwortlich.
                    </p>
                    <a href="{{ route('waffenboerse.anbieten') }}" class="btn wb-cta-btn">+ Waffe anbieten</a>
                </div>
            </div>

            <main class="main-content" id="wb-main">
                <h2 style="margin-bottom:1.5rem;">Aktuelle Angebote</h2>
                <div class="news__grid" id="wb-grid">
                    @forelse ($anzeigen as $a)
                        @php
                            $bild = $a->bilder->first()->pfad ?? null;
                            $standort = trim(($a->plz ?? '').' '.($a->ort ?? ''));
                            $herstellerModell = trim((string) $a->hersteller);
                        @endphp
                        <article class="news-card">
                            <a href="{{ route('waffenboerse.show', $a->id) }}" style="text-decoration:none;color:inherit;">
                                <div class="news-card__img-wrap">
                                    @if ($bild)
                                        <img class="news-card__img" src="{{ \App\Support\Images::boerseCardUrl($bild) }}" data-full="{{ $bild }}" onerror="kjsImgFallback(this)" alt="{{ $a->titel }}" loading="lazy">
                                    @else
                                        <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:180px;"><img src="/images/logo.png" alt="KJS" style="height:64px;opacity:.3;"></div>
                                    @endif
                                </div>
                                <div class="news-card__body">
                                    <div class="news-card__meta">
                                        <span class="news-card__cat">{{ $a->kategorie }}</span>
                                    </div>
                                    <h3 class="news-card__title">{{ $a->titel ?: '(Ohne Titel)' }}</h3>
                                    @if ($herstellerModell)
                                        <p style="font-size:.86rem;color:var(--text-muted);margin:0 0 .2rem;">{{ $herstellerModell }}</p>
                                    @endif
                                    @if ($a->kaliberText())
                                        <p style="font-size:.86rem;color:var(--text-muted);margin:0 0 .2rem;">{{ $a->kaliberText() }}</p>
                                    @endif
                                    <p style="font-size:.86rem;color:var(--text-muted);margin:0;">
                                        {{ $a->zustandText() }}@if ($standort) &middot; {{ $standort }}@endif
                                    </p>
                                    <div class="wb-card-footer">
                                        <p style="font-size:.95rem;font-weight:700;color:var(--green-dark);margin:0 0 .5rem;">{{ $a->preisText() }}</p>
                                        <span style="font-size:.88rem;font-weight:600;color:var(--green-main);">Anzeige ansehen →</span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    @empty
                        <div style="grid-column:1/-1;text-align:center;padding:3rem 1rem;background:var(--bg-light);border-radius:var(--radius-md);">
                            <h3 style="color:var(--green-dark);margin-bottom:.5rem;">Aktuell sind keine Anzeigen veröffentlicht.</h3>
                            <p style="color:var(--text-muted);">Schauen Sie gerne bald wieder vorbei.</p>
                        </div>
                    @endforelse
                </div>
            </main>

            <aside class="sidebar">
                <div class="contact-box">
                    <h4>Kontakt</h4>
                    <p>📧 <a href="mailto:info@kjs-bad-segeberg.de">info@kjs-bad-segeberg.de</a></p>
                    <p>📞 <a href="tel:+494551123456">04551 / 12 34 56</a></p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
