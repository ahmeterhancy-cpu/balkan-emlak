<?php

namespace App\Filament\Resources\SiteBelgeleri\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\SiteBelgeleri\SiteBelgesiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSiteBelgeleri extends ManageRecords
{
    protected static string $resource = SiteBelgesiResource::class;

    public function getSubheading(): ?string
    {
        return 'Listede her belgenin son sürümü görünür. Eski sürümler "Sürümler" altında saklanır; hiçbir sürüm silinmez.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Belge yükle')
                ->mutateDataUsing(function (array $data) {
                    SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
                    unset($data['grup'], $data['surum']);

                    return $data;
                }),
        ];
    }
}
