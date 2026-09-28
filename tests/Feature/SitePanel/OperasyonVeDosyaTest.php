<?php

namespace Tests\Feature\SitePanel;

use App\Filament\Resources\Gorevler\Pages\ManageGorevler;
use App\Filament\Resources\IsEmirleri\IsEmriResource;
use App\Filament\Resources\IsEmirleri\Pages\CreateIsEmri;
use App\Filament\Resources\IsEmirleri\Pages\ListIsEmirleri;
use App\Filament\Resources\IsEmirleri\Pages\ViewIsEmri;
use App\Filament\Resources\SiteBelgeleri\Pages\ManageSiteBelgeleri;
use App\Models\BagimsizBolum;
use App\Models\Gorev;
use App\Models\Hesap;
use App\Models\IsEmri;
use App\Models\IslemKaydi;
use App\Models\SiteBelgesi;
use App\Models\User;
use App\Services\SiteFinans\TahsilatServisi;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OperasyonVeDosyaTest extends TestCase
{
    use SitePanelKurulumu;

    public function test_saha_personeli_yalniz_kendi_is_emirlerini_gorur_tamamlar_ama_kapatamaz(): void
    {
        $saha = $this->giris('saha');
        $benim = IsEmri::where('atanan_id', $saha->id)->where('durum', 'atandi')->firstOrFail();
        $atanmamis = IsEmri::whereNull('atanan_id')->where('durum', 'acik')->firstOrFail();

        Livewire::test(ListIsEmirleri::class)
            ->assertCanSeeTableRecords([$benim])
            ->assertCanNotSeeTableRecords([$atanmamis])
            ->callTableAction('tamamla', $benim, ['yapilan_islem' => 'Tavan kurutuldu, üst kattaki boru contası değişti.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('tamamlandi', $benim->refresh()->durum);

        $this->get(IsEmriResource::getUrl('view', ['record' => $atanmamis]))->assertNotFound();
        $this->get(IsEmriResource::getUrl('edit', ['record' => $benim]))->assertForbidden();

        // Kapatma yöneticide: saha personelinde gizli, elle çağrı da işlemez
        Livewire::test(ViewIsEmri::class, ['record' => $benim->getRouteKey()])
            ->assertActionHidden('kapat')
            ->call('mountAction', 'kapat')
            ->call('callMountedAction');
        $this->assertSame('tamamlandi', $benim->refresh()->durum);

        // Görevler: yalnız kendi görevleri, tamamlayabilir
        $gorev = Gorev::where('user_id', $saha->id)->where('durum', 'bekliyor')->firstOrFail();
        Livewire::test(ManageGorevler::class)
            ->assertCanSeeTableRecords([$gorev])
            ->callTableAction('tamamla', $gorev, ['not' => 'Yapıldı']);
        $this->assertSame('tamamlandi', $gorev->refresh()->durum);

        // Site yöneticisi kapatır
        $this->giris('siteyonetici');
        Livewire::test(ViewIsEmri::class, ['record' => $benim->getRouteKey()])
            ->assertActionVisible('kapat')
            ->callAction('kapat');
        $this->assertSame('kapatildi', $benim->refresh()->durum);
    }

    public function test_dekont_indirme_yetkili_ve_yasak(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dekontlar/'.$this->yildiz->id.'/dekont.pdf', '%PDF-1.4 demo');

        $this->giris('muhasebe');
        $bolum = BagimsizBolum::where('site_id', $this->yildiz->id)->first();
        $t = app(TahsilatServisi::class)->kaydet([
            'hesap_id' => Hesap::where('site_id', $this->yildiz->id)->value('id'), 'bagimsiz_bolum_id' => $bolum->id,
            'tarih' => '2026-09-27', 'tutar_kurus' => 50_000, 'yontem' => 'havale',
            'belge_yolu' => 'dekontlar/'.$this->yildiz->id.'/dekont.pdf', 'dogrulandi' => false,
        ]);

        $this->get(route('panel.dekont.indir', $t))->assertOk()->assertDownload();
        $this->assertTrue(IslemKaydi::where('olay', 'belge_indirildi')->where('kayit_turu', 'Tahsilat')->where('kayit_id', $t->id)->exists());

        $this->giris('siteyonetici'); // yalnız Çamlık
        $this->get(route('panel.dekont.indir', $t))->assertForbidden();

        $this->giris('saha');
        $this->get(route('panel.dekont.indir', $t))->assertForbidden();
    }

    public function test_site_belgesi_yukleme_surum_ve_indirme_yetkisi(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('site-belgeleri/'.$this->yildiz->id.'/plan-v1.pdf', 'v1');
        Storage::disk('local')->put('site-belgeleri/'.$this->yildiz->id.'/plan-v2.pdf', 'v2');

        $this->giris('mudur');
        $v1 = SiteBelgesi::create(['site_id' => $this->yildiz->id, 'kategori' => 'yonetim_plani', 'ad' => 'Yönetim planı',
            'yol' => 'site-belgeleri/'.$this->yildiz->id.'/plan-v1.pdf', 'orijinal_ad' => 'plan.pdf']);
        $v2 = $v1->yeniSurum('site-belgeleri/'.$this->yildiz->id.'/plan-v2.pdf', 'plan.pdf');
        $this->assertSame(2, $v2->surum);

        // Listede yalnız son sürüm
        Livewire::test(ManageSiteBelgeleri::class)
            ->assertCanSeeTableRecords([$v2])
            ->assertCanNotSeeTableRecords([$v1]);

        $this->get(route('panel.site-belgesi.indir', $v1))->assertOk()->assertDownload('plan.pdf');
        $this->assertTrue(IslemKaydi::where('olay', 'belge_indirildi')->where('kayit_turu', 'SiteBelgesi')->exists());

        $this->giris('siteyonetici');
        $this->get(route('panel.site-belgesi.indir', $v2))->assertForbidden();

        $this->giris('emrullah');
        $this->get(route('panel.site-belgesi.indir', $v2))->assertForbidden();
    }

    public function test_is_emri_olusturmada_baska_sitenin_personeli_atanamaz(): void
    {
        $this->giris('siteyonetici');
        $baskaSahaci = User::factory()->create(['rol' => 'saha_personeli']);
        $this->yildiz->kullanicilar()->attach($baskaSahaci->id);

        Livewire::test(CreateIsEmri::class)
            ->fillForm(['site_id' => $this->camlik->id, 'konu' => 'Deneme', 'oncelik' => 'normal', 'durum' => 'acik', 'atanan_id' => $baskaSahaci->id])
            ->call('create')
            ->assertHasFormErrors(['atanan_id']);

        $this->assertFalse(IsEmri::where('konu', 'Deneme')->exists());
    }
}
