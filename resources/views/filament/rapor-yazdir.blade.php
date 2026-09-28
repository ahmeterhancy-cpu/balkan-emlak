<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $rapor['baslik'] }} — {{ $rapor['site'] }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; background: #fff; margin: 0; padding: 24px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #142D3E; padding-bottom: 10px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #142D3E; }
        .alt { font-size: 12px; color: #555; }
        .dugmeler button { font: inherit; padding: 6px 14px; border: 1px solid #142D3E; background: #142D3E; color: #fff; border-radius: 6px; cursor: pointer; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        @media print {
            body { padding: 0; }
            .dugmeler { display: none; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>{{ $rapor['baslik'] }}</h1>
            <div class="alt">{{ ayar('firma_adi', 'Balkan Emlak ve Yönetim') }} · {{ $rapor['site'] }} · Oluşturma: {{ now()->format('d.m.Y H:i') }} · {{ auth()->user()?->name }}</div>
        </div>
        <div class="dugmeler"><button type="button" onclick="window.print()">Yazdır / PDF</button></div>
    </header>
    @include('filament.site.rapor-icerik', ['rapor' => $rapor])
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
