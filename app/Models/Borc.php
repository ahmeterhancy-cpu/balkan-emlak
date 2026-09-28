<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use App\Support\Para;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Bağımsız bölüm borcu. Düzeltme / iptal: `duzeltilen_borc_id` dolu,
 * işaretli tutarlı karşı kayıt (gerekçeli). Asıl borcun net tutarı =
 * tutar + düzeltmeler; kalan = net − dağıtılan tahsilat.
 */
class Borc extends Model
{
    use DegismezMaliKayit, IslemKaydeder, SiteyeBagli;

    protected $table = 'borclar';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['para_birimi' => 'TRY'];

    protected $fillable = [
        'site_id', 'bagimsiz_bolum_id', 'kisi_id', 'borclandirma_id', 'duzeltilen_borc_id', 'tur', 'donem',
        'aciklama', 'vade', 'tutar_kurus', 'para_birimi', 'gerekce', 'olusturan_id',
    ];

    public array $degisebilirAlanlar = ['aciklama'];

    protected function casts(): array
    {
        return ['vade' => Gun::class, 'tutar_kurus' => 'integer'];
    }

    public const TURLER = Borclandirma::TURLER + ['acilis' => 'Açılış bakiyesi (devir)'];

    public const DURUMLAR = ['acik' => 'Açık', 'kismi' => 'Kısmen ödendi', 'odendi' => 'Ödendi', 'gecikmis' => 'Vadesi geçti', 'iptal' => 'İptal edildi'];

    protected static function booted(): void
    {
        static::creating(function (Borc $b) {
            $b->olusturan_id ??= auth()->id();
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

    public function borclandirma(): BelongsTo
    {
        return $this->belongsTo(Borclandirma::class);
    }

    public function duzeltilen(): BelongsTo
    {
        return $this->belongsTo(Borc::class, 'duzeltilen_borc_id');
    }

    public function duzeltmeler(): HasMany
    {
        return $this->hasMany(Borc::class, 'duzeltilen_borc_id');
    }

    public function dagitimlar(): HasMany
    {
        return $this->hasMany(TahsilatDagitimi::class);
    }

    /** Asıl borçlar (düzeltme satırları hariç). @param Builder<Borc> $sorgu */
    public function scopeAsil(Builder $sorgu): void
    {
        $sorgu->whereNull($sorgu->qualifyColumn('duzeltilen_borc_id'));
    }

    /**
     * net_kurus ve odenen_kurus sütunlarını ekler (liste ve raporlar için tek sorgu).
     *
     * @param Builder<Borc> $sorgu
     */
    public function scopeBakiyeli(Builder $sorgu): void
    {
        $t = $sorgu->getModel()->getTable();
        $sorgu->select($t.'.*')
            ->selectRaw("($t.tutar_kurus + COALESCE((SELECT SUM(d.tutar_kurus) FROM borclar d WHERE d.duzeltilen_borc_id = $t.id), 0)) AS net_kurus")
            ->selectRaw("COALESCE((SELECT SUM(td.tutar_kurus) FROM tahsilat_dagitimlari td WHERE td.borc_id = $t.id), 0) AS odenen_kurus");
    }

    /** Kalanı sıfırdan büyük asıl borçlar. @param Builder<Borc> $sorgu */
    public function scopeAcik(Builder $sorgu): void
    {
        $t = $sorgu->getModel()->getTable();
        $sorgu->asil()->whereRaw(
            "($t.tutar_kurus + COALESCE((SELECT SUM(d.tutar_kurus) FROM borclar d WHERE d.duzeltilen_borc_id = $t.id), 0))
             - COALESCE((SELECT SUM(td.tutar_kurus) FROM tahsilat_dagitimlari td WHERE td.borc_id = $t.id), 0) > 0"
        );
    }

    public function netKurus(): int
    {
        if (array_key_exists('net_kurus', $this->attributes)) {
            return (int) $this->attributes['net_kurus'];
        }

        return $this->tutar_kurus + (int) DB::table('borclar')->where('duzeltilen_borc_id', $this->id)->sum('tutar_kurus');
    }

    public function odenenKurus(): int
    {
        if (array_key_exists('odenen_kurus', $this->attributes)) {
            return (int) $this->attributes['odenen_kurus'];
        }

        return (int) DB::table('tahsilat_dagitimlari')->where('borc_id', $this->id)->sum('tutar_kurus');
    }

    public function kalanKurus(): int
    {
        return $this->netKurus() - $this->odenenKurus();
    }

    public function durum(): string
    {
        $kalan = $this->kalanKurus();

        return match (true) {
            $this->netKurus() === 0 => 'iptal',
            $kalan <= 0 => 'odendi',
            $this->odenenKurus() > 0 => 'kismi',
            $this->vade->isPast() && ! $this->vade->isToday() => 'gecikmis',
            default => 'acik',
        };
    }

    public function tutarMetni(): string
    {
        return Para::bicimle($this->tutar_kurus, $this->para_birimi);
    }
}
