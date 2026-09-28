<?php

namespace App\Filament\Resources\BakimPlanlari\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BakimPlanlari\BakimPlaniResource;
use App\Models\Varlik;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBakimPlanlari extends ManageRecords
{
    protected static string $resource = BakimPlaniResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni bakım planı')
                ->mutateDataUsing(function (array $data) {
                    $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
                    SiteAlanlari::ayniSite($site->id, filled($data['varlik_id'] ?? null) ? Varlik::find($data['varlik_id']) : null);

                    return $data;
                }),
        ];
    }
}
