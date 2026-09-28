<?php

/*
 * Panel yardımcı rotaları (Filament dışı): özel belge indirme, dışa aktarma.
 * Tümü `auth` arkasında; yetki politikalarla ayrıca denetlenir.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/_')->name('panel.')->group(function () {
    //
});
