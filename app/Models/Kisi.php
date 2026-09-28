<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mülk sahibi ya da müşteri. Bu kayıtlar HİÇBİR herkese açık sayfaya veya
 * veri ucuna girmez.
 */
class Kisi extends Model
{
    use IslemKaydeder;

    protected $table = 'kisiler';

    protected $fillable = ['tur', 'ad_soyad', 'telefon', 'eposta', 'iletisim_tercihi', 'pazarlama_izni', 'notlar', 'olusturan_id'];

    protected function casts(): array
    {
        return ['pazarlama_izni' => 'boolean'];
    }

    public const TURLER = ['bireysel' => 'Bireysel', 'kurum' => 'Kurum'];

    public const ILETISIM_TERCIHLERI = ['telefon' => 'Telefon', 'whatsapp' => 'WhatsApp', 'eposta' => 'E-posta'];

    public function portfoyler(): HasMany
    {
        return $this->hasMany(Portfoy::class, 'mulk_sahibi_id');
    }

    public function talepler(): HasMany
    {
        return $this->hasMany(Talep::class, 'kisi_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    /** Karşılaştırma için telefonu yalnız rakamlara indir, baştaki 0/90'ı at. */
    public static function telefonAnahtari(?string $telefon): ?string
    {
        $rakam = preg_replace('/\D/', '', (string) $telefon);
        if ($rakam === '') {
            return null;
        }

        return substr($rakam, -10);
    }

    /**
     * Telefon ya da e-posta benzerliğiyle olası mükerrer kayıtlar.
     * Otomatik birleştirme YAPILMAZ; yalnız uyarı için (şartname 7.2).
     *
     * @return Collection<int, Kisi>
     */
    public static function olasiMukerrerler(?string $telefon, ?string $eposta, ?int $haricId = null): Collection
    {
        $anahtar = static::telefonAnahtari($telefon);
        $eposta = $eposta ? mb_strtolower(trim($eposta)) : null;

        if (! $anahtar && ! $eposta) {
            return new Collection;
        }

        return static::query()
            ->when($haricId, fn ($q) => $q->whereKeyNot($haricId))
            ->where(function ($q) use ($anahtar, $eposta) {
                if ($anahtar) {
                    $q->orWhere('telefon', 'like', '%'.substr($anahtar, -7));
                }
                if ($eposta) {
                    $q->orWhereRaw('lower(eposta) = ?', [$eposta]);
                }
            })
            ->limit(20)
            ->get()
            ->filter(fn (Kisi $k) => ($anahtar && static::telefonAnahtari($k->telefon) === $anahtar)
                || ($eposta && mb_strtolower((string) $k->eposta) === $eposta))
            ->values();
    }
}
