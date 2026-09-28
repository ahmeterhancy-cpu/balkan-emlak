<?php

namespace App\Filament\Resources\Randevular\Pages;

use App\Filament\Resources\Randevular\RandevuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRandevu extends CreateRecord
{
    use RandevuVerisi;

    protected static string $resource = RandevuResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->randevuSuz($data, null);
    }
}
