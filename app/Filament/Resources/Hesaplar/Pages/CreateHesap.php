<?php

namespace App\Filament\Resources\Hesaplar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Hesaplar\HesapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHesap extends CreateRecord
{
    protected static string $resource = HesapResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
