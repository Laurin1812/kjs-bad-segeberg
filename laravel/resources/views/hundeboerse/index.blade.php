{{--
    Phase 6A (Sondermodule inventarisieren + Hundeboerse auf Laravel/MySQL):
    ersetzt hundeboerse/index.html. Server-gerenderte Kachel-Uebersicht
    (.news__grid/.news-card, wie Partner/Aktuelles) statt der bisherigen
    clientseitigen fetch()-Kartenerzeugung - siehe HundeboerseController.
    Nur status="published" (siehe dortiger Klassenkommentar).

    Hero-Bild: ausschliesslich das echte, mitgelieferte Hundeboerse-Hero
    (HundeboerseMeta::hero_bild). Fehlt es in der DB, greift derselbe echte
    Hundeboerse-Bestandspfad als technischer Fallback - bewusst KEIN
    generisches Stock-Motiv (siehe Auftrag: "keine fachfremden
    Fallback-Bilder").
--}}
<x-layouts.app title="Hundebörse">
    <x-page-hero title="Hundebörse" :bg-image="$heroBild ?: '/images/1787934528232-ChatGPT-Image-28.-Aug.-2026--18_28_24.png'" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Hundebörse'],
    ]" />

    <div class="page-content">
        <div class="container hb-overview-layout">
            <div class="hb-overview-intro">
                <p style="font-size:1.05rem;color:var(--text-body);line-height:1.8;margin-bottom:1.5rem;">
                    Hier finden Sie aktuell angebotene Jagdhunde und Würfe. Entdecken Sie die aktuellen Angebote oder
                    bieten Sie selbst einen Hund bzw. Wurf an.
                </p>
                <p style="margin-bottom:0;">
                    <a href="{{ route('hundeboerse.anbieten') }}" class="btn btn-primary">+ Hund / Wurf anbieten</a>
                </p>
            </div>

            <main class="main-content" id="hb-main">
                <h2 style="margin-bottom:1.5rem;">Aktuelle Hunde &amp; Würfe</h2>
                <div class="news__grid" id="hb-grid">
                    @forelse ($anzeigen as $a)
                        @php $bild = $a->bilder->first()->pfad ?? null; @endphp
                        <article class="news-card">
                            <a href="{{ route('hundeboerse.show', $a->id) }}" style="text-decoration:none;color:inherit;">
                                <div class="news-card__img-wrap">
                                    @if ($bild)
                                        <img class="news-card__img" src="{{ \App\Support\Images::boerseCardUrl($bild) }}" data-full="{{ $bild }}" onerror="kjsImgFallback(this)" alt="{{ $a->title }}" loading="lazy">
                                    @else
                                        <div style="background:var(--green-light);display:flex;align-items:center;justify-content:center;min-height:180px;"><img src="/images/logo.png" alt="KJS" style="height:64px;opacity:.3;"></div>
                                    @endif
                                </div>
                                <div class="news-card__body">
                                    <div class="news-card__meta">
                                        <span class="news-card__cat">{{ $a->typLabel() }}</span>
                                    </div>
                                    <h3 class="news-card__title">{{ $a->title ?: '(Ohne Titel)' }}</h3>
                                    <p style="font-size:.86rem;color:var(--text-muted);margin:0 0 .2rem;">{{ $a->breed }}</p>
                                    @php $standort = trim(($a->postal_code ?? '').' '.($a->city ?? '')); @endphp
                                    @if ($standort)
                                        <p style="font-size:.86rem;color:var(--text-muted);margin:0 0 .2rem;">{{ $standort }}</p>
                                    @endif
                                    <p style="font-size:.86rem;color:var(--text-muted);margin:0;">{{ $a->typZeile() }}</p>
                                    <div class="hb-card-footer">
                                        <p style="font-size:.95rem;font-weight:700;color:var(--green-dark);margin:0 0 .5rem;">{{ $a->preisText() }}</p>
                                        <span style="font-size:.88rem;font-weight:600;color:var(--green-main);">Details ansehen →</span>
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
