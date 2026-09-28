<?php

namespace App\Filament\Resources\BagimsizBolumler\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Models\Blok;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBagimsizBolum extends EditRecord
{
    protected static string $resource = BagimsizBolumResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['site_id']); // bölüm başka siteye taşınmaz
        SiteAlanlari::ayniSite((int) $this->getRecord()->site_id, filled($data['blok_id'] ?? null) ? Blok::find($data['blok_id']) : null);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Görüntüle'),
        ];
    }
}
