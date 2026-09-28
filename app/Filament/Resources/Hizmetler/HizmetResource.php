<?php

namespace App\Filament\Resources\Hizmetler;

use App\Filament\Resources\Hizmetler\Pages\CreateHizmet;
use App\Filament\Resources\Hizmetler\Pages\EditHizmet;
use App\Filament\Resources\Hizmetler\Pages\ListHizmetler;
use App\Models\Hizmet;
use App\Support\GorselHakki;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Site ve apartman yönetimi hizmetleri (vitrindeki hizmet kartları). */
class HizmetResource extends Resource
{
    protected static ?string $model = Hizmet::class;

    protected static ?string $slug = 'hizmetler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Hizmetler';

    protected static ?string $modelLabel = 'hizmet';

    protected static ?string $pluralModelLabel = 'hizmetler';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('İçerik')->columnSpan(2)->schema([
                TextInput::make('baslik')->label('Başlık')->required()->maxLength(255),
                Textarea::make('ozet')->label('Özet')->required()->rows(2)->maxLength(400)->helperText('Hizmet kartında görünür.'),
                RichEditor::make('kapsam')->label('Hizmet kapsamı'),
                TagsInput::make('surec')
                    ->label('Süreç adımları')
                    ->reorderable()
                    ->placeholder('Adım yazıp Enter\'a basın')
                    ->helperText('Sırasıyla: Keşif, Teklif, Sözleşme, Devir…'),
            ]),
            Section::make('Görünüm ve yayın')->columnSpan(1)->schema([
                Select::make('ikon')->label('İkon')->options(Hizmet::IKONLAR),
                FileUpload::make('gorsel')
                    ->label('Görsel')
                    ->disk('medya')
                    ->visibility('public')
                    ->directory('hizmetler')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(8192)
                    ->helperText('Kullanım hakkı olan görsel yükleyin; stok görsel gerçek yönetilen site gibi sunulmaz.'),
                ...GorselHakki::alanlar(),
                TextInput::make('slug')->label('Adres (kısa ad)')->maxLength(255)->helperText('Boşsa başlıktan üretilir.')->unique(ignoreRecord: true),
                Toggle::make('yayinda')->label('Yayında')->default(true),
                TextInput::make('sira')->label('Sıra')->numeric()->default(0),
                Textarea::make('seo_aciklama')->label('SEO açıklaması')->rows(2)->maxLength(300),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sira')
            ->defaultSort('sira')
            ->columns([
                ImageColumn::make('gorsel')->label('')->disk('medya')->imageHeight(44),
                TextColumn::make('baslik')->label('Başlık')->searchable(),
                TextColumn::make('ikon')->label('İkon')->formatStateUsing(fn (?string $state) => Hizmet::IKONLAR[$state] ?? $state)->placeholder('—'),
                IconColumn::make('yayinda')->label('Yayında')->boolean(),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHizmetler::route('/'),
            'create' => CreateHizmet::route('/create'),
            'edit' => EditHizmet::route('/{record}/edit'),
        ];
    }
}
