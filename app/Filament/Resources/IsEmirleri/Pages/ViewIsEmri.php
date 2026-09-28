<?php

namespace App\Filament\Resources\IsEmirleri\Pages;

use App\Filament\Resources\IsEmirleri\IsEmriResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIsEmri extends ViewRecord
{
    protected static string $resource = IsEmriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            IsEmriResource::tamamla(),
            IsEmriResource::kapat(),
            IsEmriResource::yenidenAc(),
            EditAction::make()->label('Düzenle'),
        ];
    }
}
