<?php

namespace App\Console\Commands;

use App\Services\Yedek\Yedekleyici;
use Illuminate\Console\Command;
use Throwable;

/**
 * Yedeği CANLI veritabanına geri yükler: mevcut veri SİLİNİR ve yedektekiyle
 * değiştirilir. Önce yedek doğrulanır, sonra mevcut durumun güvenlik yedeği
 * alınır; geri yükleme tek transaction'dır.
 */
class YedekGeriYukle extends Command
{
    protected $signature = 'balkan:geri-yukle {dosya : Geri yüklenecek yedek} {--evet : Mevcut verinin silineceğini onaylıyorum}';

    protected $description = 'Yedeği mevcut veritabanına ve dosyalara geri yükler (mevcut veri silinir)';

    public function handle(Yedekleyici $y): int
    {
        if (! $this->option('evet')) {
            $this->error('Bu işlem mevcut tüm veriyi yedektekiyle DEĞİŞTİRİR. Emin iseniz --evet ile çalıştırın.');

            return self::FAILURE;
        }

        try {
            $guvenlik = $y->geriYukle($this->argument('dosya'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Geri yükleme tamamlandı.');
        $this->line('Önceki durumun güvenlik yedeği: '.$guvenlik);
        $this->warn('Önbelleği temizleyin: php artisan optimize:clear');

        return self::SUCCESS;
    }
}
