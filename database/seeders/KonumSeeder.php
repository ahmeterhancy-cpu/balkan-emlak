<?php

namespace Database\Seeders;

use App\Models\Konum;
use Illuminate\Database\Seeder;

/**
 * Başlangıç konum ağacı. Hizmet verilen şehir ve ilçeler henüz
 * teyit edilmedi (şartname 18/1); Edirne varsayımıyla kuruldu,
 * panelden (Emlak → Konumlar) değiştirilebilir.
 */
class KonumSeeder extends Seeder
{
    public const AGAC = [
        'Edirne' => [
            'Merkez' => ['Şükrüpaşa', 'Sabuni', 'Kıyık', 'Yeniimaret', 'Karaağaç', 'İstasyon', 'Talatpaşa', 'Dilaverbey'],
            'Keşan' => ['Yukarı Zaferiye', 'Aşağı Zaferiye', 'Erikli', 'Cumhuriyet'],
            'Uzunköprü' => ['Mescit', 'Cumhuriyet'],
            'Havsa' => ['Hacıisa', 'Varoş'],
        ],
    ];

    public function run(): void
    {
        $ilSira = 0;
        foreach (self::AGAC as $il => $ilceler) {
            $ilKaydi = Konum::firstOrCreate(['ust_id' => null, 'tur' => 'il', 'ad' => $il], ['sira' => $ilSira++]);

            $ilceSira = 0;
            foreach ($ilceler as $ilce => $mahalleler) {
                $ilceKaydi = Konum::firstOrCreate(['ust_id' => $ilKaydi->id, 'tur' => 'ilce', 'ad' => $ilce], ['sira' => $ilceSira++]);

                foreach ($mahalleler as $mahalle) {
                    Konum::firstOrCreate(['ust_id' => $ilceKaydi->id, 'tur' => 'mahalle', 'ad' => $mahalle]);
                }
            }
        }
    }
}
