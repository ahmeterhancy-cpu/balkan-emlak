<?php

namespace App\Filament\Resources\Giderler\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Giderler\GiderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Yalnız taslak / reddedilmiş gider düzenlenir (GiderPolicy::update). */
class EditGider extends EditRecord
{
    protected static string $resource = GiderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Görüntüle'),
            DeleteAction::make()->visible(fn () => ! $this->getRecord()->odemeler()->exists()),
        ];
    }
}
