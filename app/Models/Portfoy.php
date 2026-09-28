<?php

namespace App\Models;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Enums\OperasyonDurumu;
use App\Enums\YayinDurumu;
use App\Models\Concerns\IslemKaydeder;
use App\Support\Para;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Portföy = gayrimenkul kaydı + web ilanı.
 *
 * Herkese açık tarafa YALNIZ `yayinda()` kapsamından geçen kayıt çıkar ve
 * vitrin şablonları mülk sahibi, açık adres, iç not, komisyon ve belge
 * alanlarına hiç dokunmaz (şartname 5.3). Açık adres ve tam harita noktası
 * yalnız `adres_goster` / `harita_gosterimi=tam` seçiliyse görünür.
 */
class Portfoy extends Model
{
    use IslemKaydeder;

    protected $table = 'portfoyler';

    protected $guarded = ['id', 'portfoy_no'];

    protected function casts(): array
    {
        return [
            'islem' => Islem::class,
            'kategori' => Kategori::class,
            'operasyon_durumu' => OperasyonDurumu::class,
            'yayin_durumu' => YayinDurumu::class,
            'ozellikler' => 'array',
            'asansor' => 'boolean',
            'balkon' => 'boolean',
            'esyali' => 'boolean',
            'site_icinde' => 'boolean',
            'adres_goster' => 'boolean',
            'one_cikan' => 'boolean',
            'yayin_tarihi' => 'datetime',
            'enlem' => 'float',
            'boylam' => 'float',
            'fiyat_kurus' => 'integer',
            'depozito_kurus' => 'integer',
            'aidat_kurus' => 'integer',
        ];
    }

    public const ISITMA = [
        'dogalgaz_kombi' => 'Doğalgaz (kombi)',
        'merkezi' => 'Merkezi',
        'yerden' => 'Yerden ısıtma',
        'klima' => 'Klima',
        'soba' => 'Soba',
        'yok' => 'Yok',
    ];

    public const OTOPARK = ['yok' => 'Yok', 'acik' => 'Açık otopark', 'kapali' => 'Kapalı otopark'];

    public const HARITA_GOSTERIMI = [
        'yok' => 'Harita gösterme',
        'yaklasik' => 'Yaklaşık bölge (mahalle düzeyi)',
        'tam' => 'Tam nokta',
    ];

    /** Panelde etiket olarak seçilebilen hazır özellikler; serbest ekleme de yapılabilir. */
    public const OZELLIK_ONERILERI = [
        'Asansör', 'Güvenlik', 'Kapalı otopark', 'Havuz', 'Oyun parkı', 'Jeneratör', 'Hidrofor',
        'Isı yalıtımı', 'Ankastre mutfak', 'Ebeveyn banyosu', 'Giyinme odası', 'Teras', 'Bahçe',
        'Şömine', 'Kiler', 'Deniz manzarası', 'Doğa manzarası', 'Metroya / otobüse yakın',
        'Okula yakın', 'Çelik kapı', 'PVC doğrama', 'Görüntülü diafon', 'Yangın merdiveni',
        'Elektrik', 'Su', 'Yola cephe', 'Köşe parsel',
    ];

