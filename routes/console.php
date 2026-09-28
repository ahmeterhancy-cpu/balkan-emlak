<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Zamanlanmış işler — sunucuda her dakika `php artisan schedule:run` cron'u gerekir (DEPLOY.md).
 *
 * Her gece şifreli yedek (son YEDEK_SAKLA adet tutulur); her pazar son yedek geçici
 * bir veritabanına geri yüklenerek doğrulanır (şartname 15, 17). Sonuç işlem geçmişine yazılır.
 */
Schedule::command('balkan:yedek')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('balkan:yedek-dogrula')->weeklyOn(0, '04:30')->withoutOverlapping();
