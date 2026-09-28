<?php

/*
 * Panel yardımcı rotaları (Filament dışı): özel belge indirme, dışa aktarma.
 * Tümü `auth` arkasında; yetki politikalarla ayrıca denetlenir.
 */

use App\Http\Controllers\Panel\BelgeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/_')->name('panel.')->group(function () {
    Route::get('belge/{belge}', BelgeController::class)->name('belge.indir');
});
