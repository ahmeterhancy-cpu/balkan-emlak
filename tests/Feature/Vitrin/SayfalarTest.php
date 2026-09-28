<?php

namespace Tests\Feature\Vitrin;

use App\Models\Ayar;
use App\Models\Hizmet;
use App\Models\Kisi;
use App\Models\Referans;
use App\Models\Yazi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SayfalarTest extends TestCase
{
    use RefreshDatabase, VitrinTestYardimcisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iskeletKur();
    }

    public function test_tum_vitrin_sayfalari_aciliyor(): void
    {
        $ilan = $this->ilan();
        $yazi = Yazi::create(['baslik' => 'Deneme yazısı', 'icerik' => '<p>Metin</p>', 'durum' => 'yayinda', 'yayin_tarihi' => now()->subDay()]);

        foreach ([
            '/', '/emlak', '/emlak?islem=kiralik', route('ilan.detay', $ilan), '/site-yonetimi',
            route('yonetim.hizmet', Hizmet::first()), '/yonetim-teklifi', '/gayrimenkulunuzu-bize-birakin',
            '/hakkimizda', '/referanslar', '/iletisim', '/bilgi-merkezi', route('bilgi.detay', $yazi),
            '/sayfa/kvkk-aydinlatma-metni', '/sitemap.xml', '/robots.txt',
        ] as $adres) {
            $this->get($adres)->assertOk();
        }
    }

    public function test_ana_sayfada_iki_hizmet_alani_ilk_ekranda(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Emlak Portföyünü İncele')
            ->assertSee('Yönetim Teklifi Al')
            ->assertSee(route('yonetim.teklif'), false)
            ->assertSee('Gayrimenkulünüzde doğru adım');
    }

    public function test_yayinda_olmayan_ilan_ziyaretciye_404_ve_listede_yok(): void
    {
        $yayinda = $this->ilan(['baslik' => 'Görünür ilan başlığı']);
        $taslak = $this->ilan(['baslik' => 'Taslak ilan başlığı', 'yayin_durumu' => 'taslak']);
        $inceleme = $this->ilan(['baslik' => 'İncelemedeki ilan', 'yayin_durumu' => 'incelemede']);

        $this->get(route('ilan.detay', $taslak))->assertNotFound();
        $this->get(route('ilan.detay', $inceleme))->assertNotFound();

        $this->get('/emlak')->assertSee('Görünür ilan başlığı')->assertDontSee('Taslak ilan başlığı')->assertDontSee('İncelemedeki ilan');
        $this->get('/sitemap.xml')->assertSee($yayinda->slug)->assertDontSee($taslak->slug);
    }

    public function test_ilan_sayfasi_ozel_bilgileri_sizdirmaz(): void
    {
        $sahip = Kisi::create(['ad_soyad' => 'GIZLI-SAHIP', 'telefon' => '0555 999 88 77']);
        $ilan = $this->ilan(['mulk_sahibi_id' => $sahip->id, 'enlem' => 41.67712345, 'boylam' => 26.55571234, 'harita_gosterimi' => 'yaklasik']);

        $yanit = $this->get(route('ilan.detay', $ilan))->assertOk();

        foreach (['GIZLI-ACIK-ADRES', 'GIZLI-IC-NOT', 'GIZLI-KOMISYON', 'GIZLI-SAHIP', '0555 999 88 77', '41.67712345', '26.55571234'] as $gizli) {
            $yanit->assertDontSee($gizli);
        }
    }

    public function test_acik_adres_yalniz_izin_verilirse_gorunur(): void
    {
        $ilan = $this->ilan(['adres_goster' => true]);

        $this->get(route('ilan.detay', $ilan))->assertSee('GIZLI-ACIK-ADRES');
    }

    public function test_satilan_ilanda_talep_formu_kapanir_ve_durum_yazar(): void
    {
        $ilan = $this->ilan(['operasyon_durumu' => 'satildi']);

        $this->get(route('ilan.detay', $ilan))
            ->assertOk()
            ->assertSee('Bu gayrimenkul satıldı.')
            ->assertDontSee('Talebimi gönder');
    }

    public function test_onizleme_yetki_ister(): void
    {
        $ilan = $this->ilan(['yayin_durumu' => 'taslak']);

        $this->get(route('ilan.onizleme', $ilan->id))->assertRedirect('/admin/login');

        $this->actingAs($ilan->danisman)->get(route('ilan.onizleme', $ilan->id))
            ->assertOk()->assertSee('Önizleme')->assertSee('noindex', false);

        $this->actingAs($this->danisman())->get(route('ilan.onizleme', $ilan->id))->assertForbidden();
    }

    public function test_dogrulanmamis_bilgi_bos_ise_sayfada_cizilmez(): void
    {
        // Adres ve çalışma saati ayarı yok: yer tutucu metin yayımlanmaz
        $this->get('/iletisim')->assertOk()->assertDontSee('Çalışma saatleri')->assertDontSee('7/24');

        Ayar::kaydet(['adres' => 'Örnek Cad. No 1 Edirne', 'calisma_saatleri' => 'Hafta içi 09.00–18.00']);

        $this->get('/iletisim')->assertSee('Örnek Cad. No 1 Edirne')->assertSee('Hafta içi 09.00–18.00');
    }

    public function test_whatsapp_yalniz_ayarda_acikken_gorunur(): void
    {
        Ayar::kaydet(['iletisim_kisileri' => [['ad' => 'Deneme Kişi', 'telefon' => '0505 111 22 33', 'whatsapp' => false]]]);
        $this->get('/iletisim')->assertSee('Deneme Kişi')->assertDontSee('wa.me/905051112233', false);

        Ayar::kaydet(['iletisim_kisileri' => [['ad' => 'Deneme Kişi', 'telefon' => '0505 111 22 33', 'whatsapp' => true]]]);
        $this->get('/iletisim')->assertSee('wa.me/905051112233', false);
    }

    public function test_referans_izinsiz_ise_gorunmez_ve_menude_yer_almaz(): void
    {
        Referans::create(['tur' => 'yorum', 'ad' => 'İzinsiz Kişi', 'metin' => 'Çok memnunuz', 'yayinda' => true, 'izin_alindi' => false]);

        $this->get('/')->assertDontSee('İzinsiz Kişi')->assertDontSee(route('referanslar'), false);

        Referans::create(['tur' => 'yorum', 'ad' => 'İzinli Kişi', 'metin' => 'Teşekkürler', 'yayinda' => true, 'izin_alindi' => true]);

        $this->get('/')->assertSee('İzinli Kişi')->assertSee(route('referanslar'), false);
    }

    public function test_yasal_taslak_metin_uyari_seridiyle_gosterilir(): void
    {
        $this->get('/sayfa/kvkk-aydinlatma-metni')->assertSee('hukuki incelemeden geçmemiştir');
    }

    public function test_zengin_metin_temizlenir(): void
    {
        $ilan = $this->ilan(['aciklama' => '<p>Güzel ev <script>alert("x")</script><a href="javascript:alert(1)" onclick="k()">tıkla</a> ve bahçeli, geniş.</p>']);

        $this->get(route('ilan.detay', $ilan))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('onclick', false)
            ->assertSee('Güzel ev');
    }

    public function test_robots_canli_disinda_her_seyi_kapatir(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /');
    }

    public function test_ileri_tarihli_ve_taslak_yazi_gorunmez(): void
    {
        $ileri = Yazi::create(['baslik' => 'İleri tarihli', 'icerik' => 'x', 'durum' => 'yayinda', 'yayin_tarihi' => now()->addDay()]);
        $taslak = Yazi::create(['baslik' => 'Taslak yazı', 'icerik' => 'x', 'durum' => 'taslak']);

        $this->get(route('bilgi.detay', $ileri))->assertNotFound();
        $this->get(route('bilgi.detay', $taslak))->assertNotFound();
        $this->get('/bilgi-merkezi')->assertDontSee('İleri tarihli')->assertDontSee('Taslak yazı');
    }
}
