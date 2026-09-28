<?php

namespace App\Filament\Pages\SiteRaporlari;

use App\Filament\Destek\CsvAktarici;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Destek\SiteRaporlari;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site raporu sayfalarının ortak tabanı: üstte site seçici ve tarih/dönem
 * girdileri, altta rapor tabloları. "CSV indir" ve "Yazdır / PDF" aynı
 * girdilerle aynı üreticiyi (SiteRaporlari) çağırır.
 */
abstract class SiteRaporSayfasi extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Site Raporları';

    protected string $view = 'filament.site.rapor-sayfasi';

    /** SiteRaporlari::RAPORLAR anahtarı. */
    protected static string $rapor;

    public ?array $filtre = [];

    public static function canAccess(): bool
    {
        return SiteRaporlari::izinli(static::$rapor);
    }

    public function getTitle(): string
    {
        return SiteRaporlari::RAPORLAR[static::$rapor];
    }

    public static function getNavigationLabel(): string
    {
        return SiteRaporlari::RAPORLAR[static::$rapor];
    }

    public function mount(): void
    {
        $this->form->fill(SiteRaporlari::varsayilanlar([]));
    }

    /** Rapora özgü girdiler (site seçiciden sonra). */
    abstract protected function girdiler(): array;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtre')
            ->columns(4)
            ->components([
                Select::make('site_id')
                    ->label('Site / apartman')
                    ->options(fn () => SiteAlanlari::siteler())
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(function ($set) {
                        $set('bolum_id', null);
                        $set('hesap_id', null);
                    }),
                ...$this->girdiler(),
            ]);
    }

    public function rapor(): array
    {
        return SiteRaporlari::uret(static::$rapor, $this->filtre ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('CSV indir')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn () => CsvAktarici::izinliMi())
                ->action(fn () => CsvAktarici::siteRaporu($this->rapor())),
            Action::make('yazdir')
                ->label('Yazdır / PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('panel.site-rapor.yazdir', ['rapor' => static::$rapor, ...array_filter($this->filtre ?? [], fn ($d) => filled($d))]), shouldOpenInNewTab: true),
        ];
    }

    /* Ortak girdi alanları */

    protected static function donemAlani(string $ad, string $etiket): TextInput
    {
        return TextInput::make($ad)->label($etiket)->placeholder('2026-09')
            ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')->live(onBlur: true);
    }

    protected static function tarihAlani(string $ad, string $etiket): DatePicker
    {
        return DatePicker::make($ad)->label($etiket)->native(false)->displayFormat('d.m.Y')->live();
    }
}
