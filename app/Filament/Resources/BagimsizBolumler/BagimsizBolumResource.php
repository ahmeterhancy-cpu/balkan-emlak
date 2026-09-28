<?php

namespace App\Filament\Resources\BagimsizBolumler;

use App\Filament\Destek\SiteAlanlari;
use App\Filament\Resources\BagimsizBolumler\Pages\CreateBagimsizBolum;
use App\Filament\Resources\BagimsizBolumler\Pages\EditBagimsizBolum;
use App\Filament\Resources\BagimsizBolumler\Pages\ListBagimsizBolumler;
use App\Filament\Resources\BagimsizBolumler\Pages\ViewBagimsizBolum;
use App\Filament\Resources\BagimsizBolumler\RelationManagers\IliskilerRelationManager;
use App\Models\BagimsizBolum;
use App\Models\BolumIliskisi;
use App\Services\SiteFinans\SiteRaporu;
use App\Services\SiteFinans\TahsilatServisi;
use App\Support\Kapsam;
use App\Support\Para;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Bağımsız bölümler (şartname 8.1). Ayrıntı sayfasında tarihli malik /
 * kiracı ilişkileri, hesap ekstresi, avans ve "Tahsilat al" / "Borç ekle".
 */
class BagimsizBolumResource extends Resource
{
    protected static ?string $model = BagimsizBolum::class;

    protected static ?string $slug = 'bagimsiz-bolumler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Bağımsız Bölümler';

    protected static ?string $modelLabel = 'bağımsız bölüm';

    protected static ?string $pluralModelLabel = 'bağımsız bölümler';

    protected static string|UnitEnum|null $navigationGroup = 'Site Yönetimi';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::siteyeBagli(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return static::bolumFormu($schema, fn ($get) => $get('site_id'), siteSec: true);
    }

    /**
     * @param  Closure  $siteId  bağımlı blok seçenekleri için site kimliği
     */
    public static function bolumFormu(Schema $schema, Closure $siteId, bool $siteSec): Schema
    {
        $alanlar = [];
        if ($siteSec) {
            $alanlar[] = SiteAlanlari::site('site_id', ['blok_id'])
                ->disabledOn('edit')
                ->columnSpanFull();
        }

        return $schema->columns(3)->components([
            ...$alanlar,
            Select::make('blok_id')
                ->label('Blok')
                ->options(fn (Select $component) => SiteAlanlari::blokSecenekleri($component->evaluate($siteId)))
                ->placeholder('Bloksuz'),
            TextInput::make('no')->label('Bölüm no')->required()->maxLength(20),
            TextInput::make('kat')->label('Kat')->maxLength(20),
            Select::make('tur')->label('Tür')->options(BagimsizBolum::TURLER)->default('daire')->required()->selectablePlaceholder(false),
            TextInput::make('brut_m2')->label('Brüt m²')->numeric()->minValue(0),
            Toggle::make('aktif')->label('Aktif (borçlandırılır)')->default(true)->inline(false),
            TextInput::make('arsa_payi')->label('Arsa payı (pay)')->numeric()->integer()->minValue(0),
            TextInput::make('arsa_payi_payda')->label('Arsa payı (payda)')->numeric()->integer()->minValue(1)
                ->requiredWith('arsa_payi'),
            Textarea::make('notlar')->label('Notlar')->rows(2)->columnSpanFull(),
        ]);
    }

