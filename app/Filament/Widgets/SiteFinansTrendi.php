<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Models\Site;
use App\Services\SiteFinans\SiteRaporu;
use App\Support\Yetki;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

/**
 * Seçili döneme kadar son 6 ay: tahakkuk, ay içi tahsilat (sütun) ve
 * onaylı gider (çizgi). Yalnız TL; dövizli tutarlar özet kartlarında ayrı.
 */
class SiteFinansTrendi extends ChartWidget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    /** Sayfanın üstünde: tembel yüklemede boş kutu görünmesin. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Son 6 ay';

    protected ?string $description = 'Tahakkuk, ay içi tahsilat ve onaylı gider (TL)';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Yetki::var('finans.goruntule') && GostergePaneli::siteGostergeleri();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $servis = app(SiteRaporu::class);
        $siteler = Site::whereKey($this->siteIdleri())->get();
        $son = Carbon::createFromFormat('Y-m-d', $this->donem().'-01');

        $etiketler = $tahakkuk = $tahsilat = $gider = [];
        for ($i = 5; $i >= 0; $i--) {
            $ay = $son->copy()->subMonths($i);
            $etiketler[] = $ay->locale('tr')->translatedFormat('M y');
            $t = $c = $g = 0;
            foreach ($siteler as $site) {
                $o = $servis->donemOzeti($site, $ay->format('Y-m'))['TRY'] ?? null;
                $t += $o['tahakkuk_kurus'] ?? 0;
                $c += $o['aydaki_tahsilat_kurus'] ?? 0;
                $g += $o['onayli_gider_kurus'] ?? 0;
            }
            $tahakkuk[] = round($t / 100);
            $tahsilat[] = round($c / 100);
            $gider[] = round($g / 100);
        }

        return [
            'labels' => $etiketler,
            'datasets' => [
                ['label' => 'Tahakkuk', 'data' => $tahakkuk, 'backgroundColor' => '#142D3E', 'borderRadius' => 4, 'maxBarThickness' => 22, 'order' => 2],
                ['label' => 'Tahsilat', 'data' => $tahsilat, 'backgroundColor' => '#739B16', 'borderRadius' => 4, 'maxBarThickness' => 22, 'order' => 2],
                [
                    'type' => 'line', 'label' => 'Gider', 'data' => $gider, 'order' => 1,
                    'borderColor' => '#C2410C', 'backgroundColor' => '#C2410C', 'borderWidth' => 2,
                    'pointRadius' => 3, 'tension' => 0.3,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                    tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + c.parsed.y.toLocaleString('tr-TR') + ' ₺' } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { callback: (v) => v >= 1000 ? (v / 1000).toLocaleString('tr-TR') + ' bin' : v } },
                },
            }
        JS);
    }
}
