<?php

namespace App\Filament\Resources\Yazilar;

use App\Filament\Resources\Yazilar\Pages\CreateYazi;
use App\Filament\Resources\Yazilar\Pages\EditYazi;
use App\Filament\Resources\Yazilar\Pages\ListYazilar;
use App\Models\User;
use App\Models\Yazi;
use App\Support\GorselHakki;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Bilgi merkezi yazıları: taslak → yayında → arşiv. */
class YaziResource extends Resource
{
    protected static ?string $model = Yazi::class;

    protected static ?string $slug = 'bilgi-merkezi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Bilgi Merkezi';

    protected static ?string $modelLabel = 'yazı';

    protected static ?string $pluralModelLabel = 'yazılar';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('İçerik')->columnSpan(2)->schema([
                TextInput::make('baslik')->label('Başlık')->required()->maxLength(255),
                Textarea::make('ozet')->label('Özet')->rows(2)->maxLength(500),
                RichEditor::make('icerik')->label('Metin')->required(),
            ]),
            Section::make('Yayın')->columnSpan(1)->schema([
                Select::make('durum')->label('Durum')->options(Yazi::DURUMLAR)->default('taslak')->selectablePlaceholder(false)->required(),
                DateTimePicker::make('yayin_tarihi')
                    ->label('Yayın tarihi')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d.m.Y H:i')
                    ->helperText('İleri tarih verilirse yazı o zamana kadar görünmez. Boşsa yayımlandığı an.'),
                Select::make('kategori')->label('Kategori')->options(Yazi::KATEGORILER)->default('emlak')->required(),
                Select::make('yazar_id')
                    ->label('Yazar')
                    ->options(fn () => User::query()->where('aktif', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn () => auth()->id())
                    ->searchable(),
                FileUpload::make('gorsel')
                    ->label('Kapak görseli')
                    ->disk('medya')
                    ->visibility('public')
                    ->directory('yazilar')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(8192)
                    ->live(),
                TextInput::make('gorsel_alt')
                    ->label('Görsel alternatif metni')
                    ->maxLength(255)
                    ->required(fn (Get $get) => filled($get('gorsel'))),
                ...GorselHakki::alanlar(),
                TextInput::make('slug')->label('Adres (kısa ad)')->maxLength(255)->helperText('Boşsa başlıktan üretilir.')->unique(ignoreRecord: true),
                Textarea::make('seo_aciklama')->label('SEO açıklaması')->rows(2)->maxLength(300),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('baslik')->label('Başlık')->searchable()->limit(60),
                TextColumn::make('kategori')->label('Kategori')->formatStateUsing(fn (?string $state) => Yazi::KATEGORILER[$state] ?? $state),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->formatStateUsing(fn (?string $state) => Yazi::DURUMLAR[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'yayinda' => 'success',
                        'arsiv' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('yayin_tarihi')->label('Yayın tarihi')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('yazar.name')->label('Yazar')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('durum')->label('Durum')->options(Yazi::DURUMLAR),
                SelectFilter::make('kategori')->label('Kategori')->options(Yazi::KATEGORILER),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListYazilar::route('/'),
            'create' => CreateYazi::route('/create'),
            'edit' => EditYazi::route('/{record}/edit'),
        ];
    }
}
