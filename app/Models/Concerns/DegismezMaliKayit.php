<?php

namespace App\Models\Concerns;

use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * İşlenmiş mali kayıt silinmez; tutarı, tarihi, bağlı olduğu hesap/bölüm
 * değiştirilemez. Düzeltme ve iade karşı kayıtla yapılır (şartname 8.2, 8.3).
 *
 * Modeller `$degisebilirAlanlar` ile yalnız izinli alanları (ör. mutabakat
 * işareti, açıklama) açar.
 */
trait DegismezMaliKayit
{
    public static function bootDegismezMaliKayit(): void
    {
        static::deleting(function (Model $model) {
            throw new DomainException('Mali kayıt silinemez; iptal veya düzeltme karşı kayıtla yapılır.');
        });

        static::updating(function (Model $model) {
            $izinli = array_merge(property_exists($model, 'degisebilirAlanlar') ? $model->degisebilirAlanlar : [], ['updated_at']);
            $yasak = array_diff(array_keys($model->getDirty()), $izinli);

            if ($yasak !== []) {
                throw new DomainException('Mali kaydın şu alanları değiştirilemez: '.implode(', ', $yasak).'. Karşı kayıtla düzeltin.');
            }
        });
    }
}
