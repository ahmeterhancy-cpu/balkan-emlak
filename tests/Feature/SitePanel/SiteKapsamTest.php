<?php

namespace Tests\Feature\SitePanel;

use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use App\Filament\Resources\Giderler\GiderResource;
use App\Filament\Resources\Giderler\Pages\ListGiderler;
use App\Filament\Resources\Hesaplar\HesapResource;
use App\Filament\Resources\Siteler\Pages\ListSiteler;
use App\Filament\Resources\Siteler\SiteResource;
use App\Filament\Resources\Tahsilatlar\Pages\ListTahsilatlar;
use App\Filament\Widgets\BenimIsEmirlerim;
use App\Filament\Widgets\BugunkuGorevlerim;
use App\Filament\Widgets\OnayBekleyenGiderler;
use App\Filament\Widgets\SiteFinansOzeti;
use App\Filament\Widgets\SiteOperasyonOzeti;
use App\Models\BagimsizBolum;
use App\Models\Borclandirma;
use App\Models\Gider;
use App\Models\Hesap;
use App\Models\Tahsilat;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/** Site kapsamı: başka sitenin kaydı listede görünmez, adresle de açılmaz (şartname 9, 17). */
class SiteKapsamTest extends TestCase
{
    use SitePanelKurulumu;

    public function test_site_yoneticisi_baska_sitenin_kayitlarini_adresle_acamaz(): void
    {
        $this->giris('siteyonetici');

        $this->get(SiteResource::getUrl('view', ['record' => $this->camlik]))->assertOk();
        $this->get(SiteResource::getUrl('view', ['record' => $this->yildiz]))->assertNotFound();
        $this->get(SiteResource::getUrl('edit', ['record' => $this->yildiz]))->assertNotFound();

        $bolum = BagimsizBolum::where('site_id', $this->yildiz->id)->first();
        $this->get(BagimsizBolumResource::getUrl('view', ['record' => $bolum]))->assertNotFound();
        $this->get(GiderResource::getUrl('view', ['record' => Gider::where('site_id', $this->yildiz->id)->first()]))->assertNotFound();
        $this->get(HesapResource::getUrl('view', ['record' => Hesap::where('site_id', $this->yildiz->id)->first()]))->assertNotFound();
        $this->get(BorclandirmaResource::getUrl('view', ['record' => Borclandirma::where('site_id', $this->yildiz->id)->first()]))->assertNotFound();

        // Yazdırma rotası da siteyi yeniden denetler
        $this->get(route('panel.site-rapor.yazdir', ['rapor' => 'donem-ozeti', 'site_id' => $this->yildiz->id, 'donem' => '2026-09']))->assertForbidden();
        $this->get(route('panel.site-rapor.yazdir', ['rapor' => 'donem-ozeti', 'site_id' => $this->camlik->id, 'donem' => '2026-09']))->assertOk();
    }

    public function test_listelerde_yalniz_yetkili_sitenin_satirlari_gorunur(): void
    {
        $this->giris('siteyonetici');

        Livewire::test(ListSiteler::class)
            ->assertCanSeeTableRecords([$this->camlik])
            ->assertCanNotSeeTableRecords([$this->yildiz]);

        Livewire::test(ListGiderler::class)
            ->assertCanSeeTableRecords(Gider::where('site_id', $this->camlik->id)->get())
            ->assertCanNotSeeTableRecords(Gider::where('site_id', $this->yildiz->id)->get());

        Livewire::test(ListTahsilatlar::class)
            ->assertCanNotSeeTableRecords(Tahsilat::where('site_id', $this->yildiz->id)->get());
    }

