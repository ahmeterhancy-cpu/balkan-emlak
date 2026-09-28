<?php

namespace App\Filament\Resources\Referanslar\Pages;

use App\Filament\Resources\Referanslar\ReferansResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferans extends EditRecord
{
    protected static string $resource = ReferansResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
