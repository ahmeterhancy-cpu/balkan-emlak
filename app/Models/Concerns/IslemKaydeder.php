<?php

namespace App\Models\Concerns;

use App\Models\IslemKaydi;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelde oluşturma / güncelleme / silme işlemlerini işlem geçmişine yazar:
 * kim, ne zaman, önceki ve sonraki değer.
 *
 * `updated` dinlenir (saved değil): increment()/decrement() yalnız
 * updating/updated olaylarını atar (Zeys'te yakalanan tuzak).
 */
trait IslemKaydeder
{
    /** Kayda yazılmayacak sütunlar (gürültü veya gizli). */
    protected static array $islemKaydiHaric = ['updated_at', 'created_at', 'remember_token', 'password'];

    public static function bootIslemKaydeder(): void
    {
        static::created(function (Model $model) {
            IslemKaydi::yaz('olusturuldu', $model, null, static::islemAlanlari($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $degisen = static::islemAlanlari($model, $model->getChanges());

            if ($degisen === []) {
                return;
            }

            $onceki = static::islemAlanlari($model, array_intersect_key($model->getOriginal(), $degisen));
            IslemKaydi::yaz('guncellendi', $model, $onceki, $degisen);
        });

        static::deleted(function (Model $model) {
            IslemKaydi::yaz('silindi', $model, static::islemAlanlari($model, $model->getAttributes()), null);
        });
    }

    /**
     * Gizli alanlar ($hidden — parola, iki aşamalı doğrulama anahtarı ve
     * kurtarma kodları) işlem geçmişine ASLA yazılmaz; yalnız değiştiği not edilir.
     */
    private static function islemAlanlari(Model $model, array $alanlar): array
    {
        $gizli = array_flip(array_merge(static::$islemKaydiHaric, $model->getHidden()));
        $sonuc = static::duzlestir(array_diff_key($alanlar, $gizli));

        foreach (array_intersect_key($alanlar, $gizli) as $ad => $_) {
            if (! in_array($ad, ['updated_at', 'created_at', 'remember_token'], true)) {
                $sonuc[$ad] = '[gizli — değişti]';
            }
        }

        return $sonuc;
    }

    /** Enum ve tarih nesnelerini JSON'a yazılabilir değere çevir. */
    private static function duzlestir(array $alanlar): array
    {
        return array_map(function ($deger) {
            if ($deger instanceof \BackedEnum) {
                return $deger->value;
            }
            if ($deger instanceof \DateTimeInterface) {
                return $deger->format('Y-m-d H:i:s');
            }

            return $deger;
        }, $alanlar);
    }
}
