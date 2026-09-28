<?php

namespace App\Services\SiteFinans;

use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Models\Tahsilat;
use App\Models\TahsilatDagitimi;
use App\Support\Donem;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tahsilat, borca dağıtım, avans ve iade (şartname 8.3).
 *
 * Borç kapama kuralı (işletme kuralı, tek yerde): aynı para birimindeki açık
 * borçlar VADESİ EN ESKİ olandan başlayarak kapatılır; eşit vadede önce açılan.
 * Kullanıcı isterse borç sırasını elle verir. Kalan tutar AVANS olarak
 * tahsilatın üzerinde "dağıtılmamış" durur; gelir sayılmaz, yeni borç
 * açıldığında mahsup edilir.
 */
class TahsilatServisi
{
    /**
     * Dağıtım önizlemesi: hangi borca ne kadar, ne kadar avans kalır.
     *
     * @param  list<int>|null  $borcSirasi
     * @return array{satirlar: list<array{borc: Borc, tutar_kurus: int, kalan_sonra: int}>, avans_kurus: int}
     */
    public function onizle(BagimsizBolum $bolum, int $tutarKurus, string $paraBirimi = 'TRY', ?array $borcSirasi = null): array
    {
        $kalan = $tutarKurus;
        $satirlar = [];

        foreach ($this->acikBorclar($bolum, $paraBirimi, $borcSirasi) as $borc) {
            if ($kalan <= 0) {
                break;
            }
            $ayrilan = min($kalan, $borc->kalanKurus());
            $satirlar[] = ['borc' => $borc, 'tutar_kurus' => $ayrilan, 'kalan_sonra' => $borc->kalanKurus() - $ayrilan];
            $kalan -= $ayrilan;
        }

        return ['satirlar' => $satirlar, 'avans_kurus' => $kalan];
    }

    /**
     * @param array{hesap_id:int, bagimsiz_bolum_id:int, tarih:string, tutar_kurus:int, yontem:string, kisi_id?:?int,
     *              aciklama?:?string, belge_yolu?:?string, dogrulandi?:bool, gerekce?:?string, borc_sirasi?:?array} $veri
     */
    public function kaydet(array $veri): Tahsilat
    {
        return DB::transaction(function () use ($veri) {
            $bolum = BagimsizBolum::query()->whereKey($veri['bagimsiz_bolum_id'])->lockForUpdate()->firstOrFail();
            $hesap = Hesap::findOrFail($veri['hesap_id']);

            if ((int) $hesap->site_id !== (int) $bolum->site_id) {
                throw new DomainException('Tahsilat hesabı ile bağımsız bölüm aynı siteye ait olmalı.');
            }
            if (! $hesap->aktif) {
                throw new DomainException('Pasif hesaba tahsilat girilemez.');
            }
            if ((int) $veri['tutar_kurus'] <= 0) {
                throw new DomainException('Tahsilat tutarı sıfırdan büyük olmalı.');
            }

            Donem::yazilabilir($bolum->site, $veri['tarih'], $veri['gerekce'] ?? null);

            $dogrulandi = $veri['dogrulandi'] ?? true;

            $tahsilat = Tahsilat::create([
                'site_id' => $bolum->site_id,
                'hesap_id' => $hesap->id,
                'bagimsiz_bolum_id' => $bolum->id,
                'kisi_id' => $veri['kisi_id'] ?? $bolum->sorumluKisiId($veri['tarih']),
                'tur' => 'tahsilat',
                'tarih' => $veri['tarih'],
                'tutar_kurus' => (int) $veri['tutar_kurus'],
                'para_birimi' => $hesap->para_birimi,
                'yontem' => $veri['yontem'],
                'aciklama' => $veri['aciklama'] ?? null,
                'belge_yolu' => $veri['belge_yolu'] ?? null,
                'dogrulandi' => $dogrulandi,
                'gerekce' => $veri['gerekce'] ?? null,
                'olusturan_id' => auth()->id(),
            ]);

            if ($dogrulandi) {
                $this->isle($tahsilat, $veri['borc_sirasi'] ?? null);
            }

            return $tahsilat->refresh();
        });
    }

