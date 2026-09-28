<?php

namespace App\Filament\Resources\Giderler;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Giderler\Pages\CreateGider;
use App\Filament\Resources\Giderler\Pages\EditGider;
use App\Filament\Resources\Giderler\Pages\ListGiderler;
use App\Filament\Resources\Giderler\Pages\ViewGider;
use App\Filament\Resources\Giderler\RelationManagers\OdemelerRelationManager;
use App\Models\Gider;
use App\Models\Hesap;
use App\Models\Site;
use App\Models\Tedarikci;
use App\Services\SiteFinans\GiderServisi;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use App\Support\Yetki;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Giderler (şartname 8.4). Kayıt/onay ile ödeme ayrı izlenir:
 * Taslak → Onay bekliyor → Onaylandı / Reddedildi; Ödenmedi → Kısmen → Ödendi.
 * Site onay limitini aşan gideri yalnız şirket yöneticisi onaylar.
 */
class GiderResource extends Resource
{
    protected static ?string $model = Gider::class;

    protected static ?string $slug = 'giderler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Giderler';

    protected static ?string $modelLabel = 'gider';

    protected static ?string $pluralModelLabel = 'giderler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function tedarikciAlani(): Select
    {
        return Select::make('tedarikci_id')
            ->label('Tedarikçi')
            ->options(fn () => Tedarikci::where('aktif', true)->orderBy('ad')->pluck('ad', 'id')->all())
            ->searchable()
            ->createOptionModalHeading('Yeni tedarikçi')
            ->createOptionForm([
                TextInput::make('ad')->label('Tedarikçi adı')->required()->maxLength(255),
                Select::make('kategori')->label('Kategori')->options(Gider::KATEGORILER),
                TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
                TextInput::make('vergi_no')->label('Vergi no')->maxLength(20),
            ])
            ->createOptionUsing(function (array $data): int {
                abort_unless(Gate::allows('create', Tedarikci::class), 403);

                return Tedarikci::create($data)->id;
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Gider')
                ->columns(3)
                ->schema([
                    SiteAlanlari::site('site_id')
                        ->afterStateUpdated(fn ($get, $set) => $set('para_birimi', Site::whereKey((int) $get('site_id'))->value('para_birimi') ?? 'TRY')),
                    static::tedarikciAlani(),
                    Select::make('kategori')->label('Kategori')->options(Gider::KATEGORILER)->required(),
                    DatePicker::make('belge_tarihi')->label('Belge tarihi')->required()->native(false)->displayFormat('d.m.Y')
                        ->default(fn () => now()->toDateString())
                        ->live()
                        ->afterStateUpdated(fn ($state, $set) => $state ? $set('donem', substr((string) $state, 0, 7)) : null),
                    TextInput::make('belge_no')->label('Belge no')->maxLength(40),
                    TextInput::make('donem')->label('Gider dönemi (YYYY-AA)')->required()
                        ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->validationMessages(['regex' => 'Dönem YYYY-AA biçiminde olmalı.'])
                        ->default(fn () => now()->format('Y-m')),
                    DatePicker::make('vade')->label('Ödeme vadesi')->native(false)->displayFormat('d.m.Y'),
                    Alanlar::para('tutar_kurus', 'Toplam tutar')->required(),
                    Select::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER)->default('TRY')->required()->selectablePlaceholder(false),
                    TextInput::make('aciklama')->label('Açıklama')->required()->maxLength(255)->columnSpanFull(),
                    FileUpload::make('belge_yolu')
                        ->label('Fatura / belge')
                        ->disk('local')
                        ->visibility('private')
                        ->directory(fn ($get, ?Gider $record) => 'gider-belgeleri/'.((int) ($get('site_id') ?? $record?->site_id)))
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->downloadable(false)
                        ->openable(false)
                        ->helperText('PDF veya görsel, en fazla 10 MB. Özel diskte; yalnız yetkili kullanıcı indirebilir.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function odenen(Gider $g): int
    {
        return array_key_exists('odenen_toplam', $g->getAttributes()) ? (int) $g->odenen_toplam : $g->odenenKurus();
    }

    public static function odemeDurumu(Gider $g): string
    {
        $o = static::odenen($g);

        return match (true) {
            $o <= 0 => 'odenmedi',
            $o < $g->tutar_kurus => 'kismi',
            default => 'odendi',
        };
    }

    public static function onayRengi(string $d): string
    {
        return match ($d) {
            'onaylandi' => 'success', 'onay_bekliyor' => 'warning', 'reddedildi' => 'danger', default => 'gray'
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'tedarikci'])->withSum('odemeler as odenen_toplam', 'tutar_kurus'))
            ->defaultSort('belge_tarihi', 'desc')
            ->columns([
                TextColumn::make('belge_tarihi')->label('Belge tarihi')->date('d.m.Y')->sortable(),
                SiteAlanlari::siteSutunu(),
                TextColumn::make('tedarikci.ad')->label('Tedarikçi')->placeholder('—')->searchable(),
                TextColumn::make('kategori')->label('Kategori')->formatStateUsing(fn ($state) => Gider::KATEGORILER[$state] ?? $state)->toggleable(),
                TextColumn::make('aciklama')->label('Açıklama')->limit(34)->searchable(),
                TextColumn::make('donem')->label('Dönem')->toggleable(),
                SiteAlanlari::paraSutunu('tutar_kurus', 'Tutar'),
                TextColumn::make('onay_durumu')->label('Onay')->badge()
                    ->formatStateUsing(fn ($state) => Gider::ONAY_DURUMLARI[$state] ?? $state)
                    ->color(fn ($state) => static::onayRengi($state))
                    ->description(fn (Gider $record) => $record->onay_durumu === 'onay_bekliyor' && app(GiderServisi::class)->limitAsiliyorMu($record) ? 'Limit üstü' : null),
                TextColumn::make('odeme')->label('Ödeme')->badge()
                    ->state(fn (Gider $record) => static::odemeDurumu($record))
                    ->formatStateUsing(fn ($state) => Gider::ODEME_DURUMLARI[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'odendi' => 'success', 'kismi' => 'info', default => 'gray'
                    }),
                SiteAlanlari::paraSutunu('kalan', 'Kalan', fn (Gider $record) => $record->tutar_kurus - static::odenen($record)),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('onay_durumu')->label('Onay durumu')->options(Gider::ONAY_DURUMLARI),
                SelectFilter::make('kategori')->label('Kategori')->options(Gider::KATEGORILER),
                SelectFilter::make('tedarikci_id')->label('Tedarikçi')->relationship('tedarikci', 'ad'),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç'),
                EditAction::make()->label('Düzenle'),
                ActionGroup::make(static::eylemler()),
            ])
            ->emptyStateHeading('Gider yok')
            ->emptyStateDescription('Gider taslak olarak kaydedilir, onaya gönderilir; onaylanınca ödeme girilebilir.');
    }

    /** @return list<Action> */
    public static function eylemler(): array
    {
        return [static::onayaGonder(), static::onayla(), static::reddet(), static::ode()];
    }

    public static function onayaGonder(): Action
    {
        return Action::make('onayaGonder')
            ->label('Onaya gönder')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn (Gider $record) => in_array($record->onay_durumu, ['taslak', 'reddedildi'], true))
            ->authorize(fn (Gider $record) => Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Gider $record) => $record->site?->ad.' — gideri onaya gönder')
            ->modalDescription(fn (Gider $record) => app(GiderServisi::class)->limitAsiliyorMu($record)
                ? 'Tutar site onay limitini aşıyor; gider şirket yöneticisi onayına düşer.'
                : 'Gider, onay yetkisi olan yöneticiye düşer.')
            ->action(fn (Gider $record, Action $action) => SiteAlanlari::calistir(fn () => app(GiderServisi::class)->onayaGonder($record), 'Gider onaya gönderildi', $action));
    }

    public static function onayla(): Action
    {
        return Action::make('onayla')
            ->label('Onayla')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Gider $record) => $record->onay_durumu === 'onay_bekliyor'
                && (Yetki::var('gider.onayla') || Yetki::var('gider.onayla_limitsiz')) && Gate::allows('view', $record))
            ->disabled(fn (Gider $record) => ! app(GiderServisi::class)->onaylayabilirMi($record))
            ->tooltip(fn (Gider $record) => app(GiderServisi::class)->onaylayabilirMi($record) ? null
                : 'Site onay limitini ('.Para::bicimle($record->site?->onay_limiti_kurus, $record->para_birimi).') aşıyor; şirket yöneticisi onayı gerekir.')
            ->requiresConfirmation()
            ->modalHeading(fn (Gider $record) => $record->site?->ad.' — gideri onayla')
            ->modalDescription(fn (Gider $record) => Para::bicimle($record->tutar_kurus, $record->para_birimi).' · '.$record->aciklama.'. Onaylanan giderin tutarı ve dönemi değiştirilemez.')
            ->action(function (Gider $record, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(fn () => app(GiderServisi::class)->onayla($record), 'Gider onaylandı', $action);
            });
    }

    public static function reddet(): Action
    {
        return Action::make('reddet')
            ->label('Reddet')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Gider $record) => $record->onay_durumu === 'onay_bekliyor'
                && (Yetki::var('gider.onayla') || Yetki::var('gider.onayla_limitsiz')) && Gate::allows('view', $record))
            ->modalHeading(fn (Gider $record) => $record->site?->ad.' — gideri reddet')
            ->schema([
                Textarea::make('gerekce')->label('Ret gerekçesi')->required()->rows(2)->maxLength(255),
            ])
            ->action(function (Gider $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(fn () => app(GiderServisi::class)->reddet($record, $data['gerekce']), 'Gider reddedildi', $action);
            });
    }

    public static function ode(): Action
    {
        return Action::make('ode')
            ->label('Öde')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->visible(fn (Gider $record) => $record->onay_durumu === 'onaylandi' && static::odemeDurumu($record) !== 'odendi')
            ->authorize(fn (Gider $record) => Gate::allows('create', Gider::class) && Gate::allows('view', $record))
            ->modalHeading(fn (Gider $record) => $record->site?->ad.' — gider ödemesi')
            ->modalDescription(fn (Gider $record) => $record->aciklama.' · kalan '.Para::bicimle($record->tutar_kurus - static::odenen($record), $record->para_birimi)
                .'. Ödeme seçilen hesaptan çıkış olarak yazılır; kısmi ödeme yapılabilir.')
            ->fillForm(fn (Gider $record) => [
                'tutar' => $record->tutar_kurus - static::odenen($record),
                'tarih' => now()->toDateString(),
            ])
            ->schema(fn (Gider $record) => [
                Select::make('hesap_id')->label('Ödeme hesabı')
                    ->options(SiteAlanlari::hesapSecenekleri($record->site_id, $record->para_birimi))
                    ->helperText('Yalnız bu sitenin '.$record->para_birimi.' hesapları.')
                    ->required(),
                Alanlar::para('tutar', 'Tutar')->required(),
                DatePicker::make('tarih')->label('Ödeme tarihi')->required()->native(false)->displayFormat('d.m.Y'),
                TextInput::make('aciklama')->label('Açıklama')->maxLength(255),
                SiteAlanlari::donemGerekcesi(fn () => $record->site_id),
            ])
            ->action(function (Gider $record, array $data, Action $action) {
                $site = SiteAlanlari::erisimDenetle($record->site_id);
                $hesap = Hesap::findOrFail((int) $data['hesap_id']);
                SiteAlanlari::ayniSite($site->id, $hesap);

                SiteAlanlari::calistir(
                    fn () => app(GiderServisi::class)->ode($record, $hesap, (int) $data['tutar'], $data['tarih'], $data['aciklama'] ?? null, $data['donem_gerekcesi'] ?? null),
                    'Ödeme kaydedildi',
                    $action,
                );
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Gider')
                ->columns(4)
                ->schema([
                    TextEntry::make('site.ad')->label('Site')->badge()->color('gray'),
                    TextEntry::make('tedarikci.ad')->label('Tedarikçi')->placeholder('—'),
                    TextEntry::make('kategori')->label('Kategori')->formatStateUsing(fn ($state) => Gider::KATEGORILER[$state] ?? $state),
                    TextEntry::make('donem')->label('Dönem')->formatStateUsing(fn ($state) => Donem::ad($state)),
                    TextEntry::make('belge_tarihi')->label('Belge tarihi')->date('d.m.Y'),
                    TextEntry::make('belge_no')->label('Belge no')->placeholder('—'),
                    TextEntry::make('vade')->label('Vade')->date('d.m.Y')->placeholder('—'),
                    TextEntry::make('tutar_kurus')->label('Tutar')->formatStateUsing(fn ($state, Gider $record) => Para::bicimle((int) $state, $record->para_birimi)),
                    TextEntry::make('aciklama')->label('Açıklama')->columnSpan(2),
                    TextEntry::make('onay_durumu')->label('Onay')->badge()
                        ->formatStateUsing(fn ($state) => Gider::ONAY_DURUMLARI[$state] ?? $state)
                        ->color(fn ($state) => static::onayRengi($state)),
                    TextEntry::make('odeme')->label('Ödeme')
                        ->state(fn (Gider $record) => (Gider::ODEME_DURUMLARI[$record->odemeDurumu()] ?? '').' · kalan '.Para::bicimle($record->kalanKurus(), $record->para_birimi)),
                    TextEntry::make('onaylayan.name')->label('Onaylayan / reddeden')->placeholder('—')
                        ->helperText(fn (Gider $record) => $record->onay_zamani?->format('d.m.Y H:i')),
                    TextEntry::make('red_gerekcesi')->label('Ret gerekçesi')->placeholder('—')->visible(fn (Gider $record) => $record->onay_durumu === 'reddedildi'),
                    TextEntry::make('limit')->label('Onay limiti')
                        ->state(fn (Gider $record) => $record->site?->onay_limiti_kurus === null ? 'Limit yok'
                            : Para::bicimle($record->site->onay_limiti_kurus, $record->para_birimi).(app(GiderServisi::class)->limitAsiliyorMu($record) ? ' — aşılıyor, şirket yöneticisi onayı gerekir' : '')),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            OdemelerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiderler::route('/'),
            'create' => CreateGider::route('/create'),
            'view' => ViewGider::route('/{record}'),
            'edit' => EditGider::route('/{record}/edit'),
        ];
    }
}
