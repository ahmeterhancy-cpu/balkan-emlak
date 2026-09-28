<?php

namespace App\Filament\Resources\Yonlendirmeler;

use App\Filament\Resources\Yonlendirmeler\Pages\CreateYonlendirme;
use App\Filament\Resources\Yonlendirmeler\Pages\EditYonlendirme;
use App\Filament\Resources\Yonlendirmeler\Pages\ListYonlendirmeler;
use App\Models\Yonlendirme;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Eski adres → yeni adres (şartname 10). Yeni siteye geçişte eski sitenin
 * adresleri buraya girilir; ilan/yazı/hizmet başlığı değişince kayıt
 * otomatik açılır. Yönlendirme yalnız 404 olacak isteklerde çalışır.
 */
class YonlendirmeResource extends Resource
{
    protected static ?string $model = Yonlendirme::class;

    protected static ?string $slug = 'yonlendirmeler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?string $navigationLabel = 'Yönlendirmeler';

    protected static ?string $modelLabel = 'yönlendirme';

    protected static ?string $pluralModelLabel = 'yönlendirmeler';

    protected static string|UnitEnum|null $navigationGroup = 'Web İçerikleri';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->description('Eski adres açıldığında ziyaretçi yeni adrese gönderilir. Yalnız sitede karşılığı olmayan (404) adresler yönlendirilir; var olan bir sayfa ezilemez.')
                ->columns(2)
                ->schema([
                    TextInput::make('eski_yol')
                        ->label('Eski adres')
                        ->placeholder('/ilan.php?id=15 ya da /eski-sayfa')
                        ->helperText('Alan adı yazmanıza gerek yok; yazarsanız atılır. Sorgu dizesi (?id=15) korunur.')
                        ->required()
                        ->maxLength(500)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? Yonlendirme::normallestir($state) : $state)
                        ->unique(ignoreRecord: true)
                        ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                            $yol = Yonlendirme::normallestir((string) $value);
                            if ($yol === '/' || preg_match('#^/(admin|livewire|onizleme)(/|$)#', $yol)) {
                                $fail('Ana sayfa, panel ve önizleme adresleri yönlendirilemez.');
                            }
                        }),
                    TextInput::make('hedef')
                        ->label('Yeni adres')
                        ->placeholder('/emlak ya da https://...')
                        ->required()
                        ->maxLength(500)
                        ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                            if ($get('eski_yol') && Yonlendirme::normallestir((string) $value) === Yonlendirme::normallestir((string) $get('eski_yol'))) {
                                $fail('Yeni adres eski adresle aynı olamaz (sonsuz döngü).');
                            }
                        }),
                    Select::make('kod')->label('Tür')->options(Yonlendirme::KODLAR)->default(301)->required(),
                    Toggle::make('aktif')->label('Etkin')->default(true)->inline(false),
                    TextInput::make('not')->label('Not')->maxLength(255)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('eski_yol')->label('Eski adres')->searchable()->copyable()->limit(60),
                TextColumn::make('hedef')->label('Yeni adres')->searchable()->limit(60),
                TextColumn::make('kod')->label('Tür')->badge()->color(fn (int $state) => $state === 301 ? 'success' : 'warning'),
                IconColumn::make('otomatik')->label('Otomatik')->boolean()->tooltip('Başlık değişince sistem açtı'),
                TextColumn::make('isabet')->label('Kullanım')->numeric()->sortable(),
                TextColumn::make('son_isabet')->label('Son kullanım')->since()->placeholder('—')->sortable(),
                IconColumn::make('aktif')->label('Etkin')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Etkin'),
                TernaryFilter::make('otomatik')->label('Otomatik'),
            ])
            ->recordActions([
                Action::make('dene')
                    ->label('Dene')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Yonlendirme $record) => url($record->eski_yol), shouldOpenInNewTab: true),
                EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListYonlendirmeler::route('/'),
            'create' => CreateYonlendirme::route('/create'),
            'edit' => EditYonlendirme::route('/{record}/edit'),
        ];
    }
}
