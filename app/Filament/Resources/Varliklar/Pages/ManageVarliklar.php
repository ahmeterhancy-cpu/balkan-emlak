<?php

namespace App\Filament\Resources\Varliklar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Varliklar\VarlikResource;
use App\Models\Blok;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVarliklar extends ManageRecords
{
    protected static string $resource = VarlikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni varlık')
                ->mutateDataUsing(function (array $data) {
                    $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
                    SiteAlanlari::ayniSite($site->id, filled($data['blok_id'] ?? null) ? Blok::find($data['blok_id']) : null);

                    return $data;
                }),
        ];
    }
}
