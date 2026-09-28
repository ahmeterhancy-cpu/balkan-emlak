<?php

namespace Tests\Feature\SiteFinans;

use App\Enums\Rol;
use App\Models\Gider;
use App\Services\SiteFinans\GiderServisi;
use App\Services\SiteFinans\HesapServisi;
use App\Services\SiteFinans\SiteRaporu;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiderVeHesapTest extends TestCase
{
    use RefreshDatabase, SiteKurulumu;

    private function gider(int $siteId, int $tl): Gider
    {
        return Gider::create([
            'site_id' => $siteId, 'kategori' => 'asansor', 'belge_tarihi' => '2026-09-05', 'donem' => '2026-09',
            'tutar_kurus' => $tl * 100, 'aciklama' => 'Asansör bakımı',
        ]);
    }

    public function test_onay_limitini_asan_gideri_site_yoneticisi_onaylayamaz(): void
    {
        $this->yonetici();
        ['site' => $site] = $this->site();
        $site->update(['onay_limiti_kurus' => 10_000_00]);
        $servis = app(GiderServisi::class);

        $kucuk = $servis->onayaGonder($this->gider($site->id, 5_000));
        $buyuk = $servis->onayaGonder($this->gider($site->id, 15_000));

        $siteYoneticisi = $this->kullanici(Rol::SiteYoneticisi, $site);
        $this->actingAs($siteYoneticisi);

        $servis->onayla($kucuk);
        $this->assertSame('onaylandi', $kucuk->fresh()->onay_durumu);
        $this->assertThrows(fn () => $servis->onayla($buyuk), DomainException::class, 'onay limitini');

        $this->actingAs($this->kullanici(Rol::SirketYoneticisi));
        $servis->onayla($buyuk->fresh());
        $this->assertSame('onaylandi', $buyuk->fresh()->onay_durumu);
    }

    public function test_odeme_onayla_ayri_ve_kismi_odeme_desteklenir(): void
    {
        $this->yonetici();
        ['site' => $site, 'hesap' => $hesap] = $this->site();
        app(HesapServisi::class)->acilisBakiyesi($hesap, 50_000_00, '2026-09-01', 'Devir tutanağı');
        $servis = app(GiderServisi::class);
        $g = $this->gider($site->id, 10_000);

        $this->assertThrows(fn () => $servis->ode($g, $hesap, 1000_00, '2026-09-10'), DomainException::class, 'Onaylanmamış');

        $servis->onayla($servis->onayaGonder($g));
        $servis->ode($g, $hesap, 4_000_00, '2026-09-10');
        $this->assertSame('kismi', $g->fresh()->odemeDurumu());

        $this->assertThrows(fn () => $servis->ode($g, $hesap, 7_000_00, '2026-09-11'), DomainException::class, 'aşamaz');

        $odeme = $servis->ode($g, $hesap, 6_000_00, '2026-09-11');
        $this->assertSame('odendi', $g->fresh()->odemeDurumu());
        $this->assertSame(40_000_00, $hesap->bakiyeKurus());

        $servis->odemeyiGeriAl($odeme, 'Yanlış hesaptan ödendi', '2026-09-12');
        $this->assertSame('kismi', $g->fresh()->odemeDurumu());
        $this->assertSame(46_000_00, $hesap->bakiyeKurus());

        // Onaylı gider silinemez, tutarı değişmez
        $this->assertThrows(fn () => $g->fresh()->delete(), DomainException::class);
        $this->assertThrows(fn () => $g->fresh()->update(['tutar_kurus' => 1]), DomainException::class);
    }

    public function test_transfer_iki_kez_sayilmaz_ve_siteler_arasi_yapilamaz(): void
    {
        $this->yonetici();
        ['hesap' => $banka, 'kasa' => $kasa] = $this->site('A');
        ['kasa' => $digerKasa] = $this->site('B');
        $h = app(HesapServisi::class);

        $h->acilisBakiyesi($kasa, 5_000_00, '2026-09-01', 'Sayım tutanağı');
        $h->transfer($kasa, $banka, 3_000_00, '2026-09-05', 'Bankaya yatırıldı');

        $this->assertSame(2_000_00, $kasa->bakiyeKurus());
        $this->assertSame(3_000_00, $banka->bakiyeKurus());

        $dokum = app(SiteRaporu::class)->hesapDokumu($banka, '2026-09-01', '2026-09-30');
        $this->assertSame(3_000_00, $dokum['giris_kurus']);
        $this->assertSame(0, $dokum['transfer_haric_giris_kurus']); // transfer gelir değil

        $this->assertThrows(fn () => $h->transfer($kasa, $digerKasa, 100, '2026-09-06'), DomainException::class, 'Farklı sitelerin');
        $this->assertThrows(fn () => $h->acilisBakiyesi($kasa, 1, '2026-09-01', 'ikinci'), DomainException::class);
    }

    public function test_kapali_doneme_kayit_yalniz_yetkili_ve_gerekceli(): void
    {
        $this->yonetici();
        ['site' => $site, 'bolumler' => $b, 'hesap' => $hesap] = $this->site();
        $this->aidat($site, '2026-08', 1000);

        $muhasebe = $this->kullanici(Rol::MuhasebeSorumlusu, $site);
        $this->actingAs($muhasebe);
        app(HesapServisi::class)->donemKapat($site, '2026-08');

        // Muhasebe kapalı döneme tahsilat giremez
        $this->assertThrows(fn () => $this->tahsil($hesap, $b[0], 100, '2026-08-20'), DomainException::class, 'kapalı');
        // Açık döneme girebilir
        $this->tahsil($hesap, $b[0], 100, '2026-09-02');

        // Şirket yöneticisi: gerekçesiz olmaz, gerekçeyle olur
        $this->actingAs($this->kullanici(Rol::SirketYoneticisi));
        $this->assertThrows(fn () => $this->tahsil($hesap, $b[1], 100, '2026-08-20'), DomainException::class, 'gerekçe');
        $this->tahsil($hesap, $b[1], 100, '2026-08-20', ['gerekce' => 'Banka dekontu geç ulaştı']);

        $this->assertSame(200_00, $hesap->bakiyeKurus());
    }

    public function test_hareket_mutabakat_isareti_disinda_degismez(): void
    {
        $this->yonetici();
        ['hesap' => $hesap] = $this->site();
        $hareket = app(HesapServisi::class)->acilisBakiyesi($hesap, 1000, '2026-09-01', 'Dayanak');

        app(HesapServisi::class)->mutabakat([$hareket], true, '2026-09-30');
        $this->assertTrue($hareket->fresh()->mutabik);

        $this->assertThrows(fn () => $hareket->fresh()->update(['tutar_kurus' => 5]), DomainException::class);
        $this->assertThrows(fn () => $hareket->fresh()->delete(), DomainException::class);
    }
}
