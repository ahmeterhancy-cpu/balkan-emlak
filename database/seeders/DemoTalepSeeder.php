<?php

namespace Database\Seeders;

use App\Enums\RandevuDurumu;
use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Models\Komisyon;
use App\Models\Portfoy;
use App\Models\Randevu;
use App\Models\Talep;
use App\Models\User;
use Illuminate\Database\Seeder;

/** DEMO başvurular, randevular ve bir komisyon kaydı. Kişiler uydurmadır. */
class DemoTalepSeeder extends Seeder
{
    public function run(): void
    {
        if (Talep::query()->exists()) {
            return;
        }

        $danisman = User::where('rol', 'emlak_danismani')->orderBy('id')->first();
        $yayinda = Portfoy::yayinda()->orderBy('id')->get();

        $ilk = $yayinda->first();

        $bilgi = Talep::create([
            'tur' => TalepTuru::IlanBilgi,
            'ad_soyad' => 'Ayşe Demir (Demo)',
            'telefon' => '0555 111 22 33',
            'eposta' => 'ayse.demo@example.com',
            'mesaj' => 'Merhaba, ilanı hafta sonu görmek isterim.',
            'portfoy_id' => $ilk?->id,
            'tercih_edilen_zaman' => now()->addDays(3)->setTime(11, 0),
            'durum' => TalepDurumu::RandevuPlanlandi,
            'atanan_id' => $ilk?->danisman_id,
            'kvkk_onay' => true,
        ]);

        Randevu::create([
            'talep_id' => $bilgi->id,
            'portfoy_id' => $ilk?->id,
            'danisman_id' => $ilk?->danisman_id,
            'musteri_adi' => $bilgi->ad_soyad,
            'musteri_telefon' => $bilgi->telefon,
            'baslangic' => now()->addDays(3)->setTime(11, 0),
            'durum' => RandevuDurumu::Planlandi,
        ]);

        Talep::create([
            'tur' => TalepTuru::PortfoyBasvurusu,
            'ad_soyad' => 'Mehmet Kaya (Demo)',
            'telefon' => '0555 222 33 44',
            'mesaj' => 'Karaağaç\'taki evimi kiraya vermek istiyorum.',
            'detay' => ['islem' => 'Kiralama', 'kategori' => 'Konut', 'konum' => 'Karaağaç, Edirne Merkez'],
            'durum' => TalepDurumu::Yeni,
            'kvkk_onay' => true,
        ]);

        Talep::create([
            'tur' => TalepTuru::YonetimTeklifi,
            'ad_soyad' => 'Can Yıldız (Demo)',
            'telefon' => '0555 333 44 55',
            'eposta' => 'can.demo@example.com',
            'mesaj' => 'Yönetim şirketimizi değiştirmeyi düşünüyoruz.',
            'detay' => [
                'site_adi' => 'Örnek Sitesi (Demo)',
                'konum' => 'Şükrüpaşa, Edirne Merkez',
                'blok_sayisi' => 3,
                'bagimsiz_bolum_sayisi' => 96,
                'ihtiyaclar' => ['Genel Yönetim', 'Şeffaf Aidat Yönetimi', 'Teknik Yönetim'],
            ],
            'durum' => TalepDurumu::IletisimeGecildi,
            'sonuc' => 'beklemede',
            'atanan_id' => User::where('rol', 'sirket_yoneticisi')->value('id'),
            'sonraki_takip' => now()->addDays(5),
            'kvkk_onay' => true,
        ]);

        Talep::create([
            'tur' => TalepTuru::Iletisim,
            'ad_soyad' => 'Zeynep Arslan (Demo)',
            'eposta' => 'zeynep.demo@example.com',
            'konu' => 'Çalışma saatleri',
            'mesaj' => 'Cumartesi günleri ofisiniz açık mı?',
            'durum' => TalepDurumu::Yeni,
            'kvkk_onay' => true,
        ]);

        Talep::create([
            'tur' => TalepTuru::MusteriTalebi,
            'kaynak' => 'telefon',
            'ad_soyad' => 'Burak Şahin (Demo)',
            'telefon' => '0555 444 55 66',
            'aranan_islem' => 'satilik',
            'aranan_kategori' => 'konut',
            'butce_min_kurus' => 3_000_000 * 100,
            'butce_max_kurus' => 4_500_000 * 100,
            'aranan_konum' => 'Edirne Merkez, okula yakın',
            'mesaj' => '3+1, asansörlü, otoparklı daire arıyor.',
            'durum' => TalepDurumu::GorusmeSuruyor,
            'atanan_id' => $danisman?->id,
            'sonraki_takip' => now()->addDays(2),
        ]);

        $satilan = Portfoy::where('operasyon_durumu', 'satildi')->first();
        if ($satilan) {
            Komisyon::create([
                'portfoy_id' => $satilan->id,
                'danisman_id' => $satilan->danisman_id,
                'taraf' => 'satici',
                'beklenen_kurus' => 68_000 * 100,
                'gerceklesen_kurus' => 68_000 * 100,
                'para_birimi' => 'TRY',
                'odeme_tarihi' => now()->subDays(12),
                'durum' => 'tahsil_edildi',
                'aciklama' => 'Demo komisyon kaydı.',
            ]);
        }
    }
}
