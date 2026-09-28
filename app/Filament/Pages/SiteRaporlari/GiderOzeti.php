<?php

namespace App\Filament\Pages\SiteRaporlari;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class GiderOzeti extends SiteRaporSayfasi
{
    protected static string $rapor = 'gider-ozeti';

    protected static ?string $slug = 'site-raporlari/gider-ozeti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 4;

    protected function girdiler(): array
    {
        return [static::donemAlani('bas_donem', 'Başlangıç dönemi'), static::donemAlani('bit_donem', 'Bitiş dönemi')];
    }
}
