<?php

namespace App\Filament\Pages\SiteRaporlari;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class DonemOzeti extends SiteRaporSayfasi
{
    protected static string $rapor = 'donem-ozeti';

    protected static ?string $slug = 'site-raporlari/donem-ozeti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 1;

    protected function girdiler(): array
    {
        return [static::donemAlani('donem', 'Dönem (YYYY-AA)')];
    }
}
