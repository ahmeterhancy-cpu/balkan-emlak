<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** İl → ilçe → mahalle ağacı. */
class Konum extends Model
{
    protected $table = 'konumlar';

    protected $fillable = ['ust_id', 'tur', 'ad', 'slug', 'sira'];

    public const TURLER = ['il' => 'İl', 'ilce' => 'İlçe', 'mahalle' => 'Mahalle'];

    protected static function booted(): void
    {
        static::saving(function (Konum $konum) {
            if (blank($konum->slug)) {
                $konum->slug = Str::slug($konum->ad);
            }
        });
    }

    public function ust(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'ust_id');
    }

    public function altlar(): HasMany
    {
        return $this->hasMany(Konum::class, 'ust_id')->orderBy('sira')->orderBy('ad');
    }

    /** @param Builder<Konum> $sorgu */
    public function scopeIller(Builder $sorgu): void
    {
        $sorgu->where('tur', 'il')->orderBy('sira')->orderBy('ad');
    }

    /** Panelde seçim listesi için: "Keşan / Edirne". */
    public function tamAd(): string
    {
        return $this->ust ? $this->ad.' / '.$this->ust->ad : $this->ad;
    }

    /** @return array<int, string> */
    public static function secenekler(string $tur, ?int $ustId = null): array
    {
        return static::query()
            ->where('tur', $tur)
            ->when($ustId, fn ($q) => $q->where('ust_id', $ustId))
            ->orderBy('sira')->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }
}
