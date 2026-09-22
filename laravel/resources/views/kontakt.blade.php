{{--
    Phase 4 Abschluss-Nacharbeit ("Kontakt-Route") + Phase 6C
    (Kontaktformular vollstaendig auf Laravel): ersetzt kontakt/index.html
    vollstaendig - Header/Footer/Breadcrumb/Page-Hero wie bei den uebrigen
    Content-Seiten (siehe z.B. datenschutz.blade.php/pages/show.blade.php),
    "page-content"-Zweispalten-Layout (bereits aus Phase 1/2 vorhandenes
    CSS, kein neues Markup noetig). Die informative Kontaktspalte kommt
    weiterhin aus der Settings-Gruppe "einstellungen" - jedes Feld wird nur
    angezeigt, wenn es in der DB einen Wert hat, kein erfundener
    Platzhaltertext.

    Das Kontaktformular unten (Phase 6C) wird bewusst INNERHALB von
    "<main class='main-content'>" platziert (nicht als eigener Grid-
    Bestandteil) - die generische Regel ".page-content .container {
    display:grid; grid-template-columns:1fr 290px; }" (siehe resources/
    css/app.css) erwartet GENAU zwei direkte Grid-Kinder (main + aside);
    ein drittes Element wuerde in die schmale 290px-Sidebarspalte
    gequetscht (identisches Fehlermuster wie beim Nachbau von
    waffenboerse/show.blade.php, siehe dortiger Kommentar).

    Erfolgs-/Fehler-Zustaende und Feld-Wiederherstellung nutzen bewusst die
    bereits bestehenden, generischen ".hb-success-box"/".hb-error-summary"/
    ".hb-field-error"/".hb-error-text"-Klassen (siehe resources/css/
    app.css-Kommentar: fuer Hundeboerse eingefuehrt, aber als generische,
    bereits mehrfach wiederverwendete Formular-UI-Bausteine gedacht - kein
    Redesign, kein neues CSS noetig). ".kontakt-form"/".form-group"/
    ".form-grid-2" sind ohnehin schon 1:1 aus dem alten css/style.css
    uebernommen (dieselben Klassennamen wie im PHP-Original).
