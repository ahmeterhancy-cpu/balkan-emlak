<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Referans proje veya müşteri görüşü. Yayın için İKİ koşul: yayımlama
 * izni alınmış olmalı VE yayında işaretli olmalı (şartname 4.3, 5.1).
 */
class Referans extends Model
{
    use IslemKaydeder;

    protected $table = 'referanslar';

    protected $fillable = ['tur', 'ad', 'alt_baslik', 'metin', 'gorsel', 'izin_alindi', 'izin_tarihi', 'yayinda', 'sira'];

    protected function casts(): array
    {
        return ['izin_alindi' => 'boolean', 'yayinda' => 'boolean', 'izin_tarihi' => \App\Casts\Gun::class];
    }

    public const TURLER = ['proje' => 'Referans proje / yönetilen site', 'yorum' => 'Müşteri görüşü'];

    /** @param Builder<Referans> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('yayinda', true)->where('izin_alindi', true)->orderBy('sira')->orderBy('id');
    }

    public function gorselUrl(): ?string
    {
        return $this->gorsel ? Storage::disk('medya')->url($this->gorsel) : null;
    }
}
