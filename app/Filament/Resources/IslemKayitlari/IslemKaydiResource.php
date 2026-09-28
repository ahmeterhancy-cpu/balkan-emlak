<?php

namespace App\Filament\Resources\IslemKayitlari;

use App\Filament\Resources\IslemKayitlari\Pages\ListIslemKayitlari;
use App\Models\IslemKaydi;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** İşlem geçmişi: yalnız okunur. Oluşturma, düzenleme ve silme yoktur. */
class IslemKaydiResource extends Resource
{
    protected static ?string $model = IslemKaydi::class;

    protected static ?string $slug = 'islem-gecmisi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'İşlem Geçmişi';

    protected static ?string $modelLabel = 'işlem kaydı';

    protected static ?string $pluralModelLabel = 'işlem kayıtları';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    public const KAYIT_TURLERI = [
        'Portfoy' => 'Portföy',
        'PortfoyBelgesi' => 'Portföy belgesi',
        'Kisi' => 'Kişi',
        'Talep' => 'Talep',
        'Randevu' => 'Randevu',
        'Komisyon' => 'Komisyon',
        'Sayfa' => 'Sayfa',
        'Hizmet' => 'Hizmet',
        'Yazi' => 'Yazı',
        'Referans' => 'Referans',
        'Sss' => 'SSS',
        'User' => 'Kullanıcı',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('kullanici'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('kullanici.name')->label('Kullanıcı')->placeholder('Sistem'),
                TextColumn::make('olay')->label('Olay')->badge()
                    ->formatStateUsing(fn (?string $state) => IslemKaydi::OLAYLAR[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'silindi' => 'danger',
                        'olusturuldu' => 'success',
                        'belge_indirildi', 'disa_aktarildi' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('kayit_turu')->label('Kayıt')
                    ->formatStateUsing(fn (?string $state, IslemKaydi $record) => (static::KAYIT_TURLERI[$state] ?? $state).($record->kayit_id ? ' #'.$record->kayit_id : ''))
                    ->placeholder('—'),
                TextColumn::make('ozet')->label('Özet')->limit(60)->searchable()->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('olay')->label('Olay')->options(IslemKaydi::OLAYLAR),
                SelectFilter::make('kayit_turu')->label('Kayıt türü')->options(static::KAYIT_TURLERI),
                SelectFilter::make('user_id')->label('Kullanıcı')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                Filter::make('tarih')
                    ->schema([
                        DatePicker::make('baslangic')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('bitis')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['baslangic'] ?? null, fn ($w, $t) => $w->whereDate('created_at', '>=', $t))
                        ->when($data['bitis'] ?? null, fn ($w, $t) => $w->whereDate('created_at', '<=', $t))),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ayrıntı')
                    ->modalHeading('İşlem ayrıntısı')
                    ->modalWidth('4xl')
                    ->schema([])
                    ->modalContent(fn (IslemKaydi $record) => view('filament.islem-farki', [
                        'kayit' => $record,
                        'satirlar' => static::farkSatirlari($record),
                    ])),
            ])
            ->toolbarActions([]);
    }

    /**
     * Önceki ve sonraki değerleri alan alan eşler.
     *
     * @return list<array{alan: string, onceki: string, sonraki: string, degisti: bool}>
     */
    public static function farkSatirlari(IslemKaydi $kayit): array
    {
        $onceki = (array) $kayit->onceki;
        $sonraki = (array) $kayit->sonraki;
        $alanlar = array_unique([...array_keys($onceki), ...array_keys($sonraki)]);

        $yaz = fn ($d): string => match (true) {
            $d === null => '—',
            is_bool($d) => $d ? 'Evet' : 'Hayır',
            is_array($d) => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $d,
        };

        return array_values(array_map(fn ($alan) => [
            'alan' => (string) $alan,
            'onceki' => $yaz($onceki[$alan] ?? null),
            'sonraki' => $yaz($sonraki[$alan] ?? null),
            'degisti' => ($onceki[$alan] ?? null) !== ($sonraki[$alan] ?? null),
        ], $alanlar));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIslemKayitlari::route('/'),
        ];
    }
}
