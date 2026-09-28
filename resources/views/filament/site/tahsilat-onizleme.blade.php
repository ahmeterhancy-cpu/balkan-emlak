{{-- Tahsilat dağıtım önizlemesi (TahsilatServisi::onizle). Kayıttan önce gösterilir; hiçbir şey yazmaz. --}}
<div style="font-size:.875rem;">
    <div style="font-weight:600;margin:0 0 .4rem 0;">Dağıtım önizlemesi</div>
    @if ($durum !== 'tamam')
        <p style="opacity:.7;margin:0;">{{ $mesaj }}</p>
    @else
        @if (! empty($dogrulanmadi))
            <div style="padding:.5rem .75rem;margin:0 0 .6rem 0;border-radius:.5rem;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);">
                Dekont doğrulanmadı: kayıt "doğrulama bekliyor" olarak açılır; aşağıdaki dağıtım doğrulandığında uygulanır (o günkü açık borçlara göre).
            </div>
        @endif
        @include('filament.site.tablo', [
            'sutunlar' => ['Borç', 'Vade', 'Şu anki kalan', 'Bu ödemeden', 'Sonra kalan'],
            'sagaYasli' => [2, 3, 4],
            'satirlar' => $satirlar,
            'bosMetin' => 'Bu bölümün bu para biriminde açık borcu yok; tutarın tamamı avans olarak kalır.',
            'alt' => ['Toplam ödeme '.$toplam, '', '', '', ''],
        ])
        <div style="padding:.5rem .75rem;border-radius:.5rem;background:rgba(127,127,127,.08);">
            Dağıtılmayan (avans): <strong>{{ $avans }}</strong>
            @if ($avans_kurus > 0)
                <span style="opacity:.75;"> — gelir sayılmaz; bölümün yeni borçlarına mahsup edilir.</span>
            @endif
        </div>
    @endif
</div>
