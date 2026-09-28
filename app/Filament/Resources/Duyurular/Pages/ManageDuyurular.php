<?php

namespace App\Filament\Resources\Duyurular\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Duyurular\DuyuruResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDuyurular extends ManageRecords
{
    protected static string $resource = DuyuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni duyuru')
                ->mutateDataUsing(function (array $data) {
                    $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

                    return DuyuruResource::denetle($data, $site->id);
                }),
        ];
    }
}
