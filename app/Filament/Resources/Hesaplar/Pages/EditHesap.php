<?php

namespace App\Filament\Resources\Hesaplar\Pages;

use App\Filament\Resources\Hesaplar\HesapResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHesap extends EditRecord
{
    protected static string $resource = HesapResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['site_id']); // hesap başka siteye taşınmaz

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Hareketler'),
        ];
    }
}
