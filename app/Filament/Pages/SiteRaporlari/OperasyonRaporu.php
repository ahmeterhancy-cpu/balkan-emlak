<?php

namespace App\Filament\Pages\SiteRaporlari;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class OperasyonRaporu extends SiteRaporSayfasi
{
    protected static string $rapor = 'operasyon';

    protected static ?string $slug = 'site-raporlari/operasyon-raporu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 6;

    protected function girdiler(): array
    {
        return [static::tarihAlani('bas', 'Başlangıç'), static::tarihAlani('bit', 'Bitiş')];
    }
}
