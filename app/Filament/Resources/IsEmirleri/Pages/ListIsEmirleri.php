<?php

namespace App\Filament\Resources\IsEmirleri\Pages;

use App\Filament\Resources\IsEmirleri\IsEmriResource;
use App\Support\Yetki;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIsEmirleri extends ListRecords
{
    protected static string $resource = IsEmriResource::class;

    public function getSubheading(): ?string
    {
        return Yetki::var('operasyon.yonet')
            ? 'Varsayılan olarak açık işler listelenir; tamamlanan ve kapatılanlar için süzgeci kaldırın.'
            : 'Yalnız size atanan iş emirleri. İşi bitirince "Tamamla" ile yapılan işlemi yazın.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni iş emri'),
        ];
    }
}
