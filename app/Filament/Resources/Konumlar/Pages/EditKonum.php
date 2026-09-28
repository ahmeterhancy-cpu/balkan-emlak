<?php

namespace App\Filament\Resources\Konumlar\Pages;

use App\Filament\Resources\Konumlar\KonumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKonum extends EditRecord
{
    protected static string $resource = KonumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
