<?php

namespace App\Filament\Resources\Kullanicilar\Pages;

use App\Filament\Resources\Kullanicilar\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni kullanıcı'),
        ];
    }
}
