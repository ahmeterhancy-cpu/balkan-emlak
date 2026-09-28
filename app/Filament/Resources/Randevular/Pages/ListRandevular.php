<?php

namespace App\Filament\Resources\Randevular\Pages;

use App\Filament\Resources\Randevular\RandevuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRandevular extends ListRecords
{
    protected static string $resource = RandevuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni randevu'),
        ];
    }
}
