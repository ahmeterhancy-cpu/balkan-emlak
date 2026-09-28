<?php

namespace App\Filament\Resources\Borclar\Pages;

use App\Filament\Resources\Borclar\BorcResource;
use Filament\Resources\Pages\ListRecords;

class ListBorclar extends ListRecords
{
    protected static string $resource = BorcResource::class;

    public function getSubheading(): ?string
    {
        return 'Tutar = asıl borç + düzeltmeler; kalan = tutar − dağıtılan tahsilat. Mali kayıtlar silinmez, düzeltme karşı kayıtla yapılır.';
    }
}
