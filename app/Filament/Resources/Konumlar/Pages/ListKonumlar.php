<?php

namespace App\Filament\Resources\Konumlar\Pages;

use App\Filament\Resources\Konumlar\KonumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKonumlar extends ListRecords
{
    protected static string $resource = KonumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni konum'),
        ];
    }
}
