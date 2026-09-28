{{-- Borçlandırma önizlemesi (Borclandirici::onizle); hiçbir kayıt yazılmaz. --}}
@if ($hata)
    <div style="padding:.75rem 1rem;border-radius:.5rem;background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.35);">
        <strong>Önizleme hesaplanamadı:</strong> {{ $hata }}
    </div>
@else
    <div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin:0 0 .75rem 0;font-size:.9rem;">
        <div><span style="opacity:.7;">Bölüm sayısı:</span> <strong>{{ $adet }}</strong></div>
        <div><span style="opacity:.7;">Toplam:</span> <strong>{{ \App\Support\Para::bicimle($toplam, $pb) }}</strong></div>
        @if ($uyari > 0)
            <div style="color:rgb(217,119,6);"><strong>{{ $uyari }}</strong> bölümde uyarı var</div>
        @endif
    </div>
    @include('filament.site.tablo', [
        'sutunlar' => ['Bölüm', 'Borç sorumlusu', 'Tutar', 'Uyarı'],
        'sagaYasli' => [2],
        'satirlar' => $satirlar,
        'alt' => [$adet.' bölüm', '', \App\Support\Para::bicimle($toplam, $pb), ''],
        'bosMetin' => 'Borçlandırılacak bölüm yok.',
    ])
@endif
