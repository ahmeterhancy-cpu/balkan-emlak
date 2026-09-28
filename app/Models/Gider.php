<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site gideri. Kayıt/onay ile ödeme ayrı izlenir: onay durumu sütunda,
 * ödeme durumu ödemelerden hesaplanır (şartname 8.4). Onaylanan giderin
 * tutarı değiştirilemez; onaylı ya da ödemesi olan gider silinemez.
 */
class Gider extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'giderler';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['para_birimi' => 'TRY', 'onay_durumu' => 'taslak'];

    protected $fillable = [
        'site_id', 'tedarikci_id', 'kategori', 'belge_tarihi', 'belge_no', 'donem', 'vade', 'tutar_kurus',
        'para_birimi', 'aciklama', 'belge_yolu', 'onay_durumu', 'olusturan_id',
    ];

    protected function casts(): array
    {
        return ['belge_tarihi' => Gun::class, 'vade' => Gun::class, 'onay_zamani' => 'datetime', 'tutar_kurus' => 'integer'];
    }

    public const KATEGORILER = [
        'temizlik' => 'Temizlik', 'guvenlik' => 'Güvenlik', 'personel' => 'Personel', 'enerji' => 'Enerji (elektrik, su, doğalgaz)',
        'asansor' => 'Asansör', 'teknik_bakim' => 'Teknik bakım', 'bahce' => 'Bahçe', 'sigorta' => 'Sigorta',
        'yonetim' => 'Yönetim hizmeti', 'demirbas' => 'Demirbaş', 'diger' => 'Diğer',
    ];

    public const ONAY_DURUMLARI = ['taslak' => 'Taslak', 'onay_bekliyor' => 'Onay bekliyor', 'onaylandi' => 'Onaylandı', 'reddedildi' => 'Reddedildi'];

    public const ODEME_DURUMLARI = ['odenmedi' => 'Ödenmedi', 'kismi' => 'Kısmen ödendi', 'odendi' => 'Ödendi'];

    protected static function booted(): void
    {
        static::creating(function (Gider $g) {
            $g->olusturan_id ??= auth()->id();
        });

        static::updating(function (Gider $g) {
            if ($g->getOriginal('onay_durumu') === 'onaylandi'
                && $g->isDirty(['tutar_kurus', 'para_birimi', 'site_id', 'donem'])) {
                throw new DomainException('Onaylanmış giderin tutarı, dönemi ve sitesi değiştirilemez.');
            }
        });

        static::deleting(function (Gider $g) {
            if ($g->onay_durumu === 'onaylandi' || $g->odemeler()->exists()) {
                throw new DomainException('Onaylanmış ya da ödemesi olan gider silinemez.');
            }
        });
    }

    public function tedarikci(): BelongsTo
    {
        return $this->belongsTo(Tedarikci::class);
    }

    public function odemeler(): HasMany
    {
        return $this->hasMany(GiderOdemesi::class);
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }

    public function odenenKurus(): int
    {
        return (int) $this->odemeler()->sum('tutar_kurus');
    }

    public function kalanKurus(): int
    {
        return $this->tutar_kurus - $this->odenenKurus();
    }

    public function odemeDurumu(): string
    {
        $odenen = $this->odenenKurus();

        return match (true) {
            $odenen <= 0 => 'odenmedi',
            $odenen < $this->tutar_kurus => 'kismi',
            default => 'odendi',
        };
    }
}
