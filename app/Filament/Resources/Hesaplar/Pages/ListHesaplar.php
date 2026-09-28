<?php

namespace App\Filament\Resources\Hesaplar\Pages;

use App\Filament\Resources\Hesaplar\HesapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHesaplar extends ListRecords
{
    protected static string $resource = HesapResource::class;

    public function getSubheading(): ?string
    {
        return 'Site paraları; şirketin emlak komisyon gelirleri burada yer almaz. Bakiyeler para birimine göre ayrıdır.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni hesap'),
        ];
    }
}
