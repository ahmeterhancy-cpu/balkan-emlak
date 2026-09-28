<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** İşlem geçmişi. Yalnız eklenir; panelde düzenleme/silme yok. */
class IslemKaydi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'islem_kayitlari';

    protected $fillable = ['user_id', 'olay', 'kayit_turu', 'kayit_id', 'ozet', 'onceki', 'sonraki', 'ip'];

    protected function casts(): array
    {
        return ['onceki' => 'array', 'sonraki' => 'array'];
    }

    public const OLAYLAR = [
        'olusturuldu' => 'Oluşturuldu',
        'guncellendi' => 'Güncellendi',
        'silindi' => 'Silindi',
        'belge_indirildi' => 'Belge indirildi',
        'disa_aktarildi' => 'Dışa aktarıldı',
    ];

    public static function yaz(string $olay, ?Model $kayit = null, ?array $onceki = null, ?array $sonraki = null, ?string $ozet = null): void
    {
        if ($ozet === null && $kayit) {
            $ozet = (string) ($kayit->baslik ?? $kayit->ad_soyad ?? $kayit->name ?? $kayit->ad ?? $kayit->musteri_adi ?? $kayit->soru ?? '');
        }

        static::create([
            'user_id' => auth()->id(),
            'olay' => $olay,
            'kayit_turu' => $kayit ? class_basename($kayit) : null,
            'kayit_id' => $kayit?->getKey(),
            'ozet' => $ozet !== null ? Str::limit($ozet, 180) : null,
            'onceki' => $onceki,
            'sonraki' => $sonraki,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
