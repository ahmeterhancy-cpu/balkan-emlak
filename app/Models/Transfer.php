<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aynı sitenin iki hesabı arasında para aktarımı. Gelir ya da gider DEĞİLDİR. */
class Transfer extends Model
{
    use DegismezMaliKayit, IslemKaydeder, SiteyeBagli;

    protected $table = 'transferler';

    protected $fillable = ['site_id', 'kaynak_hesap_id', 'hedef_hesap_id', 'tarih', 'tutar_kurus', 'aciklama', 'olusturan_id'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'tutar_kurus' => 'integer'];
    }

    public function kaynakHesap(): BelongsTo
    {
        return $this->belongsTo(Hesap::class, 'kaynak_hesap_id');
    }

    public function hedefHesap(): BelongsTo
    {
        return $this->belongsTo(Hesap::class, 'hedef_hesap_id');
    }
}
