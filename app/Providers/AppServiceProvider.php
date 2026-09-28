<?php

namespace App\Providers;

use App\Models\Hizmet;
use App\Models\Referans;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Geliştirmede sessiz N+1'i yakala (canlıda kapalı)
        Model::preventLazyLoading(! $this->app->isProduction());

        Paginator::defaultView('parca.sayfalama');

        // Web formları: IP başına dakikada 6, günde 40 gönderim
        RateLimiter::for('basvuru', fn (Request $istek) => [
            Limit::perMinute(6)->by($istek->ip()),
            Limit::perDay(40)->by($istek->ip()),
        ]);

        /*
         * Vitrin düzeninin her sayfada ihtiyaç duyduğu veriler: menüdeki
         * hizmetler ve referans menüsünün görünürlüğü (yayında referans
         * yoksa menüde boş sayfaya bağlantı verilmez). Yalnız vitrin
         * şablonuna bağlanır; panel etkilenmez. Site ayarları için
         * her yerde `ayar('anahtar')` yardımcısı kullanılır.
         */
        View::composer('duzen.site', function ($view) {
            $view->with('menuHizmetleri', Hizmet::yayinda()->get(['id', 'baslik', 'slug', 'ikon']));
            $view->with('referansVar', Referans::yayinda()->exists());
        });
    }
}
