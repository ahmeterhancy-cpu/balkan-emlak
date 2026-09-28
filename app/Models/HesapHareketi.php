<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kasa/banka defter satırı. Giriş +, çıkış −. Yalnız mutabakat işareti değişir. */
class HesapHareketi extends Model
{
    use DegismezMaliKayit, SiteyeBagli;

    protected $table = 'hesap_hareketleri';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['mutabik' => false];

    protected $fillable = ['site_id', 'hesap_id', 'tarih', 'tutar_kurus', 'tur', 'kaynak_type', 'kaynak_id', 'aciklama', 'dayanak', 'mutabik', 'mutabakat_tarihi', 'olusturan_id'];

    public array $degisebilirAlanlar = ['mutabik', 'mutabakat_tarihi'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'mutabakat_tarihi' => Gun::class, 'mutabik' => 'boolean', 'tutar_kurus' => 'integer'];
    }

    public const TURLER = [
        'acilis' => 'Açılış bakiyesi',
        'tahsilat' => 'Tahsilat',
        'tahsilat_iade' => 'Tahsilat iadesi',
        'gider_odemesi' => 'Gider ödemesi',
        'transfer' => 'Hesaplar arası transfer',
        'duzeltme' => 'Düzeltme',
    ];

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(Hesap::class);
    }

    public function kaynak(): MorphTo
    {
        return $this->morphTo();
    }
}
