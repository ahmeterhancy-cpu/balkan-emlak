<?php

namespace Tests\Feature\SiteFinans;

use App\Enums\Rol;
use App\Models\BagimsizBolum;
use App\Models\Blok;
use App\Models\BolumIliskisi;
use App\Models\Borclandirma;
use App\Models\Hesap;
use App\Models\Kisi;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteFinans\Borclandirici;
use App\Services\SiteFinans\TahsilatServisi;

/** Finans testleri için site + blok + bölüm + malik + hesap kurulumu. */
trait SiteKurulumu
{
    protected function yonetici(): User
    {
        $k = User::factory()->create(['rol' => Rol::SistemYoneticisi]);
        $this->actingAs($k);

        return $k;
    }

    protected function kullanici(Rol $rol, ?Site $atanacak = null): User
    {
        $k = User::factory()->create(['rol' => $rol]);
        $atanacak?->kullanicilar()->attach($k->id);

        return $k;
    }

    /** @return array{site: Site, bolumler: \Illuminate\Support\Collection<int, BagimsizBolum>, hesap: Hesap, kasa: Hesap} */
    protected function site(string $ad = 'Deneme Sitesi', int $bolumSayisi = 3, array $m2 = [100, 120, 80]): array
    {
        $site = Site::create(['ad' => $ad, 'tur' => 'site']);
        $blok = Blok::create(['site_id' => $site->id, 'ad' => 'A Blok']);

        $bolumler = collect(range(1, $bolumSayisi))->map(function ($i) use ($site, $blok, $m2) {
            $b = BagimsizBolum::create([
                'site_id' => $site->id, 'blok_id' => $blok->id, 'no' => (string) $i, 'tur' => 'daire',
                'brut_m2' => $m2[$i - 1] ?? 100, 'arsa_payi' => $i, 'arsa_payi_payda' => 6,
            ]);
            $malik = Kisi::create(['ad_soyad' => "Malik {$i}"]);
            BolumIliskisi::create(['bagimsiz_bolum_id' => $b->id, 'kisi_id' => $malik->id, 'rol' => 'malik', 'baslangic' => '2020-01-01']);

            return $b;
        });

        $hesap = Hesap::create(['site_id' => $site->id, 'ad' => 'Banka', 'tur' => 'banka', 'para_birimi' => 'TRY']);
        $kasa = Hesap::create(['site_id' => $site->id, 'ad' => 'Kasa', 'tur' => 'kasa', 'para_birimi' => 'TRY']);

        return ['site' => $site, 'bolumler' => $bolumler, 'hesap' => $hesap, 'kasa' => $kasa];
    }

    protected function aidat(Site $site, string $donem, int $tlBolumBasi, array $ek = []): Borclandirma
    {
        $b = Borclandirma::create($ek + [
            'site_id' => $site->id, 'donem' => $donem, 'tur' => 'aidat', 'aciklama' => "$donem aidatı",
            'vade' => $donem.'-10', 'dagitim' => 'sabit', 'birim_kurus' => $tlBolumBasi * 100,
        ]);

        return app(Borclandirici::class)->kesinlestir($b);
    }

    protected function tahsil(Hesap $hesap, BagimsizBolum $bolum, int $tl, string $tarih = '2026-09-15', array $ek = []): \App\Models\Tahsilat
    {
        return app(TahsilatServisi::class)->kaydet($ek + [
            'hesap_id' => $hesap->id, 'bagimsiz_bolum_id' => $bolum->id, 'tarih' => $tarih,
            'tutar_kurus' => $tl * 100, 'yontem' => 'havale',
        ]);
    }
}
