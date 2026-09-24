{{--
    Phase 7K (Admin-Modul "Medien") - eine Kachel (Bild oder PDF), gemeinsam
    genutzt von index.blade.php ("Alle Medien") und archiv.blade.php
    ("Archivierte Bilder") - erwartet ein Element aus App\Support\
    MediaLibrary::liste().
--}}
@php $istBild = $item['media_type'] === 'image'; @endphp
<div class="medien-kachel">
    @if ($istBild)
        <img
            class="medien-kachel__bild"
            src="{{ $item['thumb_url'] ?? $item['url'] }}"
            data-fallback="{{ $item['url'] }}"
            onerror="if (this.src !== this.dataset.fallback) { this.src = this.dataset.fallback; }"
            alt=""
            loading="lazy"
        >
    @else
        <div class="medien-kachel__datei-icon" aria-hidden="true">📄</div>
    @endif

    <div class="medien-kachel__name" title="{{ $item['original_name'] }}">{{ $item['original_name'] }}</div>
    <div class="medien-kachel__meta">
        {{ $item['size_human'] }}
        @if ($istBild && $item['width'] && $item['height'])
            &middot; {{ $item['width'] }}×{{ $item['height'] }}
        @endif
    </div>

    <div class="medien-kachel__aktionen">
        <a class="btn btn-outline btn-sm" href="{{ $item['url'] }}" target="_blank" rel="noopener">🔗 Öffnen</a>

        @if ($istBild)
            <form method="POST" action="{{ route('admin.medien.archivieren', $item['dateiname']) }}">
                @csrf
                @method('PUT')
                <button type="submit" class="btn btn-ghost btn-sm">
                    {{ $item['ist_archiviert'] ? '♻️ Wiederherstellen' : '📦 Archivieren' }}
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.medien.loeschen', [$istBild ? 'bild' : 'datei', $item['dateiname']]) }}" onsubmit="return confirm('„' + @js($item['original_name']) + '“ wirklich löschen?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger-outline btn-sm">🗑️ Löschen</button>
        </form>
    </div>
</div>
