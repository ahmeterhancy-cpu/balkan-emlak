<?php

namespace App\Filament\Resources\Tahsilatlar;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Tahsilatlar\Pages\CreateTahsilat;
use App\Filament\Resources\Tahsilatlar\Pages\ListTahsilatlar;
use App\Models\BagimsizBolum;
use App\Models\BolumIliskisi;
use App\Models\Borc;
use App\Models\Hesap;
use App\Models\Tahsilat;
use App\Services\SiteFinans\TahsilatServisi;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Tahsilatlar (şartname 8.3). Kayıt yalnız TahsilatServisi'nden geçer:
 * kasaya/bankaya yazılır, açık borçlara vadesi en eskiden başlayarak
 * dağıtılır, artan avans olarak kalır. Dekontla gelen ödeme doğrulanana
 * kadar kasaya ve borca işlenmez. İade / hatalı kayıt ters kayıtla düzeltilir.
 */
class TahsilatResource extends Resource
{
    protected static ?string $model = Tahsilat::class;

    protected static ?string $slug = 'tahsilatlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Tahsilatlar';

    protected static ?string $modelLabel = 'tahsilat';

    protected static ?string $pluralModelLabel = 'tahsilatlar';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    /** Seçilen bölümün açık borçları (seçim ve önizleme için). */
    public static function acikBorclar(mixed $bolumId, ?string $pb): Collection
    {
        if (! filled($bolumId)) {
            return collect();
        }

        return Borc::query()->where('bagimsiz_bolum_id', (int) $bolumId)
            ->when($pb, fn ($q) => $q->where('para_birimi', $pb))
            ->acik()->bakiyeli()->orderBy('vade')->orderBy('id')->get();
    }

    public static function hesapParaBirimi(mixed $hesapId): ?string
    {
        return filled($hesapId) ? Hesap::whereKey((int) $hesapId)->value('para_birimi') : null;
    }

    /** Formdaki bölümü, site ve erişim denetiminden geçirerek getir. */
    public static function formBolumu($get): ?BagimsizBolum
    {
        $bolumId = $get('bagimsiz_bolum_id');
        if (! filled($bolumId) || ! filled($get('site_id'))) {
            return null;
        }
        $bolum = BagimsizBolum::find((int) $bolumId);

        return $bolum && (int) $bolum->site_id === (int) $get('site_id') && Kapsam::siteErisimi($bolum->site_id) ? $bolum : null;
    }