--}}
<x-layouts.app title="Kontakt">
    <x-page-hero title="Kontakt" :breadcrumbs="[
        ['label' => 'Startseite', 'href' => '/'],
        ['label' => 'Kontakt'],
    ]" />

    <div class="page-content">
        <div class="container">
            <main class="main-content">
                @if ($ueberschrift)
                    <h2>{{ $ueberschrift }}</h2>
                @endif

                @if ($text)
                    <p>{{ $text }}</p>
                @endif

                @if (! $ueberschrift && ! $text)
                    <h2>Kontakt</h2>
                    <p>Haben Sie Fragen oder möchten Sie uns erreichen? Unsere Kontaktdaten finden Sie hier.</p>
                @endif

                @if (session('kontakt_success'))
                    <div class="hb-success-box" style="display:block;">
                        <h3>Vielen Dank für Ihre Nachricht!</h3>
                        <p>Wir haben Ihre Anfrage erhalten und melden uns so schnell wie möglich bei Ihnen.</p>
                    </div>
                @else
                    <div class="kontakt-form" style="margin-top:2rem;">
                        <h3>Nachricht senden</h3>

                        @if ($errors->any())
                            <div class="hb-error-summary">
                                <strong>Bitte prüfen Sie folgende Angaben:</strong>
                                <ul>
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('kontakt.store') }}" novalidate>
                            @csrf

                            {{-- Honeypot: fuer Menschen unsichtbares Feld (siehe
                                 kontakt/index.html) - muss leer bleiben, siehe
                                 KontaktController::store(). --}}
                            <input type="text" name="_honey" id="kf-honey" style="display:none;" tabindex="-1" autocomplete="off">

                            <div class="form-grid-2">
                                <div class="form-group {{ $errors->has('vorname') ? 'hb-field-error' : '' }}">
                                    <label for="kf-vorname">Vorname *</label>
                                    <input type="text" id="kf-vorname" name="vorname" value="{{ old('vorname') }}" placeholder="Max">
                                    @error('vorname')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-group {{ $errors->has('nachname') ? 'hb-field-error' : '' }}">
                                    <label for="kf-nachname">Nachname *</label>
                                    <input type="text" id="kf-nachname" name="nachname" value="{{ old('nachname') }}" placeholder="Mustermann">
                                    @error('nachname')<p class="hb-error-text">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="form-group {{ $errors->has('email') ? 'hb-field-error' : '' }}">
                                <label for="kf-email">E-Mail *</label>
                                <input type="email" id="kf-email" name="email" value="{{ old('email') }}" placeholder="max@beispiel.de">
                                @error('email')<p class="hb-error-text">{{ $message }}</p>@enderror
                            </div>

                            <div class="form-group">
                                <label for="kf-telefon">Telefon</label>
                                <input type="tel" id="kf-telefon" name="telefon" value="{{ old('telefon') }}" placeholder="04551 / ...">
                            </div>

                            <div class="form-group">
                                <label>Sind Sie bereits Jäger?</label>
                                <div class="hb-typ-toggle" style="max-width:420px;">
                                    <label class="hb-typ-option" id="kfJaegerOptionJa">
                                        <input type="radio" name="bereits_jaeger" value="Ja" id="kf-bereits-jaeger-ja" {{ old('bereits_jaeger') === 'Ja' ? 'checked' : '' }}>
                                        Ja
                                    </label>
                                    <label class="hb-typ-option" id="kfJaegerOptionNein">
                                        <input type="radio" name="bereits_jaeger" value="Nein" id="kf-bereits-jaeger-nein" {{ old('bereits_jaeger', 'Nein') === 'Nein' ? 'checked' : '' }}>
                                        Nein
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="kf-hegering-group" style="{{ old('bereits_jaeger') === 'Ja' ? '' : 'display:none;' }}">
                                <label for="kf-hegering">Hegering</label>
                                <select id="kf-hegering" name="hegering">
                                    <option value="">Bitte wählen …</option>
                                    @foreach ($hegeringe as $h)
                                        @php $wert = trim(($h->nummer ?? '').($h->name ? ' – '.$h->name : '')); @endphp
                                        <option value="{{ $wert }}" {{ old('hegering') === $wert ? 'selected' : '' }}>{{ $wert }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group {{ $errors->has('betreff') ? 'hb-field-error' : '' }}">
                                <label for="kf-betreff">Anliegen *</label>
                                <select id="kf-betreff" name="betreff">
                                    <option value="">Bitte wählen …</option>
                                    @foreach ($betreffOptionen as $option)
                                        <option value="{{ $option }}" {{ old('betreff') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('betreff')<p class="hb-error-text">{{ $message }}</p>@enderror
                            </div>

                            <div class="form-group {{ $errors->has('nachricht') ? 'hb-field-error' : '' }}">
                                <label for="kf-nachricht">Nachricht *</label>
                                <textarea id="kf-nachricht" name="nachricht" style="min-height:150px;">{{ old('nachricht') }}</textarea>
                                @error('nachricht')<p class="hb-error-text">{{ $message }}</p>@enderror
                            </div>

                            <div class="form-group {{ $errors->has('datenschutz') ? 'hb-field-error' : '' }}" style="display:flex; align-items:flex-start; gap:.75rem;">
                                <input type="checkbox" id="kf-datenschutz" name="datenschutz" value="1" style="width:auto; margin-top:.2rem;" {{ old('datenschutz') ? 'checked' : '' }}>
                                <label for="kf-datenschutz" style="font-size:.88rem; font-weight:400; color:var(--text-dark);">
                                    Ich habe die <a href="{{ route('datenschutz') }}">Datenschutzerklärung</a> gelesen und bin mit der Verarbeitung meiner Daten einverstanden. *
                                </label>
                            </div>
                            @error('datenschutz')<p class="hb-error-text">{{ $message }}</p>@enderror

                            <button type="submit" class="btn btn-primary" style="margin-top:.5rem;">Nachricht senden</button>
                        </form>
                    </div>
                @endif
            </main>

            <aside class="sidebar">
                @if ($adresse || $telefon || $email || $postadresse || ! empty($oeffnungszeiten))
                    <div class="sidebar-widget">
                        <h4>Kontaktdaten</h4>

                        @if ($adresse)
                            <p>{!! nl2br(e($adresse)) !!}</p>
                        @endif

                        @if ($telefon)
                            <p>📞 <a href="tel:{{ preg_replace('/\s|\/|\./', '', $telefon) }}">{{ $telefon }}</a></p>
                        @endif

                        @if ($email)
                            <p>📧 <a href="mailto:{{ $email }}">{{ $email }}</a></p>
                        @endif

                        @if ($postadresse)
                            <p>
                                <strong>Postadresse:</strong><br>
                                {!! nl2br(e($postadresse)) !!}
                                @if ($postadresseTelefon)
                                    <br>Tel.: <a href="tel:{{ preg_replace('/\s|\/|\./', '', $postadresseTelefon) }}">{{ $postadresseTelefon }}</a>
                                @endif
                                @if ($postadresseEmail)
                                    <br>E-Mail: <a href="mailto:{{ $postadresseEmail }}">{{ $postadresseEmail }}</a>
                                @endif
                            </p>
                        @endif

                        @if (! empty($oeffnungszeiten))
                            <p>
                                <strong>Sprechzeiten:</strong><br>
                                @foreach ($oeffnungszeiten as $zeit)
                                    @if (! empty($zeit['tage']) || ! empty($zeit['zeiten']))
                                        {{ $zeit['tage'] ?? '' }}@if (! empty($zeit['tage']) && ! empty($zeit['zeiten'])): @endif{{ $zeit['zeiten'] ?? '' }}<br>
                                    @endif
                                @endforeach
                            </p>
                        @endif

                        {{-- Google-Maps-Embed (Nachtrag Phase 6C-Review): war in
                             kontakt/index.html vorhanden (".kontakt-info"-Spalte,
                             letzter Block) und wurde beim ersten 6C-Durchgang
                             übersehen. Kein Redesign/keine neue Adresse/URL - exakt
                             dieselbe, bereits im Security-/Datenschutz-Hardening-Pass
                             (11.09.2026, siehe js/main.js-Kommentar zu
                             kjsEmbedPlaceholder) datenschutzfreundlich gemachte
                             Zwei-Klick-Einbindung: erst ein Klick auf den Button baut
                             das echte <iframe>, kein automatischer Request an Google
                             bei jedem Seitenaufruf. Dasselbe bereits in Laravel
                             portierte Muster (window.kjsActivateEmbed, resources/js/
                             app.js) wie termine.blade.php (Google-Kalender) und
                             service.blade.php (YouTube) - keine neue Architektur,
                             @vite lädt app.js bereits global im Layout. Die Karten-URL
                             ist im Alt-System ein fest im Code stehender Literal-Link
                             (nicht aus "adresse" dynamisch gebaut) und wird hier
                             bewusst textgleich übernommen statt neu generiert - nur
                             sichtbar, wenn "adresse" gepflegt ist (derselbe Anzeigename
                             "Am Schießstand 1, 24640 Hasenmoor", den auch der obige
                             Adress-Absatz zeigt). --}}
                        @php
                            // Exakt derselbe fest im Code stehende Literal-Link wie im
                            // Alt-System (kontakt/index.html) - hier bewusst als
                            // PHP-String statt erneut als roher HTML-Text, damit Blade
                            // die Ausgabe ({{ }} unten) korrekt/valide HTML-escaped
                            // (das Alt-System escapte denselben Wert ueber seine eigene
                            // kjsEscHtmlG()-Hilfsfunktion, siehe js/main.js).
                            $mapsEmbedUrl = 'https://maps.google.com/maps?q=Am+Schie%C3%9Fstand+1%2C+24640+Hasenmoor&output=embed&z=15&hl=de';
                        @endphp
                        @if ($adresse)
                            <div class="kjs-embed-placeholder" data-embed-src="{{ $mapsEmbedUrl }}" style="position:relative;aspect-ratio:auto;min-height:280px;background:var(--green-light,#eef3ec);border-radius:var(--radius-md,8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;overflow:hidden;text-align:center;padding:1rem;margin-top:1rem;">
                                <button type="button" class="btn btn-outline-green" onclick="kjsActivateEmbed(this)">🗺️ Karte anzeigen</button>
                                <p style="margin:0;font-size:.78rem;color:var(--text-muted,#666);max-width:26rem;">Beim Klick wird eine Karte von Google Maps geladen. Dabei können Daten an Google übertragen werden.</p>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="sidebar-widget">
                    <h4>KJS Segeberg</h4>
                    <ul class="sidebar-nav" data-related-nav></ul>
                </div>
            </aside>
        </div>
    </div>

    {{-- Hegering-Auswahl nur einblenden, wenn "Ja" (bereits Jäger) gewaehlt
         ist - reine Progressive Enhancement, serverseitig ist "hegering"
         ohnehin nicht Pflicht (siehe KontaktRequest). Ersetzt die alte
         fetch()-basierte Lazy-Load-Logik aus kontakt/index.html: die
         Hegering-Optionen selbst kommen hier bereits serverseitig aus der
         Datenbank (siehe KontaktController::show()), nur das Ein-/
         Ausblenden passiert noch client-seitig. Kein @push('scripts')
         (siehe waffenboerse/show.blade.php-Vorbild: der App-Layout
         definiert keinen passenden @stack). --}}
    <script>
        (function () {
            var jaRadio = document.getElementById('kf-bereits-jaeger-ja');
            var neinRadio = document.getElementById('kf-bereits-jaeger-nein');
            var gruppe = document.getElementById('kf-hegering-group');
            if (!gruppe || (!jaRadio && !neinRadio)) return;
            function toggle() {
                gruppe.style.display = (jaRadio && jaRadio.checked) ? '' : 'none';
            }
            if (jaRadio) jaRadio.addEventListener('change', toggle);
            if (neinRadio) neinRadio.addEventListener('change', toggle);
        })();
    </script>
</x-layouts.app>
