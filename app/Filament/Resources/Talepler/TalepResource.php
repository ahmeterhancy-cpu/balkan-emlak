<?php

namespace App\Filament\Resources\Talepler;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Filament\Destek\Alanlar;
use App\Filament\Destek\CsvAktarici;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Filament\Resources\Randevular\RandevuResource;
use App\Filament\Resources\Talepler\Pages\CreateTalep;
use App\Filament\Resources\Talepler\Pages\EditTalep;
use App\Filament\Resources\Talepler\Pages\ListTalepler;
use App\Filament\Resources\Talepler\RelationManagers\NotlarRelationManager;
use App\Models\Portfoy;
use App\Models\Talep;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Başvurular ve talepler: web formları (iletişim, ilan bilgisi/randevu,
 * portföy başvurusu, yönetim teklifi) ve panelde açılan müşteri arayışları.
 * Danışman yalnız kendisine atanmış talepleri görür.
 */
class TalepResource extends Resource
{
    protected static ?string $model = Talep::class;

    protected static ?string $slug = 'talepler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Başvurular ve Talepler';

    protected static ?string $modelLabel = 'talep';

    protected static ?string $pluralModelLabel = 'talepler';

    protected static string|UnitEnum|null $navigationGroup = 'Başvurular';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ad_soyad';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::talepler(parent::getEloquentQuery());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check() || ! Gate::allows('viewAny', Talep::class)) {
            return null;
        }

        $yeni = Kapsam::talepler(Talep::query())->where('durum', TalepDurumu::Yeni->value)->count();

        return $yeni > 0 ? (string) $yeni : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Yeni (henüz dönülmemiş) talepler';
    }

    public static function tur(Get $get): ?TalepTuru
    {
        $d = $get('tur');

        return $d instanceof TalepTuru ? $d : TalepTuru::tryFrom((string) $d);
    }

    /** `detay` JSON'unu okunur "Etiket: değer" satırlarına çevirir. */
    public static function detaySatirlari(?array $detay): array
    {
        $satirlar = [];
        foreach ((array) $detay as $anahtar => $deger) {
            if ($deger === null || $deger === '' || $deger === []) {
                continue;
            }
            $etiket = Talep::DETAY_ETIKETLERI[$anahtar] ?? str($anahtar)->replace('_', ' ')->ucfirst()->toString();
            $metin = is_array($deger) ? implode(', ', array_map('strval', $deger)) : (is_bool($deger) ? ($deger ? 'Evet' : 'Hayır') : (string) $deger);
            $satirlar[] = $etiket.': '.$metin;
        }

        return $satirlar;
    }

    public static function portfoySecimi(string $ad = 'portfoy_id'): Select
    {
        return Select::make($ad)
            ->label('İlgili ilan')
            ->options(fn () => RandevuResource::portfoySecenekleri())
            ->searchable();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Başvuran')
                ->columns(3)
                ->schema([
                    TextInput::make('ad_soyad')->label('Ad soyad')->required()->maxLength(255),
                    TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30)->live(onBlur: true),
                    TextInput::make('eposta')->label('E-posta')->email()->maxLength(255)->live(onBlur: true),
                    Alanlar::mukerrerUyarisi(kendisiHaric: false),
                    TextEntry::make('kisi_kaydi')
                        ->label('Bağlı kişi kaydı')
                        ->state(fn (?Talep $record) => $record?->kisi?->ad_soyad)
                        ->placeholder('Bağlı değil — üstteki "Kişi kaydına bağla" ile bağlayın.')
                        ->visible(fn (?Talep $record) => $record !== null)
                        ->columnSpanFull(),
                ]),

            Section::make('Talep')
                ->columns(3)
                ->schema([
                    Select::make('tur')->label('Tür')->options(TalepTuru::class)->required()->live()
                        ->default(TalepTuru::MusteriTalebi->value),
                    Select::make('kaynak')->label('Kaynak')->options(Talep::KAYNAKLAR)->default('telefon')->required(),
                    static::portfoySecimi(),
                    TextInput::make('konu')->label('Konu')->maxLength(255)->columnSpanFull(),
                    Textarea::make('mesaj')->label('Mesaj')->rows(4)->columnSpanFull(),
                    DateTimePicker::make('tercih_edilen_zaman')->label('Tercih edilen zaman')->seconds(false)->native(false)->displayFormat('d.m.Y H:i'),
                    TextEntry::make('detay_gorunum')
                        ->label('Form ayrıntıları')
                        ->state(fn (?Talep $record) => static::detaySatirlari($record?->detay))
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->visible(fn (?Talep $record) => static::detaySatirlari($record?->detay) !== [])
                        ->columnSpanFull(),
                    TextEntry::make('izinler')
                        ->label('Onaylar')
                        ->state(fn (?Talep $record) => $record ? ('KVKK aydınlatma: '.($record->kvkk_onay ? 'onaylandı' : 'yok').' · Pazarlama izni: '.($record->pazarlama_izni ? 'var' : 'yok')) : null)
                        ->visible(fn (?Talep $record) => $record?->kaynak === 'web')
                        ->columnSpanFull(),
                ]),

            Section::make('Müşteri arayışı')
                ->description('Aradığı gayrimenkul ve bütçe (₺).')
                ->columns(3)
                ->visible(fn (Get $get) => static::tur($get) === TalepTuru::MusteriTalebi)
                ->schema([
                    Select::make('aranan_islem')->label('İşlem')->options(Islem::secenekler()),
                    Select::make('aranan_kategori')->label('Gayrimenkul türü')->options(Kategori::secenekler()),
                    TextInput::make('aranan_konum')->label('Konum')->maxLength(255),
                    Alanlar::para('butce_min_kurus', 'Bütçe (en az)'),
                    Alanlar::para('butce_max_kurus', 'Bütçe (en çok)'),
                    Select::make('ilgilenilenPortfoyler')
                        ->label('İlgilendiği portföyler')
                        ->multiple()
                        ->relationship(
                            'ilgilenilenPortfoyler',
                            'baslik',
                            modifyQueryUsing: fn (Builder $query) => Kapsam::portfoyler($query)->orderByDesc('portfoyler.id'),
                        )
                        ->getOptionLabelFromRecordUsing(fn (Portfoy $record) => $record->portfoy_no.' — '.$record->baslik)
                        ->searchable(['baslik', 'portfoy_no'])
                        ->preload()
                        ->columnSpanFull(),
                ]),

            Section::make('Takip')
                ->columns(3)
                ->schema([
                    Select::make('durum')->label('Durum')->options(TalepDurumu::class)->default(TalepDurumu::Yeni->value)->selectablePlaceholder(false)->required(),
                    Select::make('atanan_id')
                        ->label('Atanan danışman')
                        ->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (?Talep $record) => $record ? Gate::allows('ata', $record) : Yetki::var('talep.ata')),
                    TextEntry::make('atanan_gorunum')
                        ->label('Atanan danışman')
                        ->state(fn (?Talep $record) => $record?->atanan?->name ?? auth()->user()?->name)
                        ->visible(fn (?Talep $record) => ! ($record ? Gate::allows('ata', $record) : Yetki::var('talep.ata'))),
                    DatePicker::make('sonraki_takip')->label('Sonraki takip')->native(false)->displayFormat('d.m.Y'),
                    Select::make('sonuc')
                        ->label('Teklif sonucu')
                        ->options(Talep::SONUCLAR)
                        ->visible(fn (Get $get) => static::tur($get) === TalepTuru::YonetimTeklifi),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['portfoy', 'atanan']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('tur')->label('Tür')->badge(),
                TextColumn::make('ad_soyad')->label('Ad soyad')->searchable()->weight('medium'),
                TextColumn::make('telefon')->label('İletişim')->searchable(['telefon', 'eposta'])
                    ->placeholder('—')
                    ->description(fn (Talep $record) => $record->eposta),
                TextColumn::make('portfoy.portfoy_no')
                    ->label('İlan')
                    ->placeholder('—')
                    ->url(fn (Talep $record) => $record->portfoy && Gate::allows('view', $record->portfoy)
                        ? PortfoyResource::getUrl('view', ['record' => $record->portfoy]) : null),
                TextColumn::make('durum')->label('Durum')->badge(),
                TextColumn::make('atanan.name')->label('Atanan')->placeholder('Atanmadı'),
                TextColumn::make('sonraki_takip')->label('Sonraki takip')->date('d.m.Y')->sortable()->placeholder('—')
                    ->color(fn (Talep $record) => $record->sonraki_takip?->isPast() ? 'danger' : null),
                TextColumn::make('kaynak')->label('Kaynak')->formatStateUsing(fn (?string $state) => Talep::KAYNAKLAR[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('durum')->label('Durum')->options(TalepDurumu::class)->multiple(),
                SelectFilter::make('atanan_id')
                    ->label('Atanan')
                    ->options(fn () => User::query()->danismanlar()->pluck('name', 'id')->all())
                    ->visible(fn () => Yetki::var('talep.goruntule_tumu')),
                SelectFilter::make('kaynak')->label('Kaynak')->options(Talep::KAYNAKLAR),
            ])
            ->headerActions([
                Action::make('csvIndir')
                    ->label('CSV indir')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(fn (): bool => CsvAktarici::izinliMi())
                    ->action(fn ($livewire) => CsvAktarici::talepler($livewire->getFilteredSortedTableQuery())),
            ])
            ->recordActions([EditAction::make()->label('Aç')])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('csvSecilenler')
                        ->label('Seçilenleri CSV indir')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->authorize(fn (): bool => CsvAktarici::izinliMi())
                        ->action(fn (Collection $records) => CsvAktarici::talepler($records)),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Talep yok');
    }

    public static function getRelations(): array
    {
        return [NotlarRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTalepler::route('/'),
            'create' => CreateTalep::route('/create'),
            'edit' => EditTalep::route('/{record}/edit'),
        ];
    }
}