    /**
     * Canlı dağıtım önizlemesi verisi.
     *
     * @return array{durum: string, mesaj?: string, satirlar?: list<array>, avans?: string, pb?: string, toplam?: string}
     */
    public static function onizleme($get): array
    {
        $bolum = static::formBolumu($get);
        $pb = static::hesapParaBirimi($get('hesap_id'));
        $tutar = Para::kurusa($get('tutar_kurus'));

        if (! $bolum || ! $pb || ! $tutar || $tutar <= 0) {
            return ['durum' => 'eksik', 'mesaj' => 'Site, bölüm, hesap ve tutar girilince dağıtım burada görünür.'];
        }

        $sira = array_values(array_map('intval', (array) ($get('borc_sirasi') ?? [])));
        $plan = app(TahsilatServisi::class)->onizle($bolum, $tutar, $pb, $sira ?: null);

        return [
            'durum' => 'tamam',
            'pb' => $pb,
            'dogrulanmadi' => filled($get('belge_yolu')) && ! $get('dogrulandi'),
            'toplam' => Para::bicimle($tutar, $pb),
            'satirlar' => collect($plan['satirlar'])->map(fn ($s) => [
                $s['borc']->aciklama.' ('.$s['borc']->donem.')',
                $s['borc']->vade->format('d.m.Y'),
                Para::bicimle($s['borc']->kalanKurus(), $pb),
                Para::bicimle($s['tutar_kurus'], $pb),
                Para::bicimle($s['kalan_sonra'], $pb),
            ])->all(),
            'avans' => Para::bicimle($plan['avans_kurus'], $pb),
            'avans_kurus' => $plan['avans_kurus'],
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Tahsilat')
                ->description('Tahsilat seçilen sitenin hesabına yazılır. Borç kapama sırası: aynı para birimindeki açık borçlar, vadesi en eskiden başlayarak.')
                ->columns(3)
                ->schema([
                    SiteAlanlari::site('site_id', ['bagimsiz_bolum_id', 'hesap_id', 'borc_sirasi', 'kisi_id']),
                    Select::make('bagimsiz_bolum_id')
                        ->label('Bağımsız bölüm')
                        ->options(fn ($get) => SiteAlanlari::bolumSecenekleri($get('site_id')))
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('borc_sirasi', []);
                            $set('kisi_id', null);
                        }),
                    Select::make('hesap_id')
                        ->label('Kasa / banka hesabı')
                        ->options(fn ($get) => SiteAlanlari::hesapSecenekleri($get('site_id')))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('borc_sirasi', [])),
                    DatePicker::make('tarih')->label('Tahsilat tarihi')->required()->native(false)->displayFormat('d.m.Y')
                        ->default(fn () => now()->toDateString())->maxDate(now()->addDay())->live(),
                    Alanlar::para('tutar_kurus', 'Tutar')->required()->live(onBlur: true),
                    Select::make('yontem')->label('Yöntem')->options(Tahsilat::YONTEMLER)->required()->default('havale')->selectablePlaceholder(false),
                    Select::make('kisi_id')
                        ->label('Ödeyen')
                        ->options(function ($get) {
                            $bolum = static::formBolumu($get);

                            return $bolum ? BolumIliskisi::where('bagimsiz_bolum_id', $bolum->id)->with('kisi')->get()
                                ->mapWithKeys(fn (BolumIliskisi $i) => [$i->kisi_id => $i->kisi?->ad_soyad.' ('.(BolumIliskisi::ROLLER[$i->rol] ?? $i->rol).')'])->all() : [];
                        })
                        ->placeholder('Borç sorumlusu')
                        ->helperText('Boşsa tahsilat tarihindeki borç sorumlusu yazılır.'),
                    TextInput::make('aciklama')->label('Açıklama')->maxLength(255)->columnSpan(2),
                ]),

            Section::make('Dekont')
                ->description('Dekont yüklemek tahsilatı kesinleştirmez: doğrulanmadıysa kayıt kasaya ve borca işlenmez, "Doğrula" ile sonra işlenir.')
                ->columns(2)
                ->schema([
                    FileUpload::make('belge_yolu')
                        ->label('Dekont')
                        ->disk('local')
                        ->visibility('private')
                        ->directory(fn ($get) => 'dekontlar/'.((int) $get('site_id')))
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->live()
                        ->helperText('PDF veya görsel, en fazla 10 MB. Dosya özel diskte tutulur.'),
                    Toggle::make('dogrulandi')
                        ->label('Dekont doğrulandı mı?')
                        ->helperText('Banka hesabına geçtiğini gördüyseniz açın. Kapalıysa tahsilat "doğrulama bekliyor" olarak kaydedilir.')
                        ->default(false)
                        ->live()
                        ->visible(fn ($get) => filled($get('belge_yolu'))),
                ]),

            Section::make('Dağıtım')
                ->columns(1)
                ->schema([
                    CheckboxList::make('borc_sirasi')
                        ->label('Önce kapatılacak borçlar (isteğe bağlı)')
                        ->helperText('İşaretlenen borçlar önce, kalanlar vade sırasıyla kapatılır. Hiçbiri işaretlenmezse vadesi en eski borçtan başlanır.')
                        ->options(fn ($get) => ($b = static::formBolumu($get))
                            ? static::acikBorclar($b->id, static::hesapParaBirimi($get('hesap_id')))
                                ->mapWithKeys(fn (Borc $x) => [$x->id => $x->aciklama.' · vade '.$x->vade->format('d.m.Y').' · kalan '.Para::bicimle($x->kalanKurus(), $x->para_birimi)])->all()
                            : [])
                        ->live(),
                    View::make('filament.site.tahsilat-onizleme')
                        ->viewData(fn ($get) => static::onizleme($get)),
                    SiteAlanlari::donemGerekcesi(fn ($get) => $get('site_id'), 'gerekce'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['site', 'bolum.blok', 'kisi', 'hesap'])
                ->withSum('dagitimlar as dagitilan_toplam', 'tutar_kurus')
                ->withSum('iadeler as iade_toplam', 'tutar_kurus')
                ->withCount('iadeler'))
            ->defaultSort('tarih', 'desc')
            ->columns([
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y')->sortable(),
                SiteAlanlari::siteSutunu(),
                TextColumn::make('bolum_etiket')->label('Bölüm')->state(fn (Tahsilat $record) => $record->bolum?->etiket()),
                TextColumn::make('kisi.ad_soyad')->label('Ödeyen')->placeholder('—')->searchable(),
                TextColumn::make('yontem')->label('Yöntem')->formatStateUsing(fn ($state) => Tahsilat::YONTEMLER[$state] ?? $state)->toggleable(),
                TextColumn::make('hesap.ad')->label('Hesap')->toggleable(),
                SiteAlanlari::paraSutunu('tutar_kurus', 'Tutar')
                    ->color(fn (Tahsilat $record) => $record->tutar_kurus < 0 ? 'danger' : null),
                SiteAlanlari::paraSutunu('dagitilan', 'Borca dağıtılan', fn (Tahsilat $record) => $record->tur === 'tahsilat' ? (int) $record->dagitilan_toplam : null),
                SiteAlanlari::paraSutunu('avans', 'Avans', fn (Tahsilat $record) => static::avansKurus($record)),
                IconColumn::make('dogrulandi')->label('Doğrulandı')->boolean(),
                TextColumn::make('iade')->label('İade')
                    ->state(fn (Tahsilat $record) => static::iadeDurumu($record))
                    ->badge()->color(fn ($state) => $state === '—' ? 'gray' : 'danger'),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('yontem')->label('Yöntem')->options(Tahsilat::YONTEMLER),
                SelectFilter::make('tur')->label('Kayıt türü')->options(['tahsilat' => 'Tahsilat', 'iade' => 'İade']),
                TernaryFilter::make('dogrulandi')->label('Doğrulandı'),
                Filter::make('tarih')
                    ->schema([
                        DatePicker::make('bas')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('bit')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['bas'] ?? null, fn ($q, $v) => $q->where('tarih', '>=', $v))
                        ->when($data['bit'] ?? null, fn ($q, $v) => $q->where('tarih', '<=', $v))),
            ])
            ->recordActions([
                static::dekontEylemi(),
                static::dogrulaEylemi(),
                static::iadeEylemi(),
            ])
            ->emptyStateHeading('Tahsilat yok')
            ->emptyStateDescription('"Yeni tahsilat" ile ödeme kaydedin; borçlara dağıtım önizlemesi kayıttan önce gösterilir.');
    }

    public static function avansKurus(Tahsilat $t): ?int
    {
        if ($t->tur !== 'tahsilat') {
            return null;
        }
        if (! $t->dogrulandi) {
            return 0;
        }

        return $t->tutar_kurus + (int) $t->iade_toplam - (int) $t->dagitilan_toplam;
    }

    public static function iadeDurumu(Tahsilat $t): string
    {
        if ($t->tur === 'iade') {
            return 'İade kaydı';
        }
        if ((int) $t->iadeler_count === 0) {
            return '—';
        }

        return $t->tutar_kurus + (int) $t->iade_toplam === 0 ? 'Tamamen iade' : 'Kısmi iade';
    }

    public static function dekontEylemi(): Action
    {
        return Action::make('dekont')
            ->label('Dekont')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (Tahsilat $record) => filled($record->belge_yolu) && Gate::allows('view', $record))
            ->url(fn (Tahsilat $record) => route('panel.dekont.indir', $record), shouldOpenInNewTab: true);
    }

    public static function dogrulaEylemi(): Action
    {
        return Action::make('dogrula')
            ->label('Doğrula')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Tahsilat $record) => ! $record->dogrulandi && $record->tur === 'tahsilat')
            ->authorize(fn (Tahsilat $record) => Gate::allows('create', Tahsilat::class) && Gate::allows('view', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Tahsilat $record) => $record->site?->ad.' — dekontu doğrula')
            ->modalDescription(fn (Tahsilat $record) => Para::bicimle($record->tutar_kurus, $record->para_birimi).' tutarındaki ödeme '
                .$record->hesap?->ad.' hesabına yazılacak ve açık borçlara dağıtılacak. Banka hesabına geçtiğini gördüyseniz onaylayın.')
            ->action(function (Tahsilat $record, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(fn () => app(TahsilatServisi::class)->dogrula($record), 'Tahsilat doğrulandı ve işlendi', $action);
            });
    }

    public static function iadeEylemi(): Action
    {
        return Action::make('iade')
            ->label('İade et')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Tahsilat $record) => $record->tur === 'tahsilat' && $record->dogrulandi && static::iadeDurumu($record) !== 'Tamamen iade')
            ->authorize(fn (Tahsilat $record) => Gate::allows('create', Tahsilat::class) && Gate::allows('view', $record))
            ->modalHeading(fn (Tahsilat $record) => $record->site?->ad.' · '.$record->bolum?->etiket().' — tahsilat iadesi')
            ->modalDescription(fn (Tahsilat $record) => 'Tahsilat silinmez; eksi tutarlı iade kaydı ve kasadan çıkış yazılır. Tam iadede borçlara yapılan dağıtım geri alınır. '
                .'Kısmi iade yalnız avanstan yapılabilir (şu an avans: '.Para::bicimle((int) static::avansKurus($record), $record->para_birimi).').')
            ->schema([
                Radio::make('kapsam')->label('İade kapsamı')->options(['tam' => 'Tamamı', 'kismi' => 'Avanstan kısmi'])->default('tam')->required()->live(),
                Alanlar::para('tutar', 'İade tutarı')
                    ->visible(fn ($get) => $get('kapsam') === 'kismi')
                    ->required(fn ($get) => $get('kapsam') === 'kismi'),
                DatePicker::make('tarih')->label('İade tarihi')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255)
                    ->helperText(fn (Tahsilat $record) => $record->site?->kapali_donem
                        ? Donem::ad($record->site->kapali_donem).' dahil önceki dönemler kapalı; kapalı döneme iade yalnız yetkili düzeltmeyle yapılır.'
                        : null),
            ])
            ->action(function (Tahsilat $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(
                    fn () => app(TahsilatServisi::class)->iadeEt($record, $data['gerekce'], $data['tarih'], $data['kapsam'] === 'kismi' ? (int) $data['tutar'] : null),
                    'İade kaydedildi',
                    $action,
                );
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahsilatlar::route('/'),
            'create' => CreateTahsilat::route('/create'),
        ];
    }
}
