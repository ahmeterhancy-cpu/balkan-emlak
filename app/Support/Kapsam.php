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
