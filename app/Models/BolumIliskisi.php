<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Malik / kiracı ilişkisi, başlangıç–bitiş tarihli. Taşınmada kayıt
 * silinmez ya da üzerine yazılmaz: eskisinin bitişi girilir, yeni kayıt
 * açılır; geçmiş dönem borçlarının sorumlusu değişmez.
 */
class BolumIliskisi extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'bolum_iliskileri';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['borc_sorumlusu' => false];

    protected $fillable = ['site_id', 'bagimsiz_bolum_id', 'kisi_id', 'rol', 'hisse_pay', 'hisse_payda', 'baslangic', 'bitis', 'borc_sorumlusu', 'notlar'];

    protected function casts(): array
    {
        return ['baslangic' => Gun::class, 'bitis' => Gun::class, 'borc_sorumlusu' => 'boolean'];
    }

    public const ROLLER = ['malik' => 'Malik', 'kiraci' => 'Kiracı'];

    protected static function booted(): void
    {
        static::saving(function (BolumIliskisi $i) {
            $siteId = BagimsizBolum::whereKey($i->bagimsiz_bolum_id)->value('site_id');
            if (! $siteId) {
                throw new DomainException('Bağımsız bölüm bulunamadı.');
            }
            $i->site_id = $siteId; // site her zaman bölümden gelir
            if ($i->bitis && $i->baslangic && $i->bitis->lt($i->baslangic)) {
                throw new DomainException('Bitiş tarihi başlangıçtan önce olamaz.');
            }
        });
    }

    public function bolum(): BelongsTo
    {
        return $this->belongsTo(BagimsizBolum::class, 'bagimsiz_bolum_id');
    }

    public function kisi(): BelongsTo
    {
        return $this->belongsTo(Kisi::class);
    }

    public function etkinMi(?string $gun = null): bool
    {
        $gun ??= now()->toDateString();

        return $this->baslangic->toDateString() <= $gun && ($this->bitis === null || $this->bitis->toDateString() >= $gun);
    }
}
