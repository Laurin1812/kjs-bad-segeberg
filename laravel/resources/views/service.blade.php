{{--
    Phase 2 (Oeffentliche Inhaltsseiten, Laravel-Vollmigration): ersetzt
    service.html. Kategorie-Akkordeon (<details>/<summary>, wie FAQ) statt
    JS-generiertem Markup; Jahr-/Kategorie-Filter bleiben reines Zeigen/
    Verstecken der bereits server-gerenderten .service-beitrag-Bloecke (kein
    fetch() mehr - siehe ServiceController-Klassenkommentar). YouTube-Embeds
    nutzen die datenschutzfreundliche Zwei-Klick-Einbindung
    (window.kjsActivateEmbed, siehe resources/js/app.js).
--}}
<x-layouts.app :title="$titel">
    <x-page-hero :title="$titel" :bg-image="$heroBild ?: '/images/stock/hero-default.jpg'" />

    <div class="page-content full">
        <div class="container">
            <main class="main-content" id="page-main">
                @if ($jahre->isNotEmpty())
                    <div class="service-filter-wrap">
                        <label for="service-jahr-select">Jahr:</label>
                        <select id="service-jahr-select" class="service-jahr-filter">
                            <option value="">Alle Jahre</option>
                            @foreach ($jahre as $jahr)
                                <option value="{{ $jahr }}">{{ $jahr }}</option>
                            @endforeach
                        </select>
                        @if ($kategorien->isNotEmpty())
                            <label for="service-kat-select">Kategorie:</label>
                            <select id="service-kat-select" class="service-jahr-filter">
                                <option value="">Alle Kategorien</option>
                                @foreach ($kategorien as $kat)
                                    <option value="{{ $kat['titel'] }}">{{ $kat['titel'] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif

                @forelse ($kategorien as $kat)
                    <details class="service-kategorie" data-kat="{{ $kat['titel'] }}">
                        <summary class="service-kategorie-titel">{{ $kat['titel'] }}</summary>
                        <div class="service-kategorie__body">
                            @foreach ($kat['beitraege'] as $eintrag)
                                @php $b = $eintrag['beitrag']; @endphp
                                <div class="service-beitrag" data-jahr="{{ $eintrag['jahr'] }}">
                                    <div class="service-beitrag__titel">
                                        {{ $b->titel }}
                                        @if ($b->datum)
                                            <span class="service-beitrag__datum">{{ $b->datum->format('d.m.Y') }}</span>
                                        @endif
                                    </div>
                                    @if ($eintrag['text_html'])
                                        <div class="service-beitrag__text">{!! $eintrag['text_html'] !!}</div>
                                    @endif
                                    @if ($eintrag['youtube_id'])
                                        <div class="kjs-embed-placeholder" data-embed-src="https://www.youtube-nocookie.com/embed/{{ $eintrag['youtube_id'] }}" style="position:relative;aspect-ratio:16/9;background:var(--green-light,#eef3ec);border-radius:var(--radius-md,8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;overflow:hidden;text-align:center;padding:1rem;">
                                            <button type="button" class="btn btn-outline-green" onclick="kjsActivateEmbed(this)">▶ Video laden</button>
                                            <p style="margin:0;font-size:.78rem;color:var(--text-muted,#666);max-width:26rem;">Beim Klick wird das Video von YouTube geladen. Dabei können Daten an YouTube/Google übertragen werden.</p>
                                        </div>
                                    @endif
                                    @if ($b->downloads->isNotEmpty())
                                        <div class="downloads-list">
                                            @foreach ($b->downloads as $dl)
                                                <div class="download-item">
                                                    @if ($dl->vorschau)
                                                        <a href="{{ $dl->pfad }}" target="_blank" rel="noopener noreferrer" class="download-item__thumb service-download-item__thumb">
                                                            <img src="{{ $dl->vorschau }}" alt="{{ $dl->titel }}" loading="lazy">
                                                        </a>
                                                    @else
                                                        <div class="download-item__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></div>
                                                    @endif
                                                    <div class="download-item__meta">
                                                        <div class="download-item__name">{{ $dl->titel }}</div>
                                                    </div>
                                                    <span class="download-item__actions">
                                                        <a href="{{ $dl->pfad }}" target="_blank" rel="noopener noreferrer" class="download-action" title="Öffnen" aria-label="{{ $dl->titel }} öffnen"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg></a>
                                                        <a href="{{ $dl->pfad }}" download class="download-action" title="Herunterladen" aria-label="{{ $dl->titel }} herunterladen"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p>Aktuell sind keine Beiträge vorhanden.</p>
                @endforelse

                @if ($kontaktName || $kontaktEmail)
                    <p>
                        <strong>Kontakt:</strong> {{ $kontaktName }}
                        @if ($kontaktEmail)
                            &middot; <a href="mailto:{{ $kontaktEmail }}">{{ $kontaktEmail }}</a>
                        @endif
                    </p>
                @endif
            </main>
        </div>
    </div>

    @if ($jahre->isNotEmpty())
        <script>
        (function () {
            var jahrSelect = document.getElementById('service-jahr-select');
            var katSelect = document.getElementById('service-kat-select');
            function applyFilters() {
                var jahr = jahrSelect ? jahrSelect.value : '';
                var kat = katSelect ? katSelect.value : '';
                document.querySelectorAll('.service-kategorie').forEach(function (katEl) {
                    if (kat && katEl.getAttribute('data-kat') !== kat) {
                        katEl.style.display = 'none';
                        return;
                    }
                    katEl.style.display = '';
                    var visibleCount = 0;
                    katEl.querySelectorAll('.service-beitrag').forEach(function (item) {
                        var match = !jahr || item.getAttribute('data-jahr') === jahr;
                        item.style.display = match ? '' : 'none';
                        if (match) visibleCount++;
                    });
                    katEl.style.display = visibleCount > 0 ? '' : 'none';
                    if ((jahr || kat) && visibleCount > 0) katEl.setAttribute('open', '');
                });
            }
            if (jahrSelect) jahrSelect.addEventListener('change', applyFilters);
            if (katSelect) katSelect.addEventListener('change', applyFilters);
        })();
        </script>
    @endif
</x-layouts.app>
