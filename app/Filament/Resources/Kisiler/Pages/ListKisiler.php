<?php

namespace App\Filament\Resources\Kisiler\Pages;

use App\Filament\Resources\Kisiler\KisiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKisiler extends ListRecords
{
    protected static string $resource = KisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni kişi'),
        ];
    }
}
