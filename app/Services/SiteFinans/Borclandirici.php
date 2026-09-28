<?php

namespace App\Services\SiteFinans;

use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Borclandirma;
use App\Models\IslemKaydi;
use App\Support\Donem;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Toplu borçlandırma (şartname 8.2).
 *
 *  - onizle(): bölüm bazında tutarlar + toplam kontrolü; hiçbir şey yazmaz.
 *  - kesinlestir(): borçları açar. Aynı iş İKİNCİ kez çalıştırılırsa mükerrer
 *    borç oluşmaz: kilitli satırda durum kontrolü + (borclandirma, bölüm)
 *    benzersiz anahtarı + (site, dönem, tür, etiket) benzersiz anahtarı.
 *  - m² / arsa payı dağıtımında kuruş yuvarlaması "en büyük kalan"
 *    yöntemiyle yapılır: bölüm tutarlarının toplamı girilen toplama TAM eşittir.
 */
class Borclandirici
{
    /**
     * @return Collection<int, array{bolum: BagimsizBolum, kisi_id: ?int, tutar_kurus: int, uyari: ?string}>
     */
    public function onizle(Borclandirma $b): Collection
    {
        $bolumler = BagimsizBolum::query()
            ->where('site_id', $b->site_id)
            ->where('aktif', true)
            ->when($b->bolum_turleri, fn ($q, $turler) => $q->whereIn('tur', $turler))
            ->with(['blok', 'iliskiler'])
            // Doğal sıra (2 < 10) — CAST(.. AS INTEGER) MySQL'de geçersiz, uzunluk + metin taşınabilir
            ->orderBy('blok_id')->orderByRaw('LENGTH(no)')->orderBy('no')
            ->get();

        if ($bolumler->isEmpty()) {
            throw new DomainException('Bu sitede borçlandırılacak etkin bağımsız bölüm yok.');
        }

        $tutarlar = match ($b->dagitim) {
            'sabit' => $this->sabit($b, $bolumler),
            'm2' => $this->oransal($b, $bolumler, fn (BagimsizBolum $x) => (float) $x->brut_m2, 'metrekaresi'),
            'arsa_payi' => $this->oransal($b, $bolumler, fn (BagimsizBolum $x) => $x->arsa_payi && $x->arsa_payi_payda ? $x->arsa_payi / $x->arsa_payi_payda : 0.0, 'arsa payı'),
            'ozel' => $this->ozel($b, $bolumler),
            default => throw new DomainException('Bilinmeyen dağıtım yöntemi.'),
        };

        $sorumluGunu = $b->donem.'-01';

        return $bolumler->map(fn (BagimsizBolum $bolum) => [
            'bolum' => $bolum,
            'kisi_id' => $bolum->sorumluKisiId($sorumluGunu),
            'tutar_kurus' => $tutarlar[$bolum->id] ?? 0,
            'uyari' => $bolum->sorumluKisiId($sorumluGunu) ? null : 'Bu dönem için malik / borç sorumlusu kaydı yok',
        ])->filter(fn ($s) => $s['tutar_kurus'] > 0)->values();
    }

