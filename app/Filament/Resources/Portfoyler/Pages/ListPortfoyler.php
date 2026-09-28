<?php

namespace App\Filament\Resources\Portfoyler\Pages;

use App\Filament\Resources\Portfoyler\PortfoyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortfoyler extends ListRecords
{
    protected static string $resource = PortfoyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni portföy'),
        ];
    }
}
