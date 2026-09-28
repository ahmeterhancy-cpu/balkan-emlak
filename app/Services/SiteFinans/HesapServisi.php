<?php

namespace App\Services\SiteFinans;

use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Models\IslemKaydi;
use App\Models\Site;
use App\Models\Transfer;
use App\Support\Donem;
use App\Support\Yetki;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Kasa / banka: açılış bakiyesi, transfer, mutabakat, dönem kapama.
 * Transfer iki hareket yazar (−kaynak, +hedef); raporlarda gelir/gider
 * sayılmaz (şartname 8.3).
 */
class HesapServisi
{
    public function acilisBakiyesi(Hesap $hesap, int $tutarKurus, string $tarih, string $dayanak): HesapHareketi
    {
        if (blank($dayanak)) {
            throw new DomainException('Açılış bakiyesi için dayanak (ör. devir tutanağı, banka dökümü) zorunlu.');
        }
        if (HesapHareketi::where('hesap_id', $hesap->id)->where('tur', 'acilis')->exists()) {
            throw new DomainException('Bu hesabın açılış bakiyesi zaten girilmiş; değişiklik düzeltme kaydıyla yapılır.');
        }

        Donem::yazilabilir($hesap->site, $tarih, $dayanak);

        return HesapHareketi::create([
            'site_id' => $hesap->site_id, 'hesap_id' => $hesap->id, 'tarih' => $tarih, 'tutar_kurus' => $tutarKurus,
            'tur' => 'acilis', 'aciklama' => 'Açılış bakiyesi', 'dayanak' => $dayanak, 'olusturan_id' => auth()->id(),
        ]);
    }

    /** Sayım / banka farkı gibi gerekçeli düzeltme hareketi. */
    public function duzeltme(Hesap $hesap, int $tutarKurus, string $tarih, string $gerekce): HesapHareketi
    {
        if (blank($gerekce) || $tutarKurus === 0) {
            throw new DomainException('Düzeltme için tutar ve gerekçe zorunlu.');
        }

        Donem::yazilabilir($hesap->site, $tarih, $gerekce);

        return HesapHareketi::create([
            'site_id' => $hesap->site_id, 'hesap_id' => $hesap->id, 'tarih' => $tarih, 'tutar_kurus' => $tutarKurus,
            'tur' => 'duzeltme', 'aciklama' => 'Düzeltme', 'dayanak' => $gerekce, 'olusturan_id' => auth()->id(),
        ]);
    }

    public function transfer(Hesap $kaynak, Hesap $hedef, int $tutarKurus, string $tarih, ?string $aciklama = null): Transfer
    {
        if ($kaynak->is($hedef)) {
            throw new DomainException('Kaynak ve hedef hesap aynı olamaz.');
        }
        if ((int) $kaynak->site_id !== (int) $hedef->site_id) {
            throw new DomainException('Farklı sitelerin hesapları arasında transfer yapılamaz; site paraları ayrı tutulur.');
        }
        if ($kaynak->para_birimi !== $hedef->para_birimi) {
            throw new DomainException('Farklı para birimli hesaplar arasında kur belirtilmeden transfer yapılamaz.');
        }
        if ($tutarKurus <= 0) {
            throw new DomainException('Transfer tutarı sıfırdan büyük olmalı.');
        }

        return DB::transaction(function () use ($kaynak, $hedef, $tutarKurus, $tarih, $aciklama) {
            Donem::yazilabilir($kaynak->site, $tarih);

            $t = Transfer::create([
                'site_id' => $kaynak->site_id, 'kaynak_hesap_id' => $kaynak->id, 'hedef_hesap_id' => $hedef->id,
                'tarih' => $tarih, 'tutar_kurus' => $tutarKurus, 'aciklama' => $aciklama, 'olusturan_id' => auth()->id(),
            ]);

            foreach ([[$kaynak, -$tutarKurus, 'Transfer → '.$hedef->ad], [$hedef, $tutarKurus, 'Transfer ← '.$kaynak->ad]] as [$h, $tutar, $metin]) {
                HesapHareketi::create([
                    'site_id' => $h->site_id, 'hesap_id' => $h->id, 'tarih' => $tarih, 'tutar_kurus' => $tutar, 'tur' => 'transfer',
                    'kaynak_type' => Transfer::class, 'kaynak_id' => $t->id, 'aciklama' => $metin, 'olusturan_id' => auth()->id(),
                ]);
            }

            return $t;
        });
    }

    /** @param iterable<HesapHareketi> $hareketler */
    public function mutabakat(iterable $hareketler, bool $mutabik, ?string $tarih = null): int
    {
        $adet = 0;
        foreach ($hareketler as $h) {
            $h->update(['mutabik' => $mutabik, 'mutabakat_tarihi' => $mutabik ? ($tarih ?? now()->toDateString()) : null]);
            $adet++;
        }

        return $adet;
    }

    /**
     * Dönemi kapat: bu aya kadar (dahil) mali kayıt açılamaz. Geriye alınamaz
     * değil ama yalnız `donem.duzelt` ile ve gerekçeyle yeniden açılır.
     */
    public function donemKapat(Site $site, string $donem): Site
    {
        if (! Yetki::var('donem.kapat')) {
            throw new DomainException('Dönem kapama yetkiniz yok.');
        }
        if (! Donem::gecerli($donem)) {
            throw new DomainException('Dönem YYYY-AA biçiminde olmalı.');
        }
        if ($donem >= now()->format('Y-m')) {
            throw new DomainException('İçinde bulunulan ya da ileri bir dönem kapatılamaz.');
        }
        if ($site->kapali_donem !== null && $donem <= $site->kapali_donem) {
            throw new DomainException(Donem::ad($site->kapali_donem).' zaten kapalı.');
        }

        $site->update(['kapali_donem' => $donem]);

        return $site;
    }

    public function donemAc(Site $site, string $donem, string $gerekce): Site
    {
        if (! Yetki::var('donem.duzelt')) {
            throw new DomainException('Kapalı dönemi açma yetkiniz yok.');
        }
        if (blank($gerekce)) {
            throw new DomainException('Kapalı dönemi açmak için gerekçe zorunlu.');
        }

        $onceki = $site->kapali_donem;
        $yeni = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $donem.'-01')->subMonth()->format('Y-m');
        $site->update(['kapali_donem' => $yeni >= '2000-01' ? $yeni : null]);

        IslemKaydi::yaz('guncellendi', $site, ['kapali_donem' => $onceki], ['kapali_donem' => $site->kapali_donem], 'Dönem yeniden açıldı: '.$gerekce);

        return $site;
    }
}
