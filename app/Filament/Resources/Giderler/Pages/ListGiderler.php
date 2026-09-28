<?php

namespace App\Filament\Resources\Giderler\Pages;

use App\Filament\Resources\Giderler\GiderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGiderler extends ListRecords
{
    protected static string $resource = GiderResource::class;

    public function getSubheading(): ?string
    {
        return 'Onay ve ödeme ayrı izlenir. Site onay limitini aşan gideri yalnız şirket yöneticisi onaylar.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni gider'),
        ];
    }
}
