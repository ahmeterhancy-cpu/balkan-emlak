<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kayıt düzeyinde görünürlük. Filament kaynaklarının getEloquentQuery()
 * metodu ve politikalar aynı kuralı buradan okur; böylece liste ile
 * doğrudan adres (/admin/portfoyler/5/edit) aynı sonucu verir.
 *
 *  - "_tumu" izni olan: hepsini görür.
 *  - Danışman: sorumlusu olduğu portföyleri, kendisine atanmış
 *    talepleri, kendi randevularını, kendi portföylerinin sahiplerini
 *    ve kendi taleplerinin kişilerini görür.
 */
class Kapsam
{
    public static function portfoyler(Builder $sorgu, ?User $k = null): Builder
    {
        $k ??= auth()->user();

        return Yetki::var('portfoy.goruntule_tumu', $k)
            ? $sorgu
            : $sorgu->where($sorgu->qualifyColumn('danisman_id'), $k?->id ?? 0);
    }

    public static function talepler(Builder $sorgu, ?User $k = null): Builder
    {
        $k ??= auth()->user();

        return Yetki::var('talep.goruntule_tumu', $k)
            ? $sorgu
            : $sorgu->where($sorgu->qualifyColumn('atanan_id'), $k?->id ?? 0);
    }

    public static function randevular(Builder $sorgu, ?User $k = null): Builder
    {
        $k ??= auth()->user();

        return Yetki::var('randevu.goruntule_tumu', $k)
            ? $sorgu
            : $sorgu->where($sorgu->qualifyColumn('danisman_id'), $k?->id ?? 0);
    }

    /**
     * Kullanıcının erişebildiği site kimlikleri; null = hepsi.
     *
     * @return list<int>|null
     */
    public static function siteIdleri(?User $k = null): ?array
    {
        $k ??= auth()->user();

        if (Yetki::var('site.goruntule_tumu', $k)) {
            return null;
        }

        if (! $k) {
            return [];
        }

        return array_map('intval', \Illuminate\Support\Facades\DB::table('site_kullanici')
            ->where('user_id', $k->id)->pluck('site_id')->all());
    }

    public static function siteErisimi(int $siteId, ?User $k = null): bool
    {
        $idler = self::siteIdleri($k);

        return $idler === null || in_array($siteId, $idler, true);
    }

    /** `siteler` tablosu sorgusu. */
    public static function siteler(Builder $sorgu, ?User $k = null): Builder
    {
        $idler = self::siteIdleri($k);

        return $idler === null ? $sorgu : $sorgu->whereIn($sorgu->qualifyColumn('id'), $idler);
    }

    /** site_id sütunu olan her tablo (borç, tahsilat, gider, iş emri...). */
    public static function siteyeBagli(Builder $sorgu, ?User $k = null): Builder
    {
        $idler = self::siteIdleri($k);

        return $idler === null ? $sorgu : $sorgu->whereIn($sorgu->qualifyColumn('site_id'), $idler);
    }

    /**
     * İş emri ve görevler: operasyon.yonet olan site kapsamında hepsini,
     * saha personeli yalnız kendisine atananları görür.
     */
    public static function operasyon(Builder $sorgu, string $kisiSutunu, ?User $k = null): Builder
    {
        $k ??= auth()->user();

        if (Yetki::var('operasyon.yonet', $k)) {
            return self::siteyeBagli($sorgu, $k);
        }

        return self::siteyeBagli($sorgu, $k)->where($sorgu->qualifyColumn($kisiSutunu), $k?->id ?? 0);
    }

    public static function kisiler(Builder $sorgu, ?User $k = null): Builder
    {
        $k ??= auth()->user();

        if (Yetki::var('kisi.goruntule_tumu', $k)) {
            return $sorgu;
        }

        $id = $k?->id ?? 0;

        return $sorgu->where(function (Builder $q) use ($id) {
            $q->where('olusturan_id', $id)
                ->orWhereHas('portfoyler', fn (Builder $p) => $p->where('danisman_id', $id))
                ->orWhereHas('talepler', fn (Builder $t) => $t->where('atanan_id', $id));
        });
    }
}
