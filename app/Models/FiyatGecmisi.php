<?php

namespace App\Models;

use App\Support\Para;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fiyat değişiklikleri; yalnız eklenir, düzenlenmez. */
class FiyatGecmisi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'fiyat_gecmisi';

    protected $fillable = ['portfoy_id', 'eski_fiyat_kurus', 'yeni_fiyat_kurus', 'para_birimi', 'user_id'];

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function yeniMetin(): string
    {
        return Para::bicimle($this->yeni_fiyat_kurus, $this->para_birimi);
    }

    public function eskiMetin(): string
    {
        return Para::bicimle($this->eski_fiyat_kurus, $this->para_birimi);
    }
}
