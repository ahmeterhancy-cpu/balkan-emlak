<?php

namespace App\Models\Concerns;

use App\Support\Gorsel;
use Illuminate\Database\Eloquent\Model;

/**
 * `gorsel` sütunu olan modellerde görsel değişince duyarlı sürümleri
 * (400/800/1600, WebP) kayıt anında üretir — ilk ziyaretçi beklemesin.
 */
trait GorselSurumleri
{
    public static function bootGorselSurumleri(): void
    {
        static::saved(function (Model $m) {
            if (filled($m->gorsel) && ($m->wasRecentlyCreated || $m->wasChanged('gorsel'))) {
                Gorsel::set($m->gorsel);
            }
        });
    }
}
