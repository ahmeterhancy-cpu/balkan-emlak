<?php

namespace App\Filament\Resources\Borclandirmalar;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Borclandirmalar\Pages\CreateBorclandirma;
use App\Filament\Resources\Borclandirmalar\Pages\EditBorclandirma;
use App\Filament\Resources\Borclandirmalar\Pages\ListBorclandirmalar;
use App\Filament\Resources\Borclandirmalar\Pages\ViewBorclandirma;
use App\Filament\Resources\Borclandirmalar\RelationManagers\BorclarRelationManager;
use App\Models\BagimsizBolum;
use App\Models\Borclandirma;
use App\Models\Kisi;
use App\Models\Site;
use App\Services\SiteFinans\Borclandirici;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Toplu borçlandırma (şartname 8.2, 12 adım 3–4): taslak → önizleme ve
 * toplam kontrolü → yetkili kesinleştirme. Kesinleşen iş bir daha
 * çalışmaz (Borclandirici::kesinlestir idempotent).
 */
class BorclandirmaResource extends Resource
{
    protected static ?string $model = Borclandirma::class;

    protected static ?string $slug = 'borclandirmalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Aidat ve Borçlandırma';

    protected static ?string $modelLabel = 'borçlandırma';

    protected static ?string $pluralModelLabel = 'borçlandırmalar';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    /** Sitenin varsayılan vade günüyle dönem vadesi. */
    public static function varsayilanVade(mixed $siteId, ?string $donem): ?string
    {
        if (! $donem || ! Donem::gecerli($donem) || ! filled($siteId)) {
            return null;
        }
        $gun = min(28, max(1, (int) (Site::whereKey((int) $siteId)->value('varsayilan_vade_gunu') ?? 10)));

        return $donem.'-'.str_pad((string) $gun, 2, '0', STR_PAD_LEFT);
    }

