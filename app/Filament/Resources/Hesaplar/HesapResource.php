<?php

namespace App\Filament\Resources\Hesaplar;

use App\Filament\Destek\Alanlar;
use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\Hesaplar\Pages\CreateHesap;
use App\Filament\Resources\Hesaplar\Pages\EditHesap;
use App\Filament\Resources\Hesaplar\Pages\ListHesaplar;
use App\Filament\Resources\Hesaplar\Pages\ViewHesap;
use App\Filament\Resources\Hesaplar\RelationManagers\HareketlerRelationManager;
use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Services\SiteFinans\HesapServisi;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Kasa / banka (şartname 8.3). Bakiye = hareketlerin toplamı. Açılış
 * bakiyesi tarih ve dayanakla bir kez girilir; transfer iki hareket yazar
 * ve gelir/gider sayılmaz; banka mutabakatı hareket bazında işaretlenir.
 */
class HesapResource extends Resource
{
    protected static ?string $model = Hesap::class;

    protected static ?string $slug = 'hesaplar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Kasa / Banka';

    protected static ?string $modelLabel = 'hesap';

    protected static ?string $pluralModelLabel = 'hesaplar';

    protected static string|UnitEnum|null $navigationGroup = 'Site Finansı';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'ad';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return static::hesapFormu($schema, siteSec: true);
    }

    public static function hesapFormu(Schema $schema, bool $siteSec): Schema
    {
        $hareketVar = fn (?Hesap $record) => $record !== null && $record->hareketler()->exists();

        return $schema->columns(3)->components([
            ...($siteSec ? [SiteAlanlari::site('site_id')->disabledOn('edit')] : []),
            TextInput::make('ad')->label('Hesap adı')->required()->maxLength(255),
            Select::make('tur')->label('Tür')->options(Hesap::TURLER)->required()->default('banka')->live()->selectablePlaceholder(false),
            TextInput::make('banka_adi')->label('Banka')->maxLength(255)->visible(fn ($get) => $get('tur') === 'banka'),
            TextInput::make('iban')->label('IBAN')->maxLength(40)->visible(fn ($get) => $get('tur') === 'banka'),
            Select::make('para_birimi')->label('Para birimi')->options(Para::BIRIMLER)->default('TRY')->required()
                ->selectablePlaceholder(false)
                ->disabled($hareketVar)
                ->helperText(fn (?Hesap $record) => $hareketVar($record) ? 'Hareketi olan hesabın para birimi değişmez.' : null),
            Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('site')
                ->withSum('hareketler as bakiye_toplam', 'tutar_kurus')
                ->withCount(['hareketler as mutabakatsiz_count' => fn ($q) => $q->where('mutabik', false)]))
            ->defaultSort('ad')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('ad')->label('Hesap')->searchable()
                    ->description(fn (Hesap $record) => trim(($record->banka_adi ?? '').' '.($record->iban ?? '')) ?: null),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn ($state) => Hesap::TURLER[$state] ?? $state),
                TextColumn::make('para_birimi')->label('Birim'),
                SiteAlanlari::paraSutunu('bakiye_toplam', 'Bakiye', fn (Hesap $record) => (int) $record->bakiye_toplam)->weight('bold'),
                TextColumn::make('mutabakatsiz_count')->label('Mutabakatsız hareket')->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('tur')->label('Tür')->options(Hesap::TURLER),
            ])
            ->recordActions([
                ViewAction::make()->label('Hareketler'),
            ])
            ->emptyStateHeading('Hesap yok');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Hesap')
                ->columns(4)
                ->schema([
                    TextEntry::make('site.ad')->label('Site')->badge()->color('gray'),
                    TextEntry::make('tur')->label('Tür')->formatStateUsing(fn ($state) => Hesap::TURLER[$state] ?? $state),
                    TextEntry::make('banka_adi')->label('Banka')->placeholder('—'),
                    TextEntry::make('iban')->label('IBAN')->placeholder('—'),
                    TextEntry::make('bakiye')->label('Bakiye')->state(fn (Hesap $record) => Para::bicimle($record->bakiyeKurus(), $record->para_birimi))->weight('bold'),
                    TextEntry::make('mutabakatsiz')->label('Mutabakatsız hareket')->state(fn (Hesap $record) => $record->hareketler()->where('mutabik', false)->count()),
                    TextEntry::make('acilis')->label('Açılış bakiyesi')
                        ->state(fn (Hesap $record) => ($h = $record->hareketler()->where('tur', 'acilis')->first())
                            ? Para::bicimle($h->tutar_kurus, $record->para_birimi).' · '.$h->tarih->format('d.m.Y').' · '.$h->dayanak
                            : 'Girilmedi'),
                ]),
        ]);
    }

    /* ---------------- Eylemler (hesap sayfası üstü) ---------------- */

    /** @return list<Action> */
    public static function eylemler(): array
    {
        return [static::acilisBakiyesi(), static::transfer(), static::duzeltme()];
    }

    public static function acilisBakiyesi(): Action
    {
        return Action::make('acilisBakiyesi')
            ->label('Açılış bakiyesi')
            ->icon(Heroicon::OutlinedFlag)
            ->color('gray')
            ->visible(fn (Hesap $record) => ! $record->hareketler()->where('tur', 'acilis')->exists())
            ->authorize(fn (Hesap $record) => Gate::allows('update', $record))
            ->modalHeading(fn (Hesap $record) => $record->site?->ad.' · '.$record->ad.' — açılış bakiyesi')
            ->modalDescription('Bir kez girilir; sonradan değişiklik düzeltme kaydıyla yapılır.')
            ->schema(fn (Hesap $record) => [
                Alanlar::para('tutar', 'Tutar ('.$record->para_birimi.')')->required(),
                DatePicker::make('tarih')->label('Tarih')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                Textarea::make('dayanak')->label('Dayanak (devir tutanağı, banka dökümü…)')->required()->rows(2)->maxLength(255)
                    ->helperText($record->site?->kapali_donem ? 'Kapalı döneme tarihlenirse dayanak aynı zamanda düzeltme gerekçesi sayılır.' : null),
            ])
            ->action(function (Hesap $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                SiteAlanlari::calistir(fn () => app(HesapServisi::class)->acilisBakiyesi($record, (int) $data['tutar'], $data['tarih'], $data['dayanak']), 'Açılış bakiyesi girildi', $action);
            });
    }

    public static function transfer(): Action
    {
        return Action::make('transfer')
            ->label('Transfer')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('primary')
            ->authorize(fn (Hesap $record) => Gate::allows('update', $record))
            ->modalHeading(fn (Hesap $record) => $record->site?->ad.' — '.$record->ad.' hesabından transfer')
            ->modalDescription('Aynı sitenin aynı para birimli hesabına. Transfer gelir ya da gider sayılmaz.')
            ->schema(fn (Hesap $record) => [
                Select::make('hedef_id')->label('Hedef hesap')
                    ->options(collect(SiteAlanlari::hesapSecenekleri($record->site_id, $record->para_birimi))->except($record->id)->all())
                    ->required(),
                Alanlar::para('tutar', 'Tutar ('.$record->para_birimi.')')->required(),
                DatePicker::make('tarih')->label('Tarih')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                TextInput::make('aciklama')->label('Açıklama')->maxLength(255),
            ])
            ->action(function (Hesap $record, array $data, Action $action) {
                $site = SiteAlanlari::erisimDenetle($record->site_id);
                $hedef = Hesap::findOrFail((int) $data['hedef_id']);
                SiteAlanlari::ayniSite($site->id, $hedef);
                SiteAlanlari::calistir(fn () => app(HesapServisi::class)->transfer($record, $hedef, (int) $data['tutar'], $data['tarih'], $data['aciklama'] ?? null), 'Transfer kaydedildi', $action);
            });
    }

    public static function duzeltme(): Action
    {
        return Action::make('duzeltme')
            ->label('Düzeltme kaydı')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('warning')
            ->authorize(fn (Hesap $record) => Gate::allows('update', $record))
            ->modalHeading(fn (Hesap $record) => $record->site?->ad.' · '.$record->ad.' — düzeltme kaydı')
            ->modalDescription('Sayım veya banka farkı. Mevcut hareketler değişmez; gerekçeli yeni hareket yazılır.')
            ->schema(fn (Hesap $record) => [
                Radio::make('yon')->label('Yön')->options(['giris' => 'Giriş (+)', 'cikis' => 'Çıkış (−)'])->default('giris')->required()->inline(),
                Alanlar::para('tutar', 'Tutar ('.$record->para_birimi.')')->required(),
                DatePicker::make('tarih')->label('Tarih')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255),
            ])
            ->action(function (Hesap $record, array $data, Action $action) {
                SiteAlanlari::erisimDenetle($record->site_id);
                $tutar = (int) $data['tutar'] * ($data['yon'] === 'cikis' ? -1 : 1);
                SiteAlanlari::calistir(fn () => app(HesapServisi::class)->duzeltme($record, $tutar, $data['tarih'], $data['gerekce']), 'Düzeltme kaydedildi', $action);
            });
    }

    public static function hareketTuru(?string $tur): string
    {
        return HesapHareketi::TURLER[$tur] ?? (string) $tur;
    }

    public static function getRelations(): array
    {
        return [
            HareketlerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHesaplar::route('/'),
            'create' => CreateHesap::route('/create'),
            'view' => ViewHesap::route('/{record}'),
            'edit' => EditHesap::route('/{record}/edit'),
        ];
    }
}
