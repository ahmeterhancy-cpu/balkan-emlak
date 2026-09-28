<?php

namespace App\Filament\Resources\IsEmirleri\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\IsEmirleri\IsEmriResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIsEmri extends EditRecord
{
    protected static string $resource = IsEmriResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $siteId = (int) $this->getRecord()->site_id;
        unset($data['site_id']);
        if (filled($data['atanan_id'] ?? null) && (int) $data['atanan_id'] !== (int) $this->getRecord()->atanan_id) {
            abort_unless(array_key_exists((int) $data['atanan_id'], SiteAlanlari::personelSecenekleri($siteId)), 403);
        }
        // Tamamlama / kapatma yalnız eylemlerle
        if (in_array($data['durum'] ?? null, ['tamamlandi', 'kapatildi'], true)) {
            unset($data['durum']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Görüntüle'),
            DeleteAction::make(),
        ];
    }
}
