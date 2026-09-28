<?php

namespace App\Filament\Resources\Siteler\Pages;

use App\Filament\Resources\Siteler\SiteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    /** Blok ve bölüm eklemek için düzenleme sayfasına. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
