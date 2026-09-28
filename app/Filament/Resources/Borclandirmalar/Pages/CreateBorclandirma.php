<?php

namespace App\Filament\Resources\Borclandirmalar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBorclandirma extends CreateRecord
{
    protected static string $resource = BorclandirmaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

        return BorclandirmaResource::ozelTutarlaraCevir($data) + ['durum' => 'taslak'];
    }

    /** Oluşturunca önizleme sayfasına. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Taslak kaydedildi; önizlemeyi kontrol edip kesinleştirin';
    }
}
