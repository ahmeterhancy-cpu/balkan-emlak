<?php

namespace App\Filament\Resources\Portfoyler\Pages;

use App\Filament\Resources\Portfoyler\PortfoyEylemleri;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Salt okunur görünüm. Yayındaki kendi ilanını düzenleyemeyen danışman
 * (önce yayından kaldırılmalı) kaydı buradan görür.
 */
class ViewPortfoy extends ViewRecord
{
    protected static string $resource = PortfoyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Düzenle'),
            ...PortfoyEylemleri::hepsi(),
        ];
    }
}
