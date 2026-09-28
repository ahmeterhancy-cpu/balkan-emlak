<?php

namespace App\Filament\Resources\Referanslar\Pages;

use App\Filament\Resources\Referanslar\ReferansResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferanslar extends ListRecords
{
    protected static string $resource = ReferansResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni referans'),
        ];
    }
}
