<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sitenin kasa / banka hesabı. Bakiye = hareketlerin toplamı. */
class Hesap extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'hesaplar';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['para_birimi' => 'TRY', 'aktif' => true];

    protected $fillable = ['site_id', 'ad', 'tur', 'banka_adi', 'iban', 'para_birimi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public const TURLER = ['kasa' => 'Kasa', 'banka' => 'Banka'];

    public function hareketler(): HasMany
    {
        return $this->hasMany(HesapHareketi::class);
    }

    public function bakiyeKurus(?string $tarih = null): int
    {
        return (int) $this->hareketler()->when($tarih, fn ($q) => $q->where('tarih', '<=', $tarih))->sum('tutar_kurus');
    }
}