    /** Dekontla bildirilen tahsilatı doğrula: ancak şimdi kasaya ve borca işlenir. */
    public function dogrula(Tahsilat $tahsilat): Tahsilat
    {
        return DB::transaction(function () use ($tahsilat) {
            $t = Tahsilat::query()->whereKey($tahsilat->id)->lockForUpdate()->firstOrFail();

            if ($t->dogrulandi) {
                return $t;
            }

            $t->update(['dogrulandi' => true]);
            $this->isle($t);

            return $t->refresh();
        });
    }

    /**
     * İade / hatalı tahsilat düzeltmesi. Tutar verilmezse tamamı: borç
     * dağıtımları eksi satırla geri alınır, kasadan çıkış yazılır. Tutar
     * verilirse yalnız AVANS (dağıtılmamış) kısmından iade yapılabilir.
     */
    public function iadeEt(Tahsilat $tahsilat, string $gerekce, string $tarih, ?int $tutarKurus = null): Tahsilat
    {
        if (blank($gerekce)) {
            throw new DomainException('İade için gerekçe zorunlu.');
        }

        return DB::transaction(function () use ($tahsilat, $gerekce, $tarih, $tutarKurus) {
            $t = Tahsilat::query()->whereKey($tahsilat->id)->lockForUpdate()->firstOrFail();

            if ($t->tur !== 'tahsilat' || ! $t->dogrulandi) {
                throw new DomainException('Yalnız doğrulanmış tahsilat iade edilebilir.');
            }

            $net = $t->netTutarKurus();
            if ($net <= 0) {
                throw new DomainException('Bu tahsilat zaten tamamen iade edilmiş.');
            }

            Donem::yazilabilir($t->site, $tarih, $gerekce);

            $tamIade = $tutarKurus === null || $tutarKurus === $net;
            $iadeTutari = $tamIade ? $net : $tutarKurus;

            if ($iadeTutari <= 0) {
                throw new DomainException('İade tutarı sıfırdan büyük olmalı.');
            }
            if (! $tamIade && $iadeTutari > $t->dagitilmamisKurus()) {
                throw new DomainException('Kısmi iade yalnız borca dağıtılmamış (avans) tutardan yapılabilir. Tamamını iade edip yeniden girin.');
            }

            if ($tamIade) {
                // Borçlara yapılmış net dağıtımları asıl tahsilat üzerinde eksi satırla geri al
                $netler = TahsilatDagitimi::where('tahsilat_id', $t->id)
                    ->selectRaw('borc_id, SUM(tutar_kurus) AS toplam')->groupBy('borc_id')->get();

                foreach ($netler as $n) {
                    if ((int) $n->toplam !== 0) {
                        TahsilatDagitimi::create([
                            'tahsilat_id' => $t->id, 'borc_id' => $n->borc_id, 'tutar_kurus' => -((int) $n->toplam),
                            'tarih' => $tarih, 'olusturan_id' => auth()->id(),
                        ]);
                    }
                }
            }

            $iade = Tahsilat::create([
                'site_id' => $t->site_id,
                'hesap_id' => $t->hesap_id,
                'bagimsiz_bolum_id' => $t->bagimsiz_bolum_id,
                'kisi_id' => $t->kisi_id,
                'tur' => 'iade',
                'iade_edilen_id' => $t->id,
                'tarih' => $tarih,
                'tutar_kurus' => -$iadeTutari,
                'para_birimi' => $t->para_birimi,
                'yontem' => $t->yontem,
                'aciklama' => ($tamIade ? 'İade: ' : 'Avans iadesi: ').($t->aciklama ?: '#'.$t->id),
                'dogrulandi' => true,
                'gerekce' => $gerekce,
                'olusturan_id' => auth()->id(),
            ]);

            HesapHareketi::create([
                'site_id' => $t->site_id,
                'hesap_id' => $t->hesap_id,
                'tarih' => $tarih,
                'tutar_kurus' => -$iadeTutari,
                'tur' => 'tahsilat_iade',
                'kaynak_type' => Tahsilat::class,
                'kaynak_id' => $iade->id,
                'aciklama' => 'Tahsilat iadesi — '.$gerekce,
                'olusturan_id' => auth()->id(),
            ]);

            return $iade;
        });
    }

