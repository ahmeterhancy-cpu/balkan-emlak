<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;

/**
 * Rol → izin haritası (şartname 9).
 *
 * Görüntüleme, oluşturma, düzenleme, yayımlama ve dışa aktarma ayrı
 * izinlerdir. Menü gizlemek yetmez: politikalar (app/Policies) ve
 * Filament kaynaklarının sorgu kapsamları aynı haritayı okur, böylece
 * doğrudan adresle gelen istek de reddedilir.
 *
 * "_tumu" ile biten izin yoksa kullanıcı yalnız KENDİ kayıtlarını görür:
 * danışman yalnız sorumlu olduğu portföyleri, kendisine atanmış talepleri
 * ve kendi randevularını.
 */
class Yetki
{
    public const IZINLER = [
        'panel' => 'Panele giriş',

        'portfoy.goruntule_tumu' => 'Tüm portföyleri görme',
        'portfoy.olustur' => 'Portföy oluşturma',
        'portfoy.duzenle_tumu' => 'Başkasının portföyünü düzenleme',
        'portfoy.yayimla' => 'İlan yayımlama / yayından kaldırma',
        'portfoy.sil' => 'Portföy silme',
        'portfoy.ic_bilgi' => 'Mülk sahibi, iç not ve komisyon anlaşmasını görme',
        'belge.goruntule' => 'Özel belgeleri görme / indirme',

        'kisi.goruntule_tumu' => 'Tüm kişileri görme',
        'talep.goruntule_tumu' => 'Tüm talepleri görme',
        'talep.ata' => 'Talep atama',
        'randevu.goruntule_tumu' => 'Tüm randevuları görme',
        'komisyon.goruntule' => 'Komisyon kayıtları',

        'konum.yonet' => 'Konum listesi',
        'icerik.yonet' => 'Web içerikleri',
        'kullanici.yonet' => 'Kullanıcılar ve roller',
        'ayar.yonet' => 'Site ayarları',
        'islem_gecmisi.goruntule' => 'İşlem geçmişi',
        'disa_aktar' => 'Dışa aktarma',
    ];

    /** @var array<string, list<string>> */
    private const ROLLER = [
        'sistem_yoneticisi' => ['*'],

        'sirket_yoneticisi' => [
            'panel',
            'portfoy.goruntule_tumu', 'portfoy.olustur', 'portfoy.duzenle_tumu', 'portfoy.yayimla',
            'portfoy.sil', 'portfoy.ic_bilgi', 'belge.goruntule',
            'kisi.goruntule_tumu', 'talep.goruntule_tumu', 'talep.ata', 'randevu.goruntule_tumu',
            'komisyon.goruntule', 'konum.yonet', 'icerik.yonet', 'islem_gecmisi.goruntule', 'disa_aktar',
        ],

        // Kendi portföyü: oluşturur, düzenler, incelemeye gönderir; yayımlayamaz.
        'emlak_danismani' => [
            'panel', 'portfoy.olustur', 'portfoy.ic_bilgi', 'belge.goruntule',
        ],

        'icerik_editoru' => ['panel', 'icerik.yonet'],

        // Site yönetimi modülü gelene kadar yalnız panele giriş.
        'site_yoneticisi' => ['panel'],
        'muhasebe_sorumlusu' => ['panel'],
        'saha_personeli' => ['panel'],
    ];

    public static function var(string $izin, ?User $kullanici = null): bool
    {
        $kullanici ??= auth()->user();

        if (! $kullanici instanceof User || ! $kullanici->aktif) {
            return false;
        }

        $rol = $kullanici->rol instanceof Rol ? $kullanici->rol->value : (string) $kullanici->rol;
        $izinler = self::ROLLER[$rol] ?? [];

        return in_array('*', $izinler, true) || in_array($izin, $izinler, true);
    }

    /** @return list<string> */
    public static function rolIzinleri(Rol $rol): array
    {
        $izinler = self::ROLLER[$rol->value] ?? [];

        return in_array('*', $izinler, true) ? array_keys(self::IZINLER) : $izinler;
    }
}
