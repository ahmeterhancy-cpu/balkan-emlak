<?php

namespace Database\Seeders;

use App\Models\Kisi;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Models\PortfoyMedya;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEMO ilanlar. Başlık, fiyat, metrekare ve fotoğraflar temsilidir;
 * Balkan Emlak'ın gerçek portföyü değildir. Mülk sahipleri de uydurmadır
 * (ad soyadların sonuna "(Demo)" eklenir ki panelde karışmasın).
 */
class DemoPortfoySeeder extends Seeder
{
    public function run(): void
    {
        if (Portfoy::query()->exists()) {
            return; // bir kez
        }

        $danismanlar = User::where('rol', 'emlak_danismani')->orderBy('id')->pluck('id')->all();
        $k = fn (string $il, string $ilce, ?string $mahalle = null) => $this->konum($il, $ilce, $mahalle);

        $ilanlar = [
            // [başlık, işlem, kategori, tip, konum, fiyat TL, brüt, net, oda, banyo, kat, katSayısı, yaş, ısıtma, özellikler, fotoğraflar, öne çıkan, operasyon, açıklama]
            ['Şükrüpaşa\'da site içinde 3+1 geniş daire', 'satilik', 'konut', 'daire', $k('Edirne', 'Merkez', 'Şükrüpaşa'), 4_650_000, 150, 132, '3+1', 2, '3', 5, 6, 'dogalgaz_kombi',
                ['Asansör', 'Kapalı otopark', 'Güvenlik', 'Oyun parkı', 'Ebeveyn banyosu'], ['apartman-blok', 'salon-1', 'mutfak-1', 'yatak-odasi'], true, 'aktif',
                'Site içinde, güneş alan cephede 3+1 daire. Salon ve mutfak ayrı; ebeveyn banyolu yatak odası. Sitede kapalı otopark, çocuk oyun alanı ve 7 gün güvenlik bulunur. Okul ve toplu taşımaya yürüme mesafesinde.'],
            ['Kıyık\'ta bahçeli müstakil ev', 'satilik', 'konut', 'mustakil', $k('Edirne', 'Merkez', 'Kıyık'), 7_900_000, 220, 185, '4+1', 2, 'Bahçe katı', 2, 12, 'dogalgaz_kombi',
                ['Bahçe', 'Şömine', 'Kiler', 'Doğa manzarası'], ['mustakil-klasik', 'salon-3', 'mutfak-2', 'yatak-odasi'], true, 'aktif',
                '500 m² bahçe içinde iki katlı müstakil ev. Zemin katta salon, mutfak ve misafir odası; üst katta üç yatak odası. Şömineli salon ve geniş kiler.'],
            ['Sabuni\'de eşyalı kiralık 1+1', 'kiralik', 'konut', 'daire', $k('Edirne', 'Merkez', 'Sabuni'), 16_500, 70, 60, '1+1', 1, '2', 4, 8, 'dogalgaz_kombi',
                ['Çelik kapı', 'PVC doğrama', 'Görüntülü diafon'], ['studyo-ic', 'daire-ic', 'mutfak-3'], false, 'aktif',
                'Merkeze yürüme mesafesinde, eşyalı 1+1 daire. Öğrenci ve tek kişi için uygun. Beyaz eşyalar dahil.'],
            ['Karaağaç yolunda modern villa', 'satilik', 'konut', 'villa', $k('Edirne', 'Merkez', 'Karaağaç'), 16_750_000, 320, 280, '5+1', 3, 'Bahçe katı', 3, 2, 'yerden',
                ['Havuz', 'Bahçe', 'Ebeveyn banyosu', 'Giyinme odası', 'Teras', 'Ankastre mutfak'], ['villa-havuz-3', 'salon-5', 'mutfak-1', 'yatak-odasi', 'villa-havuz-1'], true, 'aktif',
                'Yeni yapım, havuzlu ve geniş bahçeli villa. Yerden ısıtma, akıllı ev altyapısı ve teraslı üst kat. Ana yola yakın, sakin konumda.'],
            ['Yeniimaret\'te kiralık 3+1 aile dairesi', 'kiralik', 'konut', 'daire', $k('Edirne', 'Merkez', 'Yeniimaret'), 24_000, 135, 120, '3+1', 1, '1', 4, 15, 'dogalgaz_kombi',
                ['Balkon', 'Okula yakın'], ['apartman-balkon', 'salon-2', 'mutfak-2'], false, 'aktif',
                'Aile için uygun, bakımlı 3+1 daire. İki balkonlu, geniş mutfaklı. Okul ve markete yakın.'],
            ['Erikli\'de denize yakın yazlık', 'satilik', 'konut', 'yazlik', $k('Edirne', 'Keşan', 'Erikli'), 5_200_000, 110, 95, '2+1', 1, '1', 2, 9, 'klima',
                ['Deniz manzarası', 'Bahçe', 'Teras'], ['yazlik-beyaz', 'salon-4', 'mutfak-3'], true, 'aktif',
                'Denize birkaç dakika yürüme mesafesinde, bahçe kullanımlı 2+1 yazlık. Teraslı ve klimalı.'],
            ['Keşan merkezde satılık dükkân', 'satilik', 'ticari', 'dukkan', $k('Edirne', 'Keşan', 'Yukarı Zaferiye'), 6_300_000, 95, 90, null, 1, 'Zemin', 5, 20, null,
                ['Yola cephe', 'Köşe parsel'], ['dukkan-ic', 'sira-evler'], false, 'aktif',
                'Cadde üzerinde, köşe konumda geniş vitrinli dükkân. Yaya trafiği yüksek, her türlü ticarete uygun.'],
            ['Talatpaşa bulvarında kiralık ofis', 'kiralik', 'ticari', 'ofis', $k('Edirne', 'Merkez', 'Talatpaşa'), 32_000, 180, 160, null, 2, '4', 6, 4, 'merkezi',
                ['Asansör', 'Jeneratör', 'Kapalı otopark'], ['ofis-bina', 'ofis-ic'], true, 'aktif',
                'Yeni binada, açık ofis düzenine uygun 180 m² ofis katı. Toplantı odası ve iki tuvalet. Jeneratör ve kapalı otopark.'],
            ['Havsa\'da yola cepheli tarla', 'satilik', 'arsa', 'tarla', $k('Edirne', 'Havsa', 'Hacıisa'), 1_850_000, 12_500, null, null, null, null, null, null, null,
                ['Yola cephe', 'Elektrik', 'Su'], ['tarla-bugday', 'tarla-ahir'], false, 'aktif',
                'Asfalt yola cepheli, düz ve sulanabilir tarla. Elektrik direğine yakın. Tapu hazır.'],
            ['Uzunköprü\'de konut imarlı arsa', 'satilik', 'arsa', 'imarli_arsa', $k('Edirne', 'Uzunköprü', 'Mescit'), 2_400_000, 620, null, null, null, null, null, null, null,
                ['Elektrik', 'Su', 'Yola cephe'], ['bag-evi', 'tarla-bugday'], false, 'aktif',
                'Konut imarlı, altyapısı hazır arsa. Çevresi yapılaşmış, merkeze yakın.'],
            ['İstasyon\'da rezidans 2+1', 'satilik', 'konut', 'rezidans', $k('Edirne', 'Merkez', 'İstasyon'), 3_950_000, 105, 88, '2+1', 1, '6', 9, 3, 'merkezi',
                ['Asansör', 'Güvenlik', 'Kapalı otopark', 'Isı yalıtımı'], ['rezidans-dis', 'salon-5', 'mutfak-1'], false, 'rezerve',
                'Rezidans projesinde 2+1 daire. Güvenlik görevlisi ve kapalı otoparklı; aidata sosyal alan kullanımı dahil.'],
            ['Dilaverbey\'de satılan 3+1 daire', 'satilik', 'konut', 'daire', $k('Edirne', 'Merkez', 'Dilaverbey'), 3_400_000, 125, 110, '3+1', 1, '2', 4, 18, 'dogalgaz_kombi',
                ['Balkon'], ['sira-ev', 'salon-2'], false, 'satildi',
                'Satışı tamamlanan portföy; referans olarak listede tutuluyor.'],
            // Yayında olmayanlar: taslak ve incelemede (vitrinde görünmemeli)
            ['Kıyık\'ta taslak müstakil ev', 'satilik', 'konut', 'mustakil', $k('Edirne', 'Merkez', 'Kıyık'), 6_100_000, 180, 150, '3+1', 2, 'Bahçe katı', 2, 20, 'soba',
                [], ['ahsap-ev'], false, 'aktif', 'Bilgileri henüz tamamlanmadı.', 'taslak'],
            ['Keşan\'da incelemedeki 2+1', 'kiralik', 'konut', 'daire', $k('Edirne', 'Keşan', 'Cumhuriyet'), 14_000, 90, 80, '2+1', 1, '3', 4, 10, 'dogalgaz_kombi',
                ['Balkon'], ['daire-ic'], false, 'aktif', 'Danışman tarafından incelemeye gönderildi; yayın onayı bekliyor.', 'incelemede'],
        ];

        foreach ($ilanlar as $i => $v) {
            [$baslik, $islem, $kategori, $tip, $konum, $fiyat, $brut, $net, $oda, $banyo, $kat, $katSayisi, $yas, $isitma, $ozellikler, $fotolar, $oneCikan, $operasyon, $aciklama] = $v;
            $yayin = $v[19] ?? 'yayinda';

            $sahip = Kisi::create([
                'ad_soyad' => fake('tr_TR')->name().' (Demo)',
                'telefon' => '0555 000 '.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT).' '.str_pad((string) (20 + $i), 2, '0', STR_PAD_LEFT),
                'iletisim_tercihi' => 'telefon',
                'notlar' => 'Demo kayıt — gerçek kişi değildir.',
            ]);

            $p = Portfoy::create([
                'baslik' => $baslik,
                'islem' => $islem,
                'kategori' => $kategori,
                'tip' => $tip,
                'il_id' => $konum['il'],
                'ilce_id' => $konum['ilce'],
                'mahalle_id' => $konum['mahalle'],
                'acik_adres' => 'Demo açık adres — sitede gösterilmez.',
                'enlem' => 41.6771 + ($i - 6) * 0.004,
                'boylam' => 26.5557 + ($i - 6) * 0.005,
                'harita_gosterimi' => 'yaklasik',
                'fiyat_kurus' => $fiyat * 100,
                'para_birimi' => 'TRY',
                'depozito_kurus' => $islem === 'kiralik' ? $fiyat * 200 : null,
                'aidat_kurus' => in_array($tip, ['daire', 'rezidans'], true) ? 90_000 : null,
                'brut_m2' => $brut,
                'net_m2' => $net,
                'oda' => $oda,
                'banyo' => $banyo,
                'kat' => $kat,
                'kat_sayisi' => $katSayisi,
                'bina_yasi' => $yas,
                'isitma' => $isitma,
                'asansor' => in_array('Asansör', $ozellikler, true) ? true : ($kategori === 'arsa' ? null : false),
                'otopark' => in_array('Kapalı otopark', $ozellikler, true) ? 'kapali' : ($kategori === 'arsa' ? null : 'acik'),
                'balkon' => $kategori === 'konut' ? true : null,
                'esyali' => str_contains($baslik, 'eşyalı') ? true : ($kategori === 'konut' ? false : null),
                'site_icinde' => str_contains($baslik, 'site') || $tip === 'rezidans',
                'ada' => $kategori === 'arsa' ? (string) (100 + $i) : null,
                'parsel' => $kategori === 'arsa' ? (string) (10 + $i) : null,
                'imar_durumu' => $tip === 'imarli_arsa' ? 'Konut alanı (beyan)' : ($tip === 'tarla' ? 'Tarım alanı (beyan)' : null),
                'ozellikler' => $ozellikler,
                'aciklama' => '<p>'.$aciklama.'</p><p><em>Bu bir demo ilandır; gerçek bir gayrimenkulü temsil etmez.</em></p>',
                'operasyon_durumu' => $operasyon,
                'yayin_durumu' => $yayin,
                'one_cikan' => $oneCikan,
                'danisman_id' => $danismanlar[$i % max(1, count($danismanlar))] ?? null,
                'mulk_sahibi_id' => $sahip->id,
                'ic_notlar' => 'Demo iç not: mülk sahibi hafta içi 18.00 sonrası aranmalı.',
                'komisyon_anlasmasi' => $islem === 'satilik' ? '%2 + KDV (demo)' : 'Bir kira bedeli (demo)',
                'yayin_tarihi' => $yayin === 'yayinda' ? now()->subDays(30 - $i * 2) : null,
            ]);

            foreach ($fotolar as $sira => $foto) {
                $yol = IcerikSeeder::gorselKopyala($foto, 'portfoyler');
                if ($yol) {
                    PortfoyMedya::create([
                        'portfoy_id' => $p->id,
                        'yol' => $yol,
                        'tur' => 'foto',
                        'alt_metin' => $baslik.' — fotoğraf '.($sira + 1),
                        'kapak' => $sira === 0,
                        'sira' => $sira,
                    ]);
                }
            }
        }

        // Fiyat geçmişi örneği: bir ilanda indirim
        $p = Portfoy::where('tip', 'mustakil')->where('yayin_durumu', 'yayinda')->first();
        $p?->update(['fiyat_kurus' => 7_650_000 * 100]);
    }

    /** @return array{il:int,ilce:int,mahalle:?int} */
    private function konum(string $il, string $ilce, ?string $mahalle): array
    {
        $ilK = Konum::where('tur', 'il')->where('ad', $il)->firstOrFail();
        $ilceK = Konum::where('tur', 'ilce')->where('ust_id', $ilK->id)->where('ad', $ilce)->firstOrFail();
        $mahK = $mahalle ? Konum::where('tur', 'mahalle')->where('ust_id', $ilceK->id)->where('ad', $mahalle)->first() : null;

        return ['il' => $ilK->id, 'ilce' => $ilceK->id, 'mahalle' => $mahK?->id];
    }
}
