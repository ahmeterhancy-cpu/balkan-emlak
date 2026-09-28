<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\Portfoyler\Pages\EditPortfoy;
use App\Filament\Resources\Portfoyler\RelationManagers\MedyaRelationManager;
use App\Filament\Resources\Yonlendirmeler\Pages\CreateYonlendirme;
use App\Models\Hizmet;
use App\Models\PortfoyMedya;
use App\Models\Sayfa;
use App\Models\Yazi;
use App\Models\Yonlendirme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Şartname 10: eski adres yönlendirmeleri, taslak/önizleme, görsel kullanım hakkı. */
class YonlendirmeOnizlemeGorselTest extends TestCase
{
    use PanelKurulumu;

    /* ---------- Yönlendirme ---------- */

    public function test_eski_adres_yeniye_yonlenir_ve_kullanim_sayilir(): void
    {
        $y = Yonlendirme::create(['eski_yol' => 'https://eskisite.com/ilan.php?id=5', 'hedef' => 'emlak']);

        $this->assertSame('/ilan.php?id=5', $y->eski_yol); // alan adı atıldı, sorgu korundu
        $this->get('/ilan.php?id=5')->assertStatus(301)->assertRedirect('/emlak');
        $this->assertSame(1, $y->fresh()->isabet);
        $this->assertNotNull($y->fresh()->son_isabet);

        // Sorgusu farklı istek bu kayda uymaz
        $this->get('/ilan.php?id=6')->assertNotFound();
    }

    public function test_yol_kaydi_sorgulu_istegi_de_yakalar_ve_302_desteklenir(): void
    {
        Yonlendirme::create(['eski_yol' => '/hakkimizda.html/', 'hedef' => '/hakkimizda', 'kod' => 302]);

        $this->get('/hakkimizda.html?utm=x')->assertStatus(302)->assertRedirect('/hakkimizda');
    }

    public function test_var_olan_sayfa_ve_panel_yonlendirmeyle_ezilemez_pasif_kayit_calismaz(): void
    {
        Yonlendirme::create(['eski_yol' => '/emlak', 'hedef' => '/']);
        Yonlendirme::create(['eski_yol' => '/admin/olmayan', 'hedef' => '/']);
        Yonlendirme::create(['eski_yol' => '/kapali', 'hedef' => '/', 'aktif' => false]);

        $this->get('/emlak')->assertOk();
        $this->get('/admin/olmayan')->assertNotFound(); // kayıt olsa da panel adresi yönlendirilmez
        $this->get('/kapali')->assertNotFound();
    }

    public function test_yayindaki_ilanin_basligi_degisince_eski_adres_yenisine_gider_zincir_ve_dongu_olusmaz(): void
    {
        $p = $this->portfoy(['baslik' => 'Birinci Başlık', 'yayin_durumu' => 'yayinda', 'yayin_tarihi' => now()]);
        $ilk = '/ilan/'.$p->slug;

        $p->update(['baslik' => 'İkinci Başlık']);
        $ikinci = '/ilan/'.$p->slug;
        $p->update(['baslik' => 'Üçüncü Başlık']);
        $ucuncu = '/ilan/'.$p->slug;

        // Zincir yok: ilk adres doğrudan son adrese
        $this->get($ilk)->assertStatus(301)->assertRedirect($ucuncu);
        $this->get($ikinci)->assertStatus(301)->assertRedirect($ucuncu);
        $this->get($ucuncu)->assertOk();

        // Başlığa geri dönüş: döngü oluşmaz, eski adres yeniden sayfanın kendisi
        $p->update(['baslik' => 'Birinci Başlık']);
        $this->get($ilk)->assertOk();
        $this->get($ucuncu)->assertStatus(301)->assertRedirect($ilk);
        $this->assertFalse(Yonlendirme::where('eski_yol', $ilk)->exists());
    }

    public function test_hic_yayimlanmamis_ilan_icin_yonlendirme_acilmaz(): void
    {
        $p = $this->portfoy(['baslik' => 'Taslak', 'yayin_durumu' => 'taslak', 'yayin_tarihi' => null]);
        $p->update(['baslik' => 'Taslak yeni']);

        $this->assertSame(0, Yonlendirme::count());
    }

