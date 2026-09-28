<?php

namespace Tests\Feature\SiteFinans;

use App\Enums\Rol;
use App\Models\Borc;
use App\Models\IsEmri;
use App\Models\Site;
use App\Models\SiteBelgesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SiteYetkiTest extends TestCase
{
    use RefreshDatabase, SiteKurulumu;

    public function test_yetkisiz_kullanici_baska_sitenin_kayitlarina_erisemez(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');
        ['site' => $b] = $this->site('B');
        $this->aidat($a, '2026-09', 1000);
        $this->aidat($b, '2026-09', 1000);

        $muhasebeA = $this->kullanici(Rol::MuhasebeSorumlusu, $a);
        $this->actingAs($muhasebeA);

        $this->assertEqualsCanonicalizing([$a->id], Site::yetkili()->pluck('id')->all());
        $this->assertSame([$a->id], Borc::yetkili()->pluck('site_id')->unique()->values()->all());

        $borcB = Borc::where('site_id', $b->id)->first();
        $this->assertFalse(Gate::allows('view', $borcB));
        $this->assertTrue(Gate::allows('view', Borc::where('site_id', $a->id)->first()));
        $this->assertFalse(Gate::allows('view', $b));
    }

    public function test_mali_kayitlar_politikada_da_duzenlenemez(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');
        $this->aidat($a, '2026-09', 1000);

        $this->assertFalse(Gate::allows('update', Borc::first()));
        $this->assertFalse(Gate::allows('delete', Borc::first()));
    }

    public function test_emlak_danismani_site_yonetimini_gormez(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');

        $this->actingAs($this->kullanici(Rol::EmlakDanismani));

        $this->assertFalse(Gate::allows('viewAny', Site::class));
        $this->assertFalse(Gate::allows('viewAny', Borc::class));
        $this->assertSame([], Site::yetkili()->pluck('id')->all());
    }

    public function test_saha_personeli_yalniz_kendi_is_emrini_gorur_ve_tamamlar(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');
        $saha = $this->kullanici(Rol::SahaPersoneli, $a);
        $digerSaha = $this->kullanici(Rol::SahaPersoneli, $a);

        $benim = IsEmri::create(['site_id' => $a->id, 'konu' => 'Hidrofor arızası', 'atanan_id' => $saha->id]);
        $baskasi = IsEmri::create(['site_id' => $a->id, 'konu' => 'Lamba', 'atanan_id' => $digerSaha->id]);

        $this->assertSame('atandi', $benim->durum);

        $this->actingAs($saha);
        $this->assertTrue(Gate::allows('view', $benim));
        $this->assertFalse(Gate::allows('view', $baskasi));
        $this->assertTrue(Gate::allows('tamamla', $benim));
        $this->assertFalse(Gate::allows('kapat', $benim));
        $this->assertFalse(Gate::allows('create', IsEmri::class));

        $benim->tamamla('Salmastra değişti');
        $this->assertSame('tamamlandi', $benim->fresh()->durum);
    }

    public function test_is_emri_baska_sitenin_bolumune_baglanamaz(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');
        ['bolumler' => $bBolumleri] = $this->site('B');

        $this->expectException(\DomainException::class);
        IsEmri::create(['site_id' => $a->id, 'konu' => 'x', 'bagimsiz_bolum_id' => $bBolumleri[0]->id]);
    }

    public function test_site_belgesi_surumlenir_ve_muhasebe_belgesini_site_yoneticisi_gormez(): void
    {
        $this->yonetici();
        ['site' => $a] = $this->site('A');

        $v1 = SiteBelgesi::create(['site_id' => $a->id, 'kategori' => 'karar', 'ad' => 'Genel kurul kararı', 'yol' => 'x/1.pdf', 'erisim' => 'muhasebe']);
        $v2 = $v1->yeniSurum('x/2.pdf');

        $this->assertSame(2, $v2->surum);
        $this->assertFalse($v1->fresh()->sonSurumMu());
        $this->assertSame(2, SiteBelgesi::where('grup', $v1->grup)->count());

        $this->actingAs($this->kullanici(Rol::SiteYoneticisi, $a));
        $this->assertFalse(Gate::allows('view', $v2));
        $this->assertFalse(Gate::allows('delete', $v2));
    }
}
