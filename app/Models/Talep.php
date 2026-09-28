<?php

namespace App\Models;

use App\Casts\Gun;
use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Web formundan gelen başvuru ya da panelde açılan müşteri talebi.
 * Forma özgü alanlar `detay` içinde (ör. yönetim teklifinde blok ve
 * bağımsız bölüm sayısı).
 */
class Talep extends Model
{
    use IslemKaydeder;

    protected $table = 'talepler';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tur' => TalepTuru::class,
            'durum' => TalepDurumu::class,
            'detay' => 'array',
            'tercih_edilen_zaman' => 'datetime',
            'sonraki_takip' => Gun::class,
            'kvkk_onay' => 'boolean',
            'pazarlama_izni' => 'boolean',
            'butce_min_kurus' => 'integer',
            'butce_max_kurus' => 'integer',
        ];
    }

    public const KAYNAKLAR = [
        'web' => 'Web sitesi',
        'telefon' => 'Telefon',
        'whatsapp' => 'WhatsApp',
        'ofis' => 'Ofis ziyareti',
        'referans' => 'Referans',
        'ilan_sitesi' => 'İlan sitesi',
        'diger' => 'Diğer',
    ];

    public const SONUCLAR = ['beklemede' => 'Beklemede', 'kabul' => 'Kabul edildi', 'ret' => 'Reddedildi'];

    /** `detay` anahtarlarının okunur adları (panelde gösterim). */
    public const DETAY_ETIKETLERI = [
        'islem' => 'İşlem',
        'kategori' => 'Gayrimenkul türü',
        'konum' => 'Genel konum',
        'site_adi' => 'Site / apartman adı',
        'blok_sayisi' => 'Blok sayısı',
        'bagimsiz_bolum_sayisi' => 'Bağımsız bölüm sayısı',
        'ihtiyaclar' => 'İhtiyaçlar',
        'iletisim_tercihi' => 'İletişim tercihi',
    ];

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function kisi(): BelongsTo
    {
        return $this->belongsTo(Kisi::class);
    }

    public function atanan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atanan_id');
    }

    public function notlar(): HasMany
    {
        return $this->hasMany(TalepNotu::class)->latest();
    }

    public function ilgilenilenPortfoyler(): BelongsToMany
    {
        return $this->belongsToMany(Portfoy::class, 'talep_portfoy');
    }

    public function randevular(): HasMany
    {
        return $this->hasMany(Randevu::class);
    }
}
