<?php

namespace Tests\Feature\SitePanel;

use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use App\Filament\Resources\Giderler\GiderResource;
use App\Filament\Resources\Hesaplar\HesapResource;
use App\Filament\Resources\Siteler\SiteResource;
use App\Models\BagimsizBolum;
use App\Models\Borclandirma;
use App\Models\Gider;
use App\Models\Hesap;
use Tests\TestCase;

/** Tüm site yönetimi sayfaları demo veride sistem yöneticisi için açılır. */
class SayfaAcilisTest extends TestCase
{
    use SitePanelKurulumu;

    public function test_sistem_yoneticisi_tum_site_sayfalarini_acar(): void
    {
        $this->giris('yonetici');

        foreach (['/admin/siteler', '/admin/siteler/create', '/admin/bagimsiz-bolumler', '/admin/bagimsiz-bolumler/create',
            '/admin/borclandirmalar', '/admin/borclandirmalar/create', '/admin/borclar', '/admin/tahsilatlar', '/admin/tahsilatlar/create',
            '/admin/giderler', '/admin/giderler/create', '/admin/tedarikciler', '/admin/hesaplar', '/admin/hesaplar/create', '/admin/transferler', '/admin/is-emirleri', '/admin/is-emirleri/create', '/admin/varliklar', '/admin/bakim-planlari',
            '/admin/gorevler', '/admin/duyurular', '/admin/site-belgeleri',
            '/admin/site-raporlari/donem-ozeti', '/admin/site-raporlari/bolum-bakiyeleri', '/admin/site-raporlari/bolum-ekstresi',
            '/admin/site-raporlari/gider-ozeti', '/admin/site-raporlari/hesap-dokumu', '/admin/site-raporlari/operasyon-raporu',
        ] as $yol) {
            $this->get($yol)->assertOk();
        }

        $this->get(SiteResource::getUrl('view', ['record' => $this->camlik]))->assertOk();
        $this->get(SiteResource::getUrl('edit', ['record' => $this->camlik]))->assertOk();
        $bolum = BagimsizBolum::where('site_id', $this->camlik->id)->first();
        $this->get(BagimsizBolumResource::getUrl('view', ['record' => $bolum]))->assertOk();
        $this->get(BagimsizBolumResource::getUrl('edit', ['record' => $bolum]))->assertOk();
        $this->get(BorclandirmaResource::getUrl('view', ['record' => Borclandirma::first()]))->assertOk();
        foreach (Gider::all() as $g) {
            $this->get(GiderResource::getUrl('view', ['record' => $g]))->assertOk();
        }
        $this->get(HesapResource::getUrl('view', ['record' => Hesap::first()]))->assertOk();
        $this->get('/admin/tahsilatlar/create?bolum='.$bolum->id)->assertOk();
    }
}
