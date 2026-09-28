<?php

namespace App\Filament\Resources\Referanslar;

use App\Filament\Resources\Referanslar\Pages\CreateReferans;
use App\Filament\Resources\Referanslar\Pages\EditReferans;
use App\Filament\Resources\Referanslar\Pages\ListReferanslar;
use App\Models\Referans;
use App\Support\GorselHakki;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Referans projeler ve müşteri görüşleri. Yayımlama izni alınmadan
 * yayına alınamaz (şartname 4.3: izinsiz referans / sahte yorum yok).
 */
class ReferansResource extends Resource
{
    protected static ?string $model = Referans::class;

    protected static ?string $slug = 'referanslar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Referanslar';

    protected static ?string $modelLabel = 'referans';

    protected static ?string $pluralModelLabel = 'referanslar';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                Select::make('tur')->label('Tür')->options(Referans::TURLER)->default('proje')->required(),
                TextInput::make('ad')->label('Site adı / görüş sahibi')->required()->maxLength(255),
                TextInput::make('alt_baslik')->label('Alt başlık')->maxLength(255)->placeholder('Site sakini · 120 bağımsız bölüm'),
                TextInput::make('sira')->label('Sıra')->numeric()->default(0),
                Textarea::make('metin')->label('Metin')->rows(4)->columnSpanFull()
                    ->helperText('Görüş metni, sahibinin onayladığı haliyle aynen yazılır; uydurma yorum eklenmez.'),
                FileUpload::make('gorsel')
                    ->label('Görsel / logo')
                    ->disk('medya')
                    ->visibility('public')
                    ->directory('referanslar')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->columnSpanFull(),
                ...GorselHakki::alanlar(),
            ]),
            Section::make('Yayın izni')
                ->description('Referans ancak yazılı/kayıtlı yayımlama izni alındıysa sitede gösterilebilir.')
                ->columns(3)
                ->schema([
                    Toggle::make('izin_alindi')->label('Yayımlama izni alındı')->live()->inline(false),
                    DatePicker::make('izin_tarihi')->label('İzin tarihi')->native(false)->displayFormat('d.m.Y')
                        ->required(fn (Get $get) => (bool) $get('izin_alindi')),
                    Toggle::make('yayinda')
                        ->label('Yayında')
                        ->inline(false)
                        ->helperText('İzin alınmadan açılamaz.')
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value && ! $get('izin_alindi')) {
                                $fail('Yayımlama izni alınmadan referans yayına alınamaz.');
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sira')
            ->defaultSort('sira')
            ->columns([
                TextColumn::make('ad')->label('Ad')->searchable()->description(fn (Referans $record) => $record->alt_baslik),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn (?string $state) => Referans::TURLER[$state] ?? $state),
                IconColumn::make('izin_alindi')->label('İzin')->boolean(),
                IconColumn::make('yayinda')->label('Yayında')->boolean(),
            ])
            ->recordActions([EditAction::make()->label('Düzenle')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferanslar::route('/'),
            'create' => CreateReferans::route('/create'),
            'edit' => EditReferans::route('/{record}/edit'),
        ];
    }
}
