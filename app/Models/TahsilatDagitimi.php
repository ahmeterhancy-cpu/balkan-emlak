<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tahsilatın bir borca ayrılan kısmı. Geri alma eksi satırla. */
class TahsilatDagitimi extends Model
{
    use DegismezMaliKayit;

    public const UPDATED_AT = null;

    protected $table = 'tahsilat_dagitimlari';

    protected $fillable = ['tahsilat_id', 'borc_id', 'tutar_kurus', 'tarih', 'olusturan_id'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'tutar_kurus' => 'integer'];
    }

    public function tahsilat(): BelongsTo
    {
        return $this->belongsTo(Tahsilat::class);
    }

    public function borc(): BelongsTo
    {
        return $this->belongsTo(Borc::class);
    }
}
