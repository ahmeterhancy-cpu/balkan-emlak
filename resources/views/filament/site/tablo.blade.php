{{--
    Basit, yazdırılabilir tablo. $sutunlar: başlıklar; $satirlar: hücre metinleri
    (biçimlenmiş); $sagaYasli: sağa yaslanacak sütun sıraları; $alt: tek toplam
    satırı ya da $altlar: toplam satırları (para birimine göre ayrı).
    Filament'in derlenmiş CSS'i keyfi Tailwind sınıflarını içermeyebilir; satır içi stil.
--}}
@php
    $sagaYasli = $sagaYasli ?? [];
    $altlar = $altlar ?? (isset($alt) && $alt ? [$alt] : []);
    $bosMetin = $bosMetin ?? 'Kayıt yok.';
    $hiza = fn ($i) => in_array($i, $sagaYasli, true) ? 'right' : 'left';
@endphp
<div style="margin:0 0 1rem 0;">
    @if (! empty($baslik))
        <h3 style="font-weight:600;font-size:.95rem;margin:0 0 .4rem 0;">{{ $baslik }}</h3>
    @endif
    @if (! empty($not))
        <p style="opacity:.75;font-size:.8rem;margin:0 0 .4rem 0;">{{ $not }}</p>
    @endif
    <div style="overflow-x:auto;border:1px solid rgba(127,127,127,.3);border-radius:.5rem;">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
            <thead>
                <tr style="background:rgba(127,127,127,.08);">
                    @foreach ($sutunlar as $i => $s)
                        <th style="padding:.45rem .65rem;text-align:{{ $hiza($i) }};white-space:nowrap;">{{ $s }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($satirlar as $satir)
                    <tr style="border-top:1px solid rgba(127,127,127,.2);">
                        @foreach (array_values($satir) as $i => $h)
                            <td style="padding:.4rem .65rem;vertical-align:top;text-align:{{ $hiza($i) }};{{ $hiza($i) === 'right' ? 'white-space:nowrap;' : '' }}">{{ $h }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($sutunlar) }}" style="padding:.6rem .65rem;opacity:.7;">{{ $bosMetin }}</td></tr>
                @endforelse
            </tbody>
            @if ($altlar)
                <tfoot>
                    @foreach ($altlar as $j => $alt)
                        <tr style="border-top:{{ $j === 0 ? '2px solid rgba(127,127,127,.45)' : '1px solid rgba(127,127,127,.2)' }};font-weight:600;">
                            @foreach (array_values($alt) as $i => $h)
                                <td style="padding:.45rem .65rem;text-align:{{ $hiza($i) }};white-space:nowrap;">{{ $h }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tfoot>
            @endif
        </table>
    </div>
</div>
