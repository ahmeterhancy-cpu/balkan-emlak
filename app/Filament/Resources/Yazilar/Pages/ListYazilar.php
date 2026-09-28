<?php

namespace App\Filament\Resources\Yazilar\Pages;

use App\Filament\Resources\Yazilar\YaziResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListYazilar extends ListRecords
{
    protected static string $resource = YaziResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni yazı'),
        ];
    }
}