    /** Yüklenmiş ilişkilerden bugünkü borç sorumlusunun adı (ek sorgu yok). */
    public static function sorumluAdi(BagimsizBolum $b): ?string
    {
        $etkin = $b->iliskiler->filter(fn (BolumIliskisi $i) => $i->etkinMi());

        return ($etkin->firstWhere('borc_sorumlusu', true) ?? $etkin->firstWhere('rol', 'malik'))?->kisi?->ad_soyad;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['site', 'blok', 'iliskiler.kisi']))
            ->defaultSort('id')
            ->columns([
                SiteAlanlari::siteSutunu(),
                TextColumn::make('etiket')->label('Bölüm')
                    ->state(fn (BagimsizBolum $record) => $record->etiket())
                    ->searchable(query: fn ($query, string $search) => $query->where('no', 'like', "%{$search}%")),
                TextColumn::make('kat')->label('Kat')->placeholder('—'),
                TextColumn::make('tur')->label('Tür')->formatStateUsing(fn ($state) => BagimsizBolum::TURLER[$state] ?? $state),
                TextColumn::make('brut_m2')->label('Brüt m²')->numeric(2, ',', '.')->alignEnd()->placeholder('—'),
                TextColumn::make('arsa_payi')->label('Arsa payı')
                    ->state(fn (BagimsizBolum $record) => $record->arsa_payi && $record->arsa_payi_payda ? $record->arsa_payi.'/'.$record->arsa_payi_payda : null)
                    ->placeholder('—'),
                TextColumn::make('sorumlu')->label('Borç sorumlusu (bugün)')
                    ->state(fn (BagimsizBolum $record) => static::sorumluAdi($record))
                    ->placeholder('Kayıt yok')->color(fn ($state) => $state ? null : 'danger'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SiteAlanlari::siteFiltresi(),
                SelectFilter::make('blok_id')->label('Blok')->relationship('blok', 'ad', fn ($query) => Kapsam::siteyeBagli($query)),
                SelectFilter::make('tur')->label('Tür')->options(BagimsizBolum::TURLER),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç'),
                EditAction::make()->label('Düzenle'),
            ])
            ->emptyStateHeading('Bağımsız bölüm yok')
            ->emptyStateDescription('Site sayfasındaki "Toplu bölüm oluştur" ile numara aralığı açabilirsiniz.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Bölüm')
                ->columns(4)
                ->schema([
                    TextEntry::make('site.ad')->label('Site')->badge()->color('gray'),
                    TextEntry::make('etiket')->label('Bölüm')->state(fn (BagimsizBolum $record) => $record->etiket()),
                    TextEntry::make('kat')->label('Kat')->placeholder('—'),
                    TextEntry::make('tur')->label('Tür')->formatStateUsing(fn ($state) => BagimsizBolum::TURLER[$state] ?? $state),
                    TextEntry::make('brut_m2')->label('Brüt m²')->placeholder('—'),
                    TextEntry::make('arsa')->label('Arsa payı')
                        ->state(fn (BagimsizBolum $record) => $record->arsa_payi && $record->arsa_payi_payda ? $record->arsa_payi.'/'.$record->arsa_payi_payda : null)
                        ->placeholder('—'),
                    TextEntry::make('sorumlu')->label('Borç sorumlusu (bugün)')
                        ->state(fn (BagimsizBolum $record) => static::sorumluAdi($record->loadMissing('iliskiler.kisi')))
                        ->placeholder('Kayıt yok — borçlandırmada uyarı verir'),
                    TextEntry::make('avans')->label('Avans (dağıtılmamış ödeme)')
                        ->state(function (BagimsizBolum $record) {
                            $avans = app(TahsilatServisi::class)->avans($record);

                            return $avans === [] ? 'Yok' : collect($avans)->map(fn ($k, $pb) => Para::bicimle($k, $pb))->implode(' · ');
                        })
                        ->helperText('Fazla ödeme gelir sayılmaz; yeni borç açıldığında mahsup edilir.'),
                ]),
            Section::make('Hesap ekstresi')
                ->description('Borç (+), tahsilat (−), yürüyen bakiye; para birimine göre ayrı. Eksi bakiye = avans. Doğrulanmamış dekontlar dahil değildir.')
                ->schema([
                    View::make('filament.site.bolum-ekstresi')
                        ->viewData(fn (BagimsizBolum $record) => ['satirlar' => app(SiteRaporu::class)->bolumEkstresi($record)]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            IliskilerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBagimsizBolumler::route('/'),
            'create' => CreateBagimsizBolum::route('/create'),
            'view' => ViewBagimsizBolum::route('/{record}'),
            'edit' => EditBagimsizBolum::route('/{record}/edit'),
        ];
    }
}
