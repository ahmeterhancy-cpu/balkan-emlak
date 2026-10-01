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
use Filament\Widgets\Widget;

/** Açık arızalar, 14 gün içindeki bakımlar ve onay bekleyen giderler; dikkat isteyenler vurgulanır. */
class SiteOperasyonOzeti extends Widget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    /** Sayfanın üstünde: tembel yüklemede boş kutu görünmesin. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.site-operasyon-ozeti';

    public static function canView(): bool
    {
        return GostergePaneli::siteGostergeleri() && (Yetki::var('operasyon.yonet') || Yetki::var('finans.goruntule'));
    }

    protected function getViewData(): array
    {
        $idler = $this->siteIdleri();
        $satirlar = [];

        if (Yetki::var('operasyon.yonet')) {
            $acik = IsEmri::whereIn('site_id', $idler)->whereNotIn('durum', ['tamamlandi', 'kapatildi']);
            $acil = (clone $acik)->whereIn('oncelik', ['acil', 'yuksek'])->count();
            $satirlar[] = [
                'etiket' => 'Açık arıza / iş emri', 'sayi' => (clone $acik)->count(),
                'alt' => $acil ? $acil.' acil / yüksek öncelikli' : 'Acil iş yok',
                'uyari' => $acil > 0, 'simge' => 'heroicon-o-wrench-screwdriver', 'url' => IsEmriResource::getUrl('index'),
            ];

            $bakim = BakimPlani::whereIn('site_id', $idler)->where('aktif', true)->where('sonraki_bakim', '<=', now()->addDays(14)->toDateString());
            $geciken = (clone $bakim)->where('sonraki_bakim', '<', now()->toDateString())->count();
            $satirlar[] = [
                'etiket' => '14 gün içindeki bakımlar', 'sayi' => (clone $bakim)->count(),
                'alt' => $geciken ? $geciken.' tanesi gecikmiş' : 'Geciken bakım yok',
                'uyari' => $geciken > 0, 'simge' => 'heroicon-o-calendar-days', 'url' => BakimPlaniResource::getUrl('index'),
            ];
        }

        if (Yetki::var('finans.goruntule')) {
            $bekleyen = Gider::whereIn('site_id', $idler)->where('onay_durumu', 'onay_bekliyor')->with('site')->get();
            $limitUstu = $bekleyen->filter(fn (Gider $g) => app(GiderServisi::class)->limitAsiliyorMu($g))->count();
            $satirlar[] = [
                'etiket' => 'Onay bekleyen gider', 'sayi' => $bekleyen->count(),
                'alt' => $limitUstu ? $limitUstu.' tanesi site onay limitini aşıyor; şirket yöneticisi onayı gerekir' : 'Limit aşan yok',
                'uyari' => $limitUstu > 0, 'simge' => 'heroicon-o-clipboard-document-check', 'url' => GiderResource::getUrl('index'),
            ];
        }

        return ['satirlar' => $satirlar, 'baslik' => $this->siteEtiketi()];
    }
}
