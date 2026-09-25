{{--
    Phase 7L (Admin-Modul "Benutzer"), Teil B - Uebersicht.

    Zeigt ALLE Benutzer (es gibt keine Trennung wie bei anderen Modulen in
    "aktiv"/"archiviert" o.ae.) mit Name, E-Mail und Rolle. "Administrator"
    ist die einzige Rolle, die der neue Blade-Admin tatsaechlich kennt und
    prueft (siehe BenutzerController-Klassenkommentar) - alles andere zeigt
    "—".
--}}
<x-layouts.admin title="Benutzer">
    <div class="panel-header">
        <h1>👤 Benutzer</h1>
        <a class="btn btn-primary" href="{{ route('admin.benutzer.neu') }}">+ Neuer Benutzer</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        @if ($benutzer->isEmpty())
            <p class="hint-card">Keine Benutzer vorhanden.</p>
        @else
            <table class="seiten-liste-tabelle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($benutzer as $eintrag)
                        <tr>
                            <td>
                                {{ $eintrag->name }}
                                @if ($eintrag->id === $eigeneId)
                                    <span class="field-hint">(Sie)</span>
                                @endif
                            </td>
                            <td>{{ $eintrag->email }}</td>
                            <td>
                                @if (in_array('admin', is_array($eintrag->roles) ? $eintrag->roles : [], true))
                                    <span class="seitentyp-badge">Administrator</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td><a class="btn btn-sm btn-outline" href="{{ route('admin.benutzer.bearbeiten', $eintrag) }}">Bearbeiten</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.admin>
