<?php

namespace App\Filament\Resources\Borclandirmalar\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Borclandirmalar\BorclandirmaResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Yalnız taslak düzenlenir (BorclandirmaPolicy::update). */
class EditBorclandirma extends EditRecord
{
    protected static string $resource = BorclandirmaResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['ozel_satirlar'] = collect($data['ozel_tutarlar'] ?? [])
            ->map(fn ($kurus, $bolumId) => ['bolum_id' => (int) $bolumId, 'tutar' => (int) $kurus])
            ->values()->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        SiteAlanlari::erisimDenetle($data['site_id'] ?? null);

        return BorclandirmaResource::ozelTutarlaraCevir($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Önizleme'),
        ];
    }
}
