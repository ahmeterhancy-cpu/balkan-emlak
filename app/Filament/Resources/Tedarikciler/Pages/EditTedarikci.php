<?php

namespace App\Filament\Resources\Tedarikciler\Pages;

use App\Filament\Resources\Tedarikciler\TedarikciResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTedarikci extends EditRecord
{
    protected static string $resource = TedarikciResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
