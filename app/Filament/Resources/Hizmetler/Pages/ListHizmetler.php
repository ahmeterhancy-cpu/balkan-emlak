<?php

namespace App\Filament\Resources\Hizmetler\Pages;

use App\Filament\Resources\Hizmetler\HizmetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHizmetler extends ListRecords
{
    protected static string $resource = HizmetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni hizmet'),
        ];
    }
}
