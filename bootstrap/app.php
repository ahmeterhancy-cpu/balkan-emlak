<?php

use App\Models\Yonlendirme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Oturumsuz panel isteği Filament giriş ekranına (ayrı "login" rotası yok)
        $middleware->redirectGuestsTo('/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Eski adres yönlendirmeleri (panel → Web İçerikleri → Yönlendirmeler).
         * YALNIZ 404 olacak GET isteklerinde bakılır: var olan bir sayfa hiçbir
         * kayıtla ezilemez; panel ve önizleme adresleri hiç yönlendirilmez.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $istek) {
            if (! $istek->isMethod('GET') || $istek->is('admin', 'admin/*', 'onizleme/*', 'livewire/*')) {
                return null;
            }

            $y = Yonlendirme::bul($istek);
            if (! $y) {
                return null;
            }

            $y->isabetYaz();

            return redirect()->to($y->hedefAdresi(), $y->kod);
        });
    })->create();

/*
 * cPanel yerleşimi: uygulama public_html/balkan_app içinde, web kökü
 * public_html. Deploy web kökünün yolunu `.public_yolu` dosyasına yazar;
 * böylece public_path() hem web isteğinde hem artisan/cron'da (yedek,
 * görsel sürümleri, medya diski) gerçek web kökünü gösterir.
 * Yerelde dosya yok, Laravel'in kendi public/ klasörü kullanılır.
 */
if (is_file($publicYolu = dirname(__DIR__).'/.public_yolu') && is_dir($kok = trim((string) file_get_contents($publicYolu)))) {
    $app->usePublicPath($kok);
}

return $app;
