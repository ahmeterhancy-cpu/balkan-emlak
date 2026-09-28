<?php

namespace App\Support;

use App\Models\Site;
use App\Models\User;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Dönem = "YYYY-MM". Dönem kilidi: sitenin `kapali_donem` değerine kadar
 * (dahil) olan dönemlerde mali kayıt açılamaz; yalnız `donem.duzelt` izni
 * olan kullanıcı GEREKÇE yazarak düzeltme yapabilir (şartname 8.3).
 */
class Donem
{
    public static function gecerli(string $donem): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $donem);
    }

    public static function tarihten(DateTimeInterface|string $tarih): string
    {
        return Carbon::parse($tarih)->format('Y-m');
    }

    public static function ad(string $donem): string
    {
        return Carbon::createFromFormat('Y-m-d', $donem.'-01')->locale('tr')->translatedFormat('F Y');
    }

    public static function kapaliMi(Site $site, string $donem): bool
    {
        return $site->kapali_donem !== null && strcmp($donem, $site->kapali_donem) <= 0;
    }

    /**
     * Kapalı döneme kayıt girişini denetle.
     *
     * @throws DomainException
     */
    public static function yazilabilir(Site $site, DateTimeInterface|string $tarihVeyaDonem, ?string $gerekce = null, ?User $k = null): void
    {
        $donem = is_string($tarihVeyaDonem) && self::gecerli($tarihVeyaDonem)
            ? $tarihVeyaDonem
            : self::tarihten($tarihVeyaDonem);

        if (! self::kapaliMi($site, $donem)) {
            return;
        }

        if (! Yetki::var('donem.duzelt', $k)) {
            throw new DomainException(self::ad($donem).' dönemi kapalı. Bu döneme kayıt için yetkili düzeltme gerekir.');
        }

        if (blank($gerekce)) {
            throw new DomainException(self::ad($donem).' dönemi kapalı; düzeltme için gerekçe yazılmalı.');
        }
    }
}
