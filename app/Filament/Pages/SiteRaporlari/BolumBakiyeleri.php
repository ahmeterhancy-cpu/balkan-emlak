<?php

namespace App\Filament\Pages\SiteRaporlari;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class BolumBakiyeleri extends SiteRaporSayfasi
{
    protected static string $rapor = 'bolum-bakiyeleri';

    protected static ?string $slug = 'site-raporlari/bolum-bakiyeleri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 2;

    protected function girdiler(): array
    {
        return [static::tarihAlani('gun', 'Vade referans tarihi')];
    }
}
