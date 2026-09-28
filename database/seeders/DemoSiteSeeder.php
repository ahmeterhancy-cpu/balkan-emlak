<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\BagimsizBolum;
use App\Models\BakimPlani;
use App\Models\Blok;
use App\Models\BolumIliskisi;
use App\Models\Borclandirma;
use App\Models\Duyuru;
use App\Models\Gider;
use App\Models\Gorev;
use App\Models\Hesap;
use App\Models\IsEmri;
use App\Models\Kisi;
use App\Models\Konum;
use App\Models\Site;
use App\Models\Tedarikci;
use App\Models\User;
use App\Models\Varlik;
use App\Services\SiteFinans\Borclandirici;
use App\Services\SiteFinans\GiderServisi;
use App\Services\SiteFinans\HesapServisi;
use App\Services\SiteFinans\TahsilatServisi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEMO site yönetimi verisi: iki yerleşke, malik/kiracılar, üç aylık aidat,
 * kısmi tahsilatlar, giderler ve iş emirleri. Kişiler ve siteler UYDURMADIR
 * ("(Demo)" ekli). Mali kayıtlar gerçek servislerden geçerek oluşturulur;
 * doğrudan tabloya yazılmaz.
 *
 * Demo kullanıcılar (parola KullaniciSeeder::YEREL_PAROLA):
 *   siteyonetici@balkanemlak.test — Çamlık Sitesi'ne atanmış site yöneticisi
 *   muhasebe@balkanemlak.test     — iki siteye atanmış muhasebe sorumlusu
 *   saha@balkanemlak.test         — Çamlık Sitesi saha personeli
 */