    public static function form(Schema $schema): Schema
    {
        $varsayilanlariDoldur = function ($get, $set) {
            if ($vade = static::varsayilanVade($get('site_id'), $get('donem'))) {
                $set('vade', $vade);
            }
            if (Donem::gecerli((string) $get('donem')) && $get('tur')) {
                $set('aciklama', Donem::ad($get('donem')).' '.mb_strtolower(Borclandirma::TURLER[$get('tur')] ?? ''));
            }
            if ($pb = Site::whereKey((int) $get('site_id'))->value('para_birimi')) {
                $set('para_birimi', $pb);
            }
        };

        return $schema->columns(1)->components([
            Section::make('Borçlandırma')
                ->columns(3)
                ->schema([
                    SiteAlanlari::site('site_id', ['ozel_satirlar'])
                        ->afterStateUpdated(function ($get, $set) use ($varsayilanlariDoldur) {
                            $set('ozel_satirlar', []);
                            $varsayilanlariDoldur($get, $set);
                        }),
                    TextInput::make('donem')
                        ->label('Dönem (YYYY-AA)')
                        ->required()
                        ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->validationMessages(['regex' => 'Dönem YYYY-AA biçiminde olmalı (ör. 2026-10).'])
                        ->default(fn () => now()->format('Y-m'))
                        ->live(onBlur: true)
                        ->afterStateUpdated($varsayilanlariDoldur)
                        ->rule(fn ($get, ?Borclandirma $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            $var = Borclandirma::query()
                                ->where('site_id', (int) $get('site_id'))->where('donem', $value)
                                ->where('tur', (string) $get('tur'))->where('etiket', (string) ($get('etiket') ?? ''))
                                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                                ->exists();
                            if ($var) {
                                $fail('Bu site için bu dönemde aynı tür ve etikette borçlandırma zaten var. İkinci bir ek aidat için farklı etiket yazın.');
                            }
                        }),
                    Select::make('tur')
                        ->label('Borç türü')
                        ->options(Borclandirma::TURLER)
                        ->default('aidat')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated($varsayilanlariDoldur),
                    TextInput::make('etiket')
                        ->label('Etiket (isteğe bağlı)')
                        ->maxLength(60)
                        ->helperText('Aynı dönemde ikinci ek aidat için ayırıcı (ör. "Çatı onarımı").')
                        ->dehydrateStateUsing(fn ($state) => (string) ($state ?? '')),
                    TextInput::make('aciklama')->label('Açıklama')->required()->maxLength(255)->columnSpan(2),
                    DatePicker::make('vade')
                        ->label('Vade')
                        ->required()
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->default(fn ($get) => static::varsayilanVade($get('site_id') ?? SiteAlanlari::tekSiteId(), now()->format('Y-m'))),
                    Select::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER)->default('TRY')->required()->selectablePlaceholder(false),
                ]),

            Section::make('Dağıtım kuralı')
                ->description('Metrekare ve arsa payında toplam, bölümlere oranla bölünür; kuruş farkı en büyük küsurlu bölümlere verilir, bölüm tutarları toplamı girilen toplama tam eşittir.')
                ->columns(3)
                ->schema([
                    Select::make('dagitim')
                        ->label('Dağıtım')
                        ->options(Borclandirma::DAGITIMLAR)
                        ->default('sabit')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live(),
                    Alanlar::para('birim_kurus', 'Bölüm başı tutar')
                        ->visible(fn ($get) => $get('dagitim') === 'sabit')
                        ->required(fn ($get) => $get('dagitim') === 'sabit'),
                    Alanlar::para('toplam_kurus', 'Dağıtılacak toplam tutar')
                        ->visible(fn ($get) => in_array($get('dagitim'), ['m2', 'arsa_payi'], true))
                        ->required(fn ($get) => in_array($get('dagitim'), ['m2', 'arsa_payi'], true)),
                    CheckboxList::make('bolum_turleri')
                        ->label('Yalnız bu bölüm türleri')
                        ->options(BagimsizBolum::TURLER)
                        ->columns(3)
                        ->helperText('Hiçbiri seçilmezse tüm etkin bölümler borçlandırılır.')
                        ->visible(fn ($get) => $get('dagitim') !== 'ozel')
                        ->columnSpanFull(),
                    Repeater::make('ozel_satirlar')
                        ->label('Onaylı özel dağıtım (bölüm bölüm)')
                        ->visible(fn ($get) => $get('dagitim') === 'ozel')
                        ->required(fn ($get) => $get('dagitim') === 'ozel')
                        ->schema([
                            Select::make('bolum_id')
                                ->label('Bölüm')
                                ->options(fn ($get) => SiteAlanlari::bolumSecenekleri($get('../../site_id'), true))
                                ->required()
                                ->distinct()
                                ->searchable(),
                            Alanlar::para('tutar', 'Tutar')->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Bölüm ekle')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** Form "ozel_satirlar" ↔ model "ozel_tutarlar" ({bolum_id: kurus}). */
    public static function ozelTutarlaraCevir(array $data): array
    {
        if (($data['dagitim'] ?? null) === 'ozel') {
            $data['ozel_tutarlar'] = collect($data['ozel_satirlar'] ?? [])
                ->filter(fn ($s) => filled($s['bolum_id'] ?? null))
                ->mapWithKeys(fn ($s) => [(int) $s['bolum_id'] => (int) ($s['tutar'] ?? 0)])
                ->all();
            $data['birim_kurus'] = null;
            $data['toplam_kurus'] = null;
            $data['bolum_turleri'] = null;
        } else {
            $data['ozel_tutarlar'] = null;
            if (($data['dagitim'] ?? null) === 'sabit') {
                $data['toplam_kurus'] = null;
            } else {
                $data['birim_kurus'] = null;
            }
            $data['bolum_turleri'] = ($data['bolum_turleri'] ?? []) ?: null;
        }
        unset($data['ozel_satirlar']);

        return $data;
    }

    /**
     * Önizleme tablosu verisi (hata varsa mesajı).
     *
     * @return array{satirlar: list<array>, toplam: int, adet: int, uyari: int, hata: ?string, pb: string}
     */
    public static function onizleme(Borclandirma $b): array
    {
        try {
            $satirlar = app(Borclandirici::class)->onizle($b);
        } catch (DomainException $e) {
            return ['satirlar' => [], 'toplam' => 0, 'adet' => 0, 'uyari' => 0, 'hata' => $e->getMessage(), 'pb' => $b->para_birimi];
        }

        $kisiler = Kisi::whereIn('id', $satirlar->pluck('kisi_id')->filter()->unique())->pluck('ad_soyad', 'id');

        return [
            'satirlar' => $satirlar->map(fn ($s) => [
                $s['bolum']->etiket(),
                $s['kisi_id'] ? ($kisiler[$s['kisi_id']] ?? '#'.$s['kisi_id']) : '—',
                Para::bicimle($s['tutar_kurus'], $b->para_birimi),
                $s['uyari'] ?? '',
            ])->all(),
            'toplam' => (int) $satirlar->sum('tutar_kurus'),
            'adet' => $satirlar->count(),
            'uyari' => $satirlar->filter(fn ($s) => $s['uyari'])->count(),
            'hata' => null,
            'pb' => $b->para_birimi,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('site')->withCount('borclar'))
            ->defaultSort('donem', 'desc')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('donem')->label('Dönem')->sortable()->formatStateUsing(fn ($state) => Donem::ad($state)),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn ($state, Borclandirma $record) => (Borclandirma::TURLER[$state] ?? $state).($record->etiket ? ' · '.$record->etiket : '')),
                TextColumn::make('aciklama')->label('Açıklama')->limit(40)->searchable(),
                TextColumn::make('dagitim')->label('Dağıtım')->formatStateUsing(fn ($state) => Borclandirma::DAGITIMLAR[$state] ?? $state)->toggleable(),
                TextColumn::make('vade')->label('Vade')->date('d.m.Y'),
                TextColumn::make('borclar_count')->label('Borç sayısı')->alignEnd(),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => Borclandirma::DURUMLAR[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'kesin' => 'success', 'iptal' => 'gray', default => 'warning'
                    }),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('durum')->label('Durum')->options(Borclandirma::DURUMLAR),
                SelectFilter::make('tur')->label('Tür')->options(Borclandirma::TURLER),
            ])
            ->recordActions([
                ViewAction::make()->label('Önizle / aç'),
                EditAction::make()->label('Düzenle'),
            ])
            ->emptyStateHeading('Borçlandırma yok')
            ->emptyStateDescription('Yeni borçlandırma taslak olarak açılır; önizleme ve toplam kontrolünden sonra kesinleştirilir.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Borçlandırma')
                ->columns(4)
                ->schema([
                    TextEntry::make('site.ad')->label('Site')->badge()->color('gray'),
                    TextEntry::make('donem')->label('Dönem')->formatStateUsing(fn ($state) => Donem::ad($state).' ('.$state.')'),
                    TextEntry::make('tur')->label('Tür')->formatStateUsing(fn ($state, Borclandirma $record) => (Borclandirma::TURLER[$state] ?? $state).($record->etiket ? ' · '.$record->etiket : '')),
                    TextEntry::make('durum')->label('Durum')->badge()
                        ->formatStateUsing(fn ($state) => Borclandirma::DURUMLAR[$state] ?? $state)
                        ->color(fn ($state) => match ($state) {
                            'kesin' => 'success', 'iptal' => 'gray', default => 'warning'
                        }),
                    TextEntry::make('aciklama')->label('Açıklama')->columnSpan(2),
                    TextEntry::make('vade')->label('Vade')->date('d.m.Y'),
                    TextEntry::make('para_birimi')->label('Para birimi'),
                    TextEntry::make('dagitim')->label('Dağıtım')->formatStateUsing(fn ($state) => Borclandirma::DAGITIMLAR[$state] ?? $state),
                    TextEntry::make('kural')->label('Tutar')
                        ->state(fn (Borclandirma $record) => match ($record->dagitim) {
                            'sabit' => Para::bicimle($record->birim_kurus, $record->para_birimi).' / bölüm',
                            'm2', 'arsa_payi' => 'Toplam '.Para::bicimle($record->toplam_kurus, $record->para_birimi),
                            default => count($record->ozel_tutarlar ?? []).' bölüme özel tutar',
                        }),
                    TextEntry::make('bolum_turleri')->label('Bölüm türleri')
                        ->state(fn (Borclandirma $record) => $record->bolum_turleri ? collect($record->bolum_turleri)->map(fn ($t) => BagimsizBolum::TURLER[$t] ?? $t)->implode(', ') : 'Tümü'),
                    TextEntry::make('kesinlesme_zamani')->label('Kesinleşme')
                        ->state(fn (Borclandirma $record) => $record->kesinlesme_zamani ? $record->kesinlesme_zamani->format('d.m.Y H:i').' · '.$record->kesinlestiren?->name : null)
                        ->placeholder('—'),
                ]),
            Section::make('Önizleme ve toplam kontrolü')
                ->description('Hiçbir kayıt yazılmaz. Borç sorumlusu, dönemin ilk günündeki malik / borç sorumlusudur.')
                ->visible(fn (Borclandirma $record) => $record->durum === 'taslak')
                ->schema([
                    View::make('filament.site.borclandirma-onizleme')
                        ->viewData(fn (Borclandirma $record) => static::onizleme($record)),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            BorclarRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBorclandirmalar::route('/'),
            'create' => CreateBorclandirma::route('/create'),
            'view' => ViewBorclandirma::route('/{record}'),
            'edit' => EditBorclandirma::route('/{record}/edit'),
        ];
    }
}
