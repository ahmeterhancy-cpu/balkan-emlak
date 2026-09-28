<?php

namespace App\Filament\Resources\Kisiler\Pages;

use App\Filament\Resources\Kisiler\KisiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKisi extends CreateRecord
{
    protected static string $resource = KisiResource::class;

    /** Oluşturan kaydedilir: danışman kendi eklediği kişiyi görmeye devam eder. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['olusturan_id'] = auth()->id();

        return $data;
    }
}
