<?php

namespace Tests\Feature\Vitrin;

use App\Models\Konum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AramaTest extends TestCase
{
    use RefreshDatabase, VitrinTestYardimcisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iskeletKur();
    }

    public function test_islem_ve_kategori_suzgeci(): void
    {
        $this->ilan(['baslik' => 'Satılık daire A']);
        $this->ilan(['baslik' => 'Kiralık daire B', 'islem' => 'kiralik', 'fiyat_kurus' => 20_000_00]);
        $this->ilan(['baslik' => 'Tarla C', 'kategori' => 'arsa', 'tip' => 'tarla', 'oda' => null]);

        $this->get('/emlak?islem=kiralik')->assertSee('Kiralık daire B')->assertDontSee('Satılık daire A')->assertDontSee('Tarla C');
        $this->get('/emlak?kategori=arsa')->assertSee('Tarla C')->assertDontSee('Satılık daire A');
        // Ana sayfa arama kutusunun birleşik değeri
        $this->get('/emlak?kategori=arsa:tarla')->assertSee('Tarla C')->assertDontSee('Kiralık daire B');
    }

    public function test_oda_suzgeci_arsada_yok_sayilir(): void
    {
        $this->ilan(['baslik' => 'Tarla D', 'kategori' => 'arsa', 'tip' => 'tarla', 'oda' => null]);

        // Konuta özgü süzgeç arsa aramasını boşaltmamalı
        $this->get('/emlak?kategori=arsa&oda=3%2B1')->assertSee('Tarla D');
    }

    public function test_konum_fiyat_ve_metrekare_suzgeci(): void
    {
        $merkez = Konum::where('tur', 'ilce')->where('ad', 'Merkez')->first();
        $kesan = Konum::where('tur', 'ilce')->where('ad', 'Keşan')->first();

        $this->ilan(['baslik' => 'Merkez ucuz', 'ilce_id' => $merkez->id, 'fiyat_kurus' => 2_000_000_00, 'net_m2' => 80]);
        $this->ilan(['baslik' => 'Merkez pahalı', 'ilce_id' => $merkez->id, 'fiyat_kurus' => 9_000_000_00, 'net_m2' => 200]);
        $this->ilan(['baslik' => 'Keşan evi', 'ilce_id' => $kesan->id, 'fiyat_kurus' => 2_500_000_00, 'net_m2' => 95]);

        $this->get('/emlak?ilce='.$merkez->id)->assertSee('Merkez ucuz')->assertSee('Merkez pahalı')->assertDontSee('Keşan evi');
        $this->get('/emlak?konum=ilce-'.$kesan->id)->assertSee('Keşan evi')->assertDontSee('Merkez ucuz');
        // Türkçe binlik ayırıcıyla yazılmış fiyat
        $this->get('/emlak?fiyat_max=3.000.000')->assertSee('Merkez ucuz')->assertSee('Keşan evi')->assertDontSee('Merkez pahalı');
        $this->get('/emlak?m2_min=150')->assertSee('Merkez pahalı')->assertDontSee('Merkez ucuz');
    }

    public function test_siralama_fiyata_gore(): void
    {
        $this->ilan(['baslik' => 'Orta fiyat', 'fiyat_kurus' => 5_000_000_00]);
        $this->ilan(['baslik' => 'En ucuz', 'fiyat_kurus' => 1_000_000_00]);
        $this->ilan(['baslik' => 'En pahalı', 'fiyat_kurus' => 9_000_000_00]);

        $this->get('/emlak?sirala=fiyat_artan')->assertSeeInOrder(['En ucuz', 'Orta fiyat', 'En pahalı']);
        $this->get('/emlak?sirala=fiyat_azalan')->assertSeeInOrder(['En pahalı', 'Orta fiyat', 'En ucuz']);
    }

    public function test_satilan_ilan_listenin_sonunda(): void
    {
        $this->ilan(['baslik' => 'Satılmış ev', 'operasyon_durumu' => 'satildi', 'yayin_tarihi' => now()]);
        $this->ilan(['baslik' => 'Aktif ev', 'yayin_tarihi' => now()->subMonth()]);

        $this->get('/emlak')->assertSeeInOrder(['Aktif ev', 'Satılmış ev']);
    }

    public function test_suzgecler_sayfalamada_korunur_ve_sonuc_yokken_aciklama_cikar(): void
    {
        foreach (range(1, 14) as $i) {
            $this->ilan(['baslik' => 'Kiralık '.$i, 'islem' => 'kiralik', 'fiyat_kurus' => (10_000 + $i) * 100]);
        }

        $this->get('/emlak?islem=kiralik')->assertSee('islem=kiralik&amp;page=2', false);
        $this->get('/emlak?islem=kiralik&kategori=ticari')->assertSee('Bu ölçütlere uyan ilan bulunamadı');
    }

    public function test_gecersiz_suzgec_degerleri_500_vermez(): void
    {
        $this->ilan(['baslik' => 'Sağlam ilan']);

        $this->get('/emlak?islem=xyz&kategori=<b>&fiyat_min=abc&sirala=drop&il=-5&oda=99')->assertOk()->assertSee('Sağlam ilan');
    }
}
