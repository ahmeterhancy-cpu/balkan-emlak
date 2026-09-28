<?php

namespace App\Filament\Resources\Duyurular;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Duyurular\Pages\ManageDuyurular;
use App\Models\Blok;
use App\Models\Duyuru;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Site / blok bazında duyurular: taslak, yayında, arşiv (şartname 8.6).
 * Sakin portalı sonraki fazdadır; duyurular şimdilik panel içi kayıttır.
 */
class DuyuruResource extends Resource
{
    protected static ?string $model = Duyuru::class;

    protected static ?string $slug = 'duyurular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Duyurular';

    protected static ?string $modelLabel = 'duyuru';

    protected static ?string $pluralModelLabel = 'duyurular';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            SiteAlanlari::site('site_id', ['blok_id'])->disabledOn('edit'),
            Select::make('blok_id')->label('Hedef blok')->options(fn ($get) => SiteAlanlari::blokSecenekleri($get('site_id')))->placeholder('Tüm site'),
            TextInput::make('baslik')->label('Başlık')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('icerik')->label('İçerik')->required()->rows(5)->columnSpanFull(),
            Select::make('durum')->label('Durum')->options(Duyuru::DURUMLAR)->default('taslak')->required()->selectablePlaceholder(false),
            DatePicker::make('bitis_tarihi')->label('Yayın bitişi')->native(false)->displayFormat('d.m.Y'),
        ]);
    }

    public static function denetle(array $data, int $siteId): array
    {
        SiteAlanlari::ayniSite($siteId, filled($data['blok_id'] ?? null) ? Blok::find($data['blok_id']) : null);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'blok']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('baslik')->label('Başlık')->searchable()->wrap(),
                TextColumn::make('blok.ad')->label('Hedef')->placeholder('Tüm site'),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => Duyuru::DURUMLAR[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'yayinda' => 'success', 'arsiv' => 'gray', default => 'warning'
                    }),
                TextColumn::make('yayin_tarihi')->label('Yayın')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextColumn::make('bitis_tarihi')->label('Bitiş')->date('d.m.Y')->placeholder('—'),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('durum')->label('Durum')->options(Duyuru::DURUMLAR),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle')
                    ->mutateDataUsing(function (array $data, Duyuru $record) {
                        unset($data['site_id']);

                        return static::denetle($data, $record->site_id);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Duyuru yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDuyurular::route('/'),
        ];
    }
}
