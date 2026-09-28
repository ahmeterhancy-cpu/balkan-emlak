<?php

namespace App\Filament\Resources\Gorevler\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Gorevler\GorevResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGorevler extends ManageRecords
{
    protected static string $resource = GorevResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni görev')
                ->mutateDataUsing(function (array $data) {
                    $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

                    return GorevResource::denetle($data, $site->id) + ['durum' => 'bekliyor'];
                }),
        ];
    }
}
