<?php

namespace App\Filament\Resources\Siteler;

use App\Filament\Destek\SiteAlanlari;
use App\Models\Site;
use App\Services\SiteFinans\HesapServisi;
use App\Support\Donem;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/** Dönem kapama / yeniden açma (şartname 8.3, 12 adım 6). */
class SiteEylemleri
{
    public static function donemKapat(): Action
    {
        return Action::make('donemKapat')
            ->label('Dönemi kapat')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->authorize(fn (Site $record) => Gate::allows('donemKapat', $record))
            ->modalHeading(fn (Site $record) => $record->ad.' — dönemi kapat')
            ->modalDescription('Kapatılan döneme (ve öncesine) yeni mali kayıt girilemez. Önce banka mutabakatını ve dönem raporunu kontrol edin.')
            ->schema([
                TextInput::make('donem')
                    ->label('Kapatılacak dönem (YYYY-AA)')
                    ->required()
                    ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                    ->validationMessages(['regex' => 'Dönem YYYY-AA biçiminde olmalı (ör. 2026-08).'])
                    ->default(fn () => now()->subMonth()->format('Y-m')),
            ])
            ->action(function (Site $record, array $data, Action $action) {
                SiteAlanlari::calistir(
                    fn () => app(HesapServisi::class)->donemKapat($record, $data['donem']),
                    Donem::ad($data['donem']).' dahil önceki dönemler kapatıldı',
                    $action,
                );
            });
    }

    public static function donemAc(): Action
    {
        return Action::make('donemAc')
            ->label('Dönemi yeniden aç')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->visible(fn (Site $record) => $record->kapali_donem !== null)
            ->authorize(fn (Site $record) => Yetki::var('donem.duzelt') && Gate::allows('view', $record))
            ->modalHeading(fn (Site $record) => $record->ad.' — kapalı dönemi yeniden aç')
            ->modalDescription(fn (Site $record) => 'Şu an '.Donem::ad((string) $record->kapali_donem).' dahil önceki dönemler kapalı. Seçilen dönem ve sonrası yeniden açılır; işlem gerekçesiyle kayda geçer.')
            ->schema([
                TextInput::make('donem')
                    ->label('Açılacak dönem (YYYY-AA)')
                    ->required()
                    ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                    ->default(fn (Site $record) => $record->kapali_donem),
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255),
            ])
            ->action(function (Site $record, array $data, Action $action) {
                SiteAlanlari::calistir(
                    fn () => app(HesapServisi::class)->donemAc($record, $data['donem'], $data['gerekce']),
                    'Dönem yeniden açıldı',
                    $action,
                );
            });
    }
}
