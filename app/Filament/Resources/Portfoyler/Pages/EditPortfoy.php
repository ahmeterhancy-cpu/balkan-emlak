<?php

namespace App\Filament\Resources\Portfoyler\Pages;

use App\Filament\Resources\Portfoyler\PortfoyEylemleri;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPortfoy extends EditRecord
{
    use PortfoyVerisi;

    protected static string $resource = PortfoyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PortfoyEylemleri::hepsi(),
            ActionGroup::make([
                ViewAction::make()->label('Görüntüle'),
                DeleteAction::make(),
            ]),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->yetkiyeGoreSuz($data, $this->getRecord());
    }

    /**
     * Başlık değişince kısa adres (slug) da değişir; tarayıcıdaki eski
     * adres yenilemede 404 vermesin diye yeni adrese geç.
     */
    protected function afterSave(): void
    {
        if ($this->getRecord()->wasChanged('slug')) {
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->getRecord()]), navigate: true);
        }
    }
}
