<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\SiteAyarlari;
use App\Filament\Resources\Hizmetler\HizmetResource;
use App\Filament\Resources\Hizmetler\Pages\EditHizmet;
use App\Filament\Resources\IslemKayitlari\IslemKaydiResource;
use App\Filament\Resources\IslemKayitlari\Pages\ListIslemKayitlari;
use App\Filament\Resources\Kullanicilar\Pages\CreateUser;
use App\Filament\Resources\Kullanicilar\Pages\EditUser;
use App\Filament\Resources\Referanslar\Pages\CreateReferans;
use App\Filament\Resources\Yazilar\Pages\CreateYazi;
use App\Filament\Resources\Yazilar\Pages\EditYazi;
use App\Filament\Resources\Yazilar\YaziResource;
use App\Models\Ayar;
use App\Models\Hizmet;
use App\Models\IslemKaydi;
use App\Models\Referans;
use App\Models\User;
use App\Models\Yazi;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class IcerikVeSistemTest extends TestCase
{
    use PanelKurulumu;

    public function test_referans_izin_alinmadan_yayina_alinamaz(): void
    {
        $this->actingAs($this->kullanici('icerik_editoru'));

        Livewire::test(CreateReferans::class)
            ->fillForm(['tur' => 'yorum', 'ad' => 'Ayşe Hanım', 'yayinda' => true, 'izin_alindi' => false])
            ->call('create')
            ->assertHasFormErrors(['yayinda']);
        $this->assertSame(0, Referans::count());

        Livewire::test(CreateReferans::class)
            ->fillForm(['tur' => 'yorum', 'ad' => 'Ayşe Hanım', 'yayinda' => true, 'izin_alindi' => true, 'izin_tarihi' => '2026-09-01'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertTrue(Referans::firstOrFail()->yayinda);
    }

    public function test_yazi_yazari_varsayilan_kullanici_ve_slug_degisince_adres_calisir(): void
    {
        $editor = $this->kullanici('icerik_editoru');
        $this->actingAs($editor);

        Livewire::test(CreateYazi::class)
            ->fillForm(['baslik' => 'Aidat nasıl hesaplanır?', 'icerik' => '<p>Metin</p>', 'kategori' => 'yonetim', 'durum' => 'yayinda'])
            ->call('create')
            ->assertHasNoFormErrors();

        $yazi = Yazi::firstOrFail();
        $this->assertSame($editor->id, $yazi->yazar_id);
        $this->assertNotNull($yazi->yayin_tarihi);
        $this->get(YaziResource::getUrl('edit', ['record' => $yazi]))->assertOk();

        Livewire::test(EditYazi::class, ['record' => $yazi->getRouteKey()])
            ->fillForm(['baslik' => 'Aidat rehberi', 'slug' => null])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(YaziResource::getUrl('edit', ['record' => $yazi->fresh()]));
        $this->assertSame('aidat-rehberi', $yazi->fresh()->slug);
    }

    public function test_hizmet_sureci_liste_olarak_kalir(): void
    {
        $this->actingAs($this->kullanici('icerik_editoru'));
        $h = Hizmet::create(['baslik' => 'Temizlik', 'ozet' => 'Ortak alan temizliği', 'surec' => ['Keşif', 'Plan']]);

        Livewire::test(EditHizmet::class, ['record' => $h->getRouteKey()])
            ->assertSchemaStateSet(['surec' => ['Keşif', 'Plan']])
            ->fillForm(['surec' => ['Keşif', 'Plan', 'Uygulama']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Keşif', 'Plan', 'Uygulama'], $h->fresh()->surec);
        $this->get(HizmetResource::getUrl('edit', ['record' => $h->fresh()]))->assertOk();
    }

    public function test_kullanici_olusturma_rol_degisimi_ve_parola(): void
    {
        $yonetici = $this->yonetici();
        $this->actingAs($yonetici);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Yeni Danışman', 'email' => 'yeni@balkanemlak.test', 'rol' => 'emlak_danismani', 'aktif' => true, 'password' => 'GucluParola2026'])
            ->call('create')
            ->assertHasNoFormErrors();

        $u = User::where('email', 'yeni@balkanemlak.test')->firstOrFail();
        $this->assertTrue(Hash::check('GucluParola2026', $u->password));
        $eskiHash = $u->password;

        // Parolasız düzenleme eski parolayı korur, rol değişir
        Livewire::test(EditUser::class, ['record' => $u->getRouteKey()])
            ->fillForm(['rol' => 'sirket_yoneticisi', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
        $u->refresh();
        $this->assertSame('sirket_yoneticisi', $u->rol->value);
        $this->assertSame($eskiHash, $u->password);

        // Yeni parola bir kez şifrelenir
        Livewire::test(EditUser::class, ['record' => $u->getRouteKey()])
            ->fillForm(['password' => 'BaskaParola2027'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('BaskaParola2027', $u->fresh()->password));

        // Parola işlem geçmişine yazılmaz
        $this->assertFalse(IslemKaydi::query()->get()->contains(fn ($k) => str_contains(json_encode($k->sonraki), 'Parola')));
    }

    public function test_kullanici_kendi_rolunu_degistiremez(): void
    {
        $yonetici = $this->yonetici();
        $this->actingAs($yonetici);

        Livewire::test(EditUser::class, ['record' => $yonetici->getRouteKey()])
            ->assertFormFieldDisabled('rol')
            ->assertFormFieldDisabled('aktif')
            ->fillForm(['rol' => 'emlak_danismani', 'aktif' => false])
            ->call('save');

        $this->assertSame('sistem_yoneticisi', $yonetici->fresh()->rol->value);
        $this->assertTrue($yonetici->fresh()->aktif);
    }

    public function test_rol_izinleri_formda_listelenir(): void
    {
        $this->actingAs($this->yonetici());

        Livewire::test(CreateUser::class)
            ->fillForm(['rol' => 'icerik_editoru'])
            ->assertSee('Web içerikleri')
            ->assertDontSee('Tüm portföyleri görme');
    }

    public function test_islem_gecmisi_salt_okunur_ve_fark_gosterir(): void
    {
        $yonetici = $this->yonetici();
        $this->actingAs($yonetici);
        $p = $this->portfoy();
        $p->update(['baslik' => 'Değişen başlık']);
        $kayit = IslemKaydi::where('olay', 'guncellendi')->where('kayit_turu', 'Portfoy')->firstOrFail();

        $satirlar = collect(IslemKaydiResource::farkSatirlari($kayit))->keyBy('alan');
        $this->assertSame('Değişen başlık', $satirlar['baslik']['sonraki']);
        $this->assertTrue($satirlar['baslik']['degisti']);

        Livewire::test(ListIslemKayitlari::class)
            ->assertCanSeeTableRecords([$kayit])
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->mountTableAction('view', $kayit)
            ->assertSee('Değişen başlık');

        $this->actingAs($this->danisman())->get('/admin/islem-gecmisi')->assertForbidden();
    }

    public function test_site_ayarlari_kaydedilir_ve_onbellek_tazelenir(): void
    {
        $this->actingAs($this->yonetici());
        $this->assertNull(Ayar::al('telefon'));

        Livewire::test(SiteAyarlari::class)
            ->fillForm([
                'firma_adi' => 'Balkan Emlak',
                'telefon' => '0284 000 00 00',
                'adres' => '',
                'iletisim_kisileri' => [['ad' => 'Emrullah', 'gorev' => 'Danışman', 'telefon' => '0532 000 00 00', 'whatsapp' => true]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0284 000 00 00', Ayar::al('telefon'));
        $this->assertNull(Ayar::al('adres'));
        $this->assertSame('Emrullah', Ayar::al('iletisim_kisileri')[0]['ad']);
        $this->assertTrue((bool) Ayar::al('iletisim_kisileri')[0]['whatsapp']);
        $this->assertDatabaseHas('islem_kayitlari', ['ozet' => 'Site ayarları']);

        $this->actingAs($this->kullanici('sirket_yoneticisi'))->get('/admin/site-ayarlari')->assertForbidden();
    }

    public function test_yonetici_komutu_parola_uretir(): void
    {
        $this->artisan('balkan:yonetici', ['email' => 'Patron@BalkanEmlak.test', '--ad' => 'Patron'])
            ->expectsOutputToContain('Sistem yöneticisi oluşturuldu')
            ->assertSuccessful();

        $u = User::where('email', 'patron@balkanemlak.test')->firstOrFail();
        $this->assertSame('sistem_yoneticisi', $u->rol->value);
        $this->assertTrue($u->aktif);
        $this->assertSame('Patron', $u->name);

        $this->artisan('balkan:yonetici', ['email' => 'gecersiz'])->assertFailed();
    }
}
