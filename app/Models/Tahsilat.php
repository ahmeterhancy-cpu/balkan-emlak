<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\DegismezMaliKayit;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tahsilat (+) ya da iadesi (−). Dekontla gelen tahsilat doğrulanana
 * kadar (dogrulandi=false) kasaya ve borca işlenmez.
 */
class Tahsilat extends Model
{
    use DegismezMaliKayit, IslemKaydeder, SiteyeBagli;

    protected $table = 'tahsilatlar';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['tur' => 'tahsilat', 'para_birimi' => 'TRY', 'dogrulandi' => true];

    protected $fillable = [
        'site_id', 'hesap_id', 'bagimsiz_bolum_id', 'kisi_id', 'tur', 'iade_edilen_id', 'tarih', 'tutar_kurus',
        'para_birimi', 'yontem', 'aciklama', 'belge_yolu', 'dogrulandi', 'gerekce', 'olusturan_id',
    ];

    public array $degisebilirAlanlar = ['dogrulandi', 'aciklama'];

    protected function casts(): array
    {
        return ['tarih' => Gun::class, 'dogrulandi' => 'boolean', 'tutar_kurus' => 'integer'];
    }

    public const YONTEMLER = ['nakit' => 'Nakit', 'havale' => 'Havale', 'eft' => 'EFT', 'pos' => 'POS / kredi kartı', 'diger' => 'Diğer'];

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(Hesap::class);
    }

    public function bolum(): BelongsTo
    {
        return $this->belongsTo(BagimsizBolum::class, 'bagimsiz_bolum_id');
    }

    public function kisi(): BelongsTo
    {
        return $this->belongsTo(Kisi::class);
    }

    public function dagitimlar(): HasMany
    {
        return $this->hasMany(TahsilatDagitimi::class);
    }

    /** Bu tahsilata ait iade kayıtları (tam ya da avanstan kısmi). */
    public function iadeler(): HasMany
    {
        return $this->hasMany(Tahsilat::class, 'iade_edilen_id');
    }

    /** Tahsilat − iadeleri. İade satırının kendisi için 0. */
    public function netTutarKurus(): int
    {
        if ($this->tur !== 'tahsilat') {
            return 0;
        }

        return $this->tutar_kurus + (int) $this->iadeler()->sum('tutar_kurus');
    }

    /** Borçlara net ayrılan (iade ile geri alınanlar düşülmüş). */
    public function dagitilanKurus(): int
    {
        return (int) $this->dagitimlar()->sum('tutar_kurus');
    }

    /** Borca dağıtılmamış kısım = avans. Doğrulanmamış tahsilat henüz sayılmaz. */
    public function dagitilmamisKurus(): int
    {
        return $this->dogrulandi && $this->tur === 'tahsilat' ? $this->netTutarKurus() - $this->dagitilanKurus() : 0;
    }

    public function tamamenIadeMi(): bool
    {
        return $this->tur === 'tahsilat' && $this->iadeler()->exists() && $this->netTutarKurus() === 0;
    }
}
