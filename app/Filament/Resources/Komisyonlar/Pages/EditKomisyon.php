<?php

namespace App\Filament\Resources\Komisyonlar\Pages;

use App\Filament\Resources\Komisyonlar\KomisyonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKomisyon extends EditRecord
{
    protected static string $resource = KomisyonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
