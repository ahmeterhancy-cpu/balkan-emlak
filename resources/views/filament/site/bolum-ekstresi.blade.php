{{--
    Bağımsız bölüm hesap ekstresi (SiteRaporu::bolumEkstresi). Para birimine
    göre ayrı tablolar. Filament'in derlenmiş CSS'i keyfi Tailwind sınıflarını
    içermeyebilir; düzen bu yüzden satır içi stille.
--}}
@php
    $gruplar = collect($satirlar)->groupBy('para_birimi');
@endphp
@if ($gruplar->isEmpty())
    <p style="opacity:.7;margin:0;">Bu bölüm için henüz borç veya tahsilat kaydı yok.</p>
@else
    @foreach ($gruplar as $pb => $liste)
        @include('filament.site.tablo', [
            'baslik' => $gruplar->count() > 1 ? 'Para birimi: '.$pb : null,
            'sutunlar' => ['Tarih', 'Tür', 'Açıklama', 'Borç', 'Alacak', 'Bakiye'],
            'sagaYasli' => [3, 4, 5],
            'satirlar' => $liste->map(fn ($s) => [
                \Illuminate\Support\Carbon::parse($s['tarih'])->format('d.m.Y'),
                $s['tur'],
                $s['aciklama'],
                $s['borc_kurus'] ? \App\Support\Para::bicimle($s['borc_kurus'], $pb) : '',
                $s['alacak_kurus'] ? \App\Support\Para::bicimle($s['alacak_kurus'], $pb) : '',
                \App\Support\Para::bicimle($s['bakiye_kurus'], $pb).($s['bakiye_kurus'] < 0 ? ' (avans)' : ''),
            ])->all(),
            'alt' => ['', '', 'Toplam', \App\Support\Para::bicimle($liste->sum('borc_kurus'), $pb), \App\Support\Para::bicimle($liste->sum('alacak_kurus'), $pb), \App\Support\Para::bicimle((int) $liste->last()['bakiye_kurus'], $pb)],
        ])
    @endforeach
@endif
