<?php

namespace App\Filament\Resources\Hesaplar\Pages;

use App\Filament\Resources\Hesaplar\HesapResource;
use App\Models\Hesap;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHesap extends ViewRecord
{
    protected static string $resource = HesapResource::class;

    public function getTitle(): string
    {
        /** @var Hesap $h */
        $h = $this->getRecord();

        return $h->ad.' ('.$h->para_birimi.') — '.$h->site?->ad;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...HesapResource::eylemler(),
            EditAction::make()->label('Düzenle'),
        ];
    }
}
