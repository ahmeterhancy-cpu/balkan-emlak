{{-- İş emri fotoğrafları: özel diskten, yetki denetimli rota üzerinden. --}}
@foreach (['fotograflar' => 'Bildirim fotoğrafları', 'tamamlanma_fotograflari' => 'Tamamlanma kanıtı'] as $alan => $etiket)
    @if (! empty($kayit->{$alan}))
        <div style="margin:0 0 .75rem 0;">
            <div style="font-weight:600;font-size:.85rem;margin:0 0 .4rem 0;">{{ $etiket }}</div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                @foreach (array_values((array) $kayit->{$alan}) as $i => $yol)
                    <a href="{{ route('panel.is-emri.fotograf', [$kayit, $alan, $i]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('panel.is-emri.fotograf', [$kayit, $alan, $i]) }}" alt="{{ $etiket }} {{ $i + 1 }}" loading="lazy"
                             style="width:120px;height:90px;object-fit:cover;border-radius:.4rem;border:1px solid rgba(127,127,127,.3);">
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
