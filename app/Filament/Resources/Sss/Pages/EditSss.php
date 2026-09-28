<?php

namespace App\Filament\Resources\Sss\Pages;

use App\Filament\Resources\Sss\SssResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSss extends EditRecord
{
    protected static string $resource = SssResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