    public function test_emlak_danismani_hicbir_site_sayfasini_acamaz(): void
    {
        $this->giris('emrullah');

        foreach (['/admin/siteler', '/admin/bagimsiz-bolumler', '/admin/borclandirmalar', '/admin/borclar', '/admin/tahsilatlar',
            '/admin/tahsilatlar/create', '/admin/giderler', '/admin/tedarikciler', '/admin/hesaplar', '/admin/transferler',
            '/admin/is-emirleri', '/admin/varliklar', '/admin/bakim-planlari', '/admin/gorevler', '/admin/duyurular', '/admin/site-belgeleri',
            '/admin/site-raporlari/donem-ozeti', '/admin/site-raporlari/bolum-bakiyeleri', '/admin/site-raporlari/bolum-ekstresi',
            '/admin/site-raporlari/gider-ozeti', '/admin/site-raporlari/hesap-dokumu', '/admin/site-raporlari/operasyon-raporu',
        ] as $yol) {
            $this->get($yol)->assertForbidden();
        }

        // Kayıt adresi: kapsam sorgusu boş döner (404); politika da reddeder
        $this->get(SiteResource::getUrl('view', ['record' => $this->camlik]))->assertNotFound();
        $this->assertFalse(auth()->user()->can('view', $this->camlik));
        $this->get(route('panel.site-rapor.yazdir', ['rapor' => 'donem-ozeti', 'site_id' => $this->camlik->id]))->assertForbidden();
        $this->get('/admin')->assertOk();
        $this->assertFalse(SiteFinansOzeti::canView());
    }

    public function test_rollerin_gosterge_paneli_ve_site_widgetlari(): void
    {
        $this->giris('mudur');
        $this->get('/admin')->assertOk();
        $this->assertTrue(SiteFinansOzeti::canView());
        $this->assertTrue(OnayBekleyenGiderler::canView());
        Livewire::test(SiteFinansOzeti::class, ['pageFilters' => ['site_id' => $this->camlik->id, 'donem' => '2026-09']])
            ->assertSee('Tahsilat oranı · TRY')->assertSee('36.000 ₺');
        Livewire::test(OnayBekleyenGiderler::class, ['pageFilters' => []])
            ->assertSee('Sonbahar peyzaj düzenlemesi');

        $this->giris('siteyonetici');
        $this->get('/admin')->assertOk();
        Livewire::test(OnayBekleyenGiderler::class, ['pageFilters' => []])
            ->assertSee('şirket yöneticisi onayı gerekir')
            ->assertDontSee('Yıldız Apartmanı (Demo)');
        // Başka siteyi süzgeçle seçmek kapsamı genişletmez
        Livewire::test(SiteFinansOzeti::class, ['pageFilters' => ['site_id' => $this->yildiz->id]])
            ->assertSee('Çamlık Sitesi (Demo)');

        $this->giris('muhasebe');
        $this->get('/admin')->assertOk();
        Livewire::test(SiteOperasyonOzeti::class, ['pageFilters' => []])->assertSee('Onay bekleyen gider');

        $this->giris('saha');
        $this->get('/admin')->assertOk();
        $this->assertFalse(SiteFinansOzeti::canView());
        $this->assertTrue(BenimIsEmirlerim::canView());
        Livewire::test(BenimIsEmirlerim::class)->assertSee('Banyo tavanında su sızıntısı')->assertDontSee('B Blok giriş lambası');
        Livewire::test(BugunkuGorevlerim::class)->assertSee('Ortak alan temizlik kontrolü')->assertDontSee('Bahçe sulama sistemi');
        $this->get('/admin/giderler')->assertForbidden();
        $this->get('/admin/is-emirleri')->assertOk();
    }

    public function test_site_yoneticisi_kullanici_atamasini_ve_onay_limitini_degistiremez(): void
    {
        $k = $this->giris('siteyonetici');

        $this->assertFalse($k->can('yetkiDuzenle', $this->camlik));
        $this->assertTrue($k->can('update', $this->camlik));
        $this->assertTrue(User::where('email', 'mudur@balkanemlak.test')->first()->can('yetkiDuzenle', $this->camlik));
    }
}
