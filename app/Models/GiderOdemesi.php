<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gider ödemesi (+) ya da geri alınması (−). */
class GiderOdemesi extends Model
{
    use DegismezMaliKayit, IslemKaydeder;

    protected $table = 'gider_odemeleri';

    protected $fillable = ['gider_id', 'hesap_id', 'tarih', 'tutar_kurus', 'aciklama', 'iptal_edilen_id', 'olusturan_id'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'tutar_kurus' => 'integer'];
    }

    public function gider(): BelongsTo
    {
        return $this->belongsTo(Gider::class);
    }

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(Hesap::class);
    }
}