class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        if (Site::query()->exists()) {
            return;
        }

        $yonetici = User::where('rol', 'sistem_yoneticisi')->first();
        auth()->login($yonetici); // işlem geçmişi ve servis yetkileri için

        $parola = KullaniciSeeder::YEREL_PAROLA;
        $siteYoneticisi = User::updateOrCreate(['email' => 'siteyonetici@balkanemlak.test'], ['name' => 'Site Yöneticisi (Demo)', 'password' => $parola, 'rol' => Rol::SiteYoneticisi, 'aktif' => true]);
        $muhasebe = User::updateOrCreate(['email' => 'muhasebe@balkanemlak.test'], ['name' => 'Muhasebe (Demo)', 'password' => $parola, 'rol' => Rol::MuhasebeSorumlusu, 'aktif' => true]);
        $saha = User::updateOrCreate(['email' => 'saha@balkanemlak.test'], ['name' => 'Saha Personeli (Demo)', 'password' => $parola, 'rol' => Rol::SahaPersoneli, 'aktif' => true]);

        $edirne = Konum::where('tur', 'il')->where('ad', 'Edirne')->first();
        $merkez = Konum::where('tur', 'ilce')->where('ad', 'Merkez')->first();

        // --- Çamlık Sitesi: 2 blok × 12 daire, m² farklı ---
        $camlik = Site::create([
            'ad' => 'Çamlık Sitesi (Demo)', 'tur' => 'site', 'adres' => 'Demo adres, Şükrüpaşa', 'il_id' => $edirne?->id, 'ilce_id' => $merkez?->id,
            'yonetim_baslangic' => '2026-01-01', 'sorumlu_id' => $siteYoneticisi->id, 'onay_limiti_kurus' => 20_000_00, 'varsayilan_vade_gunu' => 10,
        ]);
        $camlik->kullanicilar()->attach([$siteYoneticisi->id, $muhasebe->id, $saha->id]);
        $this->bolumler($camlik, ['A Blok', 'B Blok'], 12, [95, 110, 125, 140]);

        // --- Yıldız Apartmanı: blok yok, 8 daire + 1 dükkân ---
        $yildiz = Site::create([
            'ad' => 'Yıldız Apartmanı (Demo)', 'tur' => 'apartman', 'adres' => 'Demo adres, Sabuni', 'il_id' => $edirne?->id, 'ilce_id' => $merkez?->id,
            'yonetim_baslangic' => '2026-03-01', 'onay_limiti_kurus' => 10_000_00,
        ]);
        $yildiz->kullanicilar()->attach([$muhasebe->id]);
        $this->bolumler($yildiz, [null], 8, [90, 90, 105, 105]);
        $dukkan = BagimsizBolum::create(['site_id' => $yildiz->id, 'no' => 'D1', 'kat' => 'Zemin', 'tur' => 'dukkan', 'brut_m2' => 60, 'arsa_payi' => 60, 'arsa_payi_payda' => 1000]);
        $this->malik($dukkan, 'Dükkân Sahibi (Demo)');

        $tedarikciler = collect([
            ['Demo Asansör Ltd.', 'asansor'], ['Demo Temizlik Hizmetleri', 'temizlik'], ['Demo Peyzaj', 'bahce'], ['Demo Elektrik Dağıtım', 'enerji'],
        ])->map(fn ($t) => Tedarikci::create(['ad' => $t[0], 'kategori' => $t[1]]));

        foreach ([[$camlik, 1500, 85_000_00], [$yildiz, 1200, 22_000_00]] as [$site, $aidat, $acilis]) {
            $banka = Hesap::create(['site_id' => $site->id, 'ad' => 'Banka hesabı', 'tur' => 'banka', 'banka_adi' => 'Demo Bank', 'iban' => 'TR00 0000 0000 0000 0000 0000 00']);
            $kasa = Hesap::create(['site_id' => $site->id, 'ad' => 'Kasa', 'tur' => 'kasa']);
            $hesap = app(HesapServisi::class);
            $hesap->acilisBakiyesi($banka, $acilis, '2026-07-01', 'Önceki yönetimden devir tutanağı (demo)');
            $hesap->acilisBakiyesi($kasa, 2_500_00, '2026-07-01', 'Kasa sayım tutanağı (demo)');

            // Temmuz–Eylül aidatları
            foreach (['2026-07', '2026-08', '2026-09'] as $donem) {
                $b = Borclandirma::create([
                    'site_id' => $site->id, 'donem' => $donem, 'tur' => 'aidat', 'aciklama' => $this->ay($donem).' aidatı',
                    'vade' => $donem.'-10', 'dagitim' => 'sabit', 'birim_kurus' => $aidat * 100,
                ]);
                app(Borclandirici::class)->kesinlestir($b);
            }

            // Tahsilatlar: çoğu tam, bazıları kısmi, biri fazla (avans), bazıları hiç
            $tahsilat = app(TahsilatServisi::class);
            foreach (BagimsizBolum::where('site_id', $site->id)->orderBy('id')->get() as $i => $bolum) {
                $toplamBorc = $aidat * 3;
                $odeme = match ($i % 6) {
                    0, 1, 2 => $toplamBorc,
                    3 => $aidat * 2,
                    4 => (int) ($aidat * 1.5),
                    default => 0,
                };
                if ($i === 7) {
                    $odeme = $toplamBorc + 500; // fazla ödeme → avans
                }
                if ($odeme > 0) {
                    $tahsilat->kaydet([
                        'hesap_id' => $i % 4 === 0 ? $kasa->id : $banka->id, 'bagimsiz_bolum_id' => $bolum->id,
                        'tarih' => '2026-09-'.str_pad((string) (3 + $i % 20), 2, '0', STR_PAD_LEFT),
                        'tutar_kurus' => $odeme * 100, 'yontem' => $i % 4 === 0 ? 'nakit' : 'havale',
                    ]);
                }
            }

            // Doğrulanmayı bekleyen bir dekont
            $son = BagimsizBolum::where('site_id', $site->id)->orderByDesc('id')->first();
            $tahsilat->kaydet([
                'hesap_id' => $banka->id, 'bagimsiz_bolum_id' => $son->id, 'tarih' => '2026-09-25',
                'tutar_kurus' => $aidat * 100, 'yontem' => 'havale', 'dogrulandi' => false, 'aciklama' => 'Dekont gönderildi, banka ekstresiyle karşılaştırılacak',
            ]);

            $hesap->transfer($kasa, $banka, 1_000_00, '2026-09-20', 'Kasa fazlası bankaya yatırıldı');

            // Giderler: ödenmiş, kısmi, onay bekleyen, taslak
            $gider = app(GiderServisi::class);
            $g1 = Gider::create(['site_id' => $site->id, 'tedarikci_id' => $tedarikciler[0]->id, 'kategori' => 'asansor', 'belge_tarihi' => '2026-09-05', 'belge_no' => 'DMO-101', 'donem' => '2026-09', 'vade' => '2026-09-20', 'tutar_kurus' => 4_500_00, 'aciklama' => 'Eylül asansör bakımı']);
            $gider->onayla($gider->onayaGonder($g1));
            $gider->ode($g1, $banka, 4_500_00, '2026-09-18');

            $g2 = Gider::create(['site_id' => $site->id, 'tedarikci_id' => $tedarikciler[1]->id, 'kategori' => 'temizlik', 'belge_tarihi' => '2026-09-01', 'belge_no' => 'DMO-102', 'donem' => '2026-09', 'vade' => '2026-09-30', 'tutar_kurus' => 12_000_00, 'aciklama' => 'Eylül temizlik hizmeti']);
            $gider->onayla($gider->onayaGonder($g2));
            $gider->ode($g2, $banka, 6_000_00, '2026-09-15', 'İlk taksit');

            $g3 = Gider::create(['site_id' => $site->id, 'tedarikci_id' => $tedarikciler[2]->id, 'kategori' => 'bahce', 'belge_tarihi' => '2026-09-22', 'donem' => '2026-09', 'tutar_kurus' => 26_000_00, 'aciklama' => 'Sonbahar peyzaj düzenlemesi']);
            $gider->onayaGonder($g3); // onay limitini aşıyor → şirket yöneticisi onayı

            Gider::create(['site_id' => $site->id, 'tedarikci_id' => $tedarikciler[3]->id, 'kategori' => 'enerji', 'belge_tarihi' => '2026-09-26', 'donem' => '2026-09', 'tutar_kurus' => 3_150_00, 'aciklama' => 'Ortak alan elektriği']);
        }

        // Ağustos'u kapat (Çamlık) — kapalı dönem davranışı görülsün
        app(HesapServisi::class)->donemKapat($camlik, '2026-08');

        // --- Operasyon (Çamlık) ---
        $asansor = Varlik::create(['site_id' => $camlik->id, 'blok_id' => Blok::where('site_id', $camlik->id)->value('id'), 'ad' => 'A Blok asansörü', 'tur' => 'asansor', 'marka_model' => 'Demo 630 kg']);
        $hidrofor = Varlik::create(['site_id' => $camlik->id, 'ad' => 'Hidrofor', 'tur' => 'hidrofor']);
        $yangin = Varlik::create(['site_id' => $camlik->id, 'ad' => 'Yangın tüpleri', 'tur' => 'yangin']);

        BakimPlani::create(['site_id' => $camlik->id, 'varlik_id' => $asansor->id, 'ad' => 'Aylık asansör bakımı', 'periyot_gun' => 30, 'son_bakim' => '2026-09-05', 'sonraki_bakim' => '2026-10-05', 'tedarikci_id' => $tedarikciler[0]->id]);
        $hidroforPlani = BakimPlani::create(['site_id' => $camlik->id, 'varlik_id' => $hidrofor->id, 'ad' => 'Hidrofor kontrolü', 'periyot_gun' => 90, 'son_bakim' => '2026-06-20', 'sonraki_bakim' => '2026-09-18', 'sorumlu_id' => $saha->id]);
        BakimPlani::create(['site_id' => $camlik->id, 'varlik_id' => $yangin->id, 'ad' => 'Yangın tüpü dolum kontrolü', 'periyot_gun' => 365, 'son_bakim' => '2025-11-01', 'sonraki_bakim' => '2026-11-01']);

        $bolum = BagimsizBolum::where('site_id', $camlik->id)->first();
        IsEmri::create(['site_id' => $camlik->id, 'bagimsiz_bolum_id' => $bolum->id, 'konu' => 'Banyo tavanında su sızıntısı', 'aciklama' => 'Üst kattan geldiği düşünülüyor.', 'bildiren_ad' => 'Malik (Demo)', 'oncelik' => 'yuksek', 'atanan_id' => $saha->id, 'hedef_tarih' => now()->addDays(2)->toDateString()]);
        IsEmri::create(['site_id' => $camlik->id, 'bakim_plani_id' => $hidroforPlani->id, 'konu' => 'Hidrofor periyodik kontrolü', 'oncelik' => 'normal', 'atanan_id' => $saha->id, 'hedef_tarih' => now()->addDays(5)->toDateString()]);
        IsEmri::create(['site_id' => $camlik->id, 'konu' => 'B Blok giriş lambası yanmıyor', 'oncelik' => 'dusuk']);
        $dis = IsEmri::create(['site_id' => $camlik->id, 'konu' => 'Otopark kapısı motoru', 'oncelik' => 'yuksek', 'tedarikci_id' => $tedarikciler[3]->id]);
        $dis->update(['durum' => 'dis_hizmet']);
        $biten = IsEmri::create(['site_id' => $camlik->id, 'konu' => 'Çocuk parkı salıncak zinciri', 'oncelik' => 'normal', 'atanan_id' => $saha->id]);
        $biten->tamamla('Zincir ve kilit değiştirildi.');

        foreach ([[0, 'Ortak alan temizlik kontrolü'], [1, 'Çöp konteyneri alanı yıkama'], [2, 'Bahçe sulama sistemi kontrolü']] as [$gun, $baslik]) {
            Gorev::create(['site_id' => $camlik->id, 'user_id' => $saha->id, 'baslik' => $baslik, 'tarih' => now()->addDays($gun)->toDateString()]);
        }

        Duyuru::create(['site_id' => $camlik->id, 'baslik' => 'Olağan genel kurul toplantısı', 'icerik' => 'Olağan genel kurul toplantısı ... tarihinde site toplantı salonunda yapılacaktır. (Demo)', 'durum' => 'yayinda']);
        Duyuru::create(['site_id' => $camlik->id, 'blok_id' => Blok::where('site_id', $camlik->id)->where('ad', 'B Blok')->value('id'), 'baslik' => 'B Blok su kesintisi', 'icerik' => 'Hidrofor bakımı nedeniyle 10.00–12.00 arası su kesintisi olacaktır. (Demo)', 'durum' => 'taslak']);

        auth()->logout();
    }

    private function bolumler(Site $site, array $bloklar, int $adet, array $m2): void
    {
        $sayac = 0;
        foreach ($bloklar as $blokAdi) {
            $blok = $blokAdi ? Blok::create(['site_id' => $site->id, 'ad' => $blokAdi]) : null;
            for ($no = 1; $no <= $adet; $no++) {
                $bolum = BagimsizBolum::create([
                    'site_id' => $site->id, 'blok_id' => $blok?->id, 'no' => (string) $no, 'kat' => (string) intdiv($no - 1, 4),
                    'tur' => 'daire', 'brut_m2' => $m2[($no - 1) % count($m2)], 'arsa_payi' => $m2[($no - 1) % count($m2)], 'arsa_payi_payda' => 1000,
                ]);
                $malik = $this->malik($bolum, fake('tr_TR')->name().' (Demo)');

                // Her beşinci dairede kiracı; her onuncuda kiracı borç sorumlusu
                if (++$sayac % 5 === 0) {
                    BolumIliskisi::create([
                        'bagimsiz_bolum_id' => $bolum->id, 'kisi_id' => Kisi::create(['ad_soyad' => fake('tr_TR')->name().' (Demo kiracı)'])->id,
                        'rol' => 'kiraci', 'baslangic' => '2026-02-01', 'borc_sorumlusu' => $sayac % 10 === 0,
                    ]);
                }
                unset($malik);
            }
        }
    }

    private function malik(BagimsizBolum $bolum, string $ad): Kisi
    {
        $kisi = Kisi::create(['ad_soyad' => $ad, 'telefon' => '0555 000 '.str_pad((string) ($bolum->id % 100), 2, '0', STR_PAD_LEFT).' 00']);
        BolumIliskisi::create(['bagimsiz_bolum_id' => $bolum->id, 'kisi_id' => $kisi->id, 'rol' => 'malik', 'baslangic' => '2020-01-01']);

        return $kisi;
    }

    private function ay(string $donem): string
    {
        return Carbon::createFromFormat('Y-m-d', $donem.'-01')->locale('tr')->translatedFormat('F Y');
    }
}
