<?php

namespace Tests\Feature\Panel;

use App\Enums\KomisyonDurumu;
use App\Enums\TalepTuru;
use App\Filament\Resources\IslemKayitlari\IslemKaydiResource;
use App\Filament\Widgets\EmlakOzeti;
use App\Filament\Widgets\IcerikOzeti;
use App\Filament\Widgets\OnayBekleyenIlanlar;
use App\Filament\Widgets\YaklasanRandevular;
use App\Models\Hizmet;
use App\Models\IslemKaydi;
use App\Models\Kisi;
use App\Models\Komisyon;
use App\Models\Randevu;
use App\Models\Referans;
use App\Models\Sayfa;
use App\Models\Sss;
use App\Models\Talep;
use App\Models\Yazi;
use Livewire\Livewire;
use Tests\TestCase;

/** Her kaynağın liste / oluşturma / düzenleme sayfası yönetici için açılır. */
class SayfaErisimTest extends TestCase
{
    use PanelKurulumu;

    public function test_giris_sayfasi_acilir(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Balkan Emlak');
    }

    public function test_misafir_panelden_girise_yonlenir(): void
    {
        $this->get('/admin/portfoyler')->assertRedirect('/admin/login');
    }

    public function test_yonetici_tum_kaynak_sayfalarini_acar(): void
    {
        $yonetici = $this->yonetici();
        $this->actingAs($yonetici);

        $portfoy = $this->portfoy(['danisman_id' => $yonetici->id]);
        $kayitlar = [
            'portfoyler' => $portfoy,
            'kisiler' => Kisi::first(),
            'randevular' => Randevu::create(['musteri_adi' => 'Ali', 'baslangic' => now()->addDay(), 'danisman_id' => $yonetici->id, 'portfoy_id' => $portfoy->id]),
            'komisyonlar' => Komisyon::create(['portfoy_id' => $portfoy->id, 'beklenen_kurus' => 10_000_00, 'para_birimi' => 'TRY', 'durum' => KomisyonDurumu::Bekliyor]),
            'konumlar' => $this->ilce,
            'talepler' => Talep::create(['tur' => TalepTuru::Iletisim, 'ad_soyad' => 'Veli', 'telefon' => '05321112233', 'detay' => ['ihtiyaclar' => ['Temizlik', 'Güvenlik'], 'blok_sayisi' => 3]]),
            'sayfalar' => Sayfa::create(['baslik' => 'Hakkımızda', 'icerik' => '<p>Metin</p>']),
            'hizmetler' => Hizmet::create(['baslik' => 'Genel yönetim', 'ozet' => 'Özet', 'surec' => ['Keşif', 'Teklif']]),
            'bilgi-merkezi' => Yazi::create(['baslik' => 'Aidat nedir?', 'icerik' => '<p>Metin</p>']),
            'referanslar' => Referans::create(['ad' => 'Güneş Sitesi']),
            'sss' => Sss::create(['soru' => 'Soru?', 'cevap' => 'Cevap']),
            'kullanicilar' => $yonetici,
        ];

        $this->get('/admin')->assertOk()->assertSee('Gösterge Paneli');

        foreach ($kayitlar as $yol => $kayit) {
            $this->get("/admin/$yol")->assertOk();
            $this->get("/admin/$yol/create")->assertOk();
            $this->get("/admin/$yol/{$kayit->getRouteKey()}/edit")->assertOk();
        }

        $this->get('/admin/portfoyler/'.$portfoy->getRouteKey())->assertOk();
        $this->get('/admin/islem-gecmisi')->assertOk();
        $this->get('/admin/site-ayarlari')->assertOk();
    }

    public function test_islem_gecmisinde_olusturma_duzenleme_silme_yok(): void
    {
        $this->actingAs($this->yonetici());
        $this->portfoy();

        $this->get('/admin/islem-gecmisi/create')->assertNotFound();
        $kayit = IslemKaydi::firstOrFail();
        $this->get("/admin/islem-gecmisi/{$kayit->id}/edit")->assertNotFound();

        $this->assertFalse(auth()->user()->can('update', $kayit));
        $this->assertFalse(auth()->user()->can('delete', $kayit));
        $this->assertFalse(IslemKaydiResource::canCreate());
    }

    public function test_danisman_ve_editor_gosterge_panelini_acar(): void
    {
        $danisman = $this->danisman();
        $this->portfoy(['danisman_id' => $danisman->id]);
        $this->portfoy(['danisman_id' => $this->danisman()->id]); // başkasının; sayılmamalı

        $this->actingAs($danisman)->get('/admin')->assertOk();
        $this->assertTrue(EmlakOzeti::canView());
        $this->assertFalse(OnayBekleyenIlanlar::canView());
        $this->assertFalse(IcerikOzeti::canView());
        $this->assertSame(1, EmlakOzeti::eksikBilgiliSayisi()); // fotoğrafı yok
        Livewire::test(EmlakOzeti::class)->assertSee('Yayındaki satılık');
        Livewire::test(YaklasanRandevular::class)->assertOk();

        $this->actingAs($this->kullanici('icerik_editoru'))->get('/admin')->assertOk();
        $this->assertFalse(EmlakOzeti::canView());
        $this->assertFalse(YaklasanRandevular::canView());
        $this->assertTrue(IcerikOzeti::canView());
        Livewire::test(IcerikOzeti::class)->assertSee('Taslak yazılar');

        $this->actingAs($this->kullanici('sirket_yoneticisi'));
        $this->assertTrue(OnayBekleyenIlanlar::canView());
        Livewire::test(OnayBekleyenIlanlar::class)->assertOk();
    }
}
