<?php

namespace App\Filament\Resources\Siteler\Pages;

use App\Filament\Resources\Siteler\SiteEylemleri;
use App\Filament\Resources\Siteler\SiteResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Görüntüle'),
            SiteEylemleri::donemKapat(),
            SiteEylemleri::donemAc(),
        ];
    }
}
