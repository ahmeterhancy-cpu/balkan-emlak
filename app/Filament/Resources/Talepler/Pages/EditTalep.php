<?php

namespace App\Filament\Resources\Talepler\Pages;

use App\Filament\Resources\Talepler\TalepEylemleri;
use App\Filament\Resources\Talepler\TalepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTalep extends EditRecord
{
    use TalepVerisi;

    protected static string $resource = TalepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TalepEylemleri::banaAta(),
            TalepEylemleri::kisiyeBagla(),
            TalepEylemleri::randevuOlustur(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->talepSuz($data, $this->getRecord());
    }
}
