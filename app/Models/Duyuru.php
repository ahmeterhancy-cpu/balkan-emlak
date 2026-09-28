<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Site ya da blok duyurusu. Sakin portalı (faz 3) gelince orada da görünecek. */
class Duyuru extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'duyurular';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['durum' => 'taslak'];

    protected $fillable = ['site_id', 'blok_id', 'baslik', 'icerik', 'durum', 'yayin_tarihi', 'bitis_tarihi', 'olusturan_id'];

    protected function casts(): array
    {
        return ['yayin_tarihi' => 'datetime', 'bitis_tarihi' => Gun::class];
    }

    public const DURUMLAR = ['taslak' => 'Taslak', 'yayinda' => 'Yayında', 'arsiv' => 'Arşiv'];

    protected static function booted(): void
    {
        static::saving(function (Duyuru $d) {
            $d->olusturan_id ??= auth()->id();
            if ($d->durum === 'yayinda') {
                $d->yayin_tarihi ??= now();
            }
        });
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }
}
