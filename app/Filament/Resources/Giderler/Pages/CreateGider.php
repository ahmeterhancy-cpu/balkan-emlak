<?php

namespace App\Filament\Resources\Giderler\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Giderler\GiderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGider extends CreateRecord
{
    protected static string $resource = GiderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
        $data['onay_durumu'] = 'taslak';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Gider taslak olarak kaydedildi; onaya gönderin';
    }
}
