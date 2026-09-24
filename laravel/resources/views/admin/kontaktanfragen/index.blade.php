{{--
    Phase 7G (Admin-Modul "Kontaktanfragen") - Liste der eingegangenen
    Anfragen.

    Rein server-gerendert (wie Phase 7B-7F). $anfragen kommt bereits nach
    "erstellt_am" absteigend sortiert aus KontaktanfragenController::index()
    (neueste zuerst - im alten Admin nicht explizit vorgegeben, siehe
    Auftrag "Sortierung neueste zuerst sofern nichts anderes vorgegeben").

    Spalten 1:1 an admin.js' kontaktanfragenRenderListe() angelehnt (Name,
    E-Mail, Anliegen/Betreff, Datum, Status), ergaenzt um einen kurzen
    Nachrichten-Auszug (Auftrag-Vorgabe fuer die neue Liste) und Telefon.
    KEINE Suche/Filterung (der alte Admin kennt keine, siehe Controller-
    Klassenkommentar) - genauso KEIN Status-Umschalter direkt in der Liste
    (der lebt bewusst nur auf der Detailseite, siehe anzeigen.blade.php).

    Nachrichtentext/alle Felder ausschliesslich ueber {{ }} (Blade-Escaping) -
    niemals {!! !!} (Auftrag Punkt "Sicherheit").
--}}
<x-layouts.admin title="Kontaktanfragen">
    <div class="panel-header">
        <h1>📬 Kontaktanfragen</h1>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        @if ($anfragen->isEmpty())
            <p class="hint-card">Es sind noch keine Kontaktanfragen vorhanden.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Telefon</th>
                        <th>Anliegen</th>
                        <th>Nachricht</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($anfragen as $anfrage)
                        <tr>
                            <td>{{ optional($anfrage->erstellt_am)->format('d.m.Y H:i') ?? '–' }}</td>
                            <td>
                                <a href="{{ route('admin.kontaktanfragen.anzeigen', $anfrage) }}">
                                    {{ $anfrage->name ?: '(ohne Namen)' }}
                                </a>
                            </td>
                            <td>{{ $anfrage->email ?: '–' }}</td>
                            <td>{{ $anfrage->telefon ?: '–' }}</td>
                            <td>{{ $anfrage->anliegen ?: '–' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($anfrage->nachricht ?? '', 60) ?: '–' }}</td>
                            <td>
                                @if ($anfrage->status === 'bearbeitet')
                                    <span class="text-muted">✅ Bearbeitet</span>
                                @else
                                    <span class="seitentyp-badge">🆕 Neu</span>
                                @endif
                                @if (! $anfrage->mail_versendet)
                                    <br><span class="seitentyp-badge" title="{{ $anfrage->mail_fehler ?: 'Benachrichtigungs-E-Mail konnte nicht versendet werden' }}">✉️❌</span>
                                @endif
                            </td>
                            <td>
                                <div class="item-actions">
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.kontaktanfragen.anzeigen', $anfrage) }}">👁️ Ansehen</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.admin>
