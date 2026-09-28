<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Filament\Resources\BakimPlanlari\BakimPlaniResource;
use App\Filament\Resources\Giderler\GiderResource;
use App\Filament\Resources\IsEmirleri\IsEmriResource;
use App\Models\BakimPlani;
use App\Models\Gider;
use App\Models\IsEmri;
use App\Services\SiteFinans\GiderServisi;
use App\Support\Yetki;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Açık arızalar, 14 gün içindeki bakımlar ve onay bekleyen giderler. */
class SiteOperasyonOzeti extends StatsOverviewWidget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return GostergePaneli::siteGostergeleri() && (Yetki::var('operasyon.yonet') || Yetki::var('finans.goruntule'));
    }

    protected function getHeading(): ?string
    {
        return 'Site operasyonu — '.$this->siteEtiketi();
    }

    protected function getStats(): array
    {
        $idler = $this->siteIdleri();
        $kartlar = [];

        if (Yetki::var('operasyon.yonet')) {
            $acik = IsEmri::whereIn('site_id', $idler)->whereNotIn('durum', ['tamamlandi', 'kapatildi']);
            $kartlar[] = Stat::make('Açık arıza / iş emri', (clone $acik)->count())
                ->description((clone $acik)->whereIn('oncelik', ['acil', 'yuksek'])->count().' acil / yüksek öncelikli')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->url(IsEmriResource::getUrl('index'));

            $bakim = BakimPlani::whereIn('site_id', $idler)->where('aktif', true)->where('sonraki_bakim', '<=', now()->addDays(14)->toDateString());
            $kartlar[] = Stat::make('14 gün içindeki bakımlar', (clone $bakim)->count())
                ->description((clone $bakim)->where('sonraki_bakim', '<', now()->toDateString())->count().' tanesi gecikmiş')
                ->icon('heroicon-o-calendar-days')
                ->url(BakimPlaniResource::getUrl('index'));
        }

        if (Yetki::var('finans.goruntule')) {
            $bekleyen = Gider::whereIn('site_id', $idler)->where('onay_durumu', 'onay_bekliyor')->with('site')->get();
            $limitUstu = $bekleyen->filter(fn (Gider $g) => app(GiderServisi::class)->limitAsiliyorMu($g))->count();
            $kartlar[] = Stat::make('Onay bekleyen gider', $bekleyen->count())
                ->description($limitUstu ? $limitUstu.' tanesi site onay limitini aşıyor; şirket yöneticisi onayı gerekir' : 'Limit aşan yok')
                ->icon('heroicon-o-clipboard-document-check')
                ->color($limitUstu ? 'danger' : 'gray')
                ->url(GiderResource::getUrl('index'));
        }

        return $kartlar;
    }
}
