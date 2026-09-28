<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Panelden düzenlenen site ayarları (iletişim, sosyal medya, ana sayfa
 * metinleri, görseller).
 *
 * Yalnız ANAHTARLAR listesindeki anahtarlar kaydedilir. Kaydetme önbelleği
 * her seferinde siler — silmezse panelde değiştirilen değer sitede
 * görünmez (Dura Coffee'de saatler yedi).
 */
class Ayar extends Model
{
    protected $table = 'ayarlar';

    protected $fillable = ['anahtar', 'deger'];

    private const ONBELLEK = 'balkan_ayarlar';

    private const BELLEK = 'balkan.ayarlar';

    /** JSON olarak saklanan anahtarlar. */
    private const JSON = ['iletisim_kisileri'];

    /**
     * Anahtar => varsayılan. Doğrulanmamış işletme bilgisi (adres,
     * çalışma saatleri, e-posta) BİLEREK boş: boş alan sitede hiç
     * çizilmez, yer tutucu metin yayımlanmaz (şartname 1, 2).
     */
    public const ANAHTARLAR = [
        'firma_adi' => 'Balkan Emlak ve Yönetim',
        'firma_unvani' => null,
        'alt_marka' => 'Balkan Site Yönetimi',
        'telefon' => null,
        'whatsapp' => null,
        'eposta' => null,
        'adres' => null,
        'calisma_saatleri' => null,
        'acil_iletisim' => null,
        'iletisim_kisileri' => [],
        'instagram' => null,
        'facebook' => null,
        'linkedin' => null,
        'youtube' => null,
        'harita_embed' => null,
        'bildirim_eposta' => null,
        'logo' => null,
        'logo_acik' => null,
        'hero_gorsel' => null,
        'cta_gorsel' => null,
        'yonetim_gorsel' => null,
        'ana_baslik' => 'Gayrimenkulünüzde doğru adım, yaşam alanınızda güvenilir yönetim.',
        'ana_aciklama' => 'Balkan Emlak ve Yönetim ile satılık ve kiralık portföyleri keşfedin; apartman ve site yönetimi ihtiyaçlarınız için bizimle iletişime geçin.',
        'yonetim_slogani' => 'Apartmanınız bize emanet.',
        'seo_aciklama' => 'Balkan Emlak ve Yönetim: satılık ve kiralık gayrimenkul portföyü, apartman ve site yönetimi hizmetleri.',
    ];

    public static function tumu(): array
    {
        // İstek başına bellek: kapsayıcıda tutulur, testlerde her uygulama örneğiyle sıfırlanır
        if (app()->bound(self::BELLEK)) {
            return app(self::BELLEK);
        }

        try {
            $kayitli = Cache::rememberForever(self::ONBELLEK, fn () => static::query()->pluck('deger', 'anahtar')->all());
        } catch (Throwable) {
            $kayitli = []; // tablo henüz yok (ilk göç)
        }

        $sonuc = self::ANAHTARLAR;
        foreach ($kayitli as $anahtar => $deger) {
            if (! array_key_exists($anahtar, self::ANAHTARLAR)) {
                continue;
            }
            $sonuc[$anahtar] = in_array($anahtar, self::JSON, true)
                ? (json_decode((string) $deger, true) ?: [])
                : $deger;
        }

        app()->instance(self::BELLEK, $sonuc);

        return $sonuc;
    }

    public static function al(string $anahtar, mixed $varsayilan = null): mixed
    {
        $deger = static::tumu()[$anahtar] ?? null;

        return ($deger === null || $deger === '' || $deger === []) ? $varsayilan : $deger;
    }

    /** @param array<string, mixed> $degerler */
    public static function kaydet(array $degerler): void
    {
        foreach ($degerler as $anahtar => $deger) {
            if (! array_key_exists($anahtar, self::ANAHTARLAR)) {
                continue;
            }
            if (in_array($anahtar, self::JSON, true)) {
                $deger = json_encode(array_values((array) $deger), JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($deger)) {
                $deger = $deger ? '1' : '0';
            }

            static::updateOrCreate(['anahtar' => $anahtar], ['deger' => $deger === null ? null : (string) $deger]);
        }

        static::onbellegiTemizle();
    }

    public static function onbellegiTemizle(): void
    {
        Cache::forget(self::ONBELLEK);
        app()->forgetInstance(self::BELLEK);
    }
}
