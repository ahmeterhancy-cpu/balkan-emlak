<?php

namespace App\Filament\Resources\Hizmetler\Pages;

use App\Filament\Resources\Hizmetler\HizmetResource;
use App\Filament\Destek\SlugDegisinceYonlendir;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHizmet extends EditRecord
{
    use SlugDegisinceYonlendir;

    protected static string $resource = HizmetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
