{{--
    İşlem geçmişi ayrıntısı: önceki / sonraki değerler yan yana.
    Filament'in derlenmiş CSS'i keyfi Tailwind sınıflarını içermeyebilir;
    düzen bu yüzden satır içi stil ile.
--}}
<div style="display:flex;flex-direction:column;gap:.75rem;font-size:.875rem;">
    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.25rem 1.5rem;margin:0;">
        <div><dt style="opacity:.65;">Tarih</dt><dd style="margin:0;">{{ $kayit->created_at?->format('d.m.Y H:i:s') }}</dd></div>
        <div><dt style="opacity:.65;">Kullanıcı</dt><dd style="margin:0;">{{ $kayit->kullanici?->name ?? 'Sistem' }}</dd></div>
        <div><dt style="opacity:.65;">Olay</dt><dd style="margin:0;">{{ \App\Models\IslemKaydi::OLAYLAR[$kayit->olay] ?? $kayit->olay }}</dd></div>
        <div><dt style="opacity:.65;">IP</dt><dd style="margin:0;">{{ $kayit->ip ?? '—' }}</dd></div>
    </dl>

    @if ($satirlar === [])
        <p style="opacity:.65;margin:0;">Bu kayıtta alan değişikliği yok.</p>
    @else
        <div style="overflow-x:auto;border:1px solid rgba(127,127,127,.3);border-radius:.5rem;">
            <table style="width:100%;border-collapse:collapse;text-align:left;table-layout:fixed;">
                <thead>
                    <tr style="background:rgba(127,127,127,.08);">
                        <th style="width:25%;padding:.5rem .75rem;">Alan</th>
                        <th style="padding:.5rem .75rem;">Önceki</th>
                        <th style="padding:.5rem .75rem;">Sonraki</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($satirlar as $s)
                        <tr style="border-top:1px solid rgba(127,127,127,.2);{{ $s['degisti'] && $kayit->olay === 'guncellendi' ? 'background:rgba(245,158,11,.12);' : '' }}">
                            <td style="padding:.5rem .75rem;vertical-align:top;font-family:monospace;font-size:.75rem;">{{ $s['alan'] }}</td>
                            <td style="padding:.5rem .75rem;vertical-align:top;overflow-wrap:anywhere;">{{ \Illuminate\Support\Str::limit($s['onceki'], 600) }}</td>
                            <td style="padding:.5rem .75rem;vertical-align:top;overflow-wrap:anywhere;">{{ \Illuminate\Support\Str::limit($s['sonraki'], 600) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
