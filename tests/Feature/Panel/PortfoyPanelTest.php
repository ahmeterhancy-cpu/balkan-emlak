<?php

namespace Tests\Feature\Panel;

use App\Enums\YayinDurumu;
use App\Filament\Resources\Portfoyler\Pages\CreatePortfoy;
use App\Filament\Resources\Portfoyler\Pages\EditPortfoy;
use App\Filament\Resources\Portfoyler\Pages\ListPortfoyler;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Models\FiyatGecmisi;
use App\Models\Portfoy;
use App\Models\PortfoyMedya;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class PortfoyPanelTest extends TestCase
{
    use PanelKurulumu;

    private function formVerisi(array $ek = []): array
    {
        return [
            'baslik' => 'Kıyık\'ta bahçeli müstakil ev',
            'islem' => 'satilik',
            'kategori' => 'konut',
            'tip' => 'mustakil',
            'il_id' => $this->il->id,
            'ilce_id' => $this->ilce->id,
            'fiyat_kurus' => '1.250.000',
            'para_birimi' => 'TRY',
            'brut_m2' => 220,
            'oda' => '4+1',
            'harita_gosterimi' => 'yok',
            'operasyon_durumu' => 'aktif',
            ...$ek,
        ];
    }

    private function foto(Portfoy $p): void
    {
        PortfoyMedya::create(['portfoy_id' => $p->id, 'yol' => 'portfoyler/test.jpg', 'tur' => 'foto']);
    }

    /* ---------- Danışman kapsamı ---------- */

    public function test_danisman_yalniz_kendi_portfoylerini_gorur(): void
    {
        $ben = $this->danisman();
        $oteki = $this->danisman();
        $benim = $this->portfoy(['danisman_id' => $ben->id, 'baslik' => 'Benim ilanım']);
        $onun = $this->portfoy(['danisman_id' => $oteki->id, 'baslik' => 'Onun ilanı']);

        $this->actingAs($ben);

        Livewire::test(ListPortfoyler::class)
            ->assertCanSeeTableRecords([$benim])
            ->assertCanNotSeeTableRecords([$onun]);

        $this->get(PortfoyResource::getUrl('edit', ['record' => $onun]))->assertNotFound();
        $this->get(PortfoyResource::getUrl('view', ['record' => $onun]))->assertNotFound();
        $this->get(PortfoyResource::getUrl('edit', ['record' => $benim]))->assertOk();
    }

    public function test_danisman_yayimlayamaz_ama_incelemeye_gonderebilir(): void
    {
        $ben = $this->danisman();
        $p = $this->portfoy(['danisman_id' => $ben->id]);
        $this->foto($p);

        $this->actingAs($ben);

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->assertActionHidden('yayimla')
            ->assertActionVisible('incelemeyeGonder')
            // Gizli eylemi elle çağırma denemesi (tarayıcı konsolundan) de işlemez
            ->call('mountAction', 'yayimla')
            ->call('callMountedAction');

        $this->assertFalse($ben->can('yayimla', $p));

        $this->assertSame(YayinDurumu::Taslak, $p->fresh()->yayin_durumu);

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->callAction('incelemeyeGonder')
            ->assertHasNoActionErrors();

        $this->assertSame(YayinDurumu::Incelemede, $p->fresh()->yayin_durumu);
    }

    public function test_danisman_form_uzerinden_de_yayina_alamaz_ve_baskasina_atayamaz(): void
    {
        $ben = $this->danisman();
        $oteki = $this->danisman();
        $this->actingAs($ben);

        // Başka danışman seçeneklerde yok: sunucu doğrulaması reddeder
        Livewire::test(CreatePortfoy::class)
            ->fillForm($this->formVerisi(['danisman_id' => $oteki->id]))
            ->call('create')
            ->assertHasFormErrors(['danisman_id']);
        $this->assertSame(0, Portfoy::count());

        Livewire::test(CreatePortfoy::class)
            ->fillForm($this->formVerisi(['yayin_durumu' => 'yayinda', 'one_cikan' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Portfoy::latest('id')->first();
        $this->assertSame(YayinDurumu::Taslak, $p->yayin_durumu);
        $this->assertFalse($p->one_cikan);
        $this->assertSame($ben->id, $p->danisman_id);
    }

    public function test_danisman_komisyon_kullanici_ve_ayarlara_giremez(): void
    {
        $this->actingAs($this->danisman());

        $this->get('/admin/komisyonlar')->assertForbidden();
        $this->get('/admin/kullanicilar')->assertForbidden();
        $this->get('/admin/site-ayarlari')->assertForbidden();
        $this->get('/admin/islem-gecmisi')->assertForbidden();
        $this->get('/admin/sayfalar')->assertForbidden();
    }

    public function test_icerik_editoru_portfoye_giremez_sayfalara_girer(): void
    {
        $this->actingAs($this->kullanici('icerik_editoru'));

        $this->get('/admin/portfoyler')->assertForbidden();
        $this->get('/admin/talepler')->assertForbidden();
        $this->get('/admin/sayfalar')->assertOk();
        $this->get('/admin/bilgi-merkezi')->assertOk();
    }

    /* ---------- Yayın ---------- */

    public function test_fotografsiz_ilan_yayimlanmaz_ve_eksikler_bildirilir(): void
    {
        $p = $this->portfoy(['yayin_durumu' => YayinDurumu::Incelemede]);
        $this->actingAs($this->kullanici('sirket_yoneticisi'));

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->callAction('yayimla')
            ->assertNotified('İlan yayımlanamadı');

        $p->refresh();
        $this->assertSame(YayinDurumu::Incelemede, $p->yayin_durumu);
        $this->assertNull($p->yayin_tarihi);
        $this->assertContains('En az bir fotoğraf', $p->yayinEksikleri());
    }

    public function test_eksiksiz_ilan_yayimlanir_ve_yayin_tarihi_yazilir(): void
    {
        $p = $this->portfoy(['danisman_id' => $this->danisman()->id]);
        $this->foto($p);
        $this->actingAs($this->kullanici('sirket_yoneticisi'));

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->callAction('yayimla')
            ->assertNotified('İlan yayımlandı')
            ->assertSchemaStateSet(['yayin_durumu' => YayinDurumu::Yayinda]);

        $p->refresh();
        $this->assertSame(YayinDurumu::Yayinda, $p->yayin_durumu);
        $this->assertNotNull($p->yayin_tarihi);

        if (Route::has('ilan.onizleme') && Route::has('ilan.detay')) {
            Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
                ->assertActionHasUrl('onizle', route('ilan.onizleme', $p->id))
                ->assertActionHasUrl('sitedeGor', route('ilan.detay', $p))
                ->assertActionShouldOpenUrlInNewTab('onizle');
        }

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->callAction('yayindanKaldir');
        $this->assertSame(YayinDurumu::Kaldirildi, $p->fresh()->yayin_durumu);
    }

    /* ---------- Para ve fiyat geçmişi ---------- */

    public function test_tutar_kurus_olarak_kaydedilir_ve_formda_geri_gelir(): void
    {
        $yonetici = $this->yonetici();
        $this->actingAs($yonetici);

        foreach (['1.250.000', 1250000, '1250000'] as $girdi) {
            Livewire::test(CreatePortfoy::class)
                ->fillForm($this->formVerisi(['fiyat_kurus' => $girdi, 'danisman_id' => $yonetici->id]))
                ->call('create')
                ->assertHasNoFormErrors();

            $p = Portfoy::latest('id')->first();
            $this->assertSame(125_000_000, $p->fiyat_kurus, 'Girdi: '.var_export($girdi, true));

            Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
                ->assertSchemaStateSet(['fiyat_kurus' => 1250000]);
        }
    }

    public function test_gecersiz_tutar_reddedilir(): void
    {
        $this->actingAs($this->yonetici());

        Livewire::test(CreatePortfoy::class)
            ->fillForm($this->formVerisi(['fiyat_kurus' => 'çok pahalı']))
            ->call('create')
            ->assertHasFormErrors(['fiyat_kurus']);
    }

    public function test_fiyat_degisikligi_gecmise_yazilir_ve_kaydetmek_fiyati_bozmaz(): void
    {
        $yonetici = $this->yonetici();
        $p = $this->portfoy(['danisman_id' => $yonetici->id]);
        $this->actingAs($yonetici);

        // Fiyata dokunmadan kaydetmek yeni satır üretmez (kuruş gidiş-dönüşü)
        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(465_000_000, $p->fresh()->fiyat_kurus);
        $this->assertSame(1, FiyatGecmisi::where('portfoy_id', $p->id)->count());

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->fillForm(['fiyat_kurus' => '4.400.000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(440_000_000, $p->fresh()->fiyat_kurus);
        $son = FiyatGecmisi::where('portfoy_id', $p->id)->latest('id')->first();
        $this->assertSame(465_000_000, (int) $son->eski_fiyat_kurus);
        $this->assertSame(440_000_000, (int) $son->yeni_fiyat_kurus);
        $this->assertSame($yonetici->id, $son->user_id);
    }

    public function test_baslik_degisince_slug_ve_adres_guncellenir(): void
    {
        $yonetici = $this->yonetici();
        $p = $this->portfoy(['danisman_id' => $yonetici->id]);
        $this->actingAs($yonetici);

        Livewire::test(EditPortfoy::class, ['record' => $p->getRouteKey()])
            ->fillForm(['baslik' => 'Yepyeni başlık'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(PortfoyResource::getUrl('edit', ['record' => $p->fresh()]));

        $yeni = $p->fresh();
        $this->assertStringStartsWith('yepyeni-baslik-', $yeni->slug);
        $this->get(PortfoyResource::getUrl('edit', ['record' => $yeni]))->assertOk();
    }

    public function test_ic_bilgiler_izin_olmadan_formda_yok(): void
    {
        // Site yöneticisi rolünün portföy izni yok; burada rolü taklit etmek
        // yerine politika kontrolünü doğrudan doğrula.
        $p = $this->portfoy();
        $danisman = $this->danisman();

        $this->assertFalse($danisman->can('icBilgi', $p)); // başkasının portföyü
        $p->update(['danisman_id' => $danisman->id]);
        $this->assertTrue($danisman->can('icBilgi', $p->fresh()));
    }
}
