<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Referanslar\ReferansResource;
use App\Filament\Resources\Sayfalar\SayfaResource;
use App\Filament\Resources\Yazilar\YaziResource;
use App\Models\Portfoy;
use App\Models\Referans;
use App\Models\Sayfa;
use App\Models\Yazi;
use App\Support\Yetki;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/** İçerik editörünün gösterge paneli: emlak verisi yok, yalnız web içerikleri. */
class IcerikOzeti extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Web içerikleri';

    public static function canView(): bool
    {
        return Yetki::var('icerik.yonet') && ! Gate::allows('viewAny', Portfoy::class);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Taslak yazılar', Yazi::where('durum', 'taslak')->count())
                ->description('Bilgi merkezi')
                ->url(YaziResource::getUrl('index')),
            Stat::make('Yayındaki yazılar', Yazi::where('durum', 'yayinda')->count())
                ->url(YaziResource::getUrl('index')),
            Stat::make('Hukuki inceleme bekleyen sayfa', Sayfa::where('inceleme_bekliyor', true)->count())
                ->color('warning')
                ->url(SayfaResource::getUrl('index')),
            Stat::make('İzni alınmamış referans', Referans::where('izin_alindi', false)->count())
                ->description('Yayına alınamaz')
                ->url(ReferansResource::getUrl('index')),
        ];
    }
}
