<?php

namespace App\Filament\Resources\Komisyonlar\Widgets;

use App\Enums\KomisyonDurumu;
use App\Models\Komisyon;
use App\Support\Para;
use App\Support\Yetki;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Para birimine göre ayrı toplamlar. Farklı birimler asla tek sayıda
 * toplanmaz (şartname 11, 17). İptal edilen kayıtlar dahil değildir.
 */
class KomisyonToplamlari extends StatsOverviewWidget
{
    protected ?string $heading = 'Para birimine göre toplamlar (iptaller hariç)';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Yetki::var('komisyon.goruntule');
    }

    public static function gruplar(): array
    {
        return Komisyon::query()
            ->where('durum', '!=', KomisyonDurumu::Iptal->value)
            ->selectRaw('para_birimi, SUM(beklenen_kurus) as beklenen, SUM(COALESCE(gerceklesen_kurus, 0)) as gerceklesen, COUNT(*) as adet')
            ->groupBy('para_birimi')
            ->orderBy('para_birimi')
            ->get()
            ->map(fn ($s) => [
                'para_birimi' => $s->para_birimi,
                'beklenen' => (int) $s->beklenen,
                'gerceklesen' => (int) $s->gerceklesen,
                'adet' => (int) $s->adet,
            ])
            ->all();
    }

    protected function getStats(): array
    {
        $kartlar = [];

        foreach (static::gruplar() as $g) {
            $kartlar[] = Stat::make('Beklenen · '.$g['para_birimi'], Para::bicimle($g['beklenen'], $g['para_birimi']))
                ->description($g['adet'].' kayıt');
            $kartlar[] = Stat::make('Gerçekleşen · '.$g['para_birimi'], Para::bicimle($g['gerceklesen'], $g['para_birimi']))
                ->description('Kalan: '.Para::bicimle(max(0, $g['beklenen'] - $g['gerceklesen']), $g['para_birimi']))
                ->color('success');
        }

        return $kartlar ?: [Stat::make('Komisyon kaydı', '0')->description('Henüz kayıt yok')];
    }
}
