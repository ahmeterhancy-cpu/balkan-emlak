<?php

/*
 * Panel yardımcı rotaları (Filament dışı): özel belge indirme, dışa aktarma.
 * Tümü `auth` arkasında; yetki politikalarla ayrıca denetlenir.
 */

use App\Http\Controllers\Panel\BelgeController;
use App\Http\Controllers\Panel\SiteDosyaController;
use App\Http\Controllers\Panel\SiteRaporYazdirController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/_')->name('panel.')->group(function () {
    Route::get('belge/{belge}', BelgeController::class)->name('belge.indir');

    // Site yönetimi özel dosyaları
    Route::get('dekont/{tahsilat}', [SiteDosyaController::class, 'dekont'])->name('dekont.indir');
    Route::get('gider-belgesi/{gider}', [SiteDosyaController::class, 'giderBelgesi'])->name('gider-belgesi.indir');
    Route::get('site-belgesi/{belge}', [SiteDosyaController::class, 'siteBelgesi'])->name('site-belgesi.indir');
    Route::get('is-emri/{isEmri}/{alan}/{sira}', [SiteDosyaController::class, 'isEmriFotografi'])
        ->whereNumber('sira')->name('is-emri.fotograf');

    // Site raporlarının yazdırılabilir (PDF'e kaydedilebilir) görünümü
    Route::get('site-rapor/{rapor}/yazdir', SiteRaporYazdirController::class)->name('site-rapor.yazdir');
});
