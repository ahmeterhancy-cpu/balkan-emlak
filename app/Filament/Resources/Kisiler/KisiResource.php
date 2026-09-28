<?php

namespace App\Filament\Resources\Kisiler;

use App\Filament\Destek\Alanlar;
use App\Filament\Resources\Kisiler\Pages\CreateKisi;
use App\Filament\Resources\Kisiler\Pages\EditKisi;
use App\Filament\Resources\Kisiler\Pages\ListKisiler;
use App\Filament\Resources\Kisiler\RelationManagers\PortfoylerRelationManager;
use App\Filament\Resources\Kisiler\RelationManagers\TaleplerRelationManager;
use App\Models\Kisi;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Mülk sahipleri ve müşteriler. Bu kayıtlar hiçbir herkese açık sayfaya
 * girmez. Danışman yalnız kendi portföylerinin sahiplerini, kendi
 * taleplerinin kişilerini ve kendi eklediği kişileri görür.
 */
class KisiResource extends Resource
{
    protected static ?string $model = Kisi::class;

    protected static ?string $slug = 'kisiler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Mülk Sahipleri ve Müşteriler';

    protected static ?string $modelLabel = 'kişi';

    protected static ?string $pluralModelLabel = 'kişiler';

    protected static string|UnitEnum|null $navigationGroup = 'Emlak Yönetimi';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'ad_soyad';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::kisiler(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Kişi bilgileri')
                ->columns(2)
                ->schema([
                    TextInput::make('ad_soyad')->label('Ad soyad / kurum adı')->required()->maxLength(255),
                    Select::make('tur')->label('Tür')->options(Kisi::TURLER)->default('bireysel')->selectablePlaceholder(false)->required(),
                    TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30)->live(onBlur: true),
                    TextInput::make('eposta')->label('E-posta')->email()->maxLength(255)->live(onBlur: true),
                    Alanlar::mukerrerUyarisi(),
                    Select::make('iletisim_tercihi')->label('İletişim tercihi')->options(Kisi::ILETISIM_TERCIHLERI),
                    Toggle::make('pazarlama_izni')
                        ->label('Pazarlama iletisi izni')
                        ->inline(false)
                        ->helperText('Yalnız kişi açıkça izin verdiyse işaretleyin.'),
                    Textarea::make('notlar')->label('Notlar')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ad_soyad')->label('Ad soyad')->searchable()->sortable(),
                TextColumn::make('telefon')->label('Telefon')->searchable()->placeholder('—'),
                TextColumn::make('eposta')->label('E-posta')->searchable()->placeholder('—'),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn (?string $state) => Kisi::TURLER[$state] ?? $state)->toggleable(),
                TextColumn::make('portfoyler_count')->label('Portföy')->counts('portfoyler')->toggleable(),
                TextColumn::make('talepler_count')->label('Talep')->counts('talepler')->toggleable(),
                IconColumn::make('pazarlama_izni')->label('İzin')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Kayıt')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tur')->label('Tür')->options(Kisi::TURLER),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            PortfoylerRelationManager::class,
            TaleplerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKisiler::route('/'),
            'create' => CreateKisi::route('/create'),
            'edit' => EditKisi::route('/{record}/edit'),
        ];
    }
}
