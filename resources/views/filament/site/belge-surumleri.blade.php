{{-- Site belgesinin tüm sürümleri; her indirme yetki denetimli rotadan. --}}
<div style="overflow-x:auto;border:1px solid rgba(127,127,127,.3);border-radius:.5rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="background:rgba(127,127,127,.08);text-align:left;">
                <th style="padding:.45rem .65rem;">Sürüm</th>
                <th style="padding:.45rem .65rem;">Dosya</th>
                <th style="padding:.45rem .65rem;">Yükleyen</th>
                <th style="padding:.45rem .65rem;">Tarih</th>
                <th style="padding:.45rem .65rem;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($surumler as $s)
                <tr style="border-top:1px solid rgba(127,127,127,.2);">
                    <td style="padding:.4rem .65rem;">v{{ $s->surum }}@if ($loop->first) <span style="opacity:.7;">(güncel)</span>@endif</td>
                    <td style="padding:.4rem .65rem;overflow-wrap:anywhere;">{{ $s->orijinal_ad ?: basename($s->yol) }}</td>
                    <td style="padding:.4rem .65rem;">{{ $s->yukleyen?->name ?? '—' }}</td>
                    <td style="padding:.4rem .65rem;white-space:nowrap;">{{ $s->created_at?->format('d.m.Y H:i') }}</td>
                    <td style="padding:.4rem .65rem;text-align:right;">
                        @can('view', $s)
                            <a href="{{ route('panel.site-belgesi.indir', $s) }}" target="_blank" rel="noopener" style="text-decoration:underline;">İndir</a>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
