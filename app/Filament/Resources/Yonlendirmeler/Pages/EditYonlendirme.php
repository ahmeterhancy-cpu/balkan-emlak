<?php

namespace App\Filament\Resources\Yonlendirmeler\Pages;

use App\Filament\Resources\Yonlendirmeler\YonlendirmeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditYonlendirme extends EditRecord
{
    protected static string $resource = YonlendirmeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
