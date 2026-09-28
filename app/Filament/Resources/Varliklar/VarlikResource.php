<?php

namespace App\Filament\Resources\Varliklar;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Varliklar\Pages\ManageVarliklar;
use App\Models\Blok;
use App\Models\Varlik;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\EditAction;
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

/** Bakımı izlenen varlıklar: asansör, hidrofor, yangın ekipmanı... (şartname 8.5). */
class VarlikResource extends Resource
{
    protected static ?string $model = Varlik::class;

    protected static ?string $slug = 'varliklar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog;

    protected static ?string $navigationLabel = 'Varlıklar';

    protected static ?string $modelLabel = 'varlık';

    protected static ?string $pluralModelLabel = 'varlıklar';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            SiteAlanlari::site('site_id', ['blok_id'])->disabledOn('edit'),
            Select::make('blok_id')->label('Blok')->options(fn ($get) => SiteAlanlari::blokSecenekleri($get('site_id'))),
            TextInput::make('ad')->label('Varlık adı')->required()->maxLength(255),
            Select::make('tur')->label('Tür')->options(Varlik::TURLER)->required(),
            TextInput::make('marka_model')->label('Marka / model')->maxLength(255),
            Textarea::make('notlar')->label('Notlar')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'blok'])->withCount('bakimPlanlari'))
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('ad')->label('Varlık')->searchable()->description(fn (Varlik $record) => $record->marka_model),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn ($state) => Varlik::TURLER[$state] ?? $state),
                TextColumn::make('blok.ad')->label('Blok')->placeholder('—'),
                TextColumn::make('bakim_planlari_count')->label('Bakım planı')->alignEnd(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('tur')->label('Tür')->options(Varlik::TURLER),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle')
                    ->mutateDataUsing(function (array $data, Varlik $record) {
                        unset($data['site_id']);
                        SiteAlanlari::ayniSite($record->site_id, filled($data['blok_id'] ?? null) ? Blok::find($data['blok_id']) : null);

                        return $data;
                    }),
            ])
            ->emptyStateHeading('Varlık yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVarliklar::route('/'),
        ];
    }
}
