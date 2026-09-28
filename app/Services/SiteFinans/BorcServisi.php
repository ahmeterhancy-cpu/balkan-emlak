<?php

namespace App\Services\SiteFinans;

use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\TahsilatDagitimi;
use App\Support\Donem;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tekil borç, açılış bakiyesi (devir) ve düzeltme / iptal.
 *
 * İşlenmiş borç silinmez ve tutarı değiştirilmez: düzeltme, farkı taşıyan
 * gerekçeli karşı kayıttır. Yeni net tutar ödenmiş kısmın altına inerse
 * fazla ödeme borçtan çözülür ve avansa döner (şartname 8.2).
 */
class BorcServisi
{
    /**
     * Tek bölüme borç (ör. özel gider payı ya da yönetime devirde açılış bakiyesi).
     *
     * @param array{bagimsiz_bolum_id:int, tur:string, donem:string, aciklama:string, vade:string, tutar_kurus:int, para_birimi?:string, gerekce?:?string} $veri
     */
    public function olustur(array $veri): Borc
    {
        return DB::transaction(function () use ($veri) {
            $bolum = BagimsizBolum::findOrFail($veri['bagimsiz_bolum_id']);

            if ((int) $veri['tutar_kurus'] <= 0) {
                throw new DomainException('Borç tutarı sıfırdan büyük olmalı.');
            }
            if (! Donem::gecerli($veri['donem'])) {
                throw new DomainException('Dönem YYYY-AA biçiminde olmalı.');
            }
            if ($veri['tur'] === 'acilis' && blank($veri['gerekce'] ?? null)) {
                throw new DomainException('Açılış bakiyesi için dayanak / açıklama zorunlu.');
            }

            Donem::yazilabilir($bolum->site, $veri['donem'], $veri['gerekce'] ?? null);

            $borc = Borc::create([
                'site_id' => $bolum->site_id,
                'bagimsiz_bolum_id' => $bolum->id,
                'kisi_id' => $bolum->sorumluKisiId($veri['donem'].'-01'),
                'tur' => $veri['tur'],
                'donem' => $veri['donem'],
                'aciklama' => $veri['aciklama'],
                'vade' => $veri['vade'],
                'tutar_kurus' => (int) $veri['tutar_kurus'],
                'para_birimi' => $veri['para_birimi'] ?? $bolum->site->para_birimi,
                'gerekce' => $veri['gerekce'] ?? null,
            ]);

            app(TahsilatServisi::class)->avansMahsup($bolum);

            return $borc;
        });
    }

    /** Borcun net tutarını değiştir (0 = iptal). Gerekçe zorunlu. */
    public function duzelt(Borc $borc, int $yeniNetKurus, string $gerekce): ?Borc
    {
        if (blank($gerekce)) {
            throw new DomainException('Düzeltme için gerekçe zorunlu.');
        }
        if ($borc->duzeltilen_borc_id) {
            throw new DomainException('Düzeltme satırı yeniden düzeltilemez; asıl borcu düzeltin.');
        }
        if ($yeniNetKurus < 0) {
            throw new DomainException('Borç tutarı eksi olamaz.');
        }

        return DB::transaction(function () use ($borc, $yeniNetKurus, $gerekce) {
            $b = Borc::query()->whereKey($borc->id)->lockForUpdate()->firstOrFail();
            $fark = $yeniNetKurus - $b->netKurus();

            if ($fark === 0) {
                return null;
            }

            Donem::yazilabilir($b->site, $b->donem, $gerekce);

            $duzeltme = Borc::create([
                'site_id' => $b->site_id,
                'bagimsiz_bolum_id' => $b->bagimsiz_bolum_id,
                'kisi_id' => $b->kisi_id,
                'duzeltilen_borc_id' => $b->id,
                'tur' => $b->tur,
                'donem' => $b->donem,
                'aciklama' => ($yeniNetKurus === 0 ? 'İptal: ' : 'Düzeltme: ').$b->aciklama,
                'vade' => $b->vade,
                'tutar_kurus' => $fark,
                'para_birimi' => $b->para_birimi,
                'gerekce' => $gerekce,
            ]);

            // Ödenen, yeni tutarı aşıyorsa fazlayı en son yapılan dağıtımdan başlayarak çöz
            $fazla = $b->odenenKurus() - $yeniNetKurus;
            if ($fazla > 0) {
                $dagitimlar = TahsilatDagitimi::where('borc_id', $b->id)
                    ->selectRaw('tahsilat_id, SUM(tutar_kurus) AS toplam, MAX(id) AS son')
                    ->groupBy('tahsilat_id')->orderByDesc('son')->get();

                foreach ($dagitimlar as $d) {
                    if ($fazla <= 0) {
                        break;
                    }
                    $coz = min($fazla, (int) $d->toplam);
                    if ($coz > 0) {
                        TahsilatDagitimi::create([
                            'tahsilat_id' => $d->tahsilat_id, 'borc_id' => $b->id, 'tutar_kurus' => -$coz,
                            'tarih' => now()->toDateString(), 'olusturan_id' => auth()->id(),
                        ]);
                        $fazla -= $coz;
                    }
                }

                // Çözülen tutar avans oldu: bölümün diğer açık borçlarına uygula
                app(TahsilatServisi::class)->avansMahsup($b->bolum);
            }

            return $duzeltme;
        });
    }
}
