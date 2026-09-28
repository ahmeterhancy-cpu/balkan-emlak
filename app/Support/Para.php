<?php

namespace App\Support;

/**
 * Tutarlar veritabanında kuruş (tam sayı) olarak durur.
 *
 * Farklı para birimleri hiçbir yerde tek toplamda birleştirilmez
 * (şartname 11); toplam gerekiyorsa para birimine göre gruplanır.
 */
class Para
{
    public const BIRIMLER = [
        'TRY' => '₺ Türk lirası',
        'EUR' => '€ Euro',
        'USD' => '$ ABD doları',
        'GBP' => '£ İngiliz sterlini',
    ];

    private const SEMBOL = ['TRY' => '₺', 'EUR' => '€', 'USD' => '$', 'GBP' => '£'];

    /** 1.250.000 ₺ — kuruş sıfırsa ondalık yazılmaz. */
    public static function bicimle(?int $kurus, string $birim = 'TRY'): string
    {
        if ($kurus === null) {
            return '—';
        }

        $ondalik = $kurus % 100 === 0 ? 0 : 2;
        $sayi = number_format($kurus / 100, $ondalik, ',', '.');
        $sembol = self::SEMBOL[$birim] ?? $birim;

        return $birim === 'TRY' ? $sayi.' '.$sembol : $sembol.$sayi;
    }

    /** Formdan gelen "1.250.000" / "1250000,50" / 1250000 → kuruş. */
    public static function kurusa(mixed $deger): ?int
    {
        if ($deger === null || $deger === '') {
            return null;
        }

        if (is_int($deger) || is_float($deger)) {
            return (int) round($deger * 100);
        }

        $metin = preg_replace('/[^\d,.\-]/', '', (string) $deger);

        // Hem nokta hem virgül varsa: Türkçe biçim (nokta binlik, virgül ondalık)
        if (str_contains($metin, ',')) {
            $metin = str_replace('.', '', $metin);
            $metin = str_replace(',', '.', $metin);
        } elseif (substr_count($metin, '.') > 1 || preg_match('/\.\d{3}$/', $metin)) {
            // "1.250.000" ya da "250.000" → binlik ayırıcı
            $metin = str_replace('.', '', $metin);
        }

        return is_numeric($metin) ? (int) round(((float) $metin) * 100) : null;
    }

    /** Kuruş → form alanında gösterilecek sayı (ondalıksız ise tam sayı). */
    public static function formaDegeri(?int $kurus): int|float|null
    {
        if ($kurus === null) {
            return null;
        }

        return $kurus % 100 === 0 ? intdiv($kurus, 100) : $kurus / 100;
    }

    public static function sembol(string $birim): string
    {
        return self::SEMBOL[$birim] ?? $birim;
    }
}
