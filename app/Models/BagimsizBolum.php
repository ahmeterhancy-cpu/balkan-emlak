<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BagimsizBolum extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'bagimsiz_bolumler';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['tur' => 'daire', 'aktif' => true];

    protected $fillable = ['site_id', 'blok_id', 'no', 'kat', 'tur', 'brut_m2', 'arsa_payi', 'arsa_payi_payda', 'aktif', 'notlar'];

    protected function casts(): array
    {
        return ['brut_m2' => 'float', 'aktif' => 'boolean', 'arsa_payi' => 'integer', 'arsa_payi_payda' => 'integer'];
    }

    public const TURLER = [
        'daire' => 'Daire', 'dukkan' => 'Dükkân', 'ofis' => 'Ofis', 'depo' => 'Depo', 'otopark' => 'Otopark', 'diger' => 'Diğer',
    ];

    protected static function booted(): void
    {
        static::saving(function (BagimsizBolum $b) {
            if ($b->blok_id && (int) Blok::whereKey($b->blok_id)->value('site_id') !== (int) $b->site_id) {
                throw new DomainException('Blok bu siteye ait değil.');
            }
        });
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }

    public function iliskiler(): HasMany
    {
        return $this->hasMany(BolumIliskisi::class)->orderByDesc('baslangic');
    }

    public function borclar(): HasMany
    {
        return $this->hasMany(Borc::class);
    }

    public function tahsilatlar(): HasMany
    {
        return $this->hasMany(Tahsilat::class);
    }

    /** "A Blok No 12" */
    public function etiket(): string
    {
        return trim(($this->blok?->ad ? $this->blok->ad.' ' : '').'No '.$this->no);
    }

    /**
     * O tarihteki borç sorumlusu. Kural: açıkça "borç sorumlusu" işaretli
     * etkin ilişki; yoksa etkin malik. İşletme kuralı değişirse yalnız
     * burası değişir (şartname 8.1).
     */
    public function sorumluKisiId(DateTimeInterface|string|null $tarih = null): ?int
    {
        $gun = Carbon::parse($tarih ?? now())->toDateString();

        $etkin = $this->iliskiler()
            ->where('baslangic', '<=', $gun)
            ->where(fn ($q) => $q->whereNull('bitis')->orWhere('bitis', '>=', $gun))
            ->get();

        return $etkin->firstWhere('borc_sorumlusu', true)?->kisi_id
            ?? $etkin->firstWhere('rol', 'malik')?->kisi_id;
    }
}
