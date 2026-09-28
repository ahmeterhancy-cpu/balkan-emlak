<?php

namespace App\Filament\Resources\Yonlendirmeler\Pages;

use App\Filament\Resources\Yonlendirmeler\YonlendirmeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListYonlendirmeler extends ListRecords
{
    protected static string $resource = YonlendirmeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni yönlendirme'),
        ];
    }
}
