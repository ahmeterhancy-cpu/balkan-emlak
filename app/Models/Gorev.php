<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personel görevi. Bordro/özlük kapsam dışı (şartname 8.6). */
class Gorev extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'gorevler';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['durum' => 'bekliyor'];

    protected $fillable = ['site_id', 'user_id', 'baslik', 'aciklama', 'tarih', 'durum', 'tamamlanma_notu', 'olusturan_id'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'tamamlanma_zamani' => 'datetime'];
    }

    public const DURUMLAR = ['bekliyor' => 'Bekliyor', 'tamamlandi' => 'Tamamlandı', 'iptal' => 'İptal'];

    protected static function booted(): void
    {
        static::creating(function (Gorev $g) {
            $g->olusturan_id ??= auth()->id();
        });
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tamamla(?string $not = null): void
    {
        $this->forceFill(['durum' => 'tamamlandi', 'tamamlanma_notu' => $not, 'tamamlanma_zamani' => now()])->save();
    }
}
