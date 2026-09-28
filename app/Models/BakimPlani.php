<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\SiteyeBagli;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Periyodik bakım. Bağlı iş emri tamamlanınca son/sonraki tarih ilerler. */
class BakimPlani extends Model
{
    use SiteyeBagli;

    protected $table = 'bakim_planlari';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['aktif' => true];

    protected $fillable = ['site_id', 'varlik_id', 'ad', 'periyot_gun', 'son_bakim', 'sonraki_bakim', 'tedarikci_id', 'sorumlu_id', 'aktif'];

    protected function casts(): array
    {
        return ['son_bakim' => Gun::class, 'sonraki_bakim' => Gun::class, 'aktif' => 'boolean'];
    }

    public function varlik(): BelongsTo
    {
        return $this->belongsTo(Varlik::class);
    }

    public function tedarikci(): BelongsTo
    {
        return $this->belongsTo(Tedarikci::class);
    }

    public function sorumlu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorumlu_id');
    }

    public function isEmirleri(): HasMany
    {
        return $this->hasMany(IsEmri::class);
    }

    /** Bakım yapıldı: son = tarih, sonraki = tarih + periyot. */
    public function yapildi(DateTimeInterface|string $tarih): void
    {
        $gun = Carbon::parse($tarih);
        $this->update(['son_bakim' => $gun->toDateString(), 'sonraki_bakim' => $gun->copy()->addDays($this->periyot_gun)->toDateString()]);
    }
}
