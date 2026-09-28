<?php

namespace App\Filament\Resources\IsEmirleri;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\IsEmirleri\Pages\CreateIsEmri;
use App\Filament\Resources\IsEmirleri\Pages\EditIsEmri;
use App\Filament\Resources\IsEmirleri\Pages\ListIsEmirleri;
use App\Filament\Resources\IsEmirleri\Pages\ViewIsEmri;
use App\Models\BakimPlani;
use App\Models\Gider;
use App\Models\IsEmri;
use App\Models\Tedarikci;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Bakım, arıza ve iş emirleri (şartname 8.5). Saha personeli yalnız
 * kendisine atanan iş emirlerini görür ve "Tamamla" ile kapatır; yetkili
 * kapatma / yeniden açma yöneticidedir. Fotoğraflar özel diskte.
 */
class IsEmriResource extends Resource
{
    protected static ?string $model = IsEmri::class;

    protected static ?string $slug = 'is-emirleri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Bakım, Arıza ve İş Emirleri';

    protected static ?string $modelLabel = 'iş emri';

    protected static ?string $pluralModelLabel = 'iş emirleri';

    protected static string|UnitEnum|null $navigationGroup = 'Site Operasyonu';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'konu';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::operasyon(parent::getEloquentQuery(), 'atanan_id');
    }

    public static function oncelikRengi(?string $o): string
    {
        return match ($o) {
            'acil' => 'danger', 'yuksek' => 'warning', 'dusuk' => 'gray', default => 'info'
        };
    }

    public static function durumRengi(?string $d): string
    {
        return match ($d) {
            'tamamlandi' => 'success', 'kapatildi' => 'gray', 'dis_hizmet' => 'warning', 'acik' => 'danger', default => 'info'
        };
    }

    public static function form(Schema $schema): Schema
    {
        $acil = trim((string) ayar('acil_iletisim', ''));

        return $schema->columns(1)->components([
            Section::make('İş emri')
                ->description('Form kaydı acil müdahale garantisi değildir.'.($acil !== '' ? ' Acil durumlarda: '.$acil.'.' : ''))
                ->columns(3)
                ->schema([
                    SiteAlanlari::site('site_id', ['blok_id', 'bagimsiz_bolum_id', 'bakim_plani_id', 'atanan_id', 'gider_id'])->disabledOn('edit'),
                    Select::make('blok_id')->label('Blok')
                        ->options(fn ($get) => SiteAlanlari::blokSecenekleri($get('site_id')))->live(),
                    Select::make('bagimsiz_bolum_id')->label('Bağımsız bölüm')
                        ->options(fn ($get) => SiteAlanlari::bolumSecenekleri($get('site_id')))->searchable(),
                    TextInput::make('konu')->label('Konu')->required()->maxLength(255)->columnSpan(2),
                    Select::make('bakim_plani_id')->label('Bakım planı')
                        ->options(fn ($get) => filled($get('site_id')) && Kapsam::siteErisimi((int) $get('site_id'))
                            ? BakimPlani::where('site_id', (int) $get('site_id'))->orderBy('ad')->pluck('ad', 'id')->all() : [])
                        ->helperText('Tamamlanınca planın sonraki bakım tarihi ilerler.'),
                    Textarea::make('aciklama')->label('Açıklama')->rows(3)->columnSpanFull(),
                    FileUpload::make('fotograflar')->label('Fotoğraflar')
                        ->disk('local')->visibility('private')
                        ->directory(fn ($get, ?IsEmri $record) => 'is-emirleri/'.((int) ($get('site_id') ?? $record?->site_id)))
                        ->image()->multiple()->maxFiles(8)->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->downloadable(false)->openable(false)
                        ->columnSpanFull(),
                    TextInput::make('bildiren_ad')->label('Bildiren')->maxLength(255),
                    TextInput::make('bildiren_telefon')->label('Bildiren telefonu')->tel()->maxLength(30),
                    Select::make('oncelik')->label('Öncelik')->options(IsEmri::ONCELIKLER)->default('normal')->required()->selectablePlaceholder(false),
                ]),
            Section::make('Atama ve takip')
                ->columns(3)
                ->schema([
                    Select::make('durum')->label('Durum')
                        ->options(fn (?IsEmri $record) => in_array($record?->durum, ['tamamlandi', 'kapatildi'], true)
                            ? IsEmri::DURUMLAR
                            : collect(IsEmri::DURUMLAR)->except(['tamamlandi', 'kapatildi'])->all())
                        ->default('acik')->required()->selectablePlaceholder(false)
                        ->disabled(fn (?IsEmri $record) => in_array($record?->durum, ['tamamlandi', 'kapatildi'], true))
                        ->helperText('Tamamlama "Tamamla", kapatma "Kapat" eylemiyle yapılır.'),
                    Select::make('atanan_id')->label('Atanan personel')
                        ->options(fn ($get) => SiteAlanlari::personelSecenekleri($get('site_id')))
                        ->helperText('Siteye atanmış operasyon personeli.'),
                    Select::make('tedarikci_id')->label('Tedarikçi (dış hizmet)')
                        ->options(fn () => Tedarikci::where('aktif', true)->orderBy('ad')->pluck('ad', 'id')->all())->searchable(),
                    DatePicker::make('hedef_tarih')->label('Hedef tarih')->native(false)->displayFormat('d.m.Y'),
                    Select::make('gider_id')->label('Maliyet (gider kaydı)')
                        ->options(fn ($get) => filled($get('site_id')) && Kapsam::siteErisimi((int) $get('site_id'))
                            ? Gider::where('site_id', (int) $get('site_id'))->latest('belge_tarihi')->limit(100)->get()
                                ->mapWithKeys(fn (Gider $g) => [$g->id => $g->belge_tarihi->format('d.m.Y').' · '.$g->aciklama.' · '.Para::bicimle($g->tutar_kurus, $g->para_birimi)])->all()
                            : [])
                        ->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'blok', 'bolum.blok', 'atanan', 'tedarikci']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('konu')->label('Konu')->searchable()->wrap()
                    ->description(fn (IsEmri $record) => $record->bolum?->etiket() ?? $record->blok?->ad),
                TextColumn::make('oncelik')->label('Öncelik')->badge()
                    ->formatStateUsing(fn ($state) => IsEmri::ONCELIKLER[$state] ?? $state)->color(fn ($state) => static::oncelikRengi($state)),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => IsEmri::DURUMLAR[$state] ?? $state)->color(fn ($state) => static::durumRengi($state)),
                TextColumn::make('atanan.name')->label('Atanan')->placeholder('—')
                    ->description(fn (IsEmri $record) => $record->tedarikci?->ad),
                TextColumn::make('hedef_tarih')->label('Hedef')->date('d.m.Y')->sortable()->placeholder('—')
                    ->color(fn (IsEmri $record) => $record->hedef_tarih && $record->hedef_tarih->isPast() && ! $record->hedef_tarih->isToday()
                        && ! in_array($record->durum, ['tamamlandi', 'kapatildi'], true) ? 'danger' : null),
                TextColumn::make('created_at')->label('Açılış')->date('d.m.Y')->sortable()->toggleable(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('durum')->label('Durum')->options(IsEmri::DURUMLAR)->multiple(),
                SelectFilter::make('oncelik')->label('Öncelik')->options(IsEmri::ONCELIKLER),
                Filter::make('acik')->label('Yalnız açık işler')->query(fn ($query) => $query->whereNotIn('durum', ['tamamlandi', 'kapatildi']))->default(),
            ])
            ->recordActions([
                static::tamamla(),
                ViewAction::make()->label('Aç'),
                EditAction::make()->label('Düzenle'),
            ])
            ->emptyStateHeading('İş emri yok');
    }

    public static function tamamla(): Action
    {
        return Action::make('tamamla')
            ->label('Tamamla')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (IsEmri $record) => ! in_array($record->durum, ['tamamlandi', 'kapatildi'], true))
            ->authorize(fn (IsEmri $record) => Gate::allows('tamamla', $record))
            ->modalHeading(fn (IsEmri $record) => $record->site?->ad.' — '.$record->konu)
            ->schema(fn (IsEmri $record) => [
                Textarea::make('yapilan_islem')->label('Yapılan işlem')->required()->rows(3)->maxLength(2000),
                FileUpload::make('fotograflar')->label('Görsel kanıt (isteğe bağlı)')
                    ->disk('local')->visibility('private')->directory('is-emirleri/'.$record->site_id)
                    ->image()->multiple()->maxFiles(6)->maxSize(8192)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            ])
            ->action(function (IsEmri $record, array $data) {
                try {
                    $record->tamamla($data['yapilan_islem'], array_values((array) ($data['fotograflar'] ?? [])));
                    Notification::make()->success()->title('İş emri tamamlandı')->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
                }
            });
    }

    public static function kapat(): Action
    {
        return Action::make('kapat')
            ->label('Kapat')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->visible(fn (IsEmri $record) => $record->durum === 'tamamlandi')
            ->authorize(fn (IsEmri $record) => Gate::allows('kapat', $record))
            ->requiresConfirmation()
            ->modalDescription('Tamamlanan iş kontrol edildi ve kapatılıyor.')
            ->action(function (IsEmri $record) {
                try {
                    $record->kapat();
                    Notification::make()->success()->title('İş emri kapatıldı')->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
                }
            });
    }

    public static function yenidenAc(): Action
    {
        return Action::make('yenidenAc')
            ->label('Yeniden aç')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (IsEmri $record) => in_array($record->durum, ['tamamlandi', 'kapatildi'], true))
            ->authorize(fn (IsEmri $record) => Gate::allows('kapat', $record))
            ->schema([
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255),
            ])
            ->action(function (IsEmri $record, array $data) {
                $record->yenidenAc($data['gerekce']);
                Notification::make()->success()->title('İş emri yeniden açıldı')->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('İş emri')
                ->columns(4)
                ->schema([
                    TextEntry::make('site.ad')->label('Site')->badge()->color('gray'),
                    TextEntry::make('yer')->label('Yer')->state(fn (IsEmri $record) => $record->bolum?->etiket() ?? $record->blok?->ad)->placeholder('Ortak alan'),
                    TextEntry::make('oncelik')->label('Öncelik')->badge()->formatStateUsing(fn ($state) => IsEmri::ONCELIKLER[$state] ?? $state)->color(fn ($state) => static::oncelikRengi($state)),
                    TextEntry::make('durum')->label('Durum')->badge()->formatStateUsing(fn ($state) => IsEmri::DURUMLAR[$state] ?? $state)->color(fn ($state) => static::durumRengi($state)),
                    TextEntry::make('aciklama')->label('Açıklama')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('bildiren_ad')->label('Bildiren')->placeholder('—')->helperText(fn (IsEmri $record) => $record->bildiren_telefon),
                    TextEntry::make('atanan.name')->label('Atanan')->placeholder('—'),
                    TextEntry::make('hedef_tarih')->label('Hedef tarih')->date('d.m.Y')->placeholder('—'),
                    TextEntry::make('bakimPlani.ad')->label('Bakım planı')->placeholder('—'),
                    TextEntry::make('yapilan_islem')->label('Yapılan işlem')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('tamamlanma_zamani')->label('Tamamlanma')->dateTime('d.m.Y H:i')->placeholder('—'),
                    TextEntry::make('kapanma_zamani')->label('Kapanış')->dateTime('d.m.Y H:i')->placeholder('—'),
                ]),
            Section::make('Fotoğraflar')
                ->visible(fn (IsEmri $record) => ! empty($record->fotograflar) || ! empty($record->tamamlanma_fotograflari))
                ->schema([
                    View::make('filament.site.is-emri-fotograflari')->viewData(fn (IsEmri $record) => ['kayit' => $record]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIsEmirleri::route('/'),
            'create' => CreateIsEmri::route('/create'),
            'view' => ViewIsEmri::route('/{record}'),
            'edit' => EditIsEmri::route('/{record}/edit'),
        ];
    }
}
