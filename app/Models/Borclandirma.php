<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Toplu tahakkuk işi. Taslakken değiştirilebilir; kesinleşince borçlar
 * açılır ve iş bir daha çalıştırılamaz (App\Services\SiteFinans\Borclandirici).
 */
class Borclandirma extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'borclandirmalar';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['etiket' => '', 'para_birimi' => 'TRY', 'durum' => 'taslak'];

    protected $fillable = [
        'site_id', 'donem', 'tur', 'etiket', 'aciklama', 'vade', 'dagitim', 'birim_kurus', 'toplam_kurus',
        'ozel_tutarlar', 'bolum_turleri', 'para_birimi', 'durum', 'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'vade' => Gun::class,
            'ozel_tutarlar' => 'array',
            'bolum_turleri' => 'array',
            'kesinlesme_zamani' => 'datetime',
            'birim_kurus' => 'integer',
            'toplam_kurus' => 'integer',
        ];
    }

    public const TURLER = [
        'aidat' => 'Aylık aidat',
        'ek_aidat' => 'Ek aidat',
        'gider_payi' => 'Gider payı',
        'demirbas' => 'Demirbaş',
        'diger' => 'Diğer',
    ];

    public const DAGITIMLAR = [
        'sabit' => 'Sabit tutar (bölüm başı)',
        'm2' => 'Metrekareye göre',
        'arsa_payi' => 'Arsa payına göre',
        'ozel' => 'Onaylı özel dağıtım (bölüm bölüm)',
    ];

    public const DURUMLAR = ['taslak' => 'Taslak', 'kesin' => 'Kesinleşti', 'iptal' => 'İptal'];

    protected static function booted(): void
    {
        static::creating(function (Borclandirma $b) {
            $b->olusturan_id ??= auth()->id();
            $b->etiket ??= '';
        });
    }

    public function borclar(): HasMany
    {
        return $this->hasMany(Borc::class);
    }

    public function kesinlestiren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kesinlestiren_id');
    }
}
