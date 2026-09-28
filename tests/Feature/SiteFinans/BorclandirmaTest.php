<?php

namespace Tests\Feature\SiteFinans;

use App\Models\BolumIliskisi;
use App\Models\Borc;
use App\Models\Borclandirma;
use App\Models\Kisi;
use App\Services\SiteFinans\Borclandirici;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorclandirmaTest extends TestCase
{
    use RefreshDatabase, SiteKurulumu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yonetici();
    }

    public function test_ayni_borclandirma_tekrar_calisinca_mukerrer_borc_olusmaz(): void
    {
        ['site' => $site] = $this->site();
        $b = $this->aidat($site, '2026-09', 1000);

        app(Borclandirici::class)->kesinlestir($b);
        app(Borclandirici::class)->kesinlestir($b->fresh());

        $this->assertSame(3, Borc::count());
        $this->assertSame(300_000, (int) Borc::sum('tutar_kurus'));

        // Aynı dönem/tür/etiket ile ikinci iş açılamaz
        $this->expectException(QueryException::class);
        Borclandirma::create(['site_id' => $site->id, 'donem' => '2026-09', 'tur' => 'aidat', 'aciklama' => 'tekrar', 'vade' => '2026-09-10', 'dagitim' => 'sabit', 'birim_kurus' => 100]);
    }

    public function test_ayni_donemde_etiketli_ikinci_ek_aidat_acilabilir(): void
    {
        ['site' => $site] = $this->site();

        $this->aidat($site, '2026-09', 500, ['tur' => 'ek_aidat', 'etiket' => 'cati']);
        $this->aidat($site, '2026-09', 250, ['tur' => 'ek_aidat', 'etiket' => 'boya']);

        $this->assertSame(6, Borc::count());
    }

    public function test_metrekare_dagitimi_kurusu_kurusuna_toplama_esit(): void
    {
        ['site' => $site] = $this->site('M2', 3, [100, 100, 100]);

        $b = Borclandirma::create([
            'site_id' => $site->id, 'donem' => '2026-09', 'tur' => 'gider_payi', 'aciklama' => 'Çatı onarımı',
            'vade' => '2026-09-30', 'dagitim' => 'm2', 'toplam_kurus' => 100_000_01, // bölünemeyen kuruş
        ]);

        $onizleme = app(Borclandirici::class)->onizle($b);
        $this->assertSame(100_000_01, $onizleme->sum('tutar_kurus'));
        $this->assertEqualsCanonicalizing([33_333_34, 33_333_34, 33_333_33], $onizleme->pluck('tutar_kurus')->all());

        // Önizleme hiçbir şey yazmaz
        $this->assertSame(0, Borc::count());
    }

    public function test_arsa_payi_dagitimi_orantili(): void
    {
        ['site' => $site] = $this->site(); // arsa payları 1/6, 2/6, 3/6

        $b = Borclandirma::create([
            'site_id' => $site->id, 'donem' => '2026-09', 'tur' => 'demirbas', 'aciklama' => 'Jeneratör',
            'vade' => '2026-09-30', 'dagitim' => 'arsa_payi', 'toplam_kurus' => 60_000_00,
        ]);

        $this->assertSame([10_000_00, 20_000_00, 30_000_00], app(Borclandirici::class)->onizle($b)->pluck('tutar_kurus')->all());
    }

    public function test_eksik_metrekare_anlasilir_hata_verir(): void
    {
        ['site' => $site, 'bolumler' => $bolumler] = $this->site();
        $bolumler[1]->update(['brut_m2' => null]);

        $b = Borclandirma::create(['site_id' => $site->id, 'donem' => '2026-09', 'tur' => 'gider_payi', 'aciklama' => 'x', 'vade' => '2026-09-30', 'dagitim' => 'm2', 'toplam_kurus' => 1000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('metrekaresi girilmemiş');
        app(Borclandirici::class)->onizle($b);
    }

    public function test_ozel_dagitim_baska_sitenin_bolumunu_kabul_etmez(): void
    {
        ['site' => $site, 'bolumler' => $bolumler] = $this->site();
        ['bolumler' => $digerleri] = $this->site('Diğer');

        $b = Borclandirma::create([
            'site_id' => $site->id, 'donem' => '2026-09', 'tur' => 'diger', 'aciklama' => 'x', 'vade' => '2026-09-30',
            'dagitim' => 'ozel', 'ozel_tutarlar' => [$bolumler[0]->id => 5000, $digerleri[0]->id => 5000],
        ]);

        $this->expectException(DomainException::class);
        app(Borclandirici::class)->onizle($b);
    }

    public function test_malik_degisiminde_gecmis_donem_sorumlusu_korunur(): void
    {
        ['site' => $site, 'bolumler' => $bolumler] = $this->site();
        $bolum = $bolumler[0];
        $eski = BolumIliskisi::where('bagimsiz_bolum_id', $bolum->id)->first();

        $this->aidat($site, '2026-05', 1000);

        // Taşınma: eski malik 31 Mayıs'ta çıkar, yeni malik 1 Haziran'da girer
        $eski->update(['bitis' => '2026-05-31']);
        $yeni = Kisi::create(['ad_soyad' => 'Yeni Malik']);
        BolumIliskisi::create(['bagimsiz_bolum_id' => $bolum->id, 'kisi_id' => $yeni->id, 'rol' => 'malik', 'baslangic' => '2026-06-01']);

        $this->aidat($site, '2026-06', 1000);

        $mayis = Borc::where('bagimsiz_bolum_id', $bolum->id)->where('donem', '2026-05')->sole();
        $haziran = Borc::where('bagimsiz_bolum_id', $bolum->id)->where('donem', '2026-06')->sole();

        $this->assertSame($eski->kisi_id, $mayis->kisi_id);
        $this->assertSame($yeni->id, $haziran->kisi_id);
        $this->assertSame(2, BolumIliskisi::where('bagimsiz_bolum_id', $bolum->id)->count()); // geçmiş ilişki silinmedi
    }

    public function test_kiraci_borc_sorumlusu_isaretlenirse_ona_yazilir(): void
    {
        ['site' => $site, 'bolumler' => $bolumler] = $this->site();
        $kiraci = Kisi::create(['ad_soyad' => 'Kiracı']);
        BolumIliskisi::create(['bagimsiz_bolum_id' => $bolumler[0]->id, 'kisi_id' => $kiraci->id, 'rol' => 'kiraci', 'baslangic' => '2026-01-01', 'borc_sorumlusu' => true]);

        $this->aidat($site, '2026-09', 1000);

        $this->assertSame($kiraci->id, Borc::where('bagimsiz_bolum_id', $bolumler[0]->id)->value('kisi_id'));
    }

    public function test_islenmis_borc_silinemez_ve_tutari_degistirilemez(): void
    {
        ['site' => $site] = $this->site();
        $this->aidat($site, '2026-09', 1000);
        $borc = Borc::first();

        try {
            $borc->update(['tutar_kurus' => 1]);
            $this->fail('Tutar değişmemeliydi');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        $borc->delete();
    }
}
