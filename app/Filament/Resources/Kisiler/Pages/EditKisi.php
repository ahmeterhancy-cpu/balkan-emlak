<?php

namespace App\Filament\Resources\Kisiler\Pages;

use App\Filament\Resources\Kisiler\KisiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKisi extends EditRecord
{
    protected static string $resource = KisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