    public function kesinlestir(Borclandirma $b, ?string $gerekce = null): Borclandirma
    {
        return DB::transaction(function () use ($b, $gerekce) {
            /** @var Borclandirma $kilitli */
            $kilitli = Borclandirma::query()->whereKey($b->id)->lockForUpdate()->firstOrFail();

            if ($kilitli->durum === 'kesin') {
                return $kilitli; // tekrar çalıştırma: hiçbir şey yapma (idempotent)
            }
            if ($kilitli->durum === 'iptal') {
                throw new DomainException('İptal edilmiş borçlandırma kesinleştirilemez.');
            }
            if (! Donem::gecerli($kilitli->donem)) {
                throw new DomainException('Dönem YYYY-AA biçiminde olmalı.');
            }

            Donem::yazilabilir($kilitli->site, $kilitli->donem, $gerekce);

            $satirlar = $this->onizle($kilitli);

            foreach ($satirlar as $s) {
                Borc::query()->firstOrCreate(
                    ['borclandirma_id' => $kilitli->id, 'bagimsiz_bolum_id' => $s['bolum']->id],
                    [
                        'site_id' => $kilitli->site_id,
                        'kisi_id' => $s['kisi_id'],
                        'tur' => $kilitli->tur,
                        'donem' => $kilitli->donem,
                        'aciklama' => $kilitli->aciklama,
                        'vade' => $kilitli->vade,
                        'tutar_kurus' => $s['tutar_kurus'],
                        'para_birimi' => $kilitli->para_birimi,
                        'gerekce' => $gerekce,
                    ],
                );
            }

            $kilitli->forceFill([
                'durum' => 'kesin',
                'kesinlestiren_id' => auth()->id(),
                'kesinlesme_zamani' => now(),
            ])->save();

            IslemKaydi::yaz('guncellendi', $kilitli, null, [
                'kesinlestirildi' => true,
                'borc_sayisi' => $satirlar->count(),
                'toplam_kurus' => $satirlar->sum('tutar_kurus'),
            ], 'Borçlandırma kesinleşti: '.$kilitli->aciklama);

            // Önceden ödenmiş avans varsa yeni borçlara mahsup et (FIFO)
            $tahsilat = app(TahsilatServisi::class);
            foreach ($satirlar as $s) {
                $tahsilat->avansMahsup($s['bolum']);
            }

            return $kilitli->refresh();
        });
    }

    /** @return array<int, int> */
    private function sabit(Borclandirma $b, Collection $bolumler): array
    {
        if (! $b->birim_kurus || $b->birim_kurus <= 0) {
            throw new DomainException('Bölüm başı tutar girilmeli.');
        }

        return $bolumler->mapWithKeys(fn ($x) => [$x->id => $b->birim_kurus])->all();
    }

    /** @return array<int, int> */
    private function ozel(Borclandirma $b, Collection $bolumler): array
    {
        $ozel = collect($b->ozel_tutarlar ?? [])->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]);

        $yabanci = $ozel->keys()->diff($bolumler->pluck('id'));
        if ($yabanci->isNotEmpty()) {
            throw new DomainException('Özel dağıtımda bu siteye ait olmayan bölüm var.');
        }
        if ($ozel->contains(fn ($v) => $v < 0)) {
            throw new DomainException('Özel dağıtımda eksi tutar olamaz.');
        }

        return $ozel->all();
    }

    /**
     * Toplamı ağırlığa göre böl; kuruşları en büyük kalan yöntemiyle dağıt.
     *
     * @return array<int, int>
     */
    private function oransal(Borclandirma $b, Collection $bolumler, callable $agirlik, string $ad): array
    {
        if (! $b->toplam_kurus || $b->toplam_kurus <= 0) {
            throw new DomainException('Dağıtılacak toplam tutar girilmeli.');
        }

        $agirliklar = $bolumler->mapWithKeys(fn ($x) => [$x->id => max(0.0, (float) $agirlik($x))]);
        $eksik = $agirliklar->filter(fn ($a) => $a <= 0)->keys();
        if ($eksik->isNotEmpty()) {
            $etiketler = $bolumler->whereIn('id', $eksik)->map->etiket()->implode(', ');
            throw new DomainException("Şu bölümlerin {$ad} girilmemiş: {$etiketler}");
        }

        $toplamAgirlik = $agirliklar->sum();
        $ham = $agirliklar->map(fn ($a) => $b->toplam_kurus * $a / $toplamAgirlik);
        $taban = $ham->map(fn ($v) => (int) floor($v));
        $artan = $b->toplam_kurus - $taban->sum();

        // Kalanı en büyük küsurlu bölümlere birer kuruş dağıt (eşitlikte küçük id önce)
        $sira = $ham->map(fn ($v, $id) => ['id' => $id, 'kusur' => $v - floor($v)])
            ->sort(fn ($x, $y) => [$y['kusur'], $x['id']] <=> [$x['kusur'], $y['id']])
            ->values();

        $sonuc = $taban->all();
        for ($i = 0; $i < $artan; $i++) {
            $sonuc[$sira[$i % $sira->count()]['id']]++;
        }

        return $sonuc;
    }
}
