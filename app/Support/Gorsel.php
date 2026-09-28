<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Yüklenen ilan fotoğrafı için ilan kartında kullanılan küçük kopya
 * (800 px genişlik). Liste sayfası 1600 px'lik 12 fotoğraf indirmesin
 * diye (LCP hedefi 2,5 sn, şartname 15).
 *
 * Küçük kopya üretilemezse (GD yok, bozuk dosya) sessizce asıl görsele
 * dönülür; yükleme işlemi hiçbir zaman bu yüzden düşmez.
 */
class Gorsel
{
    public const KUCUK_GENISLIK = 800;

    public static function kucukYol(string $yol): string
    {
        $nokta = strrpos($yol, '.');

        return $nokta === false ? $yol.'-800' : substr($yol, 0, $nokta).'-800'.substr($yol, $nokta);
    }

    public static function kucukUret(string $yol, string $disk = 'medya'): bool
    {
        try {
            $depo = Storage::disk($disk);
            $tam = $depo->path($yol);

            if (! is_file($tam) || ! function_exists('imagecreatefromstring')) {
                return false;
            }

            $kaynak = @imagecreatefromstring((string) file_get_contents($tam));
            if (! $kaynak) {
                return false;
            }

            $g = imagesx($kaynak);
            $y = imagesy($kaynak);

            if ($g <= self::KUCUK_GENISLIK) {
                imagedestroy($kaynak);

                return false; // zaten küçük; asıl görsel kullanılır
            }

            $yeniY = (int) round($y * self::KUCUK_GENISLIK / $g);
            $hedef = imagecreatetruecolor(self::KUCUK_GENISLIK, $yeniY);
            imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, self::KUCUK_GENISLIK, $yeniY, $g, $y);

            $kucuk = $depo->path(self::kucukYol($yol));
            $uzanti = strtolower(pathinfo($yol, PATHINFO_EXTENSION));
            $sonuc = match ($uzanti) {
                'png' => imagepng($hedef, $kucuk, 7),
                'webp' => function_exists('imagewebp') ? imagewebp($hedef, $kucuk, 78) : false,
                default => imagejpeg($hedef, $kucuk, 78),
            };

            imagedestroy($kaynak);
            imagedestroy($hedef);

            return (bool) $sonuc;
        } catch (Throwable) {
            return false;
        }
    }

    public static function kucukUrl(string $yol, string $disk = 'medya'): string
    {
        $kucuk = self::kucukYol($yol);

        return Storage::disk($disk)->exists($kucuk)
            ? Storage::disk($disk)->url($kucuk)
            : Storage::disk($disk)->url($yol);
    }
}
