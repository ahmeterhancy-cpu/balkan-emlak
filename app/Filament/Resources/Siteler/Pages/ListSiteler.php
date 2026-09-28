<?php

namespace App\Filament\Resources\Siteler\Pages;

use App\Filament\Resources\Siteler\SiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteler extends ListRecords
{
    protected static string $resource = SiteResource::class;

    public function getSubheading(): ?string
    {
        return 'Açık borç = borçlandırılan tutar (düzeltmeler dahil) − borçlara dağıtılan tahsilat; para birimine göre ayrı.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni site'),
        ];
    }
}
