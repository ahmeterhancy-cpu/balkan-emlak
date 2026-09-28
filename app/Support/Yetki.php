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
 * ve kendi randevularını. Site yönetiminde "site.goruntule_tumu" yoksa
 * kullanıcı yalnız site_kullanici tablosunda atandığı siteleri görür.
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

        // Site ve apartman yönetimi — "site.goruntule_tumu" yoksa yalnız atanmış siteler
        'site.goruntule_tumu' => 'Tüm siteleri görme',
        'site.yonet' => 'Site, blok, bağımsız bölüm, malik/kiracı kaydı',
        'finans.goruntule' => 'Borç, tahsilat, gider ve hesapları görme',
        'finans.borclandir' => 'Borçlandırma (önizleme ve kesinleştirme), borç düzeltme',
        'finans.tahsilat' => 'Tahsilat kaydı, iade, dekont doğrulama',
        'finans.gider' => 'Gider kaydı ve ödeme',
        'finans.hesap' => 'Kasa/banka, açılış bakiyesi, transfer, mutabakat',
        'gider.onayla' => 'Gider onayı (site onay limitine kadar)',
        'gider.onayla_limitsiz' => 'Onay limitini aşan gider onayı',
        'donem.kapat' => 'Dönem kapama',
        'donem.duzelt' => 'Kapalı dönemde gerekçeli düzeltme',
        'operasyon.yonet' => 'İş emri, bakım planı, görev, duyuru yönetimi',
        'operasyon.saha' => 'Kendisine atanan görev ve iş emirlerini tamamlama',
        'site_belge.goruntule' => 'Site belgeleri',
        'site_rapor' => 'Site raporları',
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
            'site.goruntule_tumu', 'site.yonet', 'finans.goruntule', 'finans.borclandir', 'finans.tahsilat',
            'finans.gider', 'finans.hesap', 'gider.onayla', 'gider.onayla_limitsiz', 'donem.kapat', 'donem.duzelt',
            'operasyon.yonet', 'site_belge.goruntule', 'site_rapor',
        ],

        // Kendi portföyü: oluşturur, düzenler, incelemeye gönderir; yayımlayamaz.
        'emlak_danismani' => [
            'panel', 'portfoy.olustur', 'portfoy.ic_bilgi', 'belge.goruntule',
        ],

        'icerik_editoru' => ['panel', 'icerik.yonet'],

        // Yalnız atanmış siteler (site_kullanici). Mali kayıt girmez; limit içi gider onaylar.
        'site_yoneticisi' => [
            'panel', 'site.yonet', 'finans.goruntule', 'finans.gider', 'gider.onayla',
            'operasyon.yonet', 'site_belge.goruntule', 'site_rapor',
        ],
        // Yalnız atanmış siteler. Borçlandırma, tahsilat, gider, hesap, dönem kapama.
        'muhasebe_sorumlusu' => [
            'panel', 'finans.goruntule', 'finans.borclandir', 'finans.tahsilat', 'finans.gider', 'finans.hesap',
            'donem.kapat', 'site_belge.goruntule', 'site_rapor', 'disa_aktar',
        ],
        // Yalnız kendisine atanan görev ve iş emirleri (şartname 9)
        'saha_personeli' => ['panel', 'operasyon.saha'],
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
