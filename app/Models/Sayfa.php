<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Kurumsal ve yasal sayfalar. `inceleme_bekliyor` açık yasal metin
 * sitede bir uyarı şeridiyle gösterilir: hukuki incelemeden geçmemiş
 * metin kesinleşmiş gibi sunulmaz (şartname 5.1).
 */
class Sayfa extends Model
{
    use IslemKaydeder;

    protected $table = 'sayfalar';

    protected $fillable = ['baslik', 'slug', 'ozet', 'icerik', 'grup', 'yayinda', 'inceleme_bekliyor', 'seo_baslik', 'seo_aciklama'];

    protected function casts(): array
    {
        return ['yayinda' => 'boolean', 'inceleme_bekliyor' => 'boolean'];
    }

    public const GRUPLAR = ['kurumsal' => 'Kurumsal', 'yasal' => 'Yasal metin'];

    protected static function booted(): void
    {
        static::saving(function (Sayfa $s) {
            $s->slug = filled($s->slug) ? Str::slug($s->slug) : Str::slug($s->baslik);
        });
    }

    /** @param Builder<Sayfa> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('yayinda', true);
    }
}
