<?php

namespace App\Filament\Resources\Borclandirmalar\Pages;

use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBorclandirmalar extends ListRecords
{
    protected static string $resource = BorclandirmaResource::class;

    public function getSubheading(): ?string
    {
        return 'Taslak → önizleme ve toplam kontrolü → kesinleştirme. Aynı iş tekrar çalıştırılsa da mükerrer borç oluşmaz.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni borçlandırma'),
        ];
    }
}
