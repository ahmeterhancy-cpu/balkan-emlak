<?php

namespace App\Filament\Resources\BagimsizBolumler\Pages;

use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBagimsizBolumler extends ListRecords
{
    protected static string $resource = BagimsizBolumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni bölüm'),
        ];
    }
}
