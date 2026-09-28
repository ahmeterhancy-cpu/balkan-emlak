<?php

namespace App\Filament\Resources\Yazilar\Pages;

use App\Filament\Resources\Yazilar\YaziResource;
use App\Filament\Destek\SlugDegisinceYonlendir;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditYazi extends EditRecord
{
    use SlugDegisinceYonlendir;

    protected static string $resource = YaziResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
