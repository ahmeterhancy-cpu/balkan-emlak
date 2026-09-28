<?php

namespace App\Filament\Resources\Borclar;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Borclar\Pages\ListBorclar;
use App\Models\Blok;
use App\Models\Borc;
use App\Services\SiteFinans\BorcServisi;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Borçlar — salt okunur liste (şartname 8.2). İşlenmiş borç düzenlenmez
 * ve silinmez; "Düzelt / iptal et" gerekçeli karşı kayıt açar
 * (BorcServisi::duzelt). Düzeltme satırları listede ayrı görünmez, asıl
 * borcun net tutarına yansır.
 */
class BorcResource extends Resource
{
    protected static ?string $model = Borc::class;

    protected static ?string $slug = 'borclar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Borçlar';

    protected static ?string $modelLabel = 'borç';

    protected static ?string $pluralModelLabel = 'borçlar';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 2;

    private const NET = '(borclar.tutar_kurus + COALESCE((SELECT SUM(d.tutar_kurus) FROM borclar d WHERE d.duzeltilen_borc_id = borclar.id), 0))';

    private const ODENEN = 'COALESCE((SELECT SUM(td.tutar_kurus) FROM tahsilat_dagitimlari td WHERE td.borc_id = borclar.id), 0)';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery())->asil();
    }

    /** Durum süzgeci — Borc::durum() ile aynı kurallar, SQL olarak. */
    public static function durumSorgusu(Builder $sorgu, string $durum): Builder
    {
        $net = self::NET;
        $odenen = self::ODENEN;
        $bugun = now()->toDateString();

        return match ($durum) {
            'iptal' => $sorgu->whereRaw("$net = 0"),
            'odendi' => $sorgu->whereRaw("$net > 0 AND $net - $odenen <= 0"),
            'kismi' => $sorgu->whereRaw("$net - $odenen > 0 AND $odenen > 0"),
            'gecikmis' => $sorgu->whereRaw("$net - $odenen > 0 AND $odenen = 0")->where('borclar.vade', '<', $bugun),
            'acik' => $sorgu->whereRaw("$net - $odenen > 0 AND $odenen = 0")->where('borclar.vade', '>=', $bugun),
            default => $sorgu,
        };
    }

    public static function durumRengi(string $durum): string
    {
        return match ($durum) {
            'odendi' => 'success', 'kismi' => 'info', 'gecikmis' => 'danger', 'iptal' => 'gray', default => 'warning',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->bakiyeli()->with(['site', 'bolum.blok', 'kisi']))
            ->defaultSort('vade', 'desc')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('bolum_etiket')->label('Bölüm')->state(fn (Borc $record) => $record->bolum?->etiket()),
                TextColumn::make('kisi.ad_soyad')->label('Sorumlu kişi')->placeholder('—')->searchable(),
                TextColumn::make('donem')->label('Dönem')->sortable(),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn ($state) => Borc::TURLER[$state] ?? $state)->toggleable(),
                TextColumn::make('aciklama')->label('Açıklama')->limit(32)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vade')->label('Vade')->date('d.m.Y')->sortable(),
                SiteAlanlari::paraSutunu('net_kurus', 'Tutar', fn (Borc $record) => $record->netKurus()),
                SiteAlanlari::paraSutunu('odenen_kurus', 'Ödenen', fn (Borc $record) => $record->odenenKurus()),
                SiteAlanlari::paraSutunu('kalan', 'Kalan', fn (Borc $record) => $record->kalanKurus())->weight('bold'),
                TextColumn::make('durum')->label('Durum')->badge()
                    ->state(fn (Borc $record) => $record->durum())
                    ->formatStateUsing(fn ($state) => Borc::DURUMLAR[$state] ?? $state)
                    ->color(fn ($state) => static::durumRengi($state)),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('donem')->label('Dönem')
                    ->options(fn () => Kapsam::siteyeBagli(Borc::query())->distinct()->orderByDesc('donem')->pluck('donem', 'donem')->all()),
                SelectFilter::make('tur')->label('Tür')->options(Borc::TURLER),
                SelectFilter::make('durum')->label('Durum')
                    ->options(Borc::DURUMLAR)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null) ? static::durumSorgusu($query, $data['value']) : $query),
                SelectFilter::make('blok')->label('Blok')
                    ->options(fn () => Kapsam::siteyeBagli(Blok::query())->with('site')->get()->mapWithKeys(fn (Blok $b) => [$b->id => $b->site?->ad.' · '.$b->ad])->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('bolum', fn ($q) => $q->where('blok_id', (int) $data['value']))
                        : $query),
            ])
            ->recordActions([
                static::duzeltEylemi(),
            ])
            ->emptyStateHeading('Borç yok')
            ->emptyStateDescription('Borçlar borçlandırma kesinleşince ya da bölüm sayfasından "Borç ekle" ile açılır.');
    }

    public static function duzeltEylemi(): Action
    {
        return Action::make('duzelt')
            ->label('Düzelt / iptal et')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->authorize(fn (Borc $record) => Gate::allows('create', Borc::class) && Gate::allows('view', $record))
            ->modalHeading(fn (Borc $record) => $record->site?->ad.' · '.$record->bolum?->etiket().' — borç düzeltme')
            ->modalDescription(fn (Borc $record) => 'Şu anki net tutar '.Para::bicimle($record->netKurus(), $record->para_birimi)
                .', ödenen '.Para::bicimle($record->odenenKurus(), $record->para_birimi)
                .'. Kayıt değişmez; fark gerekçeli karşı kayıtla yazılır. 0 girilirse borç iptal edilir; ödenmiş kısım avansa döner.')
            ->fillForm(fn (Borc $record) => ['yeni_net' => $record->netKurus()])
            ->schema([
                Alanlar::para('yeni_net', 'Yeni net tutar')->required(),
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255)
                    ->helperText(fn (Borc $record) => $record->site?->kapali_donem && Donem::kapaliMi($record->site, $record->donem)
                        ? 'Bu borcun dönemi kapalı; düzeltme yalnız "kapalı dönem düzeltme" yetkisiyle yapılır.'
                        : null),
            ])
            ->action(function (Borc $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(
                    fn () => app(BorcServisi::class)->duzelt($record, (int) $data['yeni_net'], $data['gerekce']) ?? throw new \DomainException('Tutar değişmedi; düzeltme kaydı açılmadı.'),
                    ((int) $data['yeni_net']) === 0 ? 'Borç iptal edildi' : 'Borç düzeltildi',
                    $action,
                );
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBorclar::route('/'),
        ];
    }
}
