<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Duyarlı görsel sürümleri (şartname 15: LCP ≤ 2,5 sn).
 *
 * Her görsel için 400 / 800 / 1600 px genişlikte JPEG (ya da PNG) ve WebP
 * sürümleri üretilir: `foto-800.jpg`, `foto-800.webp`, asıl boyut için `foto.webp`.
 * Tarayıcı `<picture>` + `srcset` ile ekrana uygun olanı indirir
 * (bileşen: resources/views/components/resim.blade.php).
 *
 * Kaynak iki yerde olabilir: `medya` diski (yüklenen görseller) ya da
 * `public` (public/img altındaki varsayılan site görselleri). Sürümler ilk
 * ihtiyaçta bir kez üretilir, sonra dosyadan okunur; yüklemede ise hemen
 * üretilir (PortfoyMedya). Üretilemezse (GD yok, bozuk dosya) sessizce asıl
 * görsele dönülür — sayfa ya da yükleme bu yüzden asla düşmez.
 */
class Gorsel
{
    public const GENISLIKLER = [400, 800, 1600];

    public const KUCUK_GENISLIK = 800;

    private const WEBP_KALITE = 76;

    private const JPEG_KALITE = 80;

    /** @var array<string, array> istek içi bellek */
    private static array $bellek = [];

    /* ---------- Eski arayüz (ilan kartı / kategori kutusu) ---------- */

    public static function kucukYol(string $yol): string
    {
        return self::surumYolu($yol, self::KUCUK_GENISLIK, null);
    }

    /** Yüklemede çağrılır: tüm sürümleri hemen üret. */
    public static function kucukUret(string $yol, string $disk = 'medya'): bool
    {
        return self::set($yol, $disk)['surumler'] !== [];
    }

    public static function kucukUrl(string $yol, string $disk = 'medya'): string
    {
        $set = self::set($yol, $disk);

        return $set['jpg'][self::KUCUK_GENISLIK] ?? $set['asil'];
    }

    /* ---------- Duyarlı set ---------- */

    /**
     * @param  string  $kok  'medya' (disk) ya da 'public' (public_path altında göreli yol)
     * @return array{asil: string, genislik: ?int, yukseklik: ?int, jpg: array<int, string>, webp: array<int, string>, surumler: list<int>}
     */
    public static function set(string $yol, string $kok = 'medya'): array
    {
        $anahtar = $kok.'|'.$yol;
        if (isset(self::$bellek[$anahtar])) {
            return self::$bellek[$anahtar];
        }

        $tam = self::tamYol($yol, $kok);
        $sonuc = ['asil' => self::url($yol, $kok), 'genislik' => null, 'yukseklik' => null, 'jpg' => [], 'webp' => [], 'surumler' => []];

        try {
            $boyut = $tam && is_file($tam) ? @getimagesize($tam) : false;
            if (! $boyut) {
                return self::$bellek[$anahtar] = $sonuc;
            }

            [$g, $y] = $boyut;
            $sonuc['genislik'] = $g;
            $sonuc['yukseklik'] = $y;

            $kaynak = null;
            $genislikler = array_values(array_filter(self::GENISLIKLER, fn ($w) => $w < $g));
            $genislikler[] = $g; // asıl boyut (yalnız WebP'si üretilir; JPEG'i asıl dosya)

            foreach ($genislikler as $w) {
                $asilBoy = $w === $g;

                // JPEG/PNG sürümü (asıl boyutta asıl dosyanın kendisi)
                if ($asilBoy) {
                    $sonuc['jpg'][$w] = $sonuc['asil'];
                } else {
                    $jpgYol = self::surumYolu($yol, $w, null);
                    if (! is_file(self::tamYol($jpgYol, $kok))) {
                        $kaynak ??= @imagecreatefromstring((string) file_get_contents($tam));
                        if ($kaynak) {
                            self::yaz($kaynak, $w, self::tamYol($jpgYol, $kok), strtolower(pathinfo($yol, PATHINFO_EXTENSION)));
                        }
                    }
                    if (is_file(self::tamYol($jpgYol, $kok))) {
                        $sonuc['jpg'][$w] = self::url($jpgYol, $kok);
                    }
                }

                // WebP sürümü
                if (function_exists('imagewebp')) {
                    $webpYol = self::surumYolu($yol, $asilBoy ? null : $w, 'webp');
                    if (! is_file(self::tamYol($webpYol, $kok))) {
                        $kaynak ??= @imagecreatefromstring((string) file_get_contents($tam));
                        if ($kaynak) {
                            self::yaz($kaynak, $w, self::tamYol($webpYol, $kok), 'webp');
                        }
                    }
                    if (is_file(self::tamYol($webpYol, $kok))) {
                        $sonuc['webp'][$w] = self::url($webpYol, $kok);
                    }
                }

                if (! $asilBoy && isset($sonuc['jpg'][$w])) {
                    $sonuc['surumler'][] = $w;
                }
            }

            if ($kaynak) {
                imagedestroy($kaynak);
            }
        } catch (Throwable) {
            // sürüm yoksa asıl görsel kullanılır
        }

        return self::$bellek[$anahtar] = $sonuc;
    }

    /** Tüm sürüm dosyalarının yolları (silmede kullanılır). @return list<string> */
    public static function surumYollari(string $yol): array
    {
        $liste = [self::surumYolu($yol, null, 'webp')];
        foreach (self::GENISLIKLER as $w) {
            $liste[] = self::surumYolu($yol, $w, null);
            $liste[] = self::surumYolu($yol, $w, 'webp');
        }

        return $liste;
    }

    public static function srcset(array $urller): string
    {
        return collect($urller)->map(fn ($url, $w) => $url.' '.$w.'w')->implode(', ');
    }

    /* ------------------------------------------------------------------ */

    /** "a/foto.jpg" + 800 + webp → "a/foto-800.webp"; genişlik null → "a/foto.webp" */
    private static function surumYolu(string $yol, ?int $genislik, ?string $uzanti): string
    {
        $nokta = strrpos($yol, '.');
        $govde = $nokta === false ? $yol : substr($yol, 0, $nokta);
        $eskiUzanti = $nokta === false ? '' : substr($yol, $nokta + 1);

        return $govde.($genislik ? '-'.$genislik : '').'.'.($uzanti ?? $eskiUzanti);
    }

    private static function tamYol(string $yol, string $kok): string
    {
        return $kok === 'public' ? public_path($yol) : Storage::disk($kok)->path($yol);
    }

    private static function url(string $yol, string $kok): string
    {
        return $kok === 'public' ? asset($yol) : Storage::disk($kok)->url($yol);
    }

    /** @param \GdImage $kaynak */
    private static function yaz($kaynak, int $w, string $hedefYol, string $tur): void
    {
        $g = imagesx($kaynak);
        $y = imagesy($kaynak);
        $h = (int) round($y * $w / $g);

        $hedef = imagecreatetruecolor($w, $h);
        if ($tur === 'png' || $tur === 'webp') {
            imagealphablending($hedef, false);
            imagesavealpha($hedef, true);
        }
        imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, $w, $h, $g, $y);

        match ($tur) {
            'webp' => imagewebp($hedef, $hedefYol, self::WEBP_KALITE),
            'png' => imagepng($hedef, $hedefYol, 7),
            default => imagejpeg($hedef, $hedefYol, self::JPEG_KALITE),
        };

        imagedestroy($hedef);
    }
}
