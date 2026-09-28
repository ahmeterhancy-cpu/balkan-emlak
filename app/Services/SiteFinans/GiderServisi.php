<?php

namespace App\Services\SiteFinans;

use App\Models\Gider;
use App\Models\GiderOdemesi;
use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Models\IslemKaydi;
use App\Models\User;
use App\Support\Donem;
use App\Support\Para;
use App\Support\Yetki;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gider onayı ve ödemesi (şartname 8.4).
 *   Onay: Taslak → Onay bekliyor → Onaylandı / Reddedildi
 *   Ödeme: Ödenmedi → Kısmen ödendi → Ödendi (ödemelerden hesaplanır)
 *
 * Onay limiti: sitenin `onay_limiti_kurus` değerini aşan gideri yalnız
 * `gider.onayla_limitsiz` izni olan (şirket yöneticisi) onaylar. Limit
 * girilmemişse `gider.onayla` izni yeterlidir.
 */
class GiderServisi
{
    public function onayaGonder(Gider $g): Gider
    {
        if (! in_array($g->onay_durumu, ['taslak', 'reddedildi'], true)) {
            throw new DomainException('Yalnız taslak ya da reddedilmiş gider onaya gönderilebilir.');
        }

        Donem::yazilabilir($g->site, $g->donem);
        $g->forceFill(['onay_durumu' => 'onay_bekliyor', 'red_gerekcesi' => null])->save();

        return $g;
    }

    public function limitAsiliyorMu(Gider $g): bool
    {
        $limit = $g->site->onay_limiti_kurus;

        return $limit !== null && $g->tutar_kurus > $limit;
    }

    public function onaylayabilirMi(Gider $g, ?User $k = null): bool
    {
        if (! Yetki::var('gider.onayla', $k) && ! Yetki::var('gider.onayla_limitsiz', $k)) {
            return false;
        }

        return ! $this->limitAsiliyorMu($g) || Yetki::var('gider.onayla_limitsiz', $k);
    }

    public function onayla(Gider $g, ?User $k = null): Gider
    {
        if ($g->onay_durumu !== 'onay_bekliyor') {
            throw new DomainException('Yalnız onay bekleyen gider onaylanabilir.');
        }
        if (! $this->onaylayabilirMi($g, $k)) {
            throw new DomainException($this->limitAsiliyorMu($g)
                ? 'Bu gider site onay limitini ('.Para::bicimle($g->site->onay_limiti_kurus, $g->para_birimi).') aşıyor; şirket yöneticisi onayı gerekir.'
                : 'Gider onaylama yetkiniz yok.');
        }

        $g->forceFill(['onay_durumu' => 'onaylandi', 'onaylayan_id' => ($k ?? auth()->user())?->id, 'onay_zamani' => now()])->save();

        return $g;
    }

    public function reddet(Gider $g, string $gerekce, ?User $k = null): Gider
    {
        if ($g->onay_durumu !== 'onay_bekliyor') {
            throw new DomainException('Yalnız onay bekleyen gider reddedilebilir.');
        }
        if (blank($gerekce)) {
            throw new DomainException('Ret gerekçesi zorunlu.');
        }
        if (! Yetki::var('gider.onayla', $k) && ! Yetki::var('gider.onayla_limitsiz', $k)) {
            throw new DomainException('Gider onaylama yetkiniz yok.');
        }

        $g->forceFill(['onay_durumu' => 'reddedildi', 'red_gerekcesi' => $gerekce, 'onaylayan_id' => ($k ?? auth()->user())?->id, 'onay_zamani' => now()])->save();

        return $g;
    }

    public function ode(Gider $g, Hesap $hesap, int $tutarKurus, string $tarih, ?string $aciklama = null, ?string $gerekce = null): GiderOdemesi
    {
        return DB::transaction(function () use ($g, $hesap, $tutarKurus, $tarih, $aciklama, $gerekce) {
            $gider = Gider::query()->whereKey($g->id)->lockForUpdate()->firstOrFail();

            if ($gider->onay_durumu !== 'onaylandi') {
                throw new DomainException('Onaylanmamış gider ödenemez.');
            }
            if ((int) $hesap->site_id !== (int) $gider->site_id) {
                throw new DomainException('Ödeme hesabı giderle aynı siteye ait olmalı.');
            }
            if ($hesap->para_birimi !== $gider->para_birimi) {
                throw new DomainException('Hesap ve gider para birimi farklı; kur belirtilmeden ödeme yapılamaz.');
            }
            if ($tutarKurus <= 0) {
                throw new DomainException('Ödeme tutarı sıfırdan büyük olmalı.');
            }
            if ($tutarKurus > $gider->kalanKurus()) {
                throw new DomainException('Ödeme, giderin kalan tutarını ('.Para::bicimle($gider->kalanKurus(), $gider->para_birimi).') aşamaz.');
            }

            Donem::yazilabilir($gider->site, $tarih, $gerekce);

            $odeme = GiderOdemesi::create([
                'gider_id' => $gider->id, 'hesap_id' => $hesap->id, 'tarih' => $tarih, 'tutar_kurus' => $tutarKurus,
                'aciklama' => $aciklama, 'olusturan_id' => auth()->id(),
            ]);

            HesapHareketi::create([
                'site_id' => $gider->site_id, 'hesap_id' => $hesap->id, 'tarih' => $tarih, 'tutar_kurus' => -$tutarKurus,
                'tur' => 'gider_odemesi', 'kaynak_type' => GiderOdemesi::class, 'kaynak_id' => $odeme->id,
                'aciklama' => 'Gider ödemesi — '.$gider->aciklama, 'olusturan_id' => auth()->id(),
            ]);

            return $odeme;
        });
    }

    /** Hatalı ödemeyi karşı kayıtla geri al. */
    public function odemeyiGeriAl(GiderOdemesi $o, string $gerekce, string $tarih): GiderOdemesi
    {
        if (blank($gerekce)) {
            throw new DomainException('Geri alma gerekçesi zorunlu.');
        }
        if ($o->tutar_kurus <= 0 || GiderOdemesi::where('iptal_edilen_id', $o->id)->exists()) {
            throw new DomainException('Bu ödeme zaten geri alınmış ya da bir geri alma kaydı.');
        }

        return DB::transaction(function () use ($o, $gerekce, $tarih) {
            Donem::yazilabilir($o->gider->site, $tarih, $gerekce);

            $ters = GiderOdemesi::create([
                'gider_id' => $o->gider_id, 'hesap_id' => $o->hesap_id, 'tarih' => $tarih, 'tutar_kurus' => -$o->tutar_kurus,
                'aciklama' => 'Geri alındı: '.$gerekce, 'iptal_edilen_id' => $o->id, 'olusturan_id' => auth()->id(),
            ]);

            HesapHareketi::create([
                'site_id' => $o->gider->site_id, 'hesap_id' => $o->hesap_id, 'tarih' => $tarih, 'tutar_kurus' => $o->tutar_kurus,
                'tur' => 'duzeltme', 'kaynak_type' => GiderOdemesi::class, 'kaynak_id' => $ters->id,
                'aciklama' => 'Gider ödemesi geri alındı — '.$gerekce, 'olusturan_id' => auth()->id(),
            ]);

            IslemKaydi::yaz('guncellendi', $o->gider, null, ['odeme_geri_alindi' => $o->id], 'Gider ödemesi geri alındı: '.$gerekce);

            return $ters;
        });
    }
}
