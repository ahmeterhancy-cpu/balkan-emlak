<?php

namespace App\Filament\Resources\Randevular\Pages;

use App\Filament\Resources\Randevular\RandevuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRandevu extends EditRecord
{
    use RandevuVerisi;

    protected static string $resource = RandevuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->randevuSuz($data, $this->getRecord());
    }
}
