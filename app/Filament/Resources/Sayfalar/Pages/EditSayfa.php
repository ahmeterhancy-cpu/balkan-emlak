<?php

namespace App\Filament\Resources\Sayfalar\Pages;

use App\Filament\Resources\Sayfalar\SayfaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSayfa extends EditRecord
{
    protected static string $resource = SayfaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
