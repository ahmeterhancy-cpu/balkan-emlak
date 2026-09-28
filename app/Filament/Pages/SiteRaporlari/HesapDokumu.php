<?php

namespace App\Filament\Pages\SiteRaporlari;

use App\Filament\Destek\SiteAlanlari;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class HesapDokumu extends SiteRaporSayfasi
{
    protected static string $rapor = 'hesap-dokumu';

    protected static ?string $slug = 'site-raporlari/hesap-dokumu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 5;

    protected function girdiler(): array
    {
        return [
            Select::make('hesap_id')->label('Hesap')
                ->options(fn ($get) => SiteAlanlari::hesapSecenekleri($get('site_id'), null, false))
                ->placeholder('Tüm hesaplar')->live(),
            static::tarihAlani('bas', 'Başlangıç'),
            static::tarihAlani('bit', 'Bitiş'),
        ];
    }
}
