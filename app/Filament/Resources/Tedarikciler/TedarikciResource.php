<?php

namespace App\Filament\Resources\Tedarikciler;

use App\Filament\Resources\Tedarikciler\Pages\CreateTedarikci;
use App\Filament\Resources\Tedarikciler\Pages\EditTedarikci;
use App\Filament\Resources\Tedarikciler\Pages\ListTedarikciler;
use App\Models\Gider;
use App\Models\Tedarikci;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tedarikçiler (şartname 8.4). Birden fazla sitenin ortak listesi; gider
 * sayısı kullanıcının erişebildiği sitelerden sayılır. Gideri olan
 * tedarikçi silinmez, pasife alınır.
 */
class TedarikciResource extends Resource
{
    protected static ?string $model = Tedarikci::class;

    protected static ?string $slug = 'tedarikciler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Tedarikçiler';

    protected static ?string $modelLabel = 'tedarikçi';

    protected static ?string $pluralModelLabel = 'tedarikçiler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'ad';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('ad')->label('Tedarikçi adı')->required()->maxLength(255)->columnSpan(2),
            Select::make('kategori')->label('Kategori')->options(Gider::KATEGORILER),
            TextInput::make('vergi_no')->label('Vergi no')->maxLength(20),
            TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
            TextInput::make('eposta')->label('E-posta')->email()->maxLength(255),
            TextInput::make('iban')->label('IBAN')->maxLength(40),
            Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            Textarea::make('notlar')->label('Notlar')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['giderler' => fn ($q) => Kapsam::siteyeBagli($q)]))
            ->defaultSort('ad')
            ->columns([
                TextColumn::make('ad')->label('Tedarikçi')->searchable()->sortable(),
                TextColumn::make('kategori')->label('Kategori')->formatStateUsing(fn ($state) => Gider::KATEGORILER[$state] ?? $state)->placeholder('—'),
                TextColumn::make('telefon')->label('Telefon')->placeholder('—'),
                TextColumn::make('giderler_count')->label('Gider')->alignEnd(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori')->label('Kategori')->options(Gider::KATEGORILER),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Tedarikçi yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTedarikciler::route('/'),
            'create' => CreateTedarikci::route('/create'),
            'edit' => EditTedarikci::route('/{record}/edit'),
        ];
    }
}
