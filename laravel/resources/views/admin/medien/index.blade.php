{{--
    Phase 7K (Admin-Modul "Medien") - Uebersicht.

    Zeigt ausschliesslich AKTIVE (nicht archivierte) Bilder/PDFs - archivierte
    Bilder tauchen NIE hier auf, sondern ausschliesslich unter "📦 Archiv"
    (admin.medien.archiv), 1:1 dieselbe "eigene Unterseite statt Akkordeon"-
    UX-Entscheidung wie im Altsystem (siehe Admin\MedienController-
    Klassenkommentar).
--}}
<x-layouts.admin title="Medien">
    <div class="panel-header">
        <h1>🖼️ Medien</h1>
        <a class="btn btn-outline" href="{{ route('admin.medien.archiv') }}">📦 Archiv ({{ $archivAnzahl }})</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <div class="form-card">
            <div class="form-card-title">Hochladen</div>
            <div style="display:flex;flex-wrap:wrap;gap:1.5rem;">
                <form method="POST" action="{{ route('admin.medien.bilder.hochladen') }}" enctype="multipart/form-data" style="flex:1 1 240px;min-width:0;">
                    @csrf
                    <label class="field-label" for="f-bild-upload">🖼️ Bild (JPEG/PNG/WebP)</label>
                    <input class="field-input" type="file" id="f-bild-upload" name="datei" accept="image/jpeg,image/png,image/webp" required style="max-width:100%;">
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.6rem;">Hochladen</button>
                </form>
                <form method="POST" action="{{ route('admin.medien.dateien.hochladen') }}" enctype="multipart/form-data" style="flex:1 1 240px;min-width:0;">
                    @csrf
                    <label class="field-label" for="f-datei-upload">📄 PDF</label>
                    <input class="field-input" type="file" id="f-datei-upload" name="datei" accept="application/pdf" required style="max-width:100%;">
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.6rem;">Hochladen</button>
                </form>
            </div>
        </div>

        <div class="medien-filter-tabs">
            <a class="btn btn-sm {{ $aktuellerFilter === '' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('admin.medien.index') }}">Alle ({{ $zaehler[''] }})</a>
            <a class="btn btn-sm {{ $aktuellerFilter === 'bild' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('admin.medien.index', ['typ' => 'bild']) }}">🖼️ Bilder ({{ $zaehler['bild'] }})</a>
            <a class="btn btn-sm {{ $aktuellerFilter === 'datei' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('admin.medien.index', ['typ' => 'datei']) }}">📄 Dateien ({{ $zaehler['datei'] }})</a>
        </div>

        @if (empty($angezeigt))
            <p class="hint-card">Keine Medien in dieser Ansicht.</p>
        @else
            <div class="medien-grid">
                @foreach ($angezeigt as $item)
                    @include('admin.medien._kachel', ['item' => $item])
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.admin>
