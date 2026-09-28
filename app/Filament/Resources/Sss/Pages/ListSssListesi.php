<?php

namespace App\Filament\Resources\Sss\Pages;

use App\Filament\Resources\Sss\SssResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSssListesi extends ListRecords
{
    protected static string $resource = SssResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni soru'),
        ];
    }
}
