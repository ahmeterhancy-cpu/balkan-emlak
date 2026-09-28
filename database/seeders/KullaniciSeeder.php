<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Ayar;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Yerel geliştirme hesapları. Canlıda bu parolalar KULLANILMAZ:
 * `php artisan balkan:yonetici` yeni rastgele parola üretir.
 *
 * Yerel giriş (tümü aynı parola): Balkan2026!
 *   yonetici@balkanemlak.test  — sistem yöneticisi
 *   mudur@balkanemlak.test     — şirket yöneticisi
 *   editor@balkanemlak.test    — içerik editörü
 *   emrullah@balkanemlak.test  — emlak danışmanı (diğer iki kişi de öyle)
 *
 * Emrullah Cam, Abdurrahman Seçen ve Muhammed Ünal'ın ad ve telefonları
 * tanıtım görsellerinden alındı; görevleri, yazımları ve yayımlama izinleri
 * TEYİT EDİLMEDİ (şartname 2). Görev alanı bu yüzden boş, ekip sayfasında
 * gösterilmiyorlar (`ekipte_goster` kapalı). WhatsApp da teyit edilmediği
 * için kapalı; panelden (Ayarlar) açılınca sitede WhatsApp düğmeleri çıkar.
 */
class KullaniciSeeder extends Seeder
{
    public const YEREL_PAROLA = 'Balkan2026!';

    public const ILETISIM_KISILERI = [
        ['ad' => 'Emrullah Cam', 'telefon' => '0505 393 07 22', 'eposta' => 'emrullah@balkanemlak.test'],
        ['ad' => 'Abdurrahman Seçen', 'telefon' => '0552 517 68 10', 'eposta' => 'abdurrahman@balkanemlak.test'],
        ['ad' => 'Muhammed Ünal', 'telefon' => '0543 394 90 53', 'eposta' => 'muhammed@balkanemlak.test'],
    ];

    public function run(): void
    {
        $parola = app()->isProduction() ? bin2hex(random_bytes(12)) : self::YEREL_PAROLA;

        $sabit = [
            ['yonetici@balkanemlak.test', 'Sistem Yöneticisi', Rol::SistemYoneticisi],
            ['mudur@balkanemlak.test', 'Şirket Yöneticisi', Rol::SirketYoneticisi],
            ['editor@balkanemlak.test', 'İçerik Editörü', Rol::IcerikEditoru],
        ];

        foreach ($sabit as [$eposta, $ad, $rol]) {
            User::updateOrCreate(['email' => $eposta], ['name' => $ad, 'password' => $parola, 'rol' => $rol, 'aktif' => true]);
        }

        foreach (self::ILETISIM_KISILERI as $i => $kisi) {
            User::updateOrCreate(['email' => $kisi['eposta']], [
                'name' => $kisi['ad'],
                'password' => $parola,
                'rol' => Rol::EmlakDanismani,
                'aktif' => true,
                'telefon' => $kisi['telefon'],
                'whatsapp' => null, // WhatsApp kullanımı teyit edilmedi
                'gorev' => null,
                'ekipte_goster' => false,
                'ekip_sirasi' => $i,
            ]);
        }

        // Sitede görünen iletişim listesi (ayar); boşsa doldur
        if (Ayar::al('iletisim_kisileri') === null) {
            Ayar::kaydet([
                'iletisim_kisileri' => array_map(fn ($k) => [
                    'ad' => $k['ad'],
                    'telefon' => $k['telefon'],
                    'gorev' => null,
                    'whatsapp' => false, // teyit edilince panelden açılır
                ], self::ILETISIM_KISILERI),
                'telefon' => self::ILETISIM_KISILERI[0]['telefon'],
            ]);
        }
    }
}
