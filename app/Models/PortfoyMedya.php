<?php

namespace App\Models;

use App\Support\Gorsel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** İlan fotoğrafı / kat planı. Disk: medya (public/medya). */
class PortfoyMedya extends Model
{
    protected $table = 'portfoy_medya';

    protected $fillable = ['portfoy_id', 'yol', 'tur', 'alt_metin', 'kapak', 'sira', 'gorsel_hakki', 'gorsel_kaynak'];

    protected function casts(): array
    {
        return ['kapak' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Kart için 800 px kopya (yol değişince yeniden)
        static::saved(function (PortfoyMedya $m) {
            if ($m->wasRecentlyCreated || $m->wasChanged('yol')) {
                Gorsel::kucukUret($m->yol);
            }
        });

        // Tek kapak: biri kapak yapılınca diğerlerinin işareti kalkar
        static::saved(function (PortfoyMedya $m) {
            if ($m->kapak && ($m->wasChanged('kapak') || $m->wasRecentlyCreated)) {
                static::where('portfoy_id', $m->portfoy_id)->whereKeyNot($m->id)->update(['kapak' => false]);
            }
        });

        // Kayıt silinince dosya da silinir (başka kayıt aynı dosyayı kullanmıyorsa)
        static::deleted(function (PortfoyMedya $m) {
            if ($m->yol && ! static::where('yol', $m->yol)->exists()) {
                Storage::disk('medya')->delete([$m->yol, ...Gorsel::surumYollari($m->yol)]);
            }
        });
    }

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function url(): string
    {
        return Storage::disk('medya')->url($this->yol);
    }

    /** İlan kartı için 800 px kopya; yoksa asıl görsel. */
    public function kucukUrl(): string
    {
        return Gorsel::kucukUrl($this->yol);
    }
}
