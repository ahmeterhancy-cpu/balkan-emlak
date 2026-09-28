<?php

namespace App\Filament\Pages\SiteRaporlari;

use App\Filament\Destek\SiteAlanlari;
use App\Models\BagimsizBolum;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class BolumEkstresi extends SiteRaporSayfasi
{
    protected static string $rapor = 'bolum-ekstresi';

    protected static ?string $slug = 'site-raporlari/bolum-ekstresi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 3;

    public function mount(): void
    {
        parent::mount();

        // Bölüm sayfasından ?bolum= ile gelindiyse
        $bolum = (int) request()->query('bolum');
        if ($bolum && ($site = BagimsizBolum::whereKey($bolum)->value('site_id')) && isset(SiteAlanlari::siteler()[$site])) {
            $this->form->fill([...$this->filtre, 'site_id' => $site, 'bolum_id' => $bolum, 'bas' => null]);
        }
    }

    protected function girdiler(): array
    {
        return [
            Select::make('bolum_id')->label('Bağımsız bölüm')
                ->options(fn ($get) => SiteAlanlari::bolumSecenekleri($get('site_id')))
                ->searchable()->live(),
            static::tarihAlani('bas', 'Başlangıç'),
            static::tarihAlani('bit', 'Bitiş'),
        ];
    }
}
