{{-- Site raporu gövdesi (ekran ve yazdırma ortak). $rapor: SiteRaporlari yapısı. --}}
<div style="display:flex;flex-direction:column;gap:.75rem;">
    @if (! empty($rapor['filtreler']))
        <dl style="display:flex;flex-wrap:wrap;gap:.25rem 1.5rem;margin:0;font-size:.85rem;">
            @foreach ($rapor['filtreler'] as $etiket => $deger)
                <div><dt style="display:inline;opacity:.7;">{{ $etiket }}:</dt> <dd style="display:inline;margin:0;font-weight:600;">{{ $deger }}</dd></div>
            @endforeach
        </dl>
    @endif
    @foreach ($rapor['notlar'] as $not)
        <p style="margin:0;padding:.5rem .75rem;border-radius:.5rem;background:rgba(127,127,127,.08);font-size:.8rem;">{{ $not }}</p>
    @endforeach
    @foreach ($rapor['tablolar'] as $t)
        @include('filament.site.tablo', [
            'baslik' => $t['baslik'] ?? null,
            'not' => $t['not'] ?? null,
            'sutunlar' => $t['sutunlar'],
            'sagaYasli' => $t['sagaYasli'] ?? [],
            'satirlar' => array_map(fn ($s) => array_map([\App\Filament\Destek\SiteRaporlari::class, 'metin'], array_values($s)), $t['satirlar']),
            'altlar' => array_map(fn ($s) => array_map([\App\Filament\Destek\SiteRaporlari::class, 'metin'], array_values($s)), $t['altlar'] ?? []),
        ])
    @endforeach
</div>
