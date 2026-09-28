<?php

namespace Tests\Feature\Vitrin;

use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Mail\YeniBasvuru;
use App\Models\Ayar;
use App\Models\Talep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FormlarTest extends TestCase
{
    use RefreshDatabase, VitrinTestYardimcisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iskeletKur();
    }

    public function test_ilan_talebi_ilana_ve_danismanina_baglanir(): void
    {
        Mail::fake();
        Ayar::kaydet(['bildirim_eposta' => 'ofis@example.com']);
        $ilan = $this->ilan();

        $this->from(route('ilan.detay', $ilan))
            ->post(route('ilan.talep', $ilan), $this->formKorumasi() + [
                'ad_soyad' => 'Ali Veli',
                'telefon' => '0532 123 45 67',
                'tercih_edilen_zaman' => now()->addDays(2)->format('Y-m-d\T10:00'),
                'mesaj' => 'Görmek isterim',
                'kvkk' => '1',
            ])
            ->assertRedirect(route('ilan.detay', $ilan))
            ->assertSessionHas('basari');

        $talep = Talep::sole();
        $this->assertSame(TalepTuru::IlanBilgi, $talep->tur);
        $this->assertSame(TalepDurumu::Yeni, $talep->durum);
        $this->assertSame($ilan->id, $talep->portfoy_id);
        $this->assertSame($ilan->danisman_id, $talep->atanan_id);
        $this->assertNotNull($talep->tercih_edilen_zaman);
        $this->assertTrue($talep->kvkk_onay);

        Mail::assertSent(YeniBasvuru::class, fn ($m) => $m->hasTo('ofis@example.com'));
    }

    public function test_kvkk_onayi_ve_zorunlu_alanlar(): void
    {
        $ilan = $this->ilan();

        $this->post(route('ilan.talep', $ilan), $this->formKorumasi() + ['ad_soyad' => '', 'telefon' => 'abc'])
            ->assertSessionHasErrorsIn('ilan', ['ad_soyad', 'telefon', 'kvkk']);

        $this->assertSame(0, Talep::count());
    }

    public function test_satilan_ilana_talep_acilmaz(): void
    {
        $ilan = $this->ilan(['operasyon_durumu' => 'satildi']);

        $this->post(route('ilan.talep', $ilan), $this->formKorumasi() + ['ad_soyad' => 'Ali Veli', 'telefon' => '05321234567', 'kvkk' => '1'])
            ->assertSessionHasErrors('genel');

        $this->assertSame(0, Talep::count());
    }

    public function test_taslak_ilana_talep_404(): void
    {
        $ilan = $this->ilan(['yayin_durumu' => 'taslak']);

        $this->post(route('ilan.talep', $ilan), $this->formKorumasi() + ['ad_soyad' => 'Ali Veli', 'telefon' => '05321234567', 'kvkk' => '1'])
            ->assertNotFound();
    }

    public function test_bal_kupu_ve_cok_hizli_gonderim_kayit_acmaz(): void
    {
        $gecerli = ['ad_soyad' => 'Bot Bey', 'telefon' => '05320000000', 'konu' => 'x', 'mesaj' => 'Merhaba dünya mesajı', 'kvkk' => '1'];

        // Bal küpü dolu
        $this->post(route('iletisim.gonder'), ['web_sitesi' => 'http://spam.test'] + $this->formKorumasi() + $gecerli)->assertSessionHas('basari');
        // Form 1 sn önce açılmış
        $this->post(route('iletisim.gonder'), ['form_zamani' => Crypt::encryptString((string) now()->timestamp)] + $gecerli)->assertSessionHas('basari');

        $this->assertSame(0, Talep::count());

        // Şifreli zaman yok: insan olabilir, anlaşılır hata
        $this->post(route('iletisim.gonder'), $gecerli)->assertSessionHasErrors('genel');
    }

    public function test_portfoy_basvurusu_detaylari_saklar(): void
    {
        $this->post(route('basvuru.portfoy.gonder'), $this->formKorumasi() + [
            'ad_soyad' => 'Mülk Sahibi',
            'telefon' => '0533 222 11 00',
            'islem' => 'kiralama',
            'kategori' => 'ticari',
            'konum' => 'Sabuni, Edirne',
            'kvkk' => '1',
            'pazarlama' => '1',
        ])->assertSessionHas('basari');

        $talep = Talep::sole();
        $this->assertSame(TalepTuru::PortfoyBasvurusu, $talep->tur);
        $this->assertSame(['islem' => 'Kiralama', 'kategori' => 'Ticari', 'konum' => 'Sabuni, Edirne'], $talep->detay);
        $this->assertTrue($talep->pazarlama_izni);
    }

    public function test_yonetim_teklifi_yapi_bilgilerini_ve_ihtiyaclari_saklar(): void
    {
        $this->post(route('yonetim.teklif.gonder'), $this->formKorumasi() + [
            'ad_soyad' => 'Site Temsilcisi',
            'eposta' => 'temsilci@example.com',
            'site_adi' => 'Çamlık Sitesi',
            'konum' => 'Kıyık',
            'blok_sayisi' => '4',
            'bagimsiz_bolum_sayisi' => '120',
            'ihtiyaclar' => ['Genel Yönetim', 'Teknik Yönetim'],
            'kvkk' => '1',
        ])->assertSessionHas('basari');

        $talep = Talep::sole();
        $this->assertSame(TalepTuru::YonetimTeklifi, $talep->tur);
        $this->assertSame('beklemede', $talep->sonuc);
        $this->assertSame('Çamlık Sitesi', $talep->detay['site_adi']);
        $this->assertSame(120, $talep->detay['bagimsiz_bolum_sayisi']);
        $this->assertSame(['Genel Yönetim', 'Teknik Yönetim'], $talep->detay['ihtiyaclar']);
        $this->assertFalse($talep->pazarlama_izni);
    }

    public function test_teklifte_bilinmeyen_hizmet_reddedilir(): void
    {
        $this->post(route('yonetim.teklif.gonder'), $this->formKorumasi() + [
            'ad_soyad' => 'Site Temsilcisi', 'telefon' => '05321112233', 'site_adi' => 'X', 'konum' => 'Y',
            'bagimsiz_bolum_sayisi' => '10', 'ihtiyaclar' => ['<script>'], 'kvkk' => '1',
        ])->assertSessionHasErrors('ihtiyaclar.0');
    }

    public function test_iletisim_telefon_ya_da_eposta_ister(): void
    {
        $this->post(route('iletisim.gonder'), $this->formKorumasi() + ['ad_soyad' => 'Ayşe', 'konu' => 'Soru', 'mesaj' => 'Uzun bir soru metni', 'kvkk' => '1'])
            ->assertSessionHasErrors(['telefon', 'eposta']);

        $this->post(route('iletisim.gonder'), $this->formKorumasi() + ['ad_soyad' => 'Ayşe', 'eposta' => 'ayse@example.com', 'konu' => 'Soru', 'mesaj' => 'Uzun bir soru metni', 'kvkk' => '1'])
            ->assertSessionHas('basari');

        $this->assertSame(TalepTuru::Iletisim, Talep::sole()->tur);
    }

    public function test_kisa_form_iki_ture_ayrilir(): void
    {
        $ortak = $this->formKorumasi() + ['ad_soyad' => 'Kısa Form', 'telefon' => '05321112233', 'kvkk' => '1'];

        $this->post(route('basvuru.kisa'), $ortak + ['talep_turu' => 'yonetim'])->assertSessionHas('basari');
        $this->post(route('basvuru.kisa'), $ortak + ['talep_turu' => 'emlak'])->assertSessionHas('basari');

        $this->assertEqualsCanonicalizing(
            [TalepTuru::YonetimTeklifi, TalepTuru::MusteriTalebi],
            Talep::pluck('tur')->all(),
        );
    }

    public function test_bildirim_e_postasi_hatasi_basvuruyu_dusurmez(): void
    {
        Ayar::kaydet(['bildirim_eposta' => 'ofis@example.com']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP yok'));

        $this->post(route('iletisim.gonder'), $this->formKorumasi() + ['ad_soyad' => 'Ayşe', 'telefon' => '05321112233', 'konu' => 'Soru', 'mesaj' => 'Uzun bir soru metni', 'kvkk' => '1'])
            ->assertSessionHas('basari');

        $this->assertSame(1, Talep::count());
    }

    public function test_hiz_siniri(): void
    {
        $veri = $this->formKorumasi() + ['ad_soyad' => 'Ayşe', 'telefon' => '05321112233', 'konu' => 'Soru', 'mesaj' => 'Uzun bir soru metni', 'kvkk' => '1'];

        foreach (range(1, 6) as $_) {
            $this->post(route('iletisim.gonder'), $veri)->assertRedirect();
        }

        $this->post(route('iletisim.gonder'), $veri)->assertStatus(429);
    }
}
