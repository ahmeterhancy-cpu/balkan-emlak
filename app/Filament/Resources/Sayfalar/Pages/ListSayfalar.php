<?php

namespace App\Filament\Resources\Sayfalar\Pages;

use App\Filament\Resources\Sayfalar\SayfaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSayfalar extends ListRecords
{
    protected static string $resource = SayfaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni sayfa'),
        ];
    }
}
