<?php

namespace App\Filament\Resources\Transferler;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Transferler\Pages\ListTransferler;
use App\Models\Transfer;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Hesaplar arası transferler — salt okunur. Transfer gelir / gider sayılmaz. */
class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $slug = 'transferler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Transferler';

    protected static ?string $modelLabel = 'transfer';

    protected static ?string $pluralModelLabel = 'transferler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'kaynakHesap', 'hedefHesap']))
            ->defaultSort('tarih', 'desc')
            ->columns([
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                SiteAlanlari::siteSutunu(),
                TextColumn::make('kaynakHesap.ad')->label('Kaynak hesap'),
                TextColumn::make('hedefHesap.ad')->label('Hedef hesap'),
                TextColumn::make('tutar_kurus')->label('Tutar')->alignEnd()
                    ->formatStateUsing(fn ($state, Transfer $record) => Para::bicimle((int) $state, $record->kaynakHesap?->para_birimi ?? 'TRY')),
                TextColumn::make('aciklama')->label('Açıklama')->placeholder('—')->wrap(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
            ])
            ->emptyStateHeading('Transfer yok')
            ->emptyStateDescription('Transfer, Kasa / Banka ekranında hesabın "Transfer" eylemiyle yapılır.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferler::route('/'),
        ];
    }
}
