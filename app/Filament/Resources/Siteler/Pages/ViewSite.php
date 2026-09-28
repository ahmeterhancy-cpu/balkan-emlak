<?php

namespace App\Filament\Resources\Siteler\Pages;

use App\Filament\Resources\Siteler\SiteEylemleri;
use App\Filament\Resources\Siteler\SiteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Düzenle'),
            SiteEylemleri::donemKapat(),
            SiteEylemleri::donemAc(),
        ];
    }
}
