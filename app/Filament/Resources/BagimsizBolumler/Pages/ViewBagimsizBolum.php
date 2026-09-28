<?php

namespace App\Filament\Resources\BagimsizBolumler\Pages;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BagimsizBolumler\BagimsizBolumResource;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Tahsilat;
use App\Services\SiteFinans\BorcServisi;
use App\Support\Donem;
use App\Support\Para;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewBagimsizBolum extends ViewRecord
{
    protected static string $resource = BagimsizBolumResource::class;

    public function getTitle(): string
    {
        /** @var BagimsizBolum $b */
        $b = $this->getRecord();

        return $b->etiket().' — '.$b->site?->ad;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tahsilatAl')
                ->label('Tahsilat al')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn () => Gate::allows('create', Tahsilat::class))
                ->url(fn (BagimsizBolum $record) => TahsilatResource::getUrl('create', ['site' => $record->site_id, 'bolum' => $record->id])),
            $this->borcEkle(),
            EditAction::make()->label('Düzenle'),
        ];
    }

    private function borcEkle(): Action
    {
        return Action::make('borcEkle')
            ->label('Borç ekle')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('warning')
            ->authorize(fn (BagimsizBolum $record) => Gate::allows('create', Borc::class) && Gate::allows('view', $record))
            ->modalHeading(fn (BagimsizBolum $record) => $record->site?->ad.' · '.$record->etiket().' — tekil borç')
            ->modalDescription('Tek bölüme borç (özel gider payı) ya da yönetime devirde açılış bakiyesi. Açılış bakiyesinde dayanak zorunludur. Bölümün avansı varsa yeni borca mahsup edilir.')
            ->schema([
                Select::make('tur')->label('Borç türü')->options(Borc::TURLER)->default('diger')->required()->live(),
                TextInput::make('donem')->label('Dönem (YYYY-AA)')->required()
                    ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                    ->validationMessages(['regex' => 'Dönem YYYY-AA biçiminde olmalı (ör. 2026-09).'])
                    ->default(fn () => now()->format('Y-m')),
                TextInput::make('aciklama')->label('Açıklama')->required()->maxLength(255),
                DatePicker::make('vade')->label('Vade')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                Alanlar::para('tutar_kurus', 'Tutar')->required(),
                Select::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER)
                    ->default(fn (BagimsizBolum $record) => $record->site?->para_birimi ?? 'TRY')->required()->selectablePlaceholder(false),
                Textarea::make('gerekce')
                    ->label(fn ($get) => $get('tur') === 'acilis' ? 'Dayanak (ör. devir tutanağı)' : 'Gerekçe / kapalı dönem gerekçesi')
                    ->required(fn ($get) => $get('tur') === 'acilis')
                    ->rows(2)->maxLength(255)
                    ->helperText(fn (BagimsizBolum $record) => $record->site?->kapali_donem
                        ? Donem::ad($record->site->kapali_donem).' dahil önceki dönemler kapalı; kapalı döneme borç yalnız yetkili kullanıcı tarafından gerekçeyle girilir.'
                        : null),
            ])
            ->action(function (BagimsizBolum $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(
                    fn () => app(BorcServisi::class)->olustur([
                        'bagimsiz_bolum_id' => $record->id,
                        'tur' => $data['tur'],
                        'donem' => $data['donem'],
                        'aciklama' => $data['aciklama'],
                        'vade' => $data['vade'],
                        'tutar_kurus' => (int) $data['tutar_kurus'],
                        'para_birimi' => $data['para_birimi'],
                        'gerekce' => $data['gerekce'] ?? null,
                    ]),
                    'Borç eklendi',
                    $action,
                );
            });
    }
}
