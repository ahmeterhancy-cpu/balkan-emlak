<?php

namespace App\Models\Concerns;

use App\Models\Yonlendirme;
use Illuminate\Database\Eloquent\Model;

/**
 * Herkese açık adresi slug'dan gelen modellerde (ilan, yazı, hizmet) slug
 * değişince eski adresi yenisine 301 ile bağlar. Model `vitrinYolu(string $slug)`
 * tanımlar.
 */
trait SlugYonlendirmesi
{
    public static function bootSlugYonlendirmesi(): void
    {
        static::updated(function (Model $m) {
            // Hiç yayımlanmamış kaydın eski adresi kimsede yok: yönlendirme kalabalığı açma
            $gerekli = ! method_exists($m, 'slugYonlendirmesiGerekli') || $m->slugYonlendirmesiGerekli();

            if ($gerekli && $m->wasChanged('slug') && filled($m->getOriginal('slug'))) {
                Yonlendirme::slugDegisti($m->vitrinYolu($m->getOriginal('slug')), $m->vitrinYolu($m->slug));
            }
        });
    }
}
