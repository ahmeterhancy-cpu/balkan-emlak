<?php

namespace App\Console\Commands;

use App\Services\Yedek\Yedekleyici;
use Illuminate\Console\Command;
use Throwable;

/** Şifreli yedek al ve eski yedekleri temizle. Zamanlayıcı her gece çalıştırır (routes/console.php). */
class YedekAl extends Command
{
    protected $signature = 'balkan:yedek {--sifresiz : Yalnız yerelde; YEDEK_PAROLA olmadan}';

    protected $description = 'Veritabanı + özel belgeler + medya için şifreli yedek alır';

    public function handle(Yedekleyici $y): int
    {
        if ($this->option('sifresiz') && app()->isProduction()) {
            $this->error('Canlıda şifresiz yedek alınmaz.');

            return self::FAILURE;
        }

        try {
            $yol = $y->al((bool) $this->option('sifresiz'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $silinen = $y->temizle((int) config('app.yedek_sakla', 14));

        $this->info('Yedek alındı: '.$yol.' ('.number_format(filesize($yol) / 1048576, 1, ',', '.').' MB)');
        if ($silinen) {
            $this->line("Eski yedek silindi: {$silinen}");
        }
        $this->warn('Yedeği sunucu dışına da kopyalayın (şartname 15). Sunucu kaybolursa buradaki yedek de gider.');

        return self::SUCCESS;
    }
}
