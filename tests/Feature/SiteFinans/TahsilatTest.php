<?php

namespace Tests\Feature\SiteFinans;

use App\Models\Borc;
use App\Models\Hesap;
use App\Services\SiteFinans\BorcServisi;
use App\Services\SiteFinans\SiteRaporu;
use App\Services\SiteFinans\TahsilatServisi;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahsilatTest extends TestCase
{
    use RefreshDatabase, SiteKurulumu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yonetici();
    }

    public function test_bin_tl_borca_dort_yuz_tl_odeme_alti_yuz_kalan(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);

        $this->tahsil($hesap, $b[0], 400);

        $borc = Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole();
        $this->assertSame(600_00, $borc->kalanKurus());
        $this->assertSame('kismi', $borc->durum());
        $this->assertSame(400_00, $hesap->bakiyeKurus());
    }

    public function test_fazla_odeme_avans_olarak_izlenir_ve_gelir_sayilmaz_sonra_mahsup_edilir(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);

        $this->tahsil($hesap, $b[0], 1500);

        $servis = app(TahsilatServisi::class);
        $this->assertSame(0, Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole()->kalanKurus());
        $this->assertSame(['TRY' => 500_00], $servis->avans($b[0]));

        // Dönem tahakkukuna dağıtılan 1000; fazlası tahsilat oranını şişirmez
        $ozet = app(SiteRaporu::class)->donemOzeti($site, '2026-09')['TRY'];
        $this->assertSame(1000_00, $ozet['doneme_dagitilan_kurus']);

        // Ekim aidatı kesinleşince avans otomatik mahsup edilir
        $this->aidat($site, '2026-10', 800);
        $ekim = Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('donem', '2026-10')->sole();
        $this->assertSame(300_00, $ekim->kalanKurus());
        $this->assertSame([], $servis->avans($b[0]));
    }

    public function test_odeme_en_eski_vadeli_borcu_once_kapatir(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-08', 1000);
        $this->aidat($site, '2026-09', 1000);

        $onizleme = app(TahsilatServisi::class)->onizle($b[0], 1200_00);
        $this->assertSame(['2026-08', '2026-09'], collect($onizleme['satirlar'])->map(fn ($s) => $s['borc']->donem)->all());
        $this->assertSame([1000_00, 200_00], collect($onizleme['satirlar'])->pluck('tutar_kurus')->all());

        $this->tahsil($hesap, $b[0], 1200);
        $this->assertSame(0, Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('donem', '2026-08')->sole()->kalanKurus());
        $this->assertSame(800_00, Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('donem', '2026-09')->sole()->kalanKurus());
    }

    public function test_elle_verilen_borc_sirasi_uygulanir(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-08', 1000);
        $this->aidat($site, '2026-09', 1000);
        $eylul = Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('donem', '2026-09')->sole();

        $this->tahsil($hesap, $b[0], 1000, ek: ['borc_sirasi' => [$eylul->id]]);

        $this->assertSame(0, $eylul->kalanKurus());
    }

    public function test_tam_iade_borcu_ve_kasayi_geri_getirir_kayit_silinmez(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);
        $t = $this->tahsil($hesap, $b[0], 1000);

        $iade = app(TahsilatServisi::class)->iadeEt($t, 'Yanlış daireye girildi', '2026-09-16');

        $this->assertSame(-1000_00, $iade->tutar_kurus);
        $this->assertSame(1000_00, Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole()->kalanKurus());
        $this->assertSame(0, $hesap->bakiyeKurus());
        $this->assertTrue($t->fresh()->tamamenIadeMi());

        // İkinci iade reddedilir; asıl kayıt silinemez
        $this->assertThrows(fn () => app(TahsilatServisi::class)->iadeEt($t->fresh(), 'tekrar', '2026-09-17'), DomainException::class);
        $this->assertThrows(fn () => $t->delete(), DomainException::class);
    }

    public function test_kismi_iade_yalniz_avanstan_yapilabilir(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);
        $t = $this->tahsil($hesap, $b[0], 1300); // 300 avans

        $this->assertThrows(fn () => app(TahsilatServisi::class)->iadeEt($t, 'fazla', '2026-09-20', 400_00), DomainException::class);

        app(TahsilatServisi::class)->iadeEt($t, 'Fazla ödeme iadesi', '2026-09-20', 300_00);

        $this->assertSame([], app(TahsilatServisi::class)->avans($b[0]));
        $this->assertSame(0, Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole()->kalanKurus());
        $this->assertSame(1000_00, $hesap->bakiyeKurus());
    }

    public function test_borc_duzeltmesi_fazla_odemeyi_avansa_dondurur_ve_ekstre_tutarli(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);
        $this->tahsil($hesap, $b[0], 1000);
        $borc = Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole();

        app(BorcServisi::class)->duzelt($borc, 700_00, 'Genel kurul kararıyla indirim');

        $this->assertSame(700_00, $borc->netKurus());
        $this->assertSame(0, $borc->kalanKurus());
        $this->assertSame(['TRY' => 300_00], app(TahsilatServisi::class)->avans($b[0]));
        $this->assertSame(2, Borc::where('bagimsiz_bolum_id', $b[0]->id)->count()); // asıl + karşı kayıt

        // Ekstre: 1000 borç, 1000 tahsilat, −300 düzeltme → bakiye −300 (avans)
        $ekstre = app(SiteRaporu::class)->bolumEkstresi($b[0]);
        $this->assertSame(-300_00, $ekstre->last()['bakiye_kurus']);
        $this->assertSame(0, $hesap->bakiyeKurus() - 1000_00);

        $this->assertThrows(fn () => app(BorcServisi::class)->duzelt($borc, 500_00, ''), DomainException::class);
    }

    public function test_dekontlu_tahsilat_dogrulanmadan_kasaya_ve_borca_islenmez(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000);

        $t = $this->tahsil($hesap, $b[0], 1000, ek: ['dogrulandi' => false, 'belge_yolu' => 'dekontlar/x.pdf']);

        $this->assertSame(0, $hesap->bakiyeKurus());
        $this->assertSame(1000_00, Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole()->kalanKurus());

        app(TahsilatServisi::class)->dogrula($t);
        app(TahsilatServisi::class)->dogrula($t->fresh()); // ikinci doğrulama çift kayıt açmaz

        $this->assertSame(1000_00, $hesap->bakiyeKurus());
        $this->assertSame(0, Borc::where('bagimsiz_bolum_id', $b[0]->id)->sole()->kalanKurus());
    }

    public function test_baska_sitenin_hesabina_tahsilat_girilemez(): void
    {
        ['bolumler' => $b] = $this->site('A');
        ['hesap' => $digerHesap] = $this->site('B');

        $this->expectException(DomainException::class);
        $this->tahsil($digerHesap, $b[0], 100);
    }

    public function test_farkli_para_birimleri_karismaz(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-09', 1000); // TRY
        $this->aidat($site, '2026-09', 50, ['tur' => 'ek_aidat', 'para_birimi' => 'EUR']);
        $euroHesap = Hesap::create(['site_id' => $site->id, 'ad' => 'Döviz', 'tur' => 'banka', 'para_birimi' => 'EUR']);

        $this->tahsil($euroHesap, $b[0], 50);

        // Euro ödeme TL borcu kapatmaz
        $this->assertSame(1000_00, Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('para_birimi', 'TRY')->sole()->kalanKurus());
        $this->assertSame(0, Borc::where('bagimsiz_bolum_id', $b[0]->id)->where('para_birimi', 'EUR')->sole()->kalanKurus());

        $ozet = app(SiteRaporu::class)->donemOzeti($site, '2026-09');
        $this->assertSame(3000_00, $ozet['TRY']['tahakkuk_kurus']);
        $this->assertSame(150_00, $ozet['EUR']['tahakkuk_kurus']);
    }

    public function test_tahsilat_orani_payda_sifirsa_gosterilmez(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();

        $this->assertSame([], app(SiteRaporu::class)->donemOzeti($site, '2026-09'));

        $this->aidat($site, '2026-09', 1000);
        $this->tahsil($hesap, $b[0], 1000);
        $this->tahsil($hesap, $b[1], 500);

        $this->assertSame(0.5, app(SiteRaporu::class)->donemOzeti($site, '2026-09')['TRY']['tahsilat_orani']);
    }

    public function test_gecmis_donem_tahsilati_ayri_gosterilir(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-08', 1000);
        $this->aidat($site, '2026-09', 1000);

        $this->tahsil($hesap, $b[0], 1500, '2026-09-05'); // 1000 ağustosa, 500 eylüle

        $ozet = app(SiteRaporu::class)->donemOzeti($site, '2026-09')['TRY'];
        $this->assertSame(1000_00, $ozet['gecmis_donem_tahsilati_kurus']);
        $this->assertSame(500_00, $ozet['doneme_dagitilan_kurus']);
        $this->assertSame(1500_00, $ozet['aydaki_tahsilat_kurus']);
    }

    public function test_vadesi_gecmis_borclar(): void
    {
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-08', 1000);
        $this->tahsil($hesap, $b[0], 1000);

        $gecmis = app(SiteRaporu::class)->vadesiGecmis($site, '2026-09-01');

        $this->assertCount(2, $gecmis);
        $this->assertNotContains($b[0]->id, $gecmis->pluck('bagimsiz_bolum_id'));
    }
}