    protected static function booted(): void
    {
        static::creating(function (Portfoy $p) {
            $p->portfoy_no ??= static::sonrakiNo();
            $p->slug = static::slugUret($p->baslik, $p->portfoy_no);
            $p->olusturan_id ??= auth()->id();
        });

        static::saving(function (Portfoy $p) {
            if ($p->exists && $p->isDirty('baslik')) {
                $p->slug = static::slugUret($p->baslik, $p->portfoy_no);
            }

            if ($p->isDirty('yayin_durumu') && $p->yayin_durumu === YayinDurumu::Yayinda) {
                $p->yayin_tarihi ??= now();
            }

            if (auth()->check()) {
                $p->guncelleyen_id = auth()->id();
            }
        });

        // Fiyat geçmişi: ilk fiyat da dahil her değişiklik saklanır (şartname 7.1)
        static::created(function (Portfoy $p) {
            $p->fiyatGecmisi()->create([
                'eski_fiyat_kurus' => null,
                'yeni_fiyat_kurus' => $p->fiyat_kurus,
                'para_birimi' => $p->para_birimi,
                'user_id' => auth()->id(),
            ]);
        });

        static::updated(function (Portfoy $p) {
            if ($p->wasChanged('fiyat_kurus') || $p->wasChanged('para_birimi')) {
                $p->fiyatGecmisi()->create([
                    'eski_fiyat_kurus' => $p->getOriginal('fiyat_kurus'),
                    'yeni_fiyat_kurus' => $p->fiyat_kurus,
                    'para_birimi' => $p->para_birimi,
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }

    public static function sonrakiNo(): string
    {
        $son = static::query()->max('id') ?? 0;

        do {
            $son++;
            $no = 'BLK-'.str_pad((string) $son, 5, '0', STR_PAD_LEFT);
        } while (static::query()->where('portfoy_no', $no)->exists());

        return $no;
    }

    public static function slugUret(string $baslik, string $no): string
    {
        return Str::limit(Str::slug($baslik), 80, '').'-'.Str::lower($no);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ---------- İlişkiler ---------- */

    public function il(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'ilce_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Konum::class, 'mahalle_id');
    }

    public function danisman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'danisman_id');
    }

    public function mulkSahibi(): BelongsTo
    {
        return $this->belongsTo(Kisi::class, 'mulk_sahibi_id');
    }

    public function medya(): HasMany
    {
        return $this->hasMany(PortfoyMedya::class)->orderByDesc('kapak')->orderBy('sira')->orderBy('id');
    }

    public function fotograflar(): HasMany
    {
        return $this->medya()->where('tur', 'foto');
    }

    public function planlar(): HasMany
    {
        return $this->hasMany(PortfoyMedya::class)->where('tur', 'plan')->orderBy('sira');
    }

    public function kapak(): HasOne
    {
        return $this->hasOne(PortfoyMedya::class)->where('tur', 'foto')->orderByDesc('kapak')->orderBy('sira')->orderBy('id');
    }

    public function fiyatGecmisi(): HasMany
    {
        return $this->hasMany(FiyatGecmisi::class)->latest('id');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(PortfoyBelgesi::class);
    }

    public function talepler(): HasMany
    {
        return $this->hasMany(Talep::class);
    }

    public function randevular(): HasMany
    {
        return $this->hasMany(Randevu::class);
    }

    public function komisyonlar(): HasMany
    {
        return $this->hasMany(Komisyon::class);
    }

    /* ---------- Kapsamlar ---------- */

    /** Ziyaretçinin görebileceği tek kapsam. @param Builder<Portfoy> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('yayin_durumu', YayinDurumu::Yayinda->value);
    }

    /* ---------- Vitrin yardımcıları ---------- */

    public function fiyatMetni(): string
    {
        return Para::bicimle($this->fiyat_kurus, $this->para_birimi);
    }

    /** "Şükrüpaşa, Merkez / Edirne" — genel konum; açık adres DEĞİL. */
    public function konumMetni(): string
    {
        $parca = array_filter([
            $this->mahalle?->ad,
            trim(implode(' / ', array_filter([$this->ilce?->ad, $this->il?->ad]))),
        ]);

        return implode(', ', $parca);
    }

    public function tipEtiketi(): string
    {
        return Kategori::tipEtiketi($this->tip) ?? '';
    }

    /** Satıldı / kiralandı / çekildi ise talep düğmesi kapanır (şartname 5.3). */
    public function talepAlabilir(): bool
    {
        return in_array($this->operasyon_durumu, [OperasyonDurumu::Aktif, OperasyonDurumu::Rezerve], true);
    }

    public function alanMetni(): ?string
    {
        $m2 = $this->net_m2 ?: $this->brut_m2;

        return $m2 ? number_format($m2, 0, ',', '.').' m²' : null;
    }

    /**
     * Yayına almadan önce eksikler. Boş dizi = yayına hazır.
     *
     * @return list<string>
     */
    public function yayinEksikleri(): array
    {
        $eksik = [];

        if (blank($this->baslik)) {
            $eksik[] = 'Başlık';
        }
        if (! $this->fiyat_kurus) {
            $eksik[] = 'Fiyat';
        }
        if (! $this->il_id || ! $this->ilce_id) {
            $eksik[] = 'İl ve ilçe';
        }
        if (blank($this->aciklama) || mb_strlen(strip_tags((string) $this->aciklama)) < 40) {
            $eksik[] = 'Açıklama (en az 40 karakter)';
        }
        if (! $this->brut_m2 && ! $this->net_m2) {
            $eksik[] = 'Metrekare';
        }
        if ($this->kategori === Kategori::Konut && blank($this->oda)) {
            $eksik[] = 'Oda sayısı';
        }
        if (! $this->danisman_id) {
            $eksik[] = 'Sorumlu danışman';
        }
        if (! $this->medya()->where('tur', 'foto')->exists()) {
            $eksik[] = 'En az bir fotoğraf';
        }

        return $eksik;
    }

    /** Harita gösterilecekse [enlem, boylam, tam_mi]; değilse null. */
    public function haritaNoktasi(): ?array
    {
        if ($this->harita_gosterimi === 'yok' || $this->enlem === null || $this->boylam === null) {
            return null;
        }

        if ($this->harita_gosterimi === 'tam') {
            return [$this->enlem, $this->boylam, true];
        }

        // Yaklaşık: ~500 m ızgaraya yuvarla, gerçek nokta sızmasın
        return [round($this->enlem * 200) / 200, round($this->boylam * 200) / 200, false];
    }
}
