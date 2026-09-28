<?php

namespace App\Filament\Resources\Siteler;

use App\Filament\Destek\Alanlar;
use App\Filament\Resources\Siteler\Pages\CreateSite;
use App\Filament\Resources\Siteler\Pages\EditSite;
use App\Filament\Resources\Siteler\Pages\ListSiteler;
use App\Filament\Resources\Siteler\Pages\ViewSite;
use App\Filament\Resources\Siteler\RelationManagers\BloklarRelationManager;
use App\Filament\Resources\Siteler\RelationManagers\BolumlerRelationManager;
use App\Filament\Resources\Siteler\RelationManagers\HesaplarRelationManager;
use App\Filament\Resources\Siteler\RelationManagers\KullanicilarRelationManager;
use App\Models\Konum;
use App\Models\Site;
use App\Models\User;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Yönetilen siteler / apartmanlar (şartname 8.1, 12 "Bir sitenin yönetime
 * alınması"). Kullanıcı yalnız atandığı siteleri görür; başka sitenin
 * adresini yazarak da açamaz (Kapsam → 404).
 */
class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $slug = 'siteler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Siteler / Apartmanlar';

    protected static ?string $modelLabel = 'site';

    protected static ?string $pluralModelLabel = 'siteler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Yönetimi';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ad';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteler(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Site bilgileri')
                ->columns(3)
                ->schema([
                    TextInput::make('ad')->label('Site / apartman adı')->required()->maxLength(255)->columnSpan(2),
                    Select::make('tur')->label('Tür')->options(Site::TURLER)->default('site')->required()->selectablePlaceholder(false),
                    Textarea::make('adres')->label('Adres')->rows(2)->columnSpanFull(),
                    Select::make('il_id')
                        ->label('İl')
                        ->options(fn () => Konum::secenekler('il'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('ilce_id', null)),
                    Select::make('ilce_id')
                        ->label('İlçe')
                        ->options(fn ($get): array => $get('il_id') ? Konum::secenekler('ilce', (int) $get('il_id')) : [])
                        ->searchable(),
                    Toggle::make('aktif')->label('Yönetimde (aktif)')->default(true)->inline(false),
                ]),

            Section::make('Yönetim')
                ->columns(3)
                ->schema([
                    DatePicker::make('yonetim_baslangic')->label('Yönetim başlangıcı')->native(false)->displayFormat('d.m.Y'),
                    DatePicker::make('yonetim_bitis')->label('Yönetim bitişi')->native(false)->displayFormat('d.m.Y')->afterOrEqual('yonetim_baslangic'),
                    Select::make('sorumlu_id')
                        ->label('Sorumlu yönetici')
                        ->options(fn () => User::query()->where('aktif', true)
                            ->whereIn('rol', ['sistem_yoneticisi', 'sirket_yoneticisi', 'site_yoneticisi'])
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    TextInput::make('iletisim_kisi')->label('İletişim kişisi')->maxLength(255),
                    TextInput::make('iletisim_telefon')->label('İletişim telefonu')->tel()->maxLength(30),
                ]),

            Section::make('Mali ayarlar')
                ->description('Site paraları şirketin emlak komisyon gelirlerinden ayrı tutulur. Farklı para birimleri hiçbir toplamda birleşmez.')
                ->columns(3)
                ->schema([
                    Select::make('para_birimi')
                        ->label('Varsayılan para birimi')
                        ->options(Para::BIRIMLER)
                        ->default('TRY')
                        ->required()
                        ->selectablePlaceholder(false),
                    TextInput::make('varsayilan_vade_gunu')
                        ->label('Varsayılan vade günü')
                        ->numeric()->integer()->minValue(1)->maxValue(28)->default(10)->required()
                        ->helperText('Borçlandırmada vade, dönemin bu gününe ayarlanır.'),
                    Alanlar::para('onay_limiti_kurus', 'Gider onay limiti')
                        ->helperText('Bu tutarın üstündeki gideri yalnız şirket yöneticisi onaylar. Boş = limit yok.')
                        ->disabled(fn (?Site $record) => ! static::yetkiDuzenler($record))
                        ->dehydrated(fn (?Site $record) => static::yetkiDuzenler($record)),
                    TextEntry::make('kapali_donem_bilgi')
                        ->label('Kapalı dönem')
                        ->state(fn (?Site $record) => $record?->kapali_donem ? Donem::ad($record->kapali_donem).' dahil öncesi kapalı' : 'Kapalı dönem yok')
                        ->visible(fn (?Site $record) => $record !== null),
                ]),

            Section::make('Notlar')
                ->collapsed()
                ->schema([
                    Textarea::make('notlar')->label('İç notlar')->rows(3),
                ]),
        ]);
    }

    /** Onay limiti ve kullanıcı ataması: yalnız tüm siteleri yöneten. */
    public static function yetkiDuzenler(?Site $record): bool
    {
        return $record ? Gate::allows('yetkiDuzenle', $record) : Gate::allows('create', Site::class);
    }

    /**
     * Sitelerin açık borç toplamları (para birimine göre): borç (düzeltmeler
     * dahil) − dağıtılan tahsilat. Tek sorguda, liste için.
     *
     * @param  list<int>  $siteIdleri
     * @return array<int, array<string, int>>
     */
    public static function acikBorclar(array $siteIdleri): array
    {
        if ($siteIdleri === []) {
            return [];
        }

        $borc = DB::table('borclar')->whereIn('site_id', $siteIdleri)
            ->selectRaw('site_id, para_birimi, SUM(tutar_kurus) AS t')->groupBy('site_id', 'para_birimi')->get();
        $odenen = DB::table('tahsilat_dagitimlari as td')->join('borclar as b', 'b.id', '=', 'td.borc_id')
            ->whereIn('b.site_id', $siteIdleri)
            ->selectRaw('b.site_id, b.para_birimi, SUM(td.tutar_kurus) AS t')->groupBy('b.site_id', 'b.para_birimi')->get();

        $sonuc = [];
        foreach ($borc as $r) {
            $sonuc[$r->site_id][$r->para_birimi] = (int) $r->t;
        }
        foreach ($odenen as $r) {
            $sonuc[$r->site_id][$r->para_birimi] = ($sonuc[$r->site_id][$r->para_birimi] ?? 0) - (int) $r->t;
        }

        return $sonuc;
    }

    public static function table(Table $table): Table
    {
        $onbellek = null;
        $borclar = function (Site $record) use (&$onbellek, $table): string {
            $onbellek ??= static::acikBorclar($table->getLivewire()->getTableRecords()->pluck('id')->map(fn ($i) => (int) $i)->all());
            $satir = array_filter($onbellek[$record->id] ?? []);

            return $satir === [] ? '—' : collect($satir)->map(fn ($k, $pb) => Para::bicimle($k, $pb))->implode(' · ');
        };

        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('sorumlu')
                ->withCount(['bolumler', 'isEmirleri as acik_is_emri_count' => fn ($q) => $q->whereNotIn('durum', ['tamamlandi', 'kapatildi'])]))
            ->defaultSort('ad')
            ->columns([
                TextColumn::make('ad')->label('Site / apartman')->searchable()->sortable()
                    ->description(fn (Site $record) => Site::TURLER[$record->tur] ?? $record->tur),
                TextColumn::make('bolumler_count')->label('Bölüm')->alignEnd()->sortable(),
                TextColumn::make('acik_borc')->label('Açık borç')->state($borclar)->alignEnd()->wrap(),
                TextColumn::make('acik_is_emri_count')->label('Açık iş emri')->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : null),
                TextColumn::make('kapali_donem')->label('Kapalı dönem')
                    ->formatStateUsing(fn (?string $state) => $state ? Donem::ad($state) : '—')->placeholder('—'),
                TextColumn::make('sorumlu.name')->label('Sorumlu')->placeholder('—')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make()->label('Görüntüle'),
                EditAction::make()->label('Düzenle'),
            ])
            ->emptyStateHeading('Yönetilen site yok')
            ->emptyStateDescription('Yeni site ekleyin; ardından blokları, bağımsız bölümleri ve malikleri tanımlayın.');
    }

    public static function getRelations(): array
    {
        return [
            BolumlerRelationManager::class,
            BloklarRelationManager::class,
            HesaplarRelationManager::class,
            KullanicilarRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteler::route('/'),
            'create' => CreateSite::route('/create'),
            'view' => ViewSite::route('/{record}'),
            'edit' => EditSite::route('/{record}/edit'),
        ];
    }
}
