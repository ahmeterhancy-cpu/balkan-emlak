<?php

namespace App\Filament\Resources\BagimsizBolumler\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Models\Blok;
use Filament\Resources\Pages\CreateRecord;

class CreateBagimsizBolum extends CreateRecord
{
    protected static string $resource = BagimsizBolumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
        SiteAlanlari::ayniSite($site->id, filled($data['blok_id'] ?? null) ? Blok::find($data['blok_id']) : null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
