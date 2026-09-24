{{--
    Phase 7G (Admin-Modul "Kontaktanfragen") - Detailansicht einer einzelnen
    Anfrage.

    Ersetzt admin.js' Inline-Auf-/Zuklappen (kontaktanfrageToggle()) durch
    eine eigene Seite (Auftrag-Vorgabe: eigene View "anzeigen.blade.php") -
    zeigt dieselben Felder wie dort: Absender, E-Mail, Telefon, Anliegen,
    "bereits Jäger/in" und Hegering (beide nur falls vorhanden, 1:1 wie im
    Alt-Admin), Eingangsdatum, zuletzt-bearbeitet-Datum (falls gesetzt),
    sowie die vollstaendige Nachricht.

    KEINE Bearbeiten-Maske fuer diese Felder (Auftrag ausdruecklich - siehe
    KontaktanfragenController-Klassenkommentar), KEINE Antwort-Mail-Funktion
    (existiert im Alt-Admin nicht). E-Mail/Telefon als mailto:/tel:-Links
    (Auftrag: "falls bereits gängig/unkritisch") - rein clientseitige
    Komfortfunktion, keine neue Serverlogik.

    Nachrichtentext ausschliesslich ueber {{ }} dargestellt (niemals
    {!! !!}) - eine im Formular eingegebene Nachricht wie
    "<img src=x onerror=alert(1)>" landet damit nur als sichtbarer Text,
    nie als ausgefuehrtes HTML (Auftrag Punkt "Sicherheit", 1:1 wie
    admin.js' escHtml()-Prinzip). white-space:pre-wrap erhaelt
    Zeilenumbrueche der Originalnachricht, ohne HTML zu interpretieren.
--}}
<x-layouts.admin title="Kontaktanfrage ansehen">
    <div class="panel-header">
        <h1>📬 Kontaktanfrage von {{ $anfrage->name ?: '(ohne Namen)' }}</h1>
        <a class="btn btn-outline" href="{{ route('admin.kontaktanfragen.index') }}">← Zurück zur Liste</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif

        <div class="form-card">
            <div class="form-card-title">
                @if ($anfrage->status === 'bearbeitet')
                    <span class="text-muted">✅ Bearbeitet</span>
                @else
                    <span class="seitentyp-badge">🆕 Neu</span>
                @endif
            </div>
            <div class="field-row">
                <label class="field-label">Name</label>
                <p>{{ $anfrage->name ?: '–' }}</p>
            </div>
            <div class="field-row">
                <label class="field-label">E-Mail</label>
                <p>
                    @if ($anfrage->email)
                        <a href="mailto:{{ $anfrage->email }}">{{ $anfrage->email }}</a>
                    @else
                        –
                    @endif
                </p>
            </div>
            <div class="field-row">
                <label class="field-label">Telefon</label>
                <p>
                    @if ($anfrage->telefon)
                        <a href="tel:{{ $anfrage->telefon }}">{{ $anfrage->telefon }}</a>
                    @else
                        –
                    @endif
                </p>
            </div>
            <div class="field-row">
                <label class="field-label">Anliegen</label>
                <p>{{ $anfrage->anliegen ?: '–' }}</p>
            </div>
            @if ($anfrage->bereits_jaeger)
                <div class="field-row">
                    <label class="field-label">Bereits Jäger/in</label>
                    <p>{{ $anfrage->bereits_jaeger }}</p>
                </div>
            @endif
            @if ($anfrage->hegering)
                <div class="field-row">
                    <label class="field-label">Hegering</label>
                    <p>{{ $anfrage->hegering }}</p>
                </div>
            @endif
            <div class="field-row">
                <label class="field-label">Eingegangen</label>
                <p>{{ optional($anfrage->erstellt_am)->format('d.m.Y H:i') ?? '–' }}</p>
            </div>
            @if ($anfrage->bearbeitet_am)
                <div class="field-row">
                    <label class="field-label">Zuletzt bearbeitet</label>
                    <p>{{ $anfrage->bearbeitet_am->format('d.m.Y H:i') }}</p>
                </div>
            @endif
            @unless ($anfrage->mail_versendet)
                <div class="field-row">
                    <label class="field-label">Benachrichtigung</label>
                    <p class="kjs-admin-status--error">✉️❌ Benachrichtigungs-E-Mail konnte nicht versendet werden{{ $anfrage->mail_fehler ? ' ('.$anfrage->mail_fehler.')' : '' }}.</p>
                </div>
            @endunless
            <div class="field-row">
                <label class="field-label">Nachricht</label>
                <p style="white-space:pre-wrap;background:var(--admin-bg-subtle,#f6f6f6);padding:.6rem .8rem;border-radius:6px;">{{ $anfrage->nachricht ?: '(keine Nachricht)' }}</p>
            </div>
        </div>

        <div class="save-bar">
            <form method="POST" action="{{ route('admin.kontaktanfragen.status', $anfrage) }}" style="margin:0;">
                @csrf
                @method('PUT')
                @if ($anfrage->status === 'bearbeitet')
                    <button type="submit" class="btn btn-outline">↩️ Auf „Neu" zurücksetzen</button>
                @else
                    <button type="submit" class="btn btn-primary">✅ Als bearbeitet markieren</button>
                @endif
            </form>
        </div>
    </div>
</x-layouts.admin>
