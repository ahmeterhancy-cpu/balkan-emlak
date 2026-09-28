<?php

namespace App\Console\Commands;

use App\Models\IslemKaydi;
use App\Services\Yedek\Yedekleyici;
use Illuminate\Console\Command;
use Throwable;

/**
 * Yedeği boş ve geçici bir veritabanına geri yükleyip satır sayılarını, mali
 * kontrol toplamlarını ve dosya özetlerini karşılaştırır (şartname 17:
 * "Yedekten geri yükleme test ortamında başarıyla doğrulanır"). Canlı veriye dokunmaz.
 */
class YedekDogrula extends Command
{
    protected $signature = 'balkan:yedek-dogrula {dosya? : Boşsa en son yedek}';

    protected $description = 'Yedeği geçici veritabanına geri yükleyerek doğrular';

    public function handle(Yedekleyici $y): int
    {
        $yol = $this->argument('dosya') ?: $y->sonYedek();
        if (! $yol) {
            $this->error('Yedek bulunamadı. Önce: php artisan balkan:yedek');

            return self::FAILURE;
        }

        try {
            $sonuc = $y->dogrula($yol);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        IslemKaydi::yaz('guncellendi', null, null, $sonuc + ['dosya' => basename($yol)], 'Yedek doğrulaması: '.($sonuc['basarili'] ? 'başarılı' : 'BAŞARISIZ'));

        $this->line("Yedek: {$yol}");
        $this->line("Oluşturma: {$sonuc['olusturma']} · {$sonuc['tablolar']} tablo · {$sonuc['satirlar']} satır · {$sonuc['dosyalar']} dosya");

        if (! $sonuc['basarili']) {
            foreach ($sonuc['hatalar'] as $h) {
                $this->error('✗ '.$h);
            }

            return self::FAILURE;
        }

        $this->info('✓ Geri yükleme doğrulandı: satır sayıları, mali kontrol toplamları ve dosyalar yedekle birebir.');

        return self::SUCCESS;
    }
}
