<?php

namespace App\Filament\Resources\Tedarikciler\Pages;

use App\Filament\Resources\Tedarikciler\TedarikciResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTedarikciler extends ListRecords
{
    protected static string $resource = TedarikciResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni tedarikçi'),
        ];
    }
}