    public function test_panelde_panel_adresi_ve_dongu_reddedilir_editor_ekrani_acar(): void
    {
        $this->actingAs($this->kullanici('icerik_editoru'));

        $this->get('/admin/yonlendirmeler')->assertOk();

        Livewire::test(CreateYonlendirme::class)
            ->fillForm(['eski_yol' => '/admin/portfoyler', 'hedef' => '/'])
            ->call('create')
            ->assertHasFormErrors(['eski_yol']);

        Livewire::test(CreateYonlendirme::class)
            ->fillForm(['eski_yol' => '/eski', 'hedef' => '/eski/'])
            ->call('create')
            ->assertHasFormErrors(['hedef']);

        Livewire::test(CreateYonlendirme::class)
            ->fillForm(['eski_yol' => '/eski', 'hedef' => '/emlak', 'kod' => 301])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_danisman_yonlendirmeleri_goremez(): void
    {
        $this->actingAs($this->danisman())->get('/admin/yonlendirmeler')->assertForbidden();
    }

    /* ---------- Önizleme ---------- */

    public function test_taslak_sayfa_yazi_ve_hizmet_yetkiliye_onizlenir_ziyaretciye_kapali(): void
    {
        $sayfa = Sayfa::create(['baslik' => 'Taslak Sayfa', 'icerik' => '<p>Taslak içerik ÖNİZ-SAYFA</p>', 'yayinda' => false]);
        $yazi = Yazi::create(['baslik' => 'Taslak Yazı', 'icerik' => '<p>ÖNİZ-YAZI</p>', 'durum' => 'taslak']);
        $hizmet = Hizmet::create(['baslik' => 'Taslak Hizmet', 'ozet' => 'ÖNİZ-HİZMET özeti', 'yayinda' => false]);

        // Ziyaretçi: yayındaki adres 404, önizleme girişe yönlendirir
        $this->get('/sayfa/'.$sayfa->slug)->assertNotFound();
        $this->get(route('onizleme.sayfa', $sayfa->id))->assertRedirect('/admin/login');

        $this->actingAs($this->kullanici('icerik_editoru'));
        $this->get(route('onizleme.sayfa', $sayfa->id))->assertOk()->assertSee('ÖNİZ-SAYFA')->assertSee('Önizleme:')->assertSee('noindex, nofollow', false);
        $this->get(route('onizleme.yazi', $yazi->id))->assertOk()->assertSee('ÖNİZ-YAZI')->assertSee('taslak');
        $this->get(route('onizleme.hizmet', $hizmet->id))->assertOk()->assertSee('ÖNİZ-HİZMET');

        // İçerik yetkisi olmayan panel kullanıcısı
        $this->flushSession();
        $this->actingAs($this->danisman());
        $this->get(route('onizleme.sayfa', $sayfa->id))->assertForbidden();
    }

    public function test_duzenleme_sayfalarinda_onizle_dugmesi_var(): void
    {
        $this->actingAs($this->yonetici());
        $sayfa = Sayfa::create(['baslik' => 'Deneme Sayfası', 'icerik' => '<p>x</p>']);

        $this->get('/admin/sayfalar/'.$sayfa->id.'/edit')->assertOk()->assertSee(route('onizleme.sayfa', $sayfa->id), false);
    }

    /* ---------- Görsel kullanım hakkı ---------- */

    public function test_yuklenen_ilan_fotograflarina_kullanim_hakki_yazilir(): void
    {
        Storage::fake('medya');
        $danisman = $this->danisman();
        $p = $this->portfoy(['danisman_id' => $danisman->id]);
        $this->actingAs($danisman);

        Livewire::test(MedyaRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditPortfoy::class])
            ->callTableAction('yukle', data: [
                'dosyalar' => [UploadedFile::fake()->image('a.jpg', 900, 600), UploadedFile::fake()->image('b.jpg', 900, 600)],
                'tur' => 'foto',
                'gorsel_hakki' => 'mulk_sahibi',
                'gorsel_kaynak' => 'Sahibinin e-posta izni, 28.09.2026',
            ])
            ->assertHasNoTableActionErrors();

        $medya = PortfoyMedya::where('portfoy_id', $p->id)->get();
        $this->assertCount(2, $medya);
        $this->assertSame(['mulk_sahibi'], $medya->pluck('gorsel_hakki')->unique()->values()->all());
        $this->assertSame('Sahibinin e-posta izni, 28.09.2026', $medya->first()->gorsel_kaynak);
    }
}
