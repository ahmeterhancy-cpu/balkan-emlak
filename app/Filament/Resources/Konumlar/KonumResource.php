<?php

namespace App\Filament\Resources\Konumlar;

use App\Filament\Resources\Konumlar\Pages\CreateKonum;
use App\Filament\Resources\Konumlar\Pages\EditKonum;
use App\Filament\Resources\Konumlar\Pages\ListKonumlar;
use App\Models\Konum;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** İl → ilçe → mahalle ağacı; portföy formu ve vitrin süzgeçleri buradan beslenir. */
class KonumResource extends Resource
{
    protected static ?string $model = Konum::class;

    protected static ?string $slug = 'konumlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Konumlar';

    protected static ?string $modelLabel = 'konum';

    protected static ?string $pluralModelLabel = 'konumlar';

    protected static string|UnitEnum|null $navigationGroup = 'Emlak Yönetimi';

    protected static ?int $navigationSort = 5;

    /** Seçilen türün üst kademesi: ilçe → il, mahalle → ilçe. */
    public static function ustTuru(?string $tur): ?string
    {
        return match ($tur) {
            'ilce' => 'il',
            'mahalle' => 'ilce',
            default => null,
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                Select::make('tur')
                    ->label('Tür')
                    ->options(Konum::TURLER)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ust_id', null)),
                Select::make('ust_id')
                    ->label(fn (Get $get) => $get('tur') === 'mahalle' ? 'Bağlı olduğu ilçe' : 'Bağlı olduğu il')
                    ->options(function (Get $get): array {
                        $ust = static::ustTuru($get('tur'));
                        if (! $ust) {
                            return [];
                        }

                        return Konum::query()->with('ust')->where('tur', $ust)->orderBy('ad')->get()
                            ->mapWithKeys(fn (Konum $k) => [$k->id => $k->tamAd()])->all();
                    })
                    ->searchable()
                    ->visible(fn (Get $get) => static::ustTuru($get('tur')) !== null)
                    ->required(fn (Get $get) => static::ustTuru($get('tur')) !== null),
                TextInput::make('ad')
                    ->label('Ad')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                        ->where('tur', $get('tur'))
                        ->where('ust_id', $get('ust_id'))),
                TextInput::make('sira')->label('Sıra')->numeric()->default(0)->minValue(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('ust.ust'))
            ->defaultSort('ad')
            ->columns([
                TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                TextColumn::make('tur')->label('Tür')->badge()->formatStateUsing(fn (?string $state) => Konum::TURLER[$state] ?? $state),
                TextColumn::make('ust')->label('Üst')->state(fn (Konum $record) => $record->ust?->tamAd())->placeholder('—'),
                TextColumn::make('sira')->label('Sıra')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tur')->label('Tür')->options(Konum::TURLER),
                SelectFilter::make('ust_id')
                    ->label('Üst konum')
                    ->options(fn () => Konum::query()->with('ust')->whereIn('tur', ['il', 'ilce'])->orderBy('ad')->get()
                        ->mapWithKeys(fn (Konum $k) => [$k->id => $k->tamAd()])->all())
                    ->searchable(),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKonumlar::route('/'),
            'create' => CreateKonum::route('/create'),
            'edit' => EditKonum::route('/{record}/edit'),
        ];
    }
}
