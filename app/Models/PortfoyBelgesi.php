<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Özel belge (yetki belgesi, tapu, sözleşme). Disk: `local`
 * (storage/app/private) — web kökünde DEĞİL. İndirme yalnız
 * BelgeController üzerinden, politika denetimi ve işlem kaydıyla.
 */
class PortfoyBelgesi extends Model
{
    use IslemKaydeder;

    protected $table = 'portfoy_belgeleri';

    protected $fillable = ['portfoy_id', 'tur', 'ad', 'yol', 'orijinal_ad', 'gecerlilik_bitis', 'yukleyen_id'];

    protected function casts(): array
    {
        return ['gecerlilik_bitis' => 'date'];
    }

    public const TURLER = [
        'yetki' => 'Yetkilendirme belgesi',
        'tapu' => 'Tapu',
        'sozlesme' => 'Sözleşme',
        'kimlik' => 'Kimlik / vekâlet',
        'diger' => 'Diğer',
    ];

    public const DISK = 'local';

    protected static function booted(): void
    {
        static::creating(function (PortfoyBelgesi $b) {
            $b->yukleyen_id ??= auth()->id();
        });

        static::deleted(function (PortfoyBelgesi $b) {
            Storage::disk(self::DISK)->delete($b->yol);
        });
    }

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function yukleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yukleyen_id');
    }
}