    /**
     * Bölümün avansını (dağıtılmamış tahsilatlarını) açık borçlara uygula.
     * Borçlandırma kesinleşince ve borç düzeltmesinden sonra çağrılır.
     */
    public function avansMahsup(BagimsizBolum $bolum): int
    {
        $toplam = 0;

        foreach ($this->avansKaynaklari($bolum) as $t) {
            $toplam += $this->dagit($t, $t->dagitilmamisKurus(), null, now()->toDateString());
        }

        return $toplam;
    }

    /** Bölümün toplam avansı (para birimine göre). @return array<string, int> */
    public function avans(BagimsizBolum $bolum): array
    {
        $sonuc = [];
        foreach ($this->avansKaynaklari($bolum, false) as $t) {
            $sonuc[$t->para_birimi] = ($sonuc[$t->para_birimi] ?? 0) + $t->dagitilmamisKurus();
        }

        return array_filter($sonuc);
    }

    /* ------------------------------------------------------------------ */

    /** Tahsilatı kasaya yaz ve borçlara dağıt. */
    private function isle(Tahsilat $t, ?array $borcSirasi = null): void
    {
        HesapHareketi::create([
            'site_id' => $t->site_id,
            'hesap_id' => $t->hesap_id,
            'tarih' => $t->tarih,
            'tutar_kurus' => $t->tutar_kurus,
            'tur' => 'tahsilat',
            'kaynak_type' => Tahsilat::class,
            'kaynak_id' => $t->id,
            'aciklama' => 'Tahsilat — '.($t->bolum?->etiket() ?? ''),
            'olusturan_id' => auth()->id(),
        ]);

        $this->dagit($t, $t->tutar_kurus, $borcSirasi, $t->tarih->toDateString());
    }

    /** Tahsilattan en çok $tutar kadarını açık borçlara ayır; ayrılan toplamı döndür. */
    private function dagit(Tahsilat $t, int $tutar, ?array $borcSirasi, string $tarih): int
    {
        $plan = $this->onizle($t->bolum()->first(), $tutar, $t->para_birimi, $borcSirasi);

        foreach ($plan['satirlar'] as $s) {
            TahsilatDagitimi::create([
                'tahsilat_id' => $t->id,
                'borc_id' => $s['borc']->id,
                'tutar_kurus' => $s['tutar_kurus'],
                'tarih' => $tarih,
                'olusturan_id' => auth()->id(),
            ]);
        }

        return $tutar - $plan['avans_kurus'];
    }

    /** @return Collection<int, Borc> */
    private function acikBorclar(BagimsizBolum $bolum, string $paraBirimi, ?array $borcSirasi): Collection
    {
        $borclar = Borc::query()
            ->where('bagimsiz_bolum_id', $bolum->id)
            ->where('para_birimi', $paraBirimi)
            ->acik()
            ->bakiyeli()
            ->orderBy('vade')->orderBy('id')
            ->get();

        if ($borcSirasi) {
            $sira = array_flip(array_map('intval', $borcSirasi));
            $borclar = $borclar->sortBy(fn (Borc $b) => [$sira[$b->id] ?? PHP_INT_MAX, $b->vade->timestamp, $b->id])->values();
        }

        return $borclar;
    }

    /** Dağıtılmamış tutarı olan doğrulanmış tahsilatlar (eskiden yeniye). */
    private function avansKaynaklari(BagimsizBolum $bolum, bool $kilitle = true): Collection
    {
        return Tahsilat::query()
            ->where('bagimsiz_bolum_id', $bolum->id)
            ->where('tur', 'tahsilat')
            ->where('dogrulandi', true)
            ->when($kilitle, fn ($q) => $q->lockForUpdate())
            ->orderBy('tarih')->orderBy('id')
            ->get()
            ->filter(fn (Tahsilat $t) => $t->dagitilmamisKurus() > 0)
            ->values();
    }
}
