<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Saatsiz gün: veritabanına DAİMA "Y-m-d" yazar.
 *
 * TUZAK: Laravel'in `date` dönüşümü SQLite'a "2026-06-01 00:00:00" yazar.
 * Bu değer "2026-06-01" ile metin olarak karşılaştırılınca AYNI GÜN büyük
 * sayılır: ayın 1'inde başlayan malik o gün sorumlu görünmez, ayın son
 * günündeki tahsilat "whereBetween(1, 30)" dönem raporundan düşer, bakiye
 * o günün hareketini saymaz. Testlerde yakalandı (BorclandirmaTest).
 */
class Gun implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))->format('Y-m-d');
    }
}
