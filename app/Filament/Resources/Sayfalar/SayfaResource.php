<?php

namespace App\Filament\Resources\Sayfalar;

use App\Filament\Resources\Sayfalar\Pages\CreateSayfa;
use App\Filament\Resources\Sayfalar\Pages\EditSayfa;
use App\Filament\Resources\Sayfalar\Pages\ListSayfalar;
use App\Models\Sayfa;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
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

/** Kurumsal ve yasal sayfalar. */
class SayfaResource extends Resource
{
    protected static ?string $model = Sayfa::class;

    protected static ?string $slug = 'sayfalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Sayfalar';

    protected static ?string $modelLabel = 'sayfa';

    protected static ?string $pluralModelLabel = 'sayfalar';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('İçerik')->columnSpan(2)->schema([
                TextInput::make('baslik')->label('Başlık')->required()->maxLength(255),
                Textarea::make('ozet')->label('Özet')->rows(2)->maxLength(500),
                RichEditor::make('icerik')->label('İçerik'),
            ]),
            Section::make('Yayın')->columnSpan(1)->schema([
                Select::make('grup')->label('Grup')->options(Sayfa::GRUPLAR)->default('kurumsal')->selectablePlaceholder(false)->required(),
                TextInput::make('slug')->label('Adres (kısa ad)')->maxLength(255)->helperText('Boşsa başlıktan üretilir.')
                    ->unique(ignoreRecord: true),
                Toggle::make('yayinda')->label('Yayında'),
                Toggle::make('inceleme_bekliyor')
                    ->label('Hukuki inceleme bekliyor')
                    ->helperText('Hukuki incelemeden geçmemiş metin sitede uyarı şeridiyle gösterilir.'),
                TextInput::make('seo_baslik')->label('SEO başlığı')->maxLength(255),
                Textarea::make('seo_aciklama')->label('SEO açıklaması')->rows(2)->maxLength(300),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('baslik')
            ->columns([
                TextColumn::make('baslik')->label('Başlık')->searchable()->sortable()->description(fn (Sayfa $record) => '/'.$record->slug),
                TextColumn::make('grup')->label('Grup')->badge()->formatStateUsing(fn (?string $state) => Sayfa::GRUPLAR[$state] ?? $state),
                IconColumn::make('yayinda')->label('Yayında')->boolean(),
                IconColumn::make('inceleme_bekliyor')->label('İnceleme bekliyor')->boolean()->trueColor('warning')->falseColor('gray'),
                TextColumn::make('updated_at')->label('Güncellendi')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([SelectFilter::make('grup')->label('Grup')->options(Sayfa::GRUPLAR)])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSayfalar::route('/'),
            'create' => CreateSayfa::route('/create'),
            'edit' => EditSayfa::route('/{record}/edit'),
        ];
    }
}
