<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arıza / bakım / iş emri (şartname 8.5). */
class IsEmri extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'is_emirleri';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['oncelik' => 'normal', 'durum' => 'acik'];

    protected $fillable = [
        'site_id', 'blok_id', 'bagimsiz_bolum_id', 'bakim_plani_id', 'konu', 'aciklama', 'fotograflar', 'bildiren_ad',
        'bildiren_telefon', 'oncelik', 'durum', 'atanan_id', 'tedarikci_id', 'hedef_tarih', 'yapilan_islem',
        'tamamlanma_fotograflari', 'gider_id', 'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'fotograflar' => 'array',
            'tamamlanma_fotograflari' => 'array',
            'hedef_tarih' => Gun::class,
            'tamamlanma_zamani' => 'datetime',
            'kapanma_zamani' => 'datetime',
        ];
    }

    public const ONCELIKLER = ['dusuk' => 'Düşük', 'normal' => 'Normal', 'yuksek' => 'Yüksek', 'acil' => 'Acil'];

    public const DURUMLAR = [
        'acik' => 'Açık', 'atandi' => 'Atandı', 'islemde' => 'İşlemde', 'dis_hizmet' => 'Dış hizmet bekleniyor',
        'tamamlandi' => 'Tamamlandı', 'kapatildi' => 'Kapatıldı',
    ];

    protected static function booted(): void
    {
        static::creating(function (IsEmri $e) {
            $e->olusturan_id ??= auth()->id();
        });

        static::saving(function (IsEmri $e) {
            $baglar = ['blok_id' => Blok::class, 'bagimsiz_bolum_id' => BagimsizBolum::class, 'bakim_plani_id' => BakimPlani::class, 'gider_id' => Gider::class];
            foreach ($baglar as $alan => $sinif) {
                if ($e->{$alan} && (int) $sinif::whereKey($e->{$alan})->value('site_id') !== (int) $e->site_id) {
                    throw new DomainException('İş emrine bağlanan kayıt başka bir siteye ait.');
                }
            }
            if ($e->atanan_id && $e->durum === 'acik') {
                $e->durum = 'atandi';
            }
        });
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }

    public function bolum(): BelongsTo
    {
        return $this->belongsTo(BagimsizBolum::class, 'bagimsiz_bolum_id');
    }

    public function bakimPlani(): BelongsTo
    {
        return $this->belongsTo(BakimPlani::class);
    }

    public function atanan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atanan_id');
    }

    public function tedarikci(): BelongsTo
    {
        return $this->belongsTo(Tedarikci::class);
    }

    public function gider(): BelongsTo
    {
        return $this->belongsTo(Gider::class);
    }

    /** İş yapıldı (saha personeli ya da yönetici). Bakım planı varsa tarihi ilerler. */
    public function tamamla(string $yapilanIslem, array $fotograflar = []): void
    {
        if (in_array($this->durum, ['tamamlandi', 'kapatildi'], true)) {
            throw new DomainException('İş emri zaten tamamlanmış.');
        }

        $this->forceFill([
            'durum' => 'tamamlandi',
            'yapilan_islem' => $yapilanIslem,
            'tamamlanma_fotograflari' => $fotograflar ?: null,
            'tamamlanma_zamani' => now(),
        ])->save();

        $this->bakimPlani?->yapildi(now());
    }

    /** Yetkili kapatma (yalnız tamamlanmış iş). */
    public function kapat(): void
    {
        if ($this->durum !== 'tamamlandi') {
            throw new DomainException('Yalnız tamamlanmış iş emri kapatılabilir.');
        }

        $this->forceFill(['durum' => 'kapatildi', 'kapatan_id' => auth()->id(), 'kapanma_zamani' => now()])->save();
    }

    public function yenidenAc(string $gerekce): void
    {
        $this->forceFill([
            'durum' => $this->atanan_id ? 'atandi' : 'acik',
            'yapilan_islem' => trim(($this->yapilan_islem ? $this->yapilan_islem."\n" : '').'Yeniden açıldı: '.$gerekce),
            'tamamlanma_zamani' => null,
            'kapatan_id' => null,
            'kapanma_zamani' => null,
        ])->save();
    }
}
