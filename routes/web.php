<?php

use App\Http\Controllers\AnaSayfaController;
use App\Http\Controllers\BasvuruController;
use App\Http\Controllers\BilgiController;
use App\Http\Controllers\KurumsalController;
use App\Http\Controllers\PortfoyController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\YonetimController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/panel.php';

/*
 * Vitrin. Form gönderimleri "basvuru" hız sınırına tabi (spam;
 * şartname 15). Başvurular panelde Başvurular ve Talepler'e düşer.
 */

Route::get('/', AnaSayfaController::class)->name('anasayfa');

// Emlak
Route::get('/emlak', [PortfoyController::class, 'index'])->name('emlak.index');
Route::get('/ilan/{portfoy:slug}', [PortfoyController::class, 'show'])->name('ilan.detay');
Route::get('/onizleme/ilan/{portfoy:id}', [PortfoyController::class, 'onizleme'])
    ->middleware('auth')->name('ilan.onizleme');

Route::get('/gayrimenkulunuzu-bize-birakin', [BasvuruController::class, 'portfoyFormu'])->name('basvuru.portfoy');

// Site ve apartman yönetimi
Route::get('/site-yonetimi', [YonetimController::class, 'index'])->name('yonetim.index');
Route::get('/site-yonetimi/{hizmet:slug}', [YonetimController::class, 'hizmet'])->name('yonetim.hizmet');
Route::get('/yonetim-teklifi', [BasvuruController::class, 'teklifFormu'])->name('yonetim.teklif');

// Kurumsal
Route::get('/hakkimizda', [KurumsalController::class, 'hakkimizda'])->name('hakkimizda');
Route::get('/referanslar', [KurumsalController::class, 'referanslar'])->name('referanslar');
Route::get('/iletisim', [KurumsalController::class, 'iletisim'])->name('iletisim');
Route::get('/sayfa/{slug}', [KurumsalController::class, 'sayfa'])->name('sayfa');

// Bilgi merkezi
Route::get('/bilgi-merkezi', [BilgiController::class, 'index'])->name('bilgi.index');
Route::get('/bilgi-merkezi/{yazi:slug}', [BilgiController::class, 'show'])->name('bilgi.detay');

// Form gönderimleri
Route::middleware('throttle:basvuru')->group(function () {
    Route::post('/ilan/{portfoy:slug}/talep', [BasvuruController::class, 'ilanTalebi'])->name('ilan.talep');
    Route::post('/gayrimenkulunuzu-bize-birakin', [BasvuruController::class, 'portfoyBasvurusu'])->name('basvuru.portfoy.gonder');
    Route::post('/yonetim-teklifi', [BasvuruController::class, 'teklif'])->name('yonetim.teklif.gonder');
    Route::post('/iletisim', [BasvuruController::class, 'iletisim'])->name('iletisim.gonder');
    Route::post('/kisa-talep', [BasvuruController::class, 'kisaTalep'])->name('basvuru.kisa');
});

// SEO
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
