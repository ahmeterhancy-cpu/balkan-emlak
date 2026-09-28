<?php

namespace App\Filament\Resources\Komisyonlar\Pages;

use App\Filament\Resources\Komisyonlar\KomisyonResource;
use App\Filament\Resources\Komisyonlar\Widgets\KomisyonToplamlari;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKomisyonlar extends ListRecords
{
    protected static string $resource = KomisyonResource::class;

    public function getSubheading(): ?string
    {
        return 'Resmi muhasebe belgesi değildir. Tutarlar para birimine göre ayrı tutulur ve birbirine eklenmez.';
    }

    protected function getHeaderWidgets(): array
    {
        return [KomisyonToplamlari::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni komisyon'),
        ];
    }
}
