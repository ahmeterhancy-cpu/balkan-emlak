<?php

namespace App\Filament\Resources\Portfoyler\Pages;

use App\Filament\Resources\Portfoyler\PortfoyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePortfoy extends CreateRecord
{
    use PortfoyVerisi;

    protected static string $resource = PortfoyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->yetkiyeGoreSuz($data, null);
    }

    /** Yeni kayıttan sonra fotoğraf yükleyebilsin diye düzenleme sayfasına. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
