<?php

namespace App\Filament\Resources\Talepler\Pages;

use App\Filament\Resources\Talepler\TalepResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTalep extends CreateRecord
{
    use TalepVerisi;

    protected static string $resource = TalepResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->talepSuz($data, null);
    }
}
