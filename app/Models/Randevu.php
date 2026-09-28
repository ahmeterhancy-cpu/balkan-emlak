<?php

namespace App\Models;

use App\Enums\RandevuDurumu;
use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Randevu extends Model
{
    use IslemKaydeder;

    protected $table = 'randevular';

    protected $fillable = ['talep_id', 'portfoy_id', 'danisman_id', 'musteri_adi', 'musteri_telefon', 'baslangic', 'sure_dk', 'durum', 'sonuc_notu'];

    protected function casts(): array
    {
        return ['baslangic' => 'datetime', 'durum' => RandevuDurumu::class];
    }

    public function talep(): BelongsTo
    {
        return $this->belongsTo(Talep::class);
    }

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function danisman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'danisman_id');
    }
}
