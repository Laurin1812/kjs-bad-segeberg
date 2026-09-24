{{--
    Phase 7J (Admin-Modul "Waffenboerse") - Kategorienverwaltung.

    Eigene, separate Seite statt eines in den Anzeigen-Editor eingebetteten
    Mini-Formulars - siehe Admin\WaffenboerseController-Klassenkommentar
    "Kategorie" fuer die Begruendung (Preservation: ein eingebettetes
    Formular wuerde beim Absenden die komplette Seite neu laden und damit
    alle sonstigen, noch ungespeicherten Aenderungen der gerade bearbeiteten
    Anzeige verwerfen).

    "+ Neu"/"🗑 Löschen" 1:1 wie admin.js' waffenboerseKategorieAdd()/
    -Delete() - Löschen ist serverseitig blockiert, solange noch eine
    Anzeige die Kategorie verwendet (siehe Controller).
--}}
<x-layouts.admin title="Waffenbörse – Kategorien">
    <div class="panel-header">
        <h1>🗂️ Waffenbörse – Kategorien</h1>
        <a class="btn btn-outline" href="{{ route('admin.waffenboerse.index') }}">← Zurück zur Waffenbörse</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <div class="form-card">
            <div class="form-card-title">➕ Neue Kategorie</div>
            <form method="POST" action="{{ route('admin.waffenboerse.kategorien.speichern') }}" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
                @csrf
                <div style="flex:1;min-width:220px;">
                    <label class="field-label" for="f-neue-kategorie">Name</label>
                    <input class="field-input" type="text" id="f-neue-kategorie" name="name" value="{{ old('name') }}">
                </div>
                <button type="submit" class="btn btn-primary">+ Neu</button>
            </form>
        </div>

        <div class="form-card">
            <div class="form-card-title">Bestehende Kategorien</div>
            @if ($kategorien->isEmpty())
                <p class="text-muted">Noch keine Kategorien angelegt.</p>
            @else
                <table class="seiten-liste-tabelle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kategorien as $kat)
                            <tr>
                                <td>{{ $kat->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.waffenboerse.kategorien.loeschen', $kat) }}" onsubmit="return confirm('Kategorie „{{ $kat->name }}“ wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger-outline">🗑️ Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
