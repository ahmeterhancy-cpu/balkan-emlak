<?php

namespace App\Filament\Resources\IslemKayitlari\Pages;

use App\Filament\Resources\IslemKayitlari\IslemKaydiResource;
use Filament\Resources\Pages\ListRecords;

class ListIslemKayitlari extends ListRecords
{
    protected static string $resource = IslemKaydiResource::class;

    public function getSubheading(): ?string
    {
        return 'Kayıtlar yalnız eklenir; düzenlenemez ve silinemez.';
    }
}
