{{--
    Phase 7K (Admin-Modul "Medien") - Archiv-Unterseite.

    1:1 UX wie im Altsystem: archivierte Bilder bleiben auf der Website ganz
    normal bestehen (Archivieren verschiebt/loescht NICHTS auf der Platte,
    siehe Admin\MedienController::archivToggle()) und tauchen nur hier statt
    in der normalen Uebersicht auf.
--}}
<x-layouts.admin title="Medien-Archiv">
    <div class="panel-header">
        <h1>📦 Archivierte Bilder</h1>
        <a class="btn btn-outline" href="{{ route('admin.medien.index') }}">← Zurück zu Medien</a>
    </div>
    <div class="panel-body">
        @if (session('status'))
            <p class="kjs-admin-status--success" style="margin-bottom:1.5rem;">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="kjs-admin-status--error" style="margin-bottom:1.5rem;">{{ $errors->first() }}</p>
        @endif

        <p class="text-muted" style="margin-bottom:1.5rem;font-size:.85rem;">
            Archivierte Bilder bleiben auf der Website ganz normal bestehen und tauchen nur hier nicht mehr in der normalen Übersicht auf. Über „♻️ Wiederherstellen“ lassen sie sich jederzeit zurückholen.
        </p>

        @if (empty($angezeigt))
            <p class="hint-card">Keine archivierten Bilder.</p>
        @else
            <div class="medien-grid">
                @foreach ($angezeigt as $item)
                    @include('admin.medien._kachel', ['item' => $item])
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.admin>
