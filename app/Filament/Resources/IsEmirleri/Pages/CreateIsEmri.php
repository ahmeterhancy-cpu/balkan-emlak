<?php

namespace App\Filament\Resources\IsEmirleri\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\IsEmirleri\IsEmriResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIsEmri extends CreateRecord
{
    protected static string $resource = IsEmriResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $site = SiteAlanlari::erisimDenetle($data['site_id'] ?? null);
        if (filled($data['atanan_id'] ?? null)) {
            abort_unless(array_key_exists((int) $data['atanan_id'], SiteAlanlari::personelSecenekleri($site->id)), 403);
        }
        unset($data['tamamlanma_fotograflari'], $data['yapilan_islem']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
