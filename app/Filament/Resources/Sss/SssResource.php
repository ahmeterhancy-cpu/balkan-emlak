<?php

namespace App\Filament\Resources\Sss;

use App\Filament\Resources\Sss\Pages\CreateSss;
use App\Filament\Resources\Sss\Pages\EditSss;
use App\Filament\Resources\Sss\Pages\ListSssListesi;
use App\Models\Sss;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SssResource extends Resource
{
    protected static ?string $model = Sss::class;

    protected static ?string $slug = 'sss';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'Sık Sorulan Sorular';

    protected static ?string $modelLabel = 'soru';

    protected static ?string $pluralModelLabel = 'sorular';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(3)->schema([
                TextInput::make('soru')->label('Soru')->required()->maxLength(255)->columnSpan(2),
                Select::make('grup')->label('Grup')->options(Sss::GRUPLAR)->default('genel')->required(),
                Textarea::make('cevap')->label('Cevap')->required()->rows(5)->columnSpanFull(),
                Toggle::make('yayinda')->label('Yayında')->default(true),
                TextInput::make('sira')->label('Sıra')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sira')
            ->defaultSort('sira')
            ->columns([
                TextColumn::make('soru')->label('Soru')->searchable()->wrap(),
                TextColumn::make('grup')->label('Grup')->formatStateUsing(fn (?string $state) => Sss::GRUPLAR[$state] ?? $state),
                IconColumn::make('yayinda')->label('Yayında')->boolean(),
            ])
            ->filters([SelectFilter::make('grup')->label('Grup')->options(Sss::GRUPLAR)])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSssListesi::route('/'),
            'create' => CreateSss::route('/create'),
            'edit' => EditSss::route('/{record}/edit'),
        ];
    }
}
