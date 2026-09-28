<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Support\Kapsam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Yönetilen site / apartman. */
class Site extends Model
{
    use IslemKaydeder;

    protected $table = 'siteler';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['tur' => 'site', 'para_birimi' => 'TRY', 'varsayilan_vade_gunu' => 10, 'aktif' => true];

    protected $fillable = [
        'ad', 'tur', 'adres', 'il_id', 'ilce_id', 'yonetim_baslangic', 'yonetim_bitis', 'sorumlu_id',
        'iletisim_kisi', 'iletisim_telefon', 'para_birimi', 'varsayilan_vade_gunu', 'onay_limiti_kurus',
        'kapali_donem', 'notlar', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'yonetim_baslangic' => Gun::class,
            'yonetim_bitis' => Gun::class,
            'aktif' => 'boolean',
            'onay_limiti_kurus' => 'integer',
        ];
    }

    public const TURLER = ['site' => 'Site', 'apartman' => 'Apartman'];

    /** @param Builder<Site> $sorgu */
    public function scopeYetkili(Builder $sorgu): void
    {
        Kapsam::siteler($sorgu);
    }

    public function sorumlu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorumlu_id');
    }

    public function il(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'ilce_id');
    }

    public function kullanicilar(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_kullanici');
    }

    public function bloklar(): HasMany
    {
        return $this->hasMany(Blok::class)->orderBy('sira')->orderBy('ad');
    }

    public function bolumler(): HasMany
    {
        return $this->hasMany(BagimsizBolum::class);
    }

    public function hesaplar(): HasMany
    {
        return $this->hasMany(Hesap::class);
    }

    public function borclandirmalar(): HasMany
    {
        return $this->hasMany(Borclandirma::class);
    }

    public function borclar(): HasMany
    {
        return $this->hasMany(Borc::class);
    }

    public function tahsilatlar(): HasMany
    {
        return $this->hasMany(Tahsilat::class);
    }

    public function giderler(): HasMany
    {
        return $this->hasMany(Gider::class);
    }

    public function isEmirleri(): HasMany
    {
        return $this->hasMany(IsEmri::class);
    }
}
